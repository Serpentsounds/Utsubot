<?php
/**
 * MEGASSBOT - PokemonModule.php
 * User: Benjamin
 * Date: 10/11/14
 */

namespace Utsubot\Pokemon;
use Utsubot\Permission\ModuleWithPermission;
use Utsubot\{
    IRCBot, IRCMessage, ModuleException, DatabaseInterface, MySQLDatabaseCredentials, Manager, ManagerSearchCriterion
};
use function Utsubot\{
    bold, italic, color, stripControlCodes
};
use function Utsubot\Web\resourceBody;
use function Utsubot\Pokemon\StatCalculator\{
    calculateStat, calculateIVs, calculateBase
};
use function Utsubot\Pokemon\Types\{
    colorType, hasChart, typeChart, typeMatchup, pokemonMatchup
};


class PokemonModuleException extends ModuleException {}

class PokemonModule extends ModuleWithPermission {

	/** @var $PokemonManager PokemonManager */
	private $PokemonManager;
	/** @var $AbilityManager AbilityManager */
	private $AbilityManager;
	/** @var $ItemManager ItemManager */
	private $ItemManager;
	/** @var $NatureManager NatureManager */
	private $NatureManager;
	/** @var $MoveManager MoveManager */
	private $MoveManager;
	private $LocationManager;
	/** @var $Pokedex Pokedex */
	private $Pokedex;
	/** @var $MetaTiers MetaTier[] */
	private $MetaTiers;

	public function __construct(IRCBot $IRCBot) {
        $this->_require("Utsubot\\Pokemon\\VeekunDatabaseInterface");
		$this->_require("Utsubot\\Pokemon\\MetaPokemonDatabaseInterface");

		parent::__construct($IRCBot);

		//	List of defined managers
		$managers = array("Pokemon", "Ability", "Item", "Nature", "Move", "Location");
		$interface = new VeekunDatabaseInterface();

		//	Load all managers
		foreach ($managers as $manager) {
			$className = "{$manager}Manager";
			$qualifiedName = "Utsubot\\Pokemon\\$className";
			//	Make sure the manager subclass is actually valid before instantiating it
			if (class_exists($qualifiedName) && is_subclass_of($qualifiedName, "Utsubot\\Manager")) {
				$this->{$className} = new $qualifiedName($interface);
				if (method_exists($this->{$className}, "load")) {
					$this->{$className}->load();
					$this->status("Loaded $className.");
				}
				else
					$this->status("$className can not be loaded.");
			}
			else
				$this->status("$qualifiedName not defined.");
		}

		//	Load Pokedex separately
		if (class_exists("Pokemon\\Pokedex"))
			$this->Pokedex = new Pokedex($interface);
		else
			$this->status("Pokemon\\Pokedex not defined.");

		/** @var $pokemon Pokemon[] */
		$pokemon = $this->PokemonManager->collection();
		$pokemonNames = array();
		foreach ($pokemon as $pokemonObj)
			$pokemonNames[$pokemonObj->getId()] = $pokemonObj->getNames();

		$interface = new MetaPokemonDatabaseInterface();
		$tiers = $interface->getTiers();
		foreach ($tiers as $tier) {
			$this->MetaTiers[$tier] = new MetaTier($interface);
			$MetaPokemon = $this->MetaTiers[$tier]->collection();
			foreach ($MetaPokemon as $obj)
				/** @var $obj MetaPokemon */
				$obj->setName($pokemonNames[$obj->getId()]);
		}

		$this->triggers = array(
			'dex'			=> "dex",

			'poke'			=> "poke",
			'pinfo'			=> "poke",
			'sinfo'			=> "poke",
			'names'			=> "poke",
			'dexes'			=> "poke",

			'phiddenpower'	=> "hiddenPower",
			'php'			=> "hiddenPower",

			'piv'			=> "calculateIVs",

			'pnature'		=> "nature",
			'pnat'			=> "nature",

			'pability'		=> "ability",
			'pabl'			=> "ability",

			'pitem'			=> "item",

			'pmove'			=> "move",
			'pattack'		=> "move",
			'patk'			=> "move",

			'ptype'			=> "type",

			'pcoverage'		=> "coverage",
			'pcov'			=> "coverage",

			'maxtobase'		=> "baseMax",
			'm2b'			=> "baseMax",
			'mtob'			=> "baseMax",
			'basetomax'		=> "baseMax",
			'b2m'			=> "baseMax",
			'btom'			=> "baseMax",
			'maxtobase50'	=> "baseMax",
			'm2b50'			=> "baseMax",
			'mtob50'		=> "baseMax",
			'basetomax50'	=> "baseMax",
			'b2m50'			=> "baseMax",
			'btom50'		=> "baseMax",

			'pstat'			=> "baseStat",
			'pstats'		=> "baseStat",

			'psearch'		=> "search",

			'pcompare'		=> "compare",
			'pcomp'			=> "compare",

			'mgdb'			=> "updateMetagameDatabase"

		);
	}

	/**
	 * Get a reference to a Manager Object held by this module
	 *
	 * @param string $manager Pokemon, Ability, Item, Nature, Location, or Pokedex
	 * @return Manager|Pokedex|bool The Manager or Pokedex, or false if it doesn't exist
	 */
	public function getManager($manager) {
		$manager = ucfirst(strtolower($manager));

		if (isset($this->{$manager."Manager"}))
			return $this->{$manager."Manager"};

		elseif ($manager == "Pokedex")
			return $this->Pokedex;

		return false;
	}

	public function baseMax(IRCMessage $msg) {
        $this->_require("Utsubot\\Pokemon\\ParameterParser");

        $parser = new ParameterParser();
        $result = $parser->parseBaseMaxParameters($msg->getCommandParameters(), $msg->getCommand());

		if ($result->getFrom() == "base")
			$calculated = calculateStat($result->getStat(), 31, 252, $result->getLevel(), 1.1, $result->isHp());
		else
            $calculated = calculateBase($result->getStat(), 31, 252, $result->getLevel(), 1.1, $result->isHp());

		$this->respond($msg, sprintf(
            "%s %s = %s %s",
            $result->getStat(),
            $result->getFrom(),
            $calculated,
            $result->getTo()
        ));
	}

	public function baseStat(IRCMessage $msg) {
        $this->_require("Utsubot\\Pokemon\\ParameterParser");

        $parser = new ParameterParser();
        $parser->injectManager("Pokemon", $this->PokemonManager);
        $parser->injectManager("Nature", $this->NatureManager);
        $result = $parser->parseIVStatParameters($msg->getCommandParameters());

		$statValues = array();
		for ($i = 0; $i <= 5; $i++)
			$statValues[] = calculateStat($result->getPokemon()->getBaseStat($i), $result->getStatValue($i), $result->getEV($i), $result->getLevel(), $result->getNatureMultiplier($i), $i == 0);

		//	Suffix stat range or error message to each stat
		$output = array();
        $statNames = array("HP", "Attack", "Defense", "Special Attack", "Special Defense", "Speed");
		foreach ($statNames as $key => $statName) {
			if ($statName == $result->getNatureIncreases())
				$statName = color($statName, "red");
			elseif ($statName == $result->getNatureDecreases())
				$statName = color($statName, "blue");

			$output[] = sprintf(
                "%s: %s",
                bold($statName),
                $statValues[$key]
            );
		}

		$this->respond($msg, sprintf(
            "Stats for %s: %s",
            bold($result->getPokemon()->getName()),
            implode(" ", $output)
        ));

	}

	/**
	 * Output a synopsis mimicking Dexter from the pokemon anime
	 *
	 * @param IRCMessage $msg
	 */
	public function dex(IRCMessage $msg) {
		$parameters = $msg->getCommandParameters();
		$last = array_pop($parameters);
		$version = null;

		if ($this->Pokedex->getVersionId($last) !== false) {
			$version = $last;
			$pokemonString = implode(" ", $parameters);
		}
		else
			$pokemonString = $msg->getCommandParameterString();

		if (($pokemon = $this->PokemonManager->search($pokemonString)) && $pokemon instanceof Pokemon) {
			if (!$version)
				$dexEntry = $this->Pokedex->getLatestDexEntry($pokemon->getId());
			else
				$dexEntry = $this->Pokedex->getDexEntry($pokemon->getId(), $version);

			if ($dexEntry)
				$this->respond($msg, sprintf("%03d: %s, the %s pokemon. %s", $pokemon->getDexNumber(), $pokemon->getName(), $pokemon->getSpecies(), $dexEntry));
		}

	}

	/**
	 * Output various information about a pokemon
	 *
	 * @param IRCMessage $msg
	 */
	public function poke(IRCMessage $msg) {
		if (($pokemon = $this->PokemonManager->search($msg->getCommandParameterString()))) {
			$suggestions = array();
			$info = null;
			$command = $msg->getCommand();

			//	Array means a Jaro-Winkler distance result set was returned, so prepare to offer additional suggestions
			if (is_array($pokemon)) {
				foreach ($pokemon as $key => $result) {
					//	Entries are array(J-W metric value, Pokemon object)
					if ($result[1] instanceof Pokemon) {
						if ($key == 0)
							$info = new PokemonInfoFormat($result[1]);
						else
							$suggestions[] = $result[1]->getName();
					}

				}
			}

			//	Single pokemon returned (exact or wildcard match)
			elseif ($pokemon instanceof Pokemon) {
				if ($command == "minfo") {
					$interface = new MetaPokemonDatabaseInterface();
					$pokemonUsages = $interface->getPokemonUsages($pokemon->getId());
					arsort($pokemonUsages);
					$tier = array_keys($pokemonUsages)[0];
					$info = new MetaPokemonInfoFormat($this->MetaTiers[$tier], $pokemon->getId());
				}
				else
					$info = new PokemonInfoFormat($pokemon);
			}

			//	No matches
			elseif (!$info)
				return;

			//	Try to replace default format with user-defined format, if possible
			switch ($command) {
				case "pinfo":		$format = $this->getSetting($msg->getNick(), "pinfo");			break;
				case "sinfo":		$format = PokemonInfoFormat::getSemanticFormat();				break;
				case "names":		$format = PokemonInfoFormat::getNamesFormat();					break;
				case "dexes":		$format = PokemonInfoFormat::getDexesFormat();					break;
			}

			if (!isset($format) || !$format)
				$format = null;

			$units = $this->getSetting($msg->getNick(), "units");
			if ($units)
				$info->setUnits($units);

			//	Pass format into info function for results
			$this->respond($msg, $info->parseFormat($format));

			//	There were suggestions available for the Jaro-Winkler distance, output them
			if ($suggestions) {
				//	This is just to properly format the list
				$suggestionString = "You may also be looking for ";
				$count = count($suggestions);
				foreach ($suggestions as $key => $suggestion) {
					if ($key == $count - 1 && $count > 1)
						$suggestionString .= " or ";

					$suggestionString .= bold($suggestion);

					if ($count > 2 && $key < $count - 2)
						$suggestionString .= ", ";

					if ($key == $count - 1)
						$suggestionString .= ".";
				}

				$this->respond($msg, $suggestionString);
			}
		}

	}

	public function nature(IRCMessage $msg) {
		$parameters = $msg->getCommandParameters();
		if (!count($parameters))
			throw new PokemonModuleException("No nature given.");

		//	Searching for nature given affected stats, need exactly 2 stats
		if (preg_match_all("/([+\-])([a-z]+)/i", $msg->getCommandParameterString(), $match, PREG_SET_ORDER) == 2) {

			//	Possible values the user can input
			$natureIdentifiers = array(
				"Attack" 			=> array("attack", "atk"),
				"Defense" 			=> array("defense", "def"),
				"Special Attack"	=> array("specialattack", "spatk", "spa"),
				"Special Defense"	=> array("specialdefense", "spdef", "spd"),
				"Speed"				=> array("speed", "spe")
			);

			//	Validate both stats
			$increases = $decreases = null;
			foreach ($match as $set) {

				foreach ($natureIdentifiers as $stat => $acceptedValues) {
					//	Stat match found, save stat name
					if (in_array(strtolower($set[2]), $acceptedValues)) {
						if ($set[1] == "+")
							$increases = $stat;
						elseif ($set[1] == "-")
							$decreases = $stat;

						break;
					}

					//	Speed is the final key, if this point was reached there were no stat matches
					elseif ($stat == "Speed")
						throw new PokemonModuleException("Invalid stat '{$set[2]}'.");
				}

			}

			if (!$increases || !$decreases)
				throw new PokemonModuleException("An increased and decreased stat must both be given.");

			$criteria = array(
				new ManagerSearchCriterion($this->NatureManager, "increases", "==", $increases),
				new ManagerSearchCriterion($this->NatureManager, "decreases", "==", $decreases)
			);

			$nature = $this->NatureManager->fullSearch($criteria, false);
			if (!($nature instanceof Nature))
				throw new PokemonModuleException("Invalid nature.");
		}

		elseif (!($nature = $this->NatureManager->search($parameters[0])) || !($nature instanceof Nature))
			throw new PokemonModuleException("Invalid nature.");

		$natureInfo = new NatureInfoFormat($nature);
		$this->respond($msg, $natureInfo->parseFormat());
	}

	public function move(IRCMessage $msg) {
		$parameters = $msg->getCommandParameters();
		if (!count($parameters))
			throw new PokemonModuleException("No move given.");

		$copy = $parameters;
		$format = null;
		$switch = strtolower(array_pop($copy));
		if ($switch == "-verbose") {
			$format = MoveInfoFormat::getVerboseFormat();
			$parameters = $copy;
		}
		elseif ($switch == "-contest") {
			$format = MoveInfoFormat::getContestformat();
			$parameters = $copy;
		}

		if (!($move = $this->MoveManager->search(implode(" ", $parameters))) || !($move instanceof Move))
			throw new PokemonModuleException("Invalid move.");

		$moveInfo = new MoveInfoFormat($move);
		$this->respond($msg, $moveInfo->parseFormat($format));
	}

	public function ability(IRCMessage $msg) {
		$parameters = $msg->getCommandParameters();
		if (!count($parameters))
			throw new PokemonModuleException("No ability given.");

		$copy = $parameters;
		$format = $switch = null;
		$lastWord = strtolower(array_pop($copy));
		if (substr($lastWord, 0, 1) == "-") {
			$switch = substr($lastWord, 1);
			$parameters = $copy;
		}

		if (!($ability = $this->AbilityManager->search(implode(" ", $parameters))) || !($ability instanceof Ability))
			throw new PokemonModuleException("Invalid ability.");

		switch ($switch) {
			case "verbose":
				$format = AbilityInfoFormat::getVerboseFormat();
			break;

			case "p":
			case "poke":
			case "pokemon":
				$abilityName = $ability->getName();

				$criteria = array(
					new ManagerSearchCriterion($this->PokemonManager, "ability1", "==", $abilityName),
					new ManagerSearchCriterion($this->PokemonManager, "ability2", "==", $abilityName),
					new ManagerSearchCriterion($this->PokemonManager, "ability3", "==", $abilityName)
				);
				/** @var $pokemon Pokemon[] */
				$pokemon = $this->PokemonManager->fullSearch($criteria, true, false);

				$pokemonNames = array();
				foreach ($pokemon as $object)
					$pokemonNames[] = $object->getName();

				$this->respond($msg, sprintf(
					"These pokemon can have %s: %s.",
					bold($abilityName),
					implode(", ", $pokemonNames)
				));
				return;
			break;
		}

		$abilityInfo = new AbilityInfoFormat($ability);
		$this->respond($msg, $abilityInfo->parseFormat($format));
	}

	public function item(IRCMessage $msg) {
		$parameters = $msg->getCommandParameters();
		if (!count($parameters))
			throw new PokemonModuleException("No item given.");

		$copy = $parameters;
		$format = null;
		if (strtolower(array_pop($copy)) == "-verbose") {
			$format = ItemInfoFormat::getVerboseFormat();
			$parameters = $copy;
		}

		if (!($item = $this->ItemManager->search(implode(" ", $parameters))) || !($item instanceof Item))
			throw new PokemonModuleException("Invalid item.");

		$itemInfo = new ItemInfoFormat($item);
		$this->respond($msg, $itemInfo->parseFormat($format));
	}

	public function calculateIVs(IRCMessage $msg) {
        $this->_require("Utsubot\\Pokemon\\ParameterParser");

        $parser = new ParameterParser();
        $parser->injectManager("Pokemon", $this->PokemonManager);
        $parser->injectManager("Nature", $this->NatureManager);
        $result = $parser->parseIVStatParameters($msg->getCommandParameters());

        $IVRange = calculateIVs(array_values($result->getPokemon()->getBaseStat()), $result->getStatValues(), $result->getEVs(), $result->getLevel(), $result->getNatureMultipliers());

		//	Suffix stat range or error message to each stat
		$output = array();
        $statNames = array("HP", "Attack", "Defense", "Special Attack", "Special Defense", "Speed");
		foreach ($statNames as $key => $statName) {
			if ($statName == $result->getNatureIncreases())
				$statName = color($statName, "red");
			elseif ($statName == $result->getNatureDecreases())
				$statName = color($statName, "blue");

			$output[] = sprintf(
                "%s: %s",
                bold($statName),
                implode("-", $IVRange[$key])
            );
		}

		$this->respond($msg, sprintf(
            "Possible IVs for %s: %s",
            bold($result->getPokemon()->getName()),
            implode(" ", $output)
        ));
	}

	/**
	 * Output information about a pokemon's Hidden Power stats, given a list of their IVs
	 *
	 * @param IRCMessage $msg
	 */
	public function hiddenPower(IRCMessage $msg) {
		$parameterString = $msg->getCommandParameterString();

		//	Match 6 numbers in a row
		if (preg_match('/^(\d{1,2}[\/ \\\\]){5}\d{1,2}$/', $parameterString)) {
			//	Split into individual numbers
			$ivs = array_map("intval", preg_split('/[\/ \\\\]/', $parameterString));

			$hiddenPower = (new HiddenPowerCalculator(...$ivs))->calculate();

			$this->respond($msg, sprintf(
					"Your hidden power is %s-type, with a base power of %s.",
					bold(colorType($hiddenPower->getType())),
					bold($hiddenPower->getPower())
			));
		}
	}

	private function parseTypeParameter(&$parameters) {
		$firstTwo = implode(" ", array_slice($parameters, 0, 2));
		$type = null;

		if (hasChart($parameters[0]))
			$type = array_shift($parameters);

		elseif (hasChart($firstTwo)) {
			$type = $firstTwo;
			$parameters = array_slice($parameters, 2);
		}

		elseif (strpos($parameters[0], "/") !== false) {
			$types = explode("/", $parameters[0]);
			foreach ($types as $check) {
				$result = $this->parseTypeParameter($check);
				$type[] = $result[0];
			}
			$parameters = array_slice($parameters, 1);
		}

		else {
			//	Check for multi-word pokemon
			$maxWordsPerPokemon = 3;
			for ($words = 1; $words <= $maxWordsPerPokemon; $words++) {
				//	Add 1 word at a time
				$name = implode(" ", array_slice($parameters, 0, $words));
				$type = $this->PokemonManager->search($name);

				//	Pokemon found
				if ($type instanceof Pokemon)
					break;

				//	No pokemon and we've no words left to check
				elseif ($words == $maxWordsPerPokemon)
					throw new PokemonModuleException("Invalid type.");

			}

			$parameters = array_slice($parameters, $words);
		}

		return $type;
	}

	public function type(IRCMessage $msg) {
		$parameters = $msg->getCommandParameters();
		$type1 = $type2 = null;
		$mode = "offensive";

		$next = 0;
		//	Loop through words until we find 2 parameters
		for ($i = 1; $i <= 2; $i++) {
			//	No more words to check
			if (!isset($parameters[$next]))
				break;

			//	Save next two words to check for flying press
			$nextTwo = implode(" ", array_slice($parameters, $next, 2));

			//	Two word type (flying press)
			if (hasChart($nextTwo)) {
				${"type$i"} = $nextTwo;
				$next += 2;
			}
			//	One word type (all others)
			elseif (hasChart($parameters[$next]))
				${"type$i"} = $parameters[$next++];

			//	Type list delimited by "/"
			elseif (strpos($parameters[$next], "/") !== false) {
				$types = explode("/", $parameters[$next]);

				//	Check each type individually
				foreach ($types as $type) {
					if (hasChart($type))
						${"type$i"}[] = $type;
					else
						//	Invalid type given, abort
						throw new PokemonModuleException("PokemonModule::type: Invalid type '$type'.");
				}
				$next++;
			}

			//	Type name not found, check for pokemon or move names
			else {
				//	Check for multi-word pokemon
				$maxWordsPerPokemon = 3;
				for ($words = $maxWordsPerPokemon; $words >= 1; $words--) {
					//	Add 1 word at a time
					$name = implode(" ", array_slice($parameters, $next, $words));
					$pokemon = $this->PokemonManager->search($name);
					$move = $this->MoveManager->search($name);

					//	Move found
					if ($move instanceof Move) {
						${"type$i"} = $move->getType();
						$next += $words;
						break;
					}

					//	Pokemon found
					elseif ($pokemon instanceof Pokemon) {
						${"type$i"} = $pokemon;
						$next += $words;
						break;
					}

					//	No pokemon and we've no words left to check
					#elseif ($words == $maxWordsPerPokemon)
						#throw new PokemonModuleException("PokemonModule::type: Invalid type '$name'.");

				}

			}
		}

		//	Default to a defensive matchup for a single pokemon, or for multi-type
		if (($type1 instanceof Pokemon && !$type2) || (is_array($type1) && count($type1) > 2))
			$mode = "defensive";

		if (isset($parameters[$next])) {
			switch ($parameters[$next]) {
				case "-d":	$mode = "defensive";	break;
				case "-o":	$mode = "offensive";	break;
				case "-p":	$mode = "pokemon";		break;
			}
		}

		$result = array();

		//	Pokemon mode, search for pokemon whose typing matches what was given
		if ($mode == "pokemon") {
			//	Replace pokemon with type list instead
			if ($type1 instanceof Pokemon)
				$searchType = $type1->getType(0);

			//	Normalize type list to array with indices beginning at 1
			elseif (!is_array($type1))
				$searchType = array(1 => $type1);
			else
				$searchType = array(1 => $type1[0], 2 => $type1[1]);

			$searchType = array_map(function ($element) {
				return ucwords(strtolower($element));
			}, $searchType);

			//	Add criteria to allow reverse order for dual types
			$criteria = array(
				new ManagerSearchCriterion($this->PokemonManager, "types", "==", $searchType)
			);
			if (count($searchType) == 2)
				$criteria[] = new ManagerSearchCriterion($this->PokemonManager, "types", "==", array(1 => $searchType[2], 2 => $searchType[1]));

			//	Search matching ANY criteria
			/** @var $pokemon Pokemon[] */
			$pokemon = $this->PokemonManager->fullSearch($criteria, true, false);

			//	No results
			if (!$pokemon || !count($pokemon))
				throw new PokemonModuleException("There are no ". colorType($searchType, true). "-type pokemon.");

			//	Save names of resulting pokemon
			$pokemonNames = array();
			foreach ($pokemon as $object)
				$pokemonNames[] = $object->getName();

			$response = "There are ". bold(count($pokemonNames)). " ". colorType($searchType, true). "-type pokemon: ". implode(", ", $pokemonNames). ".";
			$this->respond($msg, $response);
			return;
		}

		//	Mode was not pokemon, output offensive or defense type info

		//	Pokemon vs. ...
		if ($type1 instanceof Pokemon) {
			//	Vs. nothing, get type charts
			if (!$type2) {
				//	Type chart vs. this pokemon
				if ($mode == "defensive")
					$result = pokemonMatchup(CHART_BASIC, $type1);
				//	This pokemon's compound types vs. type chart
				elseif ($mode == "offensive")
					$result = typeChart($type1->getType(0), "offensive");
			}

			//	Vs. another pokemon
			elseif ($type2 instanceof Pokemon) {
				//	This pokemon's compound types vs. other pokemon
				if ($mode == "defensive")
					$result = pokemonMatchup($type1->getType(0), $type2);
				//	Other pokemon's compound types vs. this pokemon
				elseif ($mode == "offensive")
					$result = pokemonMatchup($type2->getType(0), $type1);
			}

			//	Vs. a type
			else {
				//	Type vs. this pokemon
				if ($mode == "defensive")
					$result = pokemonMatchup($type2, $type1);
				//	This pokemon's compound types vs. type
				elseif ($mode == "offensive")
					$result = typeMatchup($type1->getType(0), $type2);
			}
		}

		//	Type vs. ...
		else {
			//	Nothing, get type charts
			if (!$type2)
				$result = typeChart($type1, $mode);

			//	Vs. a pokemon
			elseif ($type2 instanceof Pokemon) {
				//	Pokemon's compound types vs. this type
				if ($mode == "defensive")
					$result = typeMatchup($type2->getType(0), $type1);
				//	This type vs. pokemon
				elseif ($mode == "offensive")
					$result = pokemonMatchup($type1, $type2);
			}

			//	Vs. another type
			else {
				//	Other type vs. this type
				if ($mode == "defensive")
					$result = typeMatchup($type2, $type1);
				//	This type vs. other type
				elseif ($mode == "offensive")
					$result = typeMatchup($type1, $type2);
			}
		}

		//	Vs. nothing, output chart results
		if (!$type2) {
			$abilities = array();
			//	Save ability effects, if applicable
			if ($type1 instanceof Pokemon && isset($result['abilities']))
				$abilities = $result['abilities'];

			//	Filter out 1x matchups
			$result = array_filter($result, function($element) {
				return (is_numeric($element) && $element != 1);
			});

			//	Function to format matchups. Save for additional use in ability charts if needed
			$parseChart = function($result) {
				//	Group types of the same effectiveness together
				$chart = array();
				foreach ($result as $type => $multiplier)
					$chart[(string)$multiplier][] = colorType($type);
				ksort($chart);

				//	Append list of types to each multiplier for output
				$output = array();
				foreach ($chart as $multiplier => $entry)
					$output[] = bold($multiplier."x") . ": " . implode(", ", $entry);

				//	Each element has Multipler: type1, type2, etc
				return $output;
			};
			$output = $parseChart($result);

			//	Add an extra line for abilities if needed, using the same $parseChart function
			$abilityOutput = array();
			foreach ($abilities as $ability => $abilityChart)
				$abilityOutput[] = sprintf("[%s]: %s", bold($ability), implode(" :: ", $parseChart($abilityChart)));

			//	Format intro with Pokemon name and type
			if ($type1 instanceof Pokemon)
				$outputString = sprintf("%s (%s)", bold($type1->getName()), colorType($type1->getType(0), true));
			//	Just a type
			else
				$outputString = colorType($type1, true);

			//	Append formatted chart
			$outputString .= " $mode type chart: ". implode(" :: ", $output);

			//	Stick ability output on a new line if we have any
			if ($abilityOutput)
				$outputString .= "\n". implode("; ", $abilityOutput);

			$this->respond($msg, $outputString);
		}

		//	Vs. another type, output matchup
		else {
			$abilities = array();
			//	Save ability effects, if applicable
			if (isset($result['abilities'])) {
				$abilities = $result['abilities'];
				unset($result['abilities']);
			}

			//	Flatten array after removal of abilities
			if (is_array($result)) {
				$key = array_keys($result)[0];
				$result = $result[$key];
			}

			//	Add an extra line for abilities if needed
			$abilityOutput = array();
			foreach ($abilities as $ability => $abilityChart) {
				//	There should only be a single entry
				$key = array_keys($abilityChart)[0];
				$abilityOutput[] = sprintf("[%s]: %sx", bold($ability), $abilityChart[$key]);
			}

			//	Format intro with Pokemon name and type
			if ($type1 instanceof Pokemon)
				$outputString = sprintf("%s (%s)", bold($type1->getName()), colorType($type1->getType(0), true));
			//	Just a type
			else
				$outputString = colorType($type1, true);

			$outputString .= " vs ";
			//	Format opponent
			if ($type2 instanceof Pokemon)
				$outputString .= sprintf("%s (%s)", bold($type2->getName()), colorType($type2->getType(0), true));
			//	Just a type
			else
				$outputString .= colorType($type2, true);

			$outputString .= ": ". bold($result). "x";
			//	Stick ability output on a new line if we have any
			if ($abilityOutput)
				$outputString .= "\n". implode("; ", $abilityOutput);

			$this->respond($msg, $outputString);
		}

	}

	public function coverage(IRCMessage $msg) {
		$parameters = $msg->getCommandParameters();

		$typeDisplay = array();
		foreach ($parameters as $key => $type) {
			if (!hasChart($type))
				throw new PokemonModuleException("Invalid type '$type'.");

			$typeDisplay[] = colorType($type, true);
			$parameters[$key] = strtolower($type);
		}

		$pokemonList = $this->PokemonManager->collection();
		$requiredResistances = count($parameters);
		$resistingPokemon = array();

		foreach ($pokemonList as $pokemon) {
			if ($pokemon instanceof Pokemon) {
				$abilityNames = array();
				$actualResistances = array('base' => 0);

				foreach ($parameters as $key => $type) {
					$results = pokemonMatchup($type, $pokemon);

					if (isset($results[$type]) && $results[$type] < 1)
						$actualResistances['base']++;

					elseif (isset($results['abilities'])) {
						foreach ($results['abilities'] as $ability => $chart) {
							if (!count($abilityNames))
								$abilityNames[] = $ability;

							if (isset($chart[$type]) && $chart[$type] < 1)
								@$actualResistances[$ability]++;
						}
					}


				}

				if ($actualResistances['base'] == $requiredResistances)
					$resistingPokemon[] = $pokemon->getName();

				else {
					foreach ($abilityNames as $ability) {
						if (isset($actualResistances[$ability]) && ($actualResistances[$ability] + $actualResistances['base']) == $requiredResistances)
							$resistingPokemon[] = $pokemon->getName(). " [". italic(ucwords($ability)). "]";
					}
				}

			}
		}

		$count = count($resistingPokemon);
		if ($count > 30) {
			$resistingPokemon = array_slice($resistingPokemon, 0, 30);
			$resistingPokemon[] = "and ". ($count-30). " more";
		}

		$output = "There are $count pokemon that resist ". implode(", ", $typeDisplay). ": ". implode(", ", $resistingPokemon);

		$this->respond($msg, $output);
	}

	public function search(IRCMessage $msg) {
		$parameters = $msg->getCommandParameters();

		//	Parse user-selected search category
		$categories = array("pokemon", "ability", "move", "nature", "item");
		$category = strtolower(array_shift($parameters));
		if (!in_array($category, $categories))
			throw new PokemonModuleException("Invalid search category '$category'. Valid categories are: ". implode(", ", $categories). ".");

		//	Compose collection of valid operators for input parsing
		$operators = array_merge(Manager::getNumericOperators(), Manager::getStringOperators(), Manager::getArrayOperators());
		//	Grab relevant Manager and include custom operators, if applicable
		$managerClass = ucfirst($category). "Manager";
		$qualifiedName = "Pokemon\\$managerClass";
		$customOperatorRegex = '//';
		if (method_exists($qualifiedName, "getCustomOperators")) {
			$customOperators = $qualifiedName::getCustomOperators();
			$operators = array_merge($operators, $customOperators);
			$customOperatorRegex = '/^('. preg_replace('/([\/\\*?+().{}])/', '\\\\$1', implode("|", $customOperators)). '):(.+)$/';
		}
		//	Filter duplicates
		$operators = array_unique($operators);
		/*	Make longer operators appear earlier in the array, so the resulting regex will match them before shorter ones that might begin the same
			e.g., > will prevent >= from ever matching if it appears first in the capture group
		 */
		usort($operators, function($a, $b) { return strlen($b) - strlen($a); });

		//	We need a valid manager to create the ManagerSearchCriterion objects
		if (!property_exists($this, $managerClass) || !($this->{$managerClass} instanceof Manager))
			throw new PokemonModuleException("Manager of type '$managerClass' does not exist.");
		/** @var Manager $manager */
		$manager = $this->{$managerClass};

		//	Regex to parse user input
		$regex = '/^(.*?)('. preg_replace('/([\/\\*?+().{}])/', '\\\\$1', implode("|", $operators)). ')(.+)$/';
		$return = 0;
		$criteria = array();
		$language = "English";
		foreach ($parameters as $parameter) {
			//	Customize number of results returned
			if (preg_match('/^return:(\d+)$/', $parameter, $match))
				$return = $match[1];

			//	Customize language of result set
			elseif (preg_match('/^language:(.+)$/', $parameter, $match)) {
				if (!($language = PokemonBase::getLanguage($match[1])))
					throw new PokemonModuleException("Invalid language '{$match[1]}'.");
			}

			elseif (preg_match($customOperatorRegex, $parameter, $match)) {
				list(, $operator, $value) = $match;
				$criteria[] = new ManagerSearchCriterion($manager, "", $operator, $value);
			}

			//	Apply new criterion
			elseif (preg_match($regex, $parameter, $match)) {
				list(, $field, $operator, $value) = $match;
				$criteria[] = new ManagerSearchCriterion($manager, $field, $operator, $value);
			}

			//	Abort on unknown parameter
			else
				throw new PokemonModuleException("Invalid parameter '$parameter'.");
		}

		$results = $manager->fullSearch($criteria);
		//	Apply number of results limit
		if ($return > 0)
			$results = array_slice($results, 0, $return);

		if (!count($results))
			throw new PokemonModuleException("No results found.");

		//	Convert objects to strings in given language
		foreach ($results as $key => $result) {
			/** @var PokemonBase $result */
			$results[$key] = $result->getName($language);
		}

		$this->respond($msg, implode(", ", $results));
	}

	public function compare(IRCMessage $msg) {
		$parameters = $msg->getCommandParameters();
		$next = 0;
		$pokemonList = array(null, null);

		for ($i = 0; $i <= 1; $i++) {
			//	No more words to check
			if (!isset($parameters[$next]))
				break;

			//	Type name not found, check for pokemon or move names
			else {
				//	Check for multi-word pokemon
				$maxWordsPerPokemon = 3;
				for ($words = $maxWordsPerPokemon; $words >= 1; $words--) {
					//	Add 1 word at a time
					$name = implode(" ", array_slice($parameters, $next, $words));
					$pokemon = $this->PokemonManager->search($name);

					//	Pokemon found
					if ($pokemon instanceof Pokemon) {
						$pokemonList[$i] = $pokemon;
						$next += $words;
						break;
					}

					//	No pokemon and we've no words left to check
					elseif ($words == 1)
						throw new PokemonModuleException("Invalid pokemon '$name'.");

				}

			}
		}

		if (!($pokemonList[0] instanceof Pokemon) || !($pokemonList[1] instanceof Pokemon))
			throw new PokemonModuleException("2 pokemon must be provided.");

		$data = array();
		for ($i = 0; $i <= 1; $i++) {
			$info = new PokemonInfoFormat($pokemonList[$i]);
			$data[$i] = array_map("trim", explode("%n", $info->parseFormat(PokemonInfoFormat::getCompareFormat())));
		}

		$statNames = array(2 => "HP", 3 => "Atk", 4 => "Def", 5 => "SpA", 6 => "SpD", 7 => "Spe");
		for ($i = 2; $i <= 7; $i++) {
			$stats = array(stripControlCodes($data[0][$i]), stripControlCodes($data[1][$i]));
			$colors = array("blue", "blue");

			$paddingWidth = ($i == 2) ? 4 : 3;
			$padding = array($paddingWidth - strlen($stats[0]), $paddingWidth - strlen($stats[1]));

			if ($stats[0] > $stats[1])
				$colors = array("lime", "red");

			elseif ($stats[0] < $stats[1])
				$colors = array("red", "lime");

			$data[0][$i] = str_repeat(chr(160), $padding[0]). color($data[0][$i], $colors[0]). $statNames[$i];
			$data[1][$i] = str_repeat(chr(160), $padding[1]). color($data[1][$i], $colors[1]). $statNames[$i];
		}

		for ($i = 0; $i <= 1; $i++) {
			$data[$i] = array_merge(
				array($data[$i][0], $data[$i][1], implode(" ", array_slice($data[$i], 2, 3)), implode(" ", array_slice($data[$i], 5, 3))),
				array_slice($data[$i], 8)
			);
		}

		//	hidden abl
		if (!$data[0][5] && !$data[1][5]) {
			unset($data[0][5]);
			unset($data[0][5]);
		}

		#print_r($data);


		$lines = array();
		for ($i = 0; $i <= 1; $i++) {
			$maxLength = max(array_map("strlen", array_map(array("self", "stripControlCodes"), $data[$i])));
			foreach ($data[$i] as $key => $line) {
				if ($i == 0) {
					$realLength = strlen(stripControlCodes($line));
					$line = str_repeat(chr(160), $maxLength - $realLength). $line;
				}
				$lines[$key][$i] = $line;
			}

		}
		foreach ($lines as $key => $compareValues)
			$lines[$key] = implode(" | ", $compareValues);

		$this->respond($msg, implode("\n", $lines));
	}

	public function updateMetagameDatabase(IRCMessage $msg) {
		$this->requireLevel($msg, 100);

		$mode = @$msg->getCommandParameters()[0];
		switch ($mode) {
			case "download":
				$this->downloadMetagameDatabase($msg);
			break;

			case "insert":
				$this->insertMetagameDatabase($msg);
			break;
		}
	}

	private function downloadMetagameDatabase(IRCMessage $msg) {
		$base = "http://www.smogon.com/stats/";
		$index = resourceBody($base);
		if (!preg_match_all('/^<a href="(\d{4}-\d{2}\/)">/m', $index, $match, PREG_PATTERN_ORDER))
			throw new PokemonModuleException("Unable to find latest metagame stats.");
		$latest = $match[1][count($match[1]) - 1];

		$files = array("ubers-0", "ou-0", "uu-0", "nu-0", "doublesubers-0", "doublesou-0", "doublesuu-0", "vgc2015-0");
		$jsonDir = $base. $latest. "chaos/";
		if (!is_dir("metagame"))
			mkdir("metagame");
		else {
			$this->respond($msg, "Clearing out old statistics files...");
			array_map("unlink", glob("metagame/*.json"));
		}
		$this->respond($msg, "Downloading newest metagame statistics...");
		foreach ($files as $file) {
			if (file_put_contents("metagame/$file.json", file_get_contents($jsonDir.$file.".json")))
				$this->respond($msg, "Successfully downloaded $file.");
			else
				$this->respond($msg, "Failed to download $file.");
		}
		$this->respond($msg, "Download complete.");
	}

	private function insertMetagameDatabase(IRCMessage $msg) {
		if (!is_dir("metagame"))
			throw new PokemonModuleException("There are no metagame statistics to insert. Download them first.");

		$files = glob("metagame/*.json");
		if (!$files)
			throw new PokemonModuleException("There are no metagame statistics to insert. Download them first.");

		$interface = new DatabaseInterface(MySQLDatabaseCredentials::createFromConfig("utsubot"));

		$tiers = $interface->query("SELECT * FROM `metagame_tiers` ORDER BY `id` ASC");
		$tierIds = array();
		foreach ($tiers as $id => $row)
			$tierIds[strtolower(str_replace(" ", "", $row['name']))] = $id;

		$fields = $interface->query("SELECT * FROM `metagame_fields` ORDER BY `id` ASC");
		$fieldIds = array();
		foreach ($fields as $id => $row)
			$fieldIds[$row['name']] = $id;

		$collections = array(
			'pokemon'		=> $this->PokemonManager->collection(),
			'items' 		=> $this->ItemManager->collection(),
			'abilities' 	=> $this->AbilityManager->collection(),
			'moves' 		=> $this->MoveManager->collection()
		);
		$cache = array();
		foreach ($collections as $key => $collection) {
			foreach ($collection as $currentObject) {
				/** @var $currentObject PokemonBase */
				$index = $name = $currentObject->getName();
				if (substr_count($index, " ") > 1)
					$index = implode(" ", array_slice(explode(" ", $index), 0, 2));
				$index = strtolower(str_replace(array(" ", "-"), "", $index));
				$cache[$key][$index] = array($currentObject->getId(), $currentObject->getName());
			}
		}

		$this->respond($msg, "Clearing out old data...");
		$interface->query("TRUNCATE TABLE `metagame_data`");

		$table = "`metagame_data`";
		$columns = array("`pokemon_id`", "`tier_id`", "`field_id`", "`entry`", "`value`");
		$columnCount = count($columns);
		$insertRows = 500;
		$maxData = $columnCount * $insertRows;
		$statement = $interface->prepare(
			"INSERT INTO $table (". implode(", ", $columns). ") VALUES ". implode(", ", array_fill(0, $insertRows, "(". implode(", ", array_fill(0, $columnCount, "?")). ")"))
		);

		$fieldNameTranslation = array("raw count" => "count", "checks and counters" => "counters");

		foreach ($files as $file) {
			$this->respond($msg, "Beginning to process $file...");

			$data = json_decode(file_get_contents($file), true);
			$tierId = $tierIds[$data['info']['metagame']];
			$battleCount = $data['info']['number of battles'];

			$queryData = array();
			$queryDataCount = 0;
			foreach ($data['data'] as $pokemon => $stats) {
				$pokemonIndex = strtolower(str_replace(array(" ", "-"), "", $pokemon));
				if (!isset($cache['pokemon'][$pokemonIndex]))
					continue;
				$pokemonId = $cache['pokemon'][$pokemonIndex][0];

				$total = $stats['Raw count'];
				foreach ($stats as $field => $entries) {

					$fieldName = strtolower($field);
					if (isset($fieldNameTranslation[$fieldName]))
						$fieldName = $fieldNameTranslation[$fieldName];

					if (!isset($fieldIds[$fieldName]))
						continue;

					$fieldId = $fieldIds[$fieldName];

					if ($fieldName == "count") {
						array_push($queryData, $pokemonId, $tierId, $fieldId, $battleCount, $entries);
						$queryDataCount += $columnCount;
					}

					if (!is_array($entries) || !$entries)
						continue;

					if ($fieldName == "teammates") {
						$lower = array_filter($entries, function($item) { return $item < 0; });
						$upper = array_filter($entries, function($item) { return $item > 0; });

						arsort($upper);
						$upper = array_slice($upper, 0, 10);
						asort($lower);
						$lower = array_slice($lower, 0, 10);

						$entries = array_merge($upper, $lower);
					}

					if ($fieldName == "counters") {
						$entries = array_combine(array_keys($entries), array_column($entries, 1));
						arsort($entries);
						$entries = array_slice($entries, 0, 10);
					}

					foreach ($entries as $entry => $frequency) {
						if (in_array($fieldName, array("items", "moves", "spreads")) && ($frequency / $total) < 0.05)
							continue;

						if (in_array($fieldName, array("abilities", "items", "moves", "teammates", "counters"))) {
							$cacheKey = null;
							switch ($fieldName) {
								case "abilities":
								case "items":
								case "moves":
									$cacheKey = $fieldName;
								break;
								case "teammates":
								case "counters":
									$cacheKey = "pokemon";
								break;
								default:
									continue;
								break;
							}
							$cacheKey2 = strtolower(str_replace(array(" ", "-"), "", $entry));

							if ($entry != "nothing" && !isset($cache[$cacheKey][$cacheKey2]))
								continue;
							elseif ($entry != "nothing")
								$entry = $cache[$cacheKey][$cacheKey2][1];
						}

						array_push($queryData, $pokemonId, $tierId, $fieldId, $entry, $frequency);
						$queryDataCount += $columnCount;

						if ($queryDataCount >= $maxData) {
							$statement->execute($queryData);
							$queryData = array();
							$queryDataCount = 0;
						}
					}

				}

				$this->IRCBot->console("Finished processing $pokemon data for {$data['info']['metagame']}.");
			}

			if ($queryDataCount) {
				$tempStatement = $interface->prepare(
					"INSERT INTO $table (" . implode(", ", $columns) . ") VALUES " . implode(", ", array_fill(0, floor($queryDataCount / $columnCount), "(" . implode(", ", array_fill(0, $columnCount, "?")) . ")"))
				);
				$tempStatement->execute($queryData);
				$tempStatement = null;
			}
			#$this->respond($msg, "Finished processing $file.");
		}

		$this->respond($msg, "All done.");
		$interface->disconnect($statements = array($statement));
	}

}
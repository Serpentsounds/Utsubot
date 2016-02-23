<?php
/**
 * MEGASSBOT - PokemonModule.php
 * User: Benjamin
 * Date: 10/11/14
 */

namespace Pokemon;

class PokemonModule extends \Module {
	use \AccountAccess;

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
	/** @var $StatCalculator StatCalculator */
	private $StatCalculator;
	/** @var $MetaTiers MetaTier[] */
	private $MetaTiers;

	public function __construct(\IRCBot $IRCBot) {
		parent::__construct($IRCBot, "includes/modules/pokemon/");

		//	List of defined managers
		$managers = array("Pokemon", "Ability", "Item", "Nature", "Move", "Location");
		$interface = new VeekunDatabaseInterface();

		//	Load all managers
		foreach ($managers as $manager) {
			$className = "{$manager}Manager";
			$qualifiedName = "Pokemon\\$className";
			//	Make sure the manager subclass is actually valid before instantiating it
			if (class_exists($qualifiedName) && is_subclass_of($qualifiedName, "\\Manager")) {
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

		if (class_exists("Pokemon\\StatCalculator"))
			$this->StatCalculator = new StatCalculator($this);
		else
			$this->status("Pokemon\\StatCalculator not defined.");

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
	 * @return \Manager|Pokedex|bool The Manager or Pokedex, or false if it doesn't exist
	 */
	public function getManager($manager) {
		$manager = ucfirst(strtolower($manager));

		if (isset($this->{$manager."Manager"}))
			return $this->{$manager."Manager"};

		elseif ($manager == "Pokedex")
			return $this->Pokedex;

		return false;
	}

	public function baseMax(\IRCMessage $msg) {
		$parameters = $this->StatCalculator->parseBaseMaxParameters($msg->getCommandParameters(), $msg->getCommand());

		if ($parameters['from'] == "base")
			$result = $this->StatCalculator->calculateStat($parameters['stat'], 31, 252, $parameters['level'], 1.1, $parameters['hp']);
		else
			$result = $this->StatCalculator->calculateBase($parameters['stat'], 31, 252, $parameters['level'], 1.1, $parameters['hp']);

		$this->IRCBot->message($msg->getResponseTarget(), "{$parameters['stat']} {$parameters['from']} = $result {$parameters['to']}");
	}

	public function baseStat(\IRCMessage $msg) {
		$parameters = $msg->getCommandParameters();
		/** @var $pokemon Pokemon */
		list($pokemon, $level, $increases, $decreases, $natureMultipliers, $statNames, $IVs, $EVs) = array_values($this->StatCalculator->parseIVStatParameters($parameters));

		$statValues = array();
		for ($i = 0; $i <= 5; $i++) {
			$baseStat = $pokemon->getBaseStat($statNames[$i]);
			$statValues[] = $this->StatCalculator->calculateStat($baseStat, $IVs[$i], $EVs[$i], $level, $natureMultipliers[$i], $i == 0);
		}
		//	Suffix stat range or error message to each stat
		$output = array();
		foreach ($statNames as $key => $statName) {
			if ($statName == $increases)
				$statName = \IRCUtility::color($statName, "red");
			elseif ($statName == $decreases)
				$statName = \IRCUtility::color($statName, "blue");

			$output[] = sprintf("%s: %s", \IRCUtility::bold($statName), $statValues[$key]);
		}

		$this->IRCBot->message($msg->getResponseTarget(), sprintf("Stats for %s: %s", \IRCUtility::bold($pokemon->getName()), implode(" ", $output)));

	}

	/**
	 * Output a synopsis mimicking Dexter from the pokemon anime
	 *
	 * @param \IRCMessage $msg
	 */
	public function dex(\IRCMessage $msg) {
		$parameters = $msg->getCommandParameters();
		$last = array_pop($parameters);
		$version = null;

		if ($this->Pokedex->getVersionId($last) !== false) {
			$version = $last;
			$pokemonString = implode(" ", $parameters);
		}
		else
			$pokemonString = $msg->getCommandParameterString();

		if (($pokemon = $this->PokemonManager->get($pokemonString)) && $pokemon instanceof Pokemon) {
			if (!$version)
				$dexEntry = $this->Pokedex->getLatestDexEntry($pokemon->getId());
			else
				$dexEntry = $this->Pokedex->getDexEntry($pokemon->getId(), $version);

			if ($dexEntry)
				$this->IRCBot->message($msg->getResponseTarget(), sprintf("%03d: %s, the %s pokemon. %s", $pokemon->getDexNumber(), $pokemon->getName(), $pokemon->getSpecies(), $dexEntry));
		}

	}

	/**
	 * Output various information about a pokemon
	 *
	 * @param \IRCMessage $msg
	 */
	public function poke(\IRCMessage $msg) {
		if (($pokemon = $this->PokemonManager->get($msg->getCommandParameterString()))) {
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
			$this->IRCBot->message($msg->getResponseTarget(), $info->parseFormat($format));

			//	There were suggestions available for the Jaro-Winkler distance, output them
			if ($suggestions) {
				//	This is just to properly format the list
				$suggestionString = "You may also be looking for ";
				$count = count($suggestions);
				foreach ($suggestions as $key => $suggestion) {
					if ($key == $count - 1 && $count > 1)
						$suggestionString .= " or ";

					$suggestionString .= \IRCUtility::bold($suggestion);

					if ($count > 2 && $key < $count - 2)
						$suggestionString .= ", ";

					if ($key == $count - 1)
						$suggestionString .= ".";
				}

				$this->IRCBot->message($msg->getResponseTarget(), $suggestionString);
			}
		}

	}

	public function nature(\IRCMessage $msg) {
		$parameters = $msg->getCommandParameters();
		if (!count($parameters))
			throw new \ModuleException("No nature given.");

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
						throw new \ModuleException("Invalid stat '{$set[2]}'.");
				}

			}

			if (!$increases || !$decreases)
				throw new \ModuleException("An increased and decreased stat must both be given.");

			$criteria = array(
				new \ManagerSearchCriterion($this->NatureManager, "increases", "==", $increases),
				new \ManagerSearchCriterion($this->NatureManager, "decreases", "==", $decreases)
			);

			$nature = $this->NatureManager->search($criteria, false);
			if (!($nature instanceof Nature))
				throw new \ModuleException("Invalid nature.");
		}

		elseif (!($nature = $this->NatureManager->get($parameters[0])) || !($nature instanceof Nature))
			throw new \ModuleException("Invalid nature.");

		$natureInfo = new NatureInfoFormat($nature);
		$this->IRCBot->message($msg->getResponseTarget(), $natureInfo->parseFormat());
	}

	public function move(\IRCMessage $msg) {
		$parameters = $msg->getCommandParameters();
		if (!count($parameters))
			throw new \ModuleException("No move given.");

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

		if (!($move = $this->MoveManager->get(implode(" ", $parameters))) || !($move instanceof Move))
			throw new \ModuleException("Invalid move.");

		$moveInfo = new MoveInfoFormat($move);
		$this->IRCBot->message($msg->getResponseTarget(), $moveInfo->parseFormat($format));
	}

	public function ability(\IRCMessage $msg) {
		$parameters = $msg->getCommandParameters();
		if (!count($parameters))
			throw new \ModuleException("No ability given.");

		$copy = $parameters;
		$format = $switch = null;
		$lastWord = strtolower(array_pop($copy));
		if (substr($lastWord, 0, 1) == "-") {
			$switch = substr($lastWord, 1);
			$parameters = $copy;
		}

		if (!($ability = $this->AbilityManager->get(implode(" ", $parameters))) || !($ability instanceof Ability))
			throw new \ModuleException("Invalid ability.");

		switch ($switch) {
			case "verbose":
				$format = AbilityInfoFormat::getVerboseFormat();
			break;

			case "p":
			case "poke":
			case "pokemon":
				$abilityName = $ability->getName();

				$criteria = array(
					new \ManagerSearchCriterion($this->PokemonManager, "ability1", "==", $abilityName),
					new \ManagerSearchCriterion($this->PokemonManager, "ability2", "==", $abilityName),
					new \ManagerSearchCriterion($this->PokemonManager, "ability3", "==", $abilityName)
				);
				/** @var $pokemon Pokemon[] */
				$pokemon = $this->PokemonManager->search($criteria, true, false);

				$pokemonNames = array();
				foreach ($pokemon as $object)
					$pokemonNames[] = $object->getName();

				$this->IRCBot->message($msg->getResponseTarget(), sprintf(
					"These pokemon can have %s: %s.",
					\IRCUtility::bold($abilityName),
					implode(", ", $pokemonNames)
				));
				return;
			break;
		}

		$abilityInfo = new AbilityInfoFormat($ability);
		$this->IRCBot->message($msg->getResponseTarget(), $abilityInfo->parseFormat($format));
	}

	public function item(\IRCMessage $msg) {
		$parameters = $msg->getCommandParameters();
		if (!count($parameters))
			throw new \ModuleException("No item given.");

		$copy = $parameters;
		$format = null;
		if (strtolower(array_pop($copy)) == "-verbose") {
			$format = ItemInfoFormat::getVerboseFormat();
			$parameters = $copy;
		}

		if (!($item = $this->ItemManager->get(implode(" ", $parameters))) || !($item instanceof Item))
			throw new \ModuleException("Invalid item.");

		$itemInfo = new ItemInfoFormat($item);
		$this->IRCBot->message($msg->getResponseTarget(), $itemInfo->parseFormat($format));
	}

	public function getFirstPokemon($parameters) {
		$maxWordsPerPokemon = 3;
		$pokemon = null;

		for ($words = 1; $words <= $maxWordsPerPokemon; $words++) {
			//	Add 1 word at a time
			$name = implode(" ", array_slice($parameters, 0, $words));
			$pokemon = $this->PokemonManager->get($name);

			//	Pokemon found
			if ($pokemon instanceof Pokemon)
				break;

			//	No pokemon and we've no words left to check
			elseif ($words == $maxWordsPerPokemon)
				throw new \ModuleException("Unable to find pokemon.");

		}

		return $pokemon;
	}

	public function calculateIVs(\IRCMessage $msg) {
		$parameters = $msg->getCommandParameters();
		/** @var $pokemon Pokemon */
		list($pokemon, $level, $increases, $decreases, $natureMultipliers, $statNames, $statValues, $EVs) = array_values($this->StatCalculator->parseIVStatParameters($parameters));

		$IVRange = array();
		for ($i = 0; $i <= 5; $i++) {
			$baseStat = $pokemon->getBaseStat($statNames[$i]);
			
			for ($IV = 0; $IV <= 31; $IV++) {
				//	Stat formula with this IV plugged in
				$statWithIV = floor(floor((($IV + 2 * $baseStat + ($EVs[$i]/4)) * $level / 100 + 5)) * $natureMultipliers[$i]);
				//	Adjust for HP formula
				if ($i == 0)
					$statWithIV = floor(($IV + 2 * $baseStat + ($EVs[$i]/4) + 100) * $level / 100 + 10);

				//	User supplied stat is lower than stat with 0 IVs, invalid
				if ($IV == 0 && $statValues[$i] < $statWithIV) {
					$IVRange[$i][0] = "Too low";
					break;
				}
				//	User supplied stat is higher than stat with 31 IVs, invalid
				elseif ($IV == 31 && $statValues[$i] > $statWithIV) {
					$IVRange[$i][0] = "TOO DAMN HIGH";
					break;
				}

				//	Stat matches, this is a possible IV match
				if ($statWithIV == $statValues[$i]) {
					//	Add lower bound if it doesn't exist
					if (!isset($IVRange[$i][0]))
						$IVRange[$i][0] = $IV;
					//	Update upper bound
					$IVRange[$i][1] = $IV;
				}

			}
			//	Remove range if bounds are the same
			if (isset($IVRange[$i][1]) && $IVRange[$i][0] == $IVRange[$i][1])
				unset($IVRange[$i][1]);
		}

		//	Suffix stat range or error message to each stat
		$output = array();
		foreach ($statNames as $key => $statName) {
			if ($statName == $increases)
				$statName = \IRCUtility::color($statName, "red");
			elseif ($statName == $decreases)
				$statName = \IRCUtility::color($statName, "blue");

			$output[] = sprintf("%s: %s", \IRCUtility::bold($statName), implode("-", $IVRange[$key]));
		}

		$this->IRCBot->message($msg->getResponseTarget(), sprintf("Possible IVs for %s: %s", \IRCUtility::bold($pokemon->getName()), implode(" ", $output)));
	}

	/**
	 * Output information about a pokemon's Hidden Power stats, given a list of their IVs
	 *
	 * @param \IRCMessage $msg
	 */
	public function hiddenPower(\IRCMessage $msg) {
		$parameterString = $msg->getCommandParameterString();

		//	Match 6 numbers in a row
		if (preg_match('/^(\d{1,2}[\/ \\\\]){5}\d{1,2}$/', $parameterString)) {
			//	Split into individual numbers
			$ivs = preg_split('/[\/ \\\\]/', $parameterString);

			//	Adjust order to put speed between physical and special stats (necessary for math), and copy array
			$typeTerms = $powerTerms = array($ivs[0], $ivs[1], $ivs[2], $ivs[5], $ivs[3], $ivs[4]);

			/*	Hidden power type formula is given by (15/63)(a+2b+4c+8d+16e+32f), where a through f correspond to our reordered stats, and are 0 or 1 if the IV is even or odd (last binary bit)
			 *	The floor()'d result is an index applied to a list of types
			 *	This routine converts each IV to its term value in that equation
			 */
			array_walk($typeTerms, function(&$iv, $key) {
				//	The 0-5 index corresponds with the term placement and coefficient, so we can use it calculate the term value
				$iv = ($iv % 2) * pow(2, $key);
			});

			//	Apply the final part of the type formula to the list of types
			$types = array("Fighting", "Flying", "Poison", "Ground", "Rock", "Bug", "Ghost", "Steel", "Fire", "Water", "Grass", "Electric", "Psychic", "Ice", "Dragon", "Dark");
			$index = intval(floor(array_sum($typeTerms) * 15 / 63));
			$type = $types[$index];


			/*	Hidden power base power formula is given by (40/63)(a+2b+4c+8d+16e+32f)+30, where a through f again correspond to the stats, but this time take on the 2nd to last binary bit
			 *	This can be easily determined by checking if the value modulo 4 is greater than 1
			 * 	The floor()'d result is the base power
			 */
			array_walk($powerTerms, function(&$iv, $key) {
				//	This operates similarly to the type routine
				$secondToLast = ($iv % 4 > 1) ? 1 : 0;
				$iv = $secondToLast * pow(2, $key);
			});

			$power = floor(array_sum($powerTerms) * 40 / 63 + 30);


			$this->IRCBot->message($msg->getResponseTarget(), sprintf("Your hidden power is %s-type, with a base power of %s.", \IRCUtility::bold(Types::colorType($type)), \IRCUtility::bold($power)));
		}
	}

	private function parseTypeParameter(&$parameters) {
		$firstTwo = implode(" ", array_slice($parameters, 0, 2));
		$type = null;

		if (Types::hasChart($parameters[0]))
			$type = array_shift($parameters);

		elseif (Types::hasChart($firstTwo)) {
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
				$type = $this->PokemonManager->get($name);

				//	Pokemon found
				if ($type instanceof Pokemon)
					break;

				//	No pokemon and we've no words left to check
				elseif ($words == $maxWordsPerPokemon)
					throw new \ModuleException("PokemonModule::parseTypeParameters: Invalid type.");

			}

			$parameters = array_slice($parameters, $words);
		}

		return $type;
	}

	public function type(\IRCMessage $msg) {
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
			if (Types::hasChart($nextTwo)) {
				${"type$i"} = $nextTwo;
				$next += 2;
			}
			//	One word type (all others)
			elseif (Types::hasChart($parameters[$next]))
				${"type$i"} = $parameters[$next++];

			//	Type list delimited by "/"
			elseif (strpos($parameters[$next], "/") !== false) {
				$types = explode("/", $parameters[$next]);

				//	Check each type individually
				foreach ($types as $type) {
					if (Types::hasChart($type))
						${"type$i"}[] = $type;
					else
						//	Invalid type given, abort
						throw new \ModuleException("PokemonModule::type: Invalid type '$type'.");
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
					$pokemon = $this->PokemonManager->get($name, false, false);
					$move = $this->MoveManager->get($name);

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
						#throw new \ModuleException("PokemonModule::type: Invalid type '$name'.");

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

		//	Output pokemon mode and halt
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
				new \ManagerSearchCriterion($this->PokemonManager, "types", "==", $searchType)
			);
			if (count($searchType) == 2)
				$criteria[] = new \ManagerSearchCriterion($this->PokemonManager, "types", "==", array(1 => $searchType[2], 2 => $searchType[1]));

			//	Search matching ANY criteria
			/** @var $pokemon Pokemon[] */
			$pokemon = $this->PokemonManager->search($criteria, true, false);

			//	No results
			if (!$pokemon || !count($pokemon))
				throw new \ModuleException("There are no ". Types::colorType($searchType, true). "-type pokemon.");

			//	Save names of resulting pokemon
			$pokemonNames = array();
			foreach ($pokemon as $object)
				$pokemonNames[] = $object->getName();

			$response = "There are ". \IRCUtility::bold(count($pokemonNames)). " ". Types::colorType($searchType, true). "-type pokemon: ". implode(", ", $pokemonNames). ".";
			$this->IRCBot->message($msg->getResponseTarget(), $response);
			return;
		}

		//	Mode was not pokemon, output offensive or defense type info

		//	Pokemon vs. ...
		if ($type1 instanceof Pokemon) {
			//	Vs. nothing, get type charts
			if (!$type2) {
				//	Type chart vs. this pokemon
				if ($mode == "defensive")
					$result = Types::pokemonMatchup("chart", $type1);
				//	This pokemon's compound types vs. type chart
				elseif ($mode == "offensive")
					$result = Types::typeChart($type1->getType(0), "offensive");
			}

			//	Vs. another pokemon
			elseif ($type2 instanceof Pokemon) {
				//	This pokemon's compound types vs. other pokemon
				if ($mode == "defensive")
					$result = Types::pokemonMatchup($type1->getType(0), $type2);
				//	Other pokemon's compound types vs. this pokemon
				elseif ($mode == "offensive")
					$result = Types::pokemonMatchup($type2->getType(0), $type1);
			}

			//	Vs. a type
			else {
				//	Type vs. this pokemon
				if ($mode == "defensive")
					$result = Types::pokemonMatchup($type2, $type1);
				//	This pokemon's compound types vs. type
				elseif ($mode == "offensive")
					$result = Types::typeMatchup($type1->getType(0), $type2);
			}
		}

		//	Type vs. ...
		else {
			//	Nothing, get type charts
			if (!$type2)
				$result = Types::typeChart($type1, $mode);

			//	Vs. a pokemon
			elseif ($type2 instanceof Pokemon) {
				//	Pokemon's compound types vs. this type
				if ($mode == "defensive")
					$result = Types::typeMatchup($type2->getType(0), $type1);
				//	This type vs. pokemon
				elseif ($mode == "offensive")
					$result = Types::pokemonMatchup($type1, $type2);
			}

			//	Vs. another type
			else {
				//	Other type vs. this type
				if ($mode == "defensive")
					$result = Types::typeMatchup($type2, $type1);
				//	This type vs. other type
				elseif ($mode == "offensive")
					$result = Types::typeMatchup($type1, $type2);
			}
		}

		//	Output chart
		if (!$type2) {
			$abilities = array();
			//	Save ability effects, if applicable
			if ($type1 instanceof Pokemon && isset($result['abilities']))
				$abilities = $result['abilities'];

			//	Filter out 1x matchups
			$result = array_filter($result, function($element) {
				return (is_numeric($element) && $element != 1);
			});

			//	Function to format matchups. Save for use in ability charts if needed
			$parseChart = function($result) {
				//	Group types of the same effectiveness together
				$chart = array();
				foreach ($result as $type => $multiplier)
					$chart[(string)$multiplier][] = Types::colorType($type);
				ksort($chart);

				//	Append list of types to each multiplier
				$output = array();
				foreach ($chart as $multiplier => $entry)
					$output[] = \IRCUtility::bold($multiplier."x") . ": " . implode(", ", $entry);

				//	Each element has Multipler: type1, type2, etc
				return $output;
			};
			$output = $parseChart($result);

			//	Add an extra line for abilities if needed, using the same $parseChart functions
			$abilityOutput = array();
			foreach ($abilities as $ability => $abilityChart)
				$abilityOutput[] = sprintf("[%s]: %s", \IRCUtility::bold($ability), implode(" :: ", $parseChart($abilityChart)));

			//	Format intro with Pokemon name and type
			if ($type1 instanceof Pokemon)
				$outputString = sprintf("%s (%s)", \IRCUtility::bold($type1->getName()), Types::colorType($type1->getType(0), true));
			//	Just a type
			else
				$outputString = Types::colorType($type1, true);

			//	Append formatted chart
			$outputString .= " $mode type chart: ". implode(" :: ", $output);

			//	Stick ability output on a new line if we have any
			if ($abilityOutput)
				$outputString .= "\n". implode("; ", $abilityOutput);

			$this->IRCBot->message($msg->getResponseTarget(), $outputString);
		}

		//	Output matchup
		else {
			$abilities = array();
			//	Save ability effects, if applicable
			if (isset($result['abilities'])) {
				$abilities = $result['abilities'];
				unset($result['abilities']);
			}

			if (is_array($result)) {
				$key = array_keys($result)[0];
				$result = $result[$key];
			}

			//	Add an extra line for abilities if needed
			$abilityOutput = array();
			foreach ($abilities as $ability => $abilityChart) {
				//	There should only be a single entry
				$key = array_keys($abilityChart)[0];
				$abilityOutput[] = sprintf("[%s]: %sx", \IRCUtility::bold($ability), $abilityChart[$key]);
			}

			//	Format intro with Pokemon name and type
			if ($type1 instanceof Pokemon)
				$outputString = sprintf("%s (%s)", \IRCUtility::bold($type1->getName()), Types::colorType($type1->getType(0), true));
			//	Just a type
			else
				$outputString = Types::colorType($type1, true);

			$outputString .= " vs ";
			//	Format opponent
			if ($type2 instanceof Pokemon)
				$outputString .= sprintf("%s (%s)", \IRCUtility::bold($type2->getName()), Types::colorType($type2->getType(0), true));
			//	Just a type
			else
				$outputString .= Types::colorType($type2, true);

			$outputString .= ": ". \IRCUtility::bold($result). "x";
			//	Stick ability output on a new line if we have any
			if ($abilityOutput)
				$outputString .= "\n". implode("; ", $abilityOutput);

			$this->IRCBot->message($msg->getResponseTarget(), $outputString);
		}

	}

	public function coverage(\IRCMessage $msg) {
		$parameters = $msg->getCommandParameters();

		$typeDisplay = array();
		foreach ($parameters as $key => $type) {
			if (!Types::hasChart($type))
				throw new \ModuleException("Invalid type '$type'.");

			$typeDisplay[] = Types::colorType($type, true);
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
					$results = Types::pokemonMatchup($type, $pokemon);

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
							$resistingPokemon[] = $pokemon->getName(). " [". \IRCUtility::italic(ucwords($ability)). "]";
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

		$this->IRCBot->message($msg->getResponseTarget(), $output);
	}

	public function search(\IRCMessage $msg) {
		$parameters = $msg->getCommandParameters();

		//	Parse user-selected search category
		$categories = array("pokemon", "ability", "move", "nature", "item");
		$category = strtolower(array_shift($parameters));
		if (!in_array($category, $categories))
			throw new \ModuleException("Invalid search category '$category'. Valid categories are: ". implode(", ", $categories). ".");

		//	Compose collection of valid operators for input parsing
		$operators = array_merge(\Manager::getNumericOperators(), \Manager::getStringOperators(), \Manager::getArrayOperators());
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
		if (!property_exists($this, $managerClass) || !($this->{$managerClass} instanceof \Manager))
			throw new \ModuleException("Manager of type '$managerClass' does not exist.");
		/** @var \Manager $manager */
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
				if (!($language = PokemonCommon::getLanguage($match[1])))
					throw new \ModuleException("Invalid language '{$match[1]}'.");
			}

			elseif (preg_match($customOperatorRegex, $parameter, $match)) {
				list(, $operator, $value) = $match;
				$criteria[] = new \ManagerSearchCriterion($manager, "", $operator, $value);
			}

			//	Apply new criterion
			elseif (preg_match($regex, $parameter, $match)) {
				list(, $field, $operator, $value) = $match;
				$criteria[] = new \ManagerSearchCriterion($manager, $field, $operator, $value);
			}

			//	Abort on unknown parameter
			else
				throw new \ModuleException("Invalid parameter '$parameter'.");
		}

		$results = $manager->search($criteria);
		//	Apply number of results limit
		if ($return > 0)
			$results = array_slice($results, 0, $return);

		if (!count($results))
			throw new \ModuleException("No results found.");

		//	Convert objects to strings in given language
		foreach ($results as $key => $result) {
			/** @var PokemonCommon $result */
			$results[$key] = $result->getName($language);
		}

		$this->IRCBot->message($msg->getResponseTarget(), implode(", ", $results));
	}

	public function compare(\IRCMessage $msg) {
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
					$pokemon = $this->PokemonManager->get($name, false, false);

					//	Pokemon found
					if ($pokemon instanceof Pokemon) {
						$pokemonList[$i] = $pokemon;
						$next += $words;
						break;
					}

					//	No pokemon and we've no words left to check
					elseif ($words == 1)
						throw new \ModuleException("Invalid pokemon '$name'.");

				}

			}
		}

		if (!($pokemonList[0] instanceof Pokemon) || !($pokemonList[1] instanceof Pokemon))
			throw new \ModuleException("2 pokemon must be provided.");

		$data = array();
		for ($i = 0; $i <= 1; $i++) {
			$info = new PokemonInfoFormat($pokemonList[$i]);
			$data[$i] = array_map("trim", explode("%n", $info->parseFormat(PokemonInfoFormat::getCompareFormat())));
		}

		$statNames = array(2 => "HP", 3 => "Atk", 4 => "Def", 5 => "SpA", 6 => "SpD", 7 => "Spe");
		for ($i = 2; $i <= 7; $i++) {
			$stats = array(\IRCUtility::stripControlCodes($data[0][$i]), \IRCUtility::stripControlCodes($data[1][$i]));
			$colors = array("blue", "blue");

			$paddingWidth = ($i == 2) ? 4 : 3;
			$padding = array($paddingWidth - strlen($stats[0]), $paddingWidth - strlen($stats[1]));

			if ($stats[0] > $stats[1])
				$colors = array("lime", "red");

			elseif ($stats[0] < $stats[1])
				$colors = array("red", "lime");

			$data[0][$i] = str_repeat(chr(160), $padding[0]). \IRCUtility::color($data[0][$i], $colors[0]). $statNames[$i];
			$data[1][$i] = str_repeat(chr(160), $padding[1]). \IRCUtility::color($data[1][$i], $colors[1]). $statNames[$i];
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
			$maxLength = max(array_map("strlen", array_map(array("IRCUtility", "stripControlCodes"), $data[$i])));
			foreach ($data[$i] as $key => $line) {
				if ($i == 0) {
					$realLength = strlen(\IRCUtility::stripControlCodes($line));
					$line = str_repeat(chr(160), $maxLength - $realLength). $line;
				}
				$lines[$key][$i] = $line;
			}

		}
		foreach ($lines as $key => $compareValues)
			$lines[$key] = implode(" | ", $compareValues);

		$this->IRCBot->message($msg->getResponseTarget(), implode("\n", $lines));
	}

	public function updateMetagameDatabase(\IRCMessage $msg) {
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

	private function downloadMetagameDatabase(\IRCMessage $msg) {
		$base = "http://www.smogon.com/stats/";
		$index = \WebAccess::resourcebody($base);
		if (!preg_match_all('/^<a href="(\d{4}-\d{2}\/)">/m', $index, $match, PREG_PATTERN_ORDER))
			throw new \ModuleException("Unable to find latest metagame stats.");
		$latest = $match[1][count($match[1]) - 1];

		$files = array("ubers-0", "ou-0", "uu-0", "nu-0", "doublesubers-0", "doublesou-0", "doublesuu-0", "vgc2015-0");
		$jsonDir = $base. $latest. "chaos/";
		if (!is_dir("metagame"))
			mkdir("metagame");
		else {
			$this->IRCBot->message($msg->getResponseTarget(), "Clearing out old statistics files...");
			array_map("unlink", glob("metagame/*.json"));
		}
		$this->IRCBot->message($msg->getResponseTarget(), "Downloading newest metagame statistics...");
		foreach ($files as $file) {
			if (file_put_contents("metagame/$file.json", file_get_contents($jsonDir.$file.".json")))
				$this->IRCBot->message($msg->getResponseTarget(), "Successfully downloaded $file.");
			else
				$this->IRCBot->message($msg->getResponseTarget(), "Failed to download $file.");
		}
		$this->IRCBot->message($msg->getResponseTarget(), "Download complete.");
	}

	private function insertMetagameDatabase(\IRCmessage $msg) {
		if (!is_dir("metagame"))
			throw new \ModuleException("There are no metagame statistics to insert. Download them first.");

		$files = glob("metagame/*.json");
		if (!$files)
			throw new \ModuleException("There are no metagame statistics to insert. Download them first.");

		$interface = new \DatabaseInterface("utsubot");

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
				/** @var $currentObject PokemonCommon */
				$index = $name = $currentObject->getName();
				if (substr_count($index, " ") > 1)
					$index = implode(" ", array_slice(explode(" ", $index), 0, 2));
				$index = strtolower(str_replace(array(" ", "-"), "", $index));
				$cache[$key][$index] = array($currentObject->getId(), $currentObject->getName());
			}
		}

		$this->IRCBot->message($msg->getResponseTarget(), "Clearing out old data...");
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
			$this->IRCBot->message($msg->getResponseTarget(), "Beginning to process $file...");

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
			#$this->IRCBot->message($msg->getResponseTarget(), "Finished processing $file.");
		}

		$this->IRCBot->message($msg->getResponseTarget(), "All done.");
		$interface->disconnect($statements = array($statement));
	}

}
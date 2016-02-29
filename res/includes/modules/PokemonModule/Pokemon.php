<?php
/**
 * PHPBot - pokemon.php
 * User: Benjamin
 * Date: 14/05/14
 */

namespace Pokemon;

class Pokemon {
	use PokemonCommon {
		PokemonCommon::search as PCSearch;
	}

	/*****************************
	 * * * * * Constants * * * * *
	 *****************************/

	//	Max number of each attribute per pokemon
	const NUMBER_OF_TYPES = 2;
	const NUMBER_OF_ABILITIES = 3;
	const NUMBER_OF_EGG_GROUPS = 2;


	/***************************
	 * * * * * Members * * * * *
	 ***************************/

	//	Search
	private $regexSearch = "//";

	//	Attributes
	private $baseStats = array("hp" => 0, "attack" => 0, "defense" => 0, "special attack" => 0, "special defense" => 0, "speed" => 0);
	private $types = array();
	private $abilities = array();
	private $evolutions = array();
	private $preEvolution = array();
	private $alternateForms = array();

	private $evYield = array("hp" => 0, "attack" => 0, "defense" => 0, "special attack" => 0, "special defense" => 0, "speed" => 0);
	private $baseExp = 0;
	private $catchRate = 0;
	private $ratioMale = 0;
	private $eggSteps = 0;
	private $eggGroup = array();
	private $dexNumbers = array();
	private $baseHappiness = 0;

	//	Pokedex Fields
	private $habitat = "";
	private $species = "";
	private $height = 0;	//	In meters
	private $weight = 0;	//	In kilograms
	private $color = "";

	//	Other info
	private $isBaby = false;

	/***************************
	 * * * * * Methods * * * * *
	 ***************************/

	/**
	 * @param int|array $args Pass a unique id to construct a blank pokemon, or pass an array of 'attribute' => 'value' pairs to fill them in
	 */
	public function __construct($args) {
		//	Default number of array elements
		#$this->abilities = array_fill(1, self::NUMBER_OF_ABILITIES, "");
		#$this->types = array_fill(1, self::NUMBER_OF_TYPES, "");
		#$this->eggGroups = array_fill(1, self::NUMBER_OF_EGG_GROUPS, "");

		//	Only ID passed, construct blank pokemon
		if (is_numeric($args))
			$this->setId($args);

		//	Array of properties passed, parse to construct a pokemon
		elseif (is_array($args)) {
			foreach ($args as $key => $val) {
				switch ($key) {
					case "id":
					case "pid":
						$this->setId($val);
					break;

					case "dex":
					case "dexnum":
					case "dexnums":
						$this->setDexNumber($val);
					break;
					case "National":
					case "Kanto":
					case "Original Johto":
					case "Hoenn":
					case "Original Sinnoh":
					case "Extended Sinnoh":
					case "Updated Johto":
					case "Original Unova":
					case "Updated Unova":
					case "Central Kalos":
					case "Coastal Kalos":
					case "Mountain Kalos":
						$this->setDexNumber($val, $key);
					break;

					case "names":
					case "japanese":
					case "official roomaji":
					case "romaji":
					case "roumaji":
					case "korean":
					case "chinese":
					case "french":
					case "german":
					case "spanish":
					case "italian":
					case "english":
					case "czech":
						$this->setName($val, $key);
					break;

					case "alt":
					case "regex":
						$this->setRegexSearch($val);
					break;

					case "stats":
					case "hp":
					case "hit points":
					case "atk":
					case "attack":
					case "def":
					case "defense":
					case  "spa":
					case "special attack":
					case "special-attack":
					case "spd":
					case "special defense":
					case "special-defense":
					case "spe":
					case "speed":
						$this->setBaseStat($val, $key);
					break;

					case "evs":
						$this->setEvYield($val);
					break;

					case "type":
					case "type1":
					case "type2":
					case "types":
						//	"type" or "type1" at index 1, optional "type2" at index 2
						$index = ($key == "type") ? 1 : substr($key, -1);
						$this->setType($val, $index);
					break;

					case "abls":
					case "abilities":
					case "abl1":
					case "abl2":
					case "abl3":
					case "ablhidden":
					case "abldw":
						//	"abl1" at index 1, "abl2" at index 2, "abl3", "abldw", or "ablhidden" at index 3
						$index = ($key == "ablhidden" || $key == "abldw") ? 3 : substr($key, -1);
						$this->setAbility($val, $index);
					break;

					case "evo":
					case "evolution":
					case "evolutions":
						$this->setEvolution($val);
					break;
					case "preevo":
					case "preEvolution":
						$this->setPreEvolution($val);
					break;

					case "alts":
					case "alternateForms":
						$this->setAlternateForms($val);
					break;

					case "genders":
						//	Check for array ("male" => ratio, "female" => ratio)
						if (is_array($val)) {
							//	If value > 1, pass true to treat as % rather than decimal
							if (isset($val['male']))
								$this->setGenderRate($val['male'], "male", (($val['male'] > 1) ? true : false));
							elseif (isset($val['female']))
								$this->setGenderRate($val['female'], "female", (($val['female'] > 1) ? true : false));
						}
					break;
					//	Individual genders
					case "percentMale":
						$this->setGenderRate($val, "male", (($val > 1) ? true : false));
					break;
					case "percentFemale":
						$this->setGenderRate($val, "female", (($val > 1) ? true : false));
					break;

					//	Various fields
					case "height":			$this->setHeight($val);				break;
					case "weight":			$this->setWeight($val);				break;
					case "habitat":			$this->setHabitat($val);			break;
					case "species":			$this->setSpecies($val);			break;
					case "generation":		$this->setGeneration($val);			break;
					case "eggSteps":		$this->setEggSteps($val);			break;
					case "eggCycles":		$this->setEggSteps($val, true);		break;
					case "isBaby":			$this->setBaby($val);				break;
					case "color":			$this->setColor($val);				break;
					case "experience":		$this->setBaseExp($val);			break;
					case "captureRate":		$this->setCatchRate($val);			break;
					case "happiness":		$this->setBaseHappiness($val);		break;
					case "eggGroup":		$this->setEggGroup($val);			break;
				}
			}
		}
	}

	/**
	 * Test if a search term matches this pokemon
	 *
	 * @param int|string $search Term to search against (id number or any name)
	 * @param bool $strict False to allow wildcard searches
	 * @return bool Search result
	 */
	public function search($search, $strict = false) {
		//	Test base search function in PokemonCommon
		if ($this->PCSearch($search, $strict))
			return true;

		//	If no match, attempt to match this pokemon's regular expression search
		elseif ($this->regexSearch != "//" && preg_match($this->regexSearch, $search))
			return true;

		//	No match
		return false;
	}

	/**
	 * Retrieve the pokemon's dex number. This will be the same even among alternate forms
	 *
	 * @param string $dex (Optional) Which local pokedex index to retrieve from (default "National")
	 * @return int|bool Dex number, or false if it doesn't exist
	 */
	public function getDexNumber($dex = "National") {
		$dex = ucwords(strtolower($dex));
		if (!isset($this->dexNumbers[$dex]))
			return false;

		return $this->dexNumbers[$dex];
	}

	/**
	 * Retrieve the regular expression used to match against failed searches (for alternate spellings or misspellings)
	 *
	 * @return string The regular expression
	 */
	public function getRegexSearch() {
		return $this->regexSearch;
	}

	/**
	 * Get an ability or all abilities of this pokemon
	 *
	 * @param int $number The ability number (1-3) to retrieve. Pass 0 to retrieve all abilities
	 * @param bool $string (Optional) If retrieving all abilities, pass true to return as a string instead of an array
	 * @param string $separator (Optional) If returning all abilities as a string, you can override the default separator of comma
	 * @return array|string The array of abilities, ability name, or string list of abilities
	 */
	public function getAbility($number = 0, $string = false, $separator = ",") {
		//	Ability number
		if (self::intRange($number, 1, self::NUMBER_OF_ABILITIES) && isset($this->abilities[$number]))
			return $this->abilities[$number];

		//	All abilities
		elseif ($number === 0) {
			//	Return array
			if (!$string)
				return $this->abilities;

			//	Return string with separator
			elseif (gettype($separator) == "string")
				return implode($separator, $this->abilities);

			//	Default separator
			else
				return implode(",", $this->abilities);
		}

		//	Invalid call
		return "";
	}

	/**
	 * Get a base stat or all base stats of this pokemon
	 * Also used to retrieve ev yield
	 *
	 * @param mixed $stat The stat as an abbreviation (hp, atk, def, spa, spd, spe), index 0-5, or full name spelled out. Pass "all" to retrieve all stats
	 * @param bool $evs (Optional) Pass true to retrieve EV yield instead of base stats
	 * @param bool $string (Optional) If retrieving all stats, pass true to return as a string instead of array
	 * @param string $separator (Optional) If returning all stats as a string, you can ovverride the default separator of comma
	 * @return array|string The array of stats, stat value, or string list of stats
	 */
	public function getBaseStat($stat = "all", $evs = false, $string = false, $separator = ",") {
		$stat = strtolower($stat);
		$stats = ($evs) ? $this->evYield : $this->baseStats;

		//	Single stat or ev
		if (isset($stats[$stat]))
			return $stats[$stat];

		//	Single stat or ev by index
		$keys = array_keys($stats);
		if (self::intRange($stat, 0, 5))
			return $stats[$keys[$stat]];

		//	Single stat or ev by short name
		$shortNames = array("hp", "atk", "def", "spa", "spd", "spe");
		if (($key = array_search($stat, $shortNames)) !== false)
			return $stats[$keys[$key]];

		//	All stats or evs
		if ($stat == "all") {
			//	Return array
			if (!$string)
				return $stats;

			//	Default separator
			if (gettype($separator) != "string")
				$separator = ",";

			//	Return string with separator
			return implode($separator, $stats);
		}

		//	Invalid call
		return "";
	}

	public function getBaseStatTotal() {
		return array_sum($this->baseStats);
	}


	/**
	 * Wrapper for ev yield calls to getBaseStat
	 *
	 * @param mixed $stat The stat as an abbreviation (hp, atk, def, spa, spd, spe), index 0-5, or full name spelled out. Pass "all" to retrieve all stats
	 * @param bool $string (Optional) If retrieving all stats, pass true to return as a string instead of array
	 * @param string $separator (Optional) If returning all stats as a string, you can ovverride the default separator of comma
	 * @return array|string The array of abilities, ability name, or string list of abilities
	 */
	public function getEvYield($stat, $string = false, $separator = ",") {
		return $this->getBaseStat($stat, true, $string, $separator);
	}

	/**
	 * Get all types or a single type for this pokemon
	 *
	 * @param int $number (Optional) The index of the type to retrieve, or 0 (default) for all types
	 * @param bool $string (Optional) If retrieving all types, pass true to return as a string instead of an array
	 * @param string $separator (Optional) If returning all types as a string, you can override the default separator of forward slash
	 * @return array|string The type or array of types
	 */
	public function getType($number = 0, $string = false, $separator = "/") {
		if (self::intRange($number, 1, self::NUMBER_OF_TYPES) && isset($this->types[$number]))
			return $this->types[$number];

		//	All types
		if ($number === 0) {
			//	Remove trailing empty type
			$types = array_filter($this->types);

			//	Return array
			if (!$string)
				return $types;

			//	Return string with separator
			if (gettype($separator) == "string")
				return implode($separator, $types);

			//	Default separator
			return implode("/", $types);
		}

		//	Invalid call
		return "";
	}

	/**
	 * Get one or more evolutions for this pokemon
	 *
	 * @param int|string $evo The name of the desired evolution to check, "all" for all, or an index to check the nth evo
	 * @param int $return (Optional) Pass 1 to return evolution name, 2 to return method, 3 to return both
	 * @param boolean $preEvo (Optional) Pass true to search for pre evolutions instead
	 * @param boolean $short (Optional) Pass true to return only the first set of details for each evolution method
	 * @return string|bool The name of the evolution(s), the method(s), or false on invalid search
	 */
	public function getEvolution($evo, $return = 3, $preEvo = false, $short = false) {
		$evoArray = ($preEvo) ? $this->preEvolution : $this->evolutions;

		//	Index passed
		$evoNames = array_keys($evoArray);
		if (self::intRange($evo, 0, count($evoNames) - 1))
			return $this->getEvolution($evoNames[$evo], $return, $preEvo, $short);

		$evo = ucwords(strtolower($evo));

		//	Return all evos
		if ($evo == "All") {
			$evos = array();
			foreach ($evoArray as $currentEvo => $methods)
				$evos[] = $this->getEvolution($currentEvo, $return, $preEvo, $short);

			return implode(";", $evos);
		}

		//	Return a specific evo
		if (isset($evoArray[$evo])) {
			if ($return == 1)
				return $evo;

			$conditions = array();

			foreach ($evoArray[$evo] as $method => $details) {
				switch ($method) {
					case "level-up":	$methodDisplay = "Level";		break;
					case "trade":		$methodDisplay = "Trade";		break;
					case "use-item":	$methodDisplay = "Use";			break;
					case "shed":		$methodDisplay = "Shed";		break;
					default:			$methodDisplay = "$method";		break;
				}

				foreach ($details as $count => $instance) {
					//	Begin method string with method name
					$conditions[$method][$count] = array($methodDisplay);

					foreach ($instance as $condition => $value) {
						//	Fill in method string with details
						switch ($condition) {
							case "level":				$conditions[$method][$count][] = $value;						break;
							case "useItem":				$conditions[$method][$count][] = $value;						break;
							case "holdItem":			$conditions[$method][$count][] = "Holding $value";				break;
							case "gender":				$conditions[$method][$count][] = "($value)";					break;
							case "location":			$conditions[$method][$count][] = "at $value";					break;
							case "generation":			$conditions[$method][$count][] = "(Gen $value)";				break;
							case "time":				$conditions[$method][$count][] = "during $value";				break;
							case "knowsMove":			$conditions[$method][$count][] = "(Knows $value)";				break;
							case "knowsMoveType":		$conditions[$method][$count][] = "(Knows $value-type move)";	break;
							case "happiness":			$conditions[$method][$count][] = "($value+ happiness)";			break;
							case "beauty":				$conditions[$method][$count][] = "($value+ beauty)";			break;
							case "affection":			$conditions[$method][$count][] = "($value+ affection)";			break;
							case "partyPokemon":		$conditions[$method][$count][] = "($value in party)";			break;
							case "partyPokemonType":	$conditions[$method][$count][] = "($value-type in party)";		break;
							case "tradeFor":			$conditions[$method][$count][] = "for $value";					break;
							case "relativeStats":		$conditions[$method][$count][] = "with $value";					break;
							case "rain":				$conditions[$method][$count][] = "(Raining in overworld)";		break;
							case "upsideDown";			$conditions[$method][$count][] = "(Turn system upside-down)";	break;
						}
					}

					//	Join parts of method string
					$conditions[$method][$count] = implode(" ", $conditions[$method][$count]);
				}

				//	Return only first set of conditions for this method
				if ($short)
					$conditions[$method] = $conditions[$method][0];
				//	Join all sets of details
				else
					$conditions[$method] = implode(\IRCUtility::italic(" OR "), $conditions[$method]);
			}

			//	Join conditions for each method
			$conditions = implode(\IRCUtility::italic(" OR "), $conditions);

			if ($return == 2)
				return $conditions;

			elseif ($return == 3)
				return "$evo/$conditions";
		}

		//	Invalid
		return false;
	}

	/**
	 * Wrapper for pre-evolution mode of getEvolution
	 *
	 * @param int|string $evo The name of the desired evolution to check, "all" for all, or an index to check the nth evo
	 * @param boolean $method (Optional) Pass true to return the method rather than the pokemon name
	 * @param boolean $short (Optional) Pass true to return only the first set of details for each evolution method
	 * @return string|bool The name of the evolution(s), the method(s), or false on invalid search
	 */
	public function getPreEvolution($evo, $method = true, $short = false) {
		return $this->getEvolution($evo, $method, true, $short);
	}

	/**
	 * Get the base experience this pokemon awards upon defeat
	 *
	 * @return int Base experience value
	 */
	public function getBaseExp() {
		return $this->baseExp;
	}

	/**
	 * Get the pokemon's capture rate, which determines how easy it is to catch (lower values = more difficult)
	 *
	 * @return int Capture rate
	 */
	public function getCatchRate() {
		return $this->catchRate;
	}

	/**
	 * Get the likelihood of this pokemon being male or female
	 *
	 * @param string $gender "male" or "female"
	 * @param bool $percent True to return as a percentage, false for a decimal
	 * @return bool|int Returns relevant rate, or false on failure
	 */
	public function getGenderRatio($gender = "male", $percent = false) {
		if ($gender == "male")
			$rate = $this->ratioMale;
		elseif ($gender == "female")
			$rate = 1 - $this->ratioMale;
		//	Only male and female are valid options
		else
			return false;

		if ($percent)
			$rate *= 100;

		return $rate;
	}

	/**
	 * Get the minimum number of steps required to hatch an egg holding this pokemon, or the number of egg cycles
	 *
	 * @param bool $cycles True for cycles, false for step count
	 * @return int Number of steps or cycles
	 */
	public function getEggSteps($cycles = false) {
		//	Egg cycles rather than total step count
		if ($cycles)
			return intval(($this->eggSteps / 255) - 1);

		return $this->eggSteps;
	}

	/**
	 * Get a pokemon's habitat as defined in the in-game pokedex of older versions
	 *
	 * @return string The habitat name
	 */
	public function getHabitat() {
		return $this->habitat;
	}

	/**
	 * Get a pokemon's species as defined in the in-game pokedex
	 *
	 * @return string The species name
	 */
	public function getSpecies() {
		return $this->species;
	}

	/**
	 * Get a pokemon's height
	 *
	 * @param string $unit (Optional) Unit of length, default meters
	 * @return bool|float Height value, or false on conversion failure
	 */
	public function getHeight($unit = "meter") {
		if (in_array($unit, array("meter", "metre", "m")))
			return $this->height;
		elseif (($height = \Conversion::convert("distance", $this->height, "meter", $unit)) !== false)
			return $height;

		return false;
	}

	/**
	 * Get a pokemon's weight
	 *
	 * @param string $unit (Optional) Unit of weight/mass, default kilograms
	 * @return bool|float Weight value, or false on conversion failure
	 */
	public function getWeight($unit = "kilogram") {
		if (in_array($unit, array("kilogram", "kg")))
			return $this->weight;
		elseif (($weight = \Conversion::convert("mass", $this->weight, "kilogram", $unit)) !== false)
			return $weight;

		return false;
	}

	/**
	 * Get a pokemon's color as defined in the in-game pokedex
	 *
	 * @return string Name of color
	 */
	public function getColor() {
		return $this->color;
	}

	/**
	 * Get a pokemon's base happiness value
	 *
	 * @return int Happiness val
	 */
	public function getBaseHappiness() {
		return $this->baseHappiness;
	}

	/**
	 * Determine whether or not this pokemon is considered a "baby" pokemon
	 *
	 * @return bool true or false
	 */
	public function isBaby() {
		return $this->isBaby;
	}

	/**
	 * Get an egg group or all egg groups for this pokemon
	 *
	 * @param int|string $eggGroup The index of an egg group, or "all" to return all egg groups
	 * @return string|array|bool The egg group name, array of egg groups, or false on failure
	 */
	public function getEggGroup($eggGroup = "all") {
		if (is_int($eggGroup) && isset($this->eggGroup[$eggGroup]))
			return $this->eggGroup[$eggGroup];

		elseif ($eggGroup == "all")
			return $this->eggGroup;

		return false;
	}

	/**
	 * Sets the dex nunber for this pokemon
	 *
	 * @param int $number A non-negative integer as the dex number
	 * @param string $dex (Optional) The name of the dex, default "National"
	 * @return bool True on success, false on failure
	 */
	public function setDexNumber($number, $dex = "National") {
		//	Array of dex numbers, set them all
		if (is_array($number)) {
			$return = true;
			foreach ($number as $newDex => $newNumber) {
				if (!$this->setDexNumber($newNumber, $newDex))
					$return = false;
			}
			return $return;
		}


		//	Number must be a non-negative integer
		if (!self::intRange($number, 0))
			return false;
		//	Dex name must be a string
		if (!is_string($dex))
			return false;

		//	Success
		$this->dexNumbers[$dex] = intval($number);
		return true;
	}

	/**
	 * Set the regular expression used to perform additional searches for this pokemon
	 *
	 * @param string $regex A valid regular expression
	 * @return bool True on success, false on failure
	 */
	public function setRegexSearch($regex) {
		//	Ensure regex delimeters are in place
		if (substr($regex, 0, 1) != "/")
			$regex = "/$regex/";

		//	Must be a valid regular expression
		if (@preg_match($regex, null) === false)
			return false;

		//	Success
		$this->regexSearch = $regex;
		return true;
	}

	/**
	 * Sets an ability or multiply abilities for this pokemon
	 *
	 * @param string|array $ability An ability name or array of ability names (to set multiple)
	 * @param int $number (Optional) The index (0-2) of the ability to set. Unused if passing array of abilities (use those indicies instead)
	 * @return bool True on success, false on any failure
	 */
	public function setAbility($ability, $number = 1) {
		//	Set multiple abilities at once
		if (is_array($ability)) {
			$return = true;

			//	Intended structure: array(1 => "Ability 1", 2 => "Ability 2", 3 => "Hidden Ability")
			foreach ($ability as $key => $val) {
				if (!$this->setAbility($val, $key))
					$return = false;	//	False if any fail
			}
			return $return;
		}

		//	Set single ability

		//	Index limited by number of possible abilities
		if (!self::intRange($number, 1, self::NUMBER_OF_ABILITIES))
			return false;

		//	Success, format ability
		$this->abilities[$number] = ucwords(strtolower($ability));
		return true;
	}

	/**
	 * Sets a base stat or multiple base stats for this pokemon
	 *
	 * @param int|array $value A stat value or array of stats values (to set multiple)
	 * @param string|int $stat The stat as an abbreviation (hp, atk, def, spa, spd, spe), index 0-5, or full name spelled out. Unused it passing array of stats (use those indices instead)
	 * @param bool $evs Pass true to operate on the EV Yield rather than base stats of this pokemon
	 * @return bool True on success, false on any failure
	 */
	public function setBaseStat($value, $stat = "hp", $evs = false) {
		$stats =& $this->baseStats;
		if ($evs)
			$stats =& $this->evYield;

		//	Set multiple stats at once
		if (is_array($value)) {
			$return = true;

			//	Intended structure: array("hp" => (val), "attack" => (val), etc);
			foreach ($value as $key => $val) {
				if (!$this->setBaseStat($val, $key, $evs))
					$return = false;	//	False if any fail
			}
			return $return;
		}

		//	Set single stat

		//	Stat must be a non-negative integer
		if (!self::intRange($value, 0))
			return false;

		//	Strip dashes for multi word stats
		$stat = str_replace("-", " ", $stat);

		//	Set stat by name
		$stat = strtolower($stat);
		if (isset($stats[$stat])) {
			$stats[$stat] = intval($value);
			return true;
		}

		//	Set stat by index: 0 => hp, 1 => atk, 2 => def, 3 => spa, 4 => spd, 5 => spe
		$keys = array_keys($stats);
		if (self::intRange($stat, 0, 5)) {
			$stats[$keys[$stat]] = intval($value);
			return true;
		}

		//	Set stat by short name
		$shortNames = array("hp", "atk", "def", "spa", "spd", "spe");
		if (($key = array_search($stat, $shortNames)) !== false) {
			$stats[$keys[$key]] = intval($value);
			return true;
		}

		//	Invalid call
		return false;
	}

	/**
	 * Sets the ev yield for this pokemon. Wrapper for passing $evs = true to setBaseStat
	 *
	 * @param int|array $value A stat value or array of stats values (to set multiple)
	 * @param string|int $stat The stat as an abbreviation (hp, atk, def, spa, spd, spe), index 0-5, or full name spelled out. Unused it passing array of stats (use those indices instead)
	 * @return bool True on success, false on any failure
	 */
	public function setEvYield($value, $stat = "hp") {
		return $this->setBaseStat($value, $stat, true);
	}

	/**
	 * Set a type or both types for this pokemon
	 *
	 * @param string|array $value A type name, a list of type names separated by "/", or an array of type names
	 * @param int $number (Optional) If setting a single type, you can pass 1 or 2 to specify type 1 or 2
	 * @return bool True on success, false on any failure
	 */
	public function setType($value, $number = 1) {
		//	Types class not loaded, can't verify types
		if (!class_exists("Pokemon\\Types"))
			return false;

		//	Multiple types given as array
		if (is_array($value)) {
			$set = 0;
			$return = true;
			foreach ($value as $type) {
				if (!$this->setType($type, $set+1))	//	Return false on any failure
					$return = false;
				else
					$set++;

				if ($set >= self::NUMBER_OF_TYPES)	//	Don't set too many types
					break;
			}
			return $return;
		}

		//	Multiple types given as / delimited string
		if (strpos($value, "/") !== false)
			return $this->setType(explode("/", $value));

		//	Index limited by number of possible types
		if (!self::intRange($number, 1, self::NUMBER_OF_TYPES))
			return false;

		//	Type doesn't exist
		if (!Types::isType($value))
			return false;

		//	Success, format type
		$this->types[$number] = ucwords(strtolower($value));
		return true;
	}

	/**
	 * Set one or more evolutions for this pokemon
	 *
	 * @param int|string|array $evo (Optional) The id of the pokemon this pokemon evolves into. You can pass an array as well, mimicking the structure of the PokemonDatabaseInterface evolution arrays
	 * 								Leave blank to clear saved evolutions.
	 * @param string $method (Optional) How this pokemon evolves into the given evolution (e.g., level up, trade, etc).
	 * @param array $details (Optional) Conditions to be met (e.g., minimum level, hold item, etc).
	 * @param boolean $isPreEvo (Optional) Whether or not this is a pre-evolution
	 * @param boolean $add (Optional) Pass true to append this evolution to the list, rather than overwrite the list. Not applicable for pre-evolutions
	 * @return boolean True on success, false on failure
	 */
	public function setEvolution($evo = "", $method = "", $details = array(), $isPreEvo = false, $add = false) {
		if (!$isPreEvo && !$add) {
			$this->evolutions = array();
			if (!$evo)
				return true;
		}

		//	Full evolution array given from PokemonDatabaseInterface
		if (is_array($evo)) {
			//	For each evolution...
			foreach ($evo as $newEvo => $newMethods) {
				/*	There is a list of methods.
				 * 	This is generalized to fit a special case. At the time of writing, the only pokemon->evolution relationship with more than 1 method is feebas, depending on version	 */
				foreach ($newMethods as $newMethod => $detailsList) {
					/*	For each method, there is a collection of details arrays.
					 *	This covers cases like Leafeon and Glaceon who evolve using the same method, but with different circumstances, depending on version
					 *  Array is key-sorted first to ensure methods are added chronologically */
					ksort($detailsList);
					foreach ($detailsList as $newDetails)
						$this->setEvolution($newEvo, $newMethod, $newDetails, $isPreEvo, true);
				}
			}
			return true;
		}

		//	Individual parameters given
		elseif ($evo) {
			//	Pre evolution
			if ($isPreEvo)
				$this->preEvolution[$evo][$method][] = $details;
			else
				$this->evolutions[$evo][$method][] = $details;

			return true;
		}
		return false;
	}

	/**
	 * Set the pre-evolution for this pokemon
	 * Wrapper for setEvolution() with $isPreEvo=true
	 *
	 * @param int|string|array $preEvo (Optional) The id of the pokemon this pokemon evolves from. You can pass an array as well, mimicking the structure of the PokemonDatabaseInterface evolution arrays
	 * 								Leave blank to clear saved evolutions.
	 * @param string $method (Optional) How this pokemon evolves from the given evolution (e.g., level up, trade, etc).
	 * @param array $details (Optional) Conditions to be met (e.g., minimum level, hold item, etc).
	 * @return boolean True on success, false on failure
	 */
	public function setPreEvolution($preEvo = "", $method = "", $details = array()) {
		return $this->setEvolution($preEvo, $method, $details, true);
	}

	/**
	 * @param array $alts
	 */
	public function setAlternateForms($alts) {
		$this->alternateForms = $alts;
	}

	/**
	 * Set a pokemon's base experience awarded upon defeat
	 *
	 * @param int $baseExp The experience value
	 * @return boolean True on success, false on failuire
	 */
	public function setBaseExp($baseExp) {
		if (!self::intRange($baseExp, 0))
			return false;

		$this->baseExp = $baseExp;
		return true;
	}

	/**
	 * Set the pokemon's capture rate, which determines how easy it is to catch (lower values = more difficult)
	 *
	 * @param int $catchRate The capture rate
	 * @return boolean True on success, false on failure
	 */
	public function setCatchRate($catchRate) {
		if (!self::intRange($catchRate, 3, 255))
			return false;

		$this->catchRate = $catchRate;
		return true;
	}

	/**
	 * Set the likelihood of this pokemon being male or female
	 *
	 * @param string $genderRate The rate as percent or decimal
	 * @param string $gender (Optional) Default "male" or "female"
	 * @param bool $percent True if passing a percentage, default false for a decimal
	 * @return bool Returns true on success, fales on failure
	 */
	public function setGenderRate($genderRate, $gender = "male", $percent = false) {
		if (!is_numeric($genderRate) || !self::intRange(intval($genderRate), -1, 100))
			return false;

		if ($percent)
			$genderRate /= 100;
		if ($gender == "female")
			$genderRate = 1 - $genderRate;

		$this->ratioMale = $genderRate;
		return true;
	}

	/**
	 * Set the minimum number of steps required to hatch an egg holding this pokemon, or the number of egg cycles
	 *
	 * @param int $eggSteps Minimum number of steps to take, or number of egg cycles
	 * @param bool (Optional) $cycles True for cycles, default false for step count
	 * @return bool True on success, false on failure
	 */
	public function setEggSteps($eggSteps, $cycles = false) {
		//	Egg cycles rather than total step count
		if ($cycles)
			$eggSteps = ($eggSteps + 1) * 255;

		//	Highest value of any pokemon is 30855 (120 cycles)
		if (!self::intRange($eggSteps, -1, 30855))
			return false;

		$this->eggSteps = $eggSteps;
		return true;
	}

	/**
	 * Set a pokemon's habitat as defined in the in-game pokedex of older versions
	 *
	 * @param string $habitat The habitat name
	 * @return bool True on success, false on failure
	 */
	public function setHabitat($habitat) {
		if (!is_string($habitat))
			return false;

		$this->habitat = $habitat;
		return true;
	}

	/**
	 * Set a pokemon's species as defined in the in-game pokedex
	 *
	 * @param string $species The species name
	 * @return bool True on success, false on failure
	 */
	public function setSpecies($species) {
		if (!is_string($species))
			return false;

		$this->species = $species;
		return true;
	}

	/**
	 * Set a pokemon's height
	 *
	 * @param float $height Height value
	 * @param string $unit (Optional) Unit of length, default meters
	 * @return bool True on success, false on failure
	 */
	public function setHeight($height, $unit = "meter") {
		if (!is_numeric($height))
			return false;

		if (in_array($unit, array("meter", "metre", "m")))
			$this->height = $height;
		elseif (($newHeight = \Conversion::convert("distance", $height, "meter", $unit)) !== false)
			$this->height = $newHeight;
		else
			return false;

		return true;
	}

	/**
	 * Set a pokemon's weight
	 *
	 * @param float $weight Weight value
	 * @param string $unit (Optional) Unit of weight/mass, default kilograms
	 * @return bool True on success, false on failure
	 */
	public function setWeight($weight, $unit = "kilogram") {
		if (!is_numeric($weight))
			return false;

		if (in_array($unit, array("kilogram", "kg")))
			$this->weight = $weight;
		elseif (($newWeight = \Conversion::convert("mass", $weight, "kilogram", $unit)) !== false)
			$this->weight = $newWeight;
		else
			return false;

		return true;
	}

	/**
	 * Set a pokemon's color as defined in the in-game pokedex
	 *
	 * @param string $color Name of color
	 * @return bool True on success, false on failure
	 */
	public function setColor($color) {
		if (!is_string($color))
			return false;

		$this->color = $color;
		return true;
	}

	/**
	 * Set a pokemon's base happiness
	 *
	 * @param int $happiness Base happiness value
	 * @return bool True on success, false on failure
	 */
	public function setBaseHappiness($happiness) {
		if (!self::intRange($happiness, 0, 255))
			return false;

		$this->baseHappiness = $happiness;
		return true;
	}

	/**
	 * Set whether or not this pokemon is considered a "baby" pokemon
	 *
	 * @param bool $isBaby true or false
	 * @return bool True on success, false on failure
	 */
	public function setBaby($isBaby) {
		if (!is_bool($isBaby) && !is_int($isBaby))
			return false;

		$this->isBaby = (bool) $isBaby;
		return true;
	}

	/**
	 * Set this pokemon's egg group(s) used for breeding
	 *
	 * @param string|array $eggGroup An egg group name or an array of them
	 * @return bool True on success, false on failure
	 */
	public function setEggGroup($eggGroup) {
		if (!is_array($eggGroup)) {
			if (!is_string($eggGroup))
				return false;

			$eggGroup = array($eggGroup);
		}

		$this->eggGroup = $eggGroup;
		return true;
	}


}
<?php
/**
 * PHPBot - types.php
 * User: Benjamin
 * Date: 15/05/14
 */

namespace Pokemon;

class TypesException extends \Exception {}

class Types {

	use \IRCFormatting;

	const IMMUNE = 0;
	const NOT_VERY_EFFECTIVE = 1;
	const SUPER_EFFECTIVE = 2;
	const EFFECTIVE = 3;

	const CHART_BASIC = 10;
    const CHART_SPECIAL = 11;
    const CHART_BOTH = 12;

	private static $typeList = array(
		"Bug",		"Dark",		"Dragon",	"Electric",	"Fairy",	"Fighting",
		"Fire", 	"Flying",	"Ghost",	"Grass",	"Ground",	"Ice",
		"Normal",	"Poison",	"Psychic",	"Rock",		"Steel",	"Water"		);

	private static $specialTypes = array(
		"Flying Press",			"Freeze-dry"			);

	private static $typeChart = array(
		"bug"		=>	array(	self::NOT_VERY_EFFECTIVE	=>	array(	"fairy",	"fighting",	"fire",		"flying",	"ghost",	"poison",	"steel"	),
								self::SUPER_EFFECTIVE		=>	array(	"dark",		"grass",	"psychic"	)),

		"dark"		=>	array(	self::NOT_VERY_EFFECTIVE	=>	array(	"dark",		"fairy",	"fighting"	),
								self::SUPER_EFFECTIVE		=>	array(	"ghost",	"psychic"	)),

		"dragon"	=>	array(	self::IMMUNE				=>	array(	"fairy"		),
								self::NOT_VERY_EFFECTIVE	=>	array(	"steel"		),
								self::SUPER_EFFECTIVE		=>	array(	"dragon"	)),

		"electric"	=>	array(	self::IMMUNE				=>	array(	"ground"	),
								self::NOT_VERY_EFFECTIVE	=>	array(	"dragon",	"electric",	"grass"		),
								self::SUPER_EFFECTIVE		=>	array(	"flying",	"water"		)),

		"fairy"		=>	array(	self::NOT_VERY_EFFECTIVE	=>	array(	"fire",		"poison",	"steel"		),
								self::SUPER_EFFECTIVE		=>	array(	"dark",		"dragon",	"fighting"	)),

		"fighting"	=>	array(	self::IMMUNE				=>	array(	"ghost"		),
								self::NOT_VERY_EFFECTIVE	=>	array(	"bug",		"fairy",	"flying",	"poison",	"psychic"	),
								self::SUPER_EFFECTIVE		=>	array(	"dark",		"ice",		"normal",	"rock",		"steel"		)),

		"fire"		=>	array(	self::NOT_VERY_EFFECTIVE	=>	array(	"dragon",	"fire",		"rock",		"water"		),
								self::SUPER_EFFECTIVE		=>	array(	"bug",		"grass",	"ice",		"steel"		)),

		"flying"	=>	array(	self::NOT_VERY_EFFECTIVE	=>	array(	"electric",	"rock",		"steel"		),
								self::SUPER_EFFECTIVE		=>	array(	"bug",		"fighting",	"grass"		)),

		"ghost"		=>	array(	self::IMMUNE				=>	array(	"normal"	),
								self::NOT_VERY_EFFECTIVE	=>	array(	"dark"		),
								self::SUPER_EFFECTIVE		=>	array(	"ghost",	"psychic"	)),

		"grass"		=>	array(	self::NOT_VERY_EFFECTIVE	=>	array(	"bug",		"dragon",	"fire",		"flying",	"grass",	"poison",	"steel"	),
								self::SUPER_EFFECTIVE		=>	array(	"ground",	"rock",		"water"		)),

		"ground"	=>	array(	self::IMMUNE				=>	array(	"flying"	),
								self::NOT_VERY_EFFECTIVE	=>	array(	"bug",		"grass"		),
								self::SUPER_EFFECTIVE		=>	array(	"electric",	"fire",		"poison",	"rock",		"steel"		)),

		"ice"		=>	array(	self::NOT_VERY_EFFECTIVE	=>	array(	"fire",		"ice",		"steel",	"water"		),
								self::SUPER_EFFECTIVE		=>	array(	"dragon",	"flying",	"grass",	"ground"	)),

		"normal"	=>	array(	self::IMMUNE				=>	array(	"ghost"		),
								self::NOT_VERY_EFFECTIVE	=>	array(	"rock",		"steel"		)),

		"poison"	=>	array(	self::IMMUNE				=>	array(	"steel"		),
								self::NOT_VERY_EFFECTIVE	=>	array(	"ghost",	"ground",	"poison",	"rock"		),
								self::SUPER_EFFECTIVE		=>	array(	"fairy",	"grass"		)),

		"psychic"	=>	array(	self::IMMUNE				=>	array(	"dark"		),
								self::NOT_VERY_EFFECTIVE	=>	array(	"psychic",	"steel"		),
								self::SUPER_EFFECTIVE		=>	array(	"fighting",	"poison"	)),

		"rock"		=>	array(	self::NOT_VERY_EFFECTIVE	=>	array(	"fighting",	"ground",	"steel"		),
								self::SUPER_EFFECTIVE		=>	array(	"bug",		"fire",		"flying",	"ice"		)),

		"steel"		=>	array(	self::NOT_VERY_EFFECTIVE	=>	array(	"electric",	"fire",		"steel",	"water"		),
								self::SUPER_EFFECTIVE		=>	array(	"fairy",	"ice",		"rock"		)),

		"water"		=>	array(	self::NOT_VERY_EFFECTIVE	=>	array(	"dragon",	"grass",	"water"		),
								self::SUPER_EFFECTIVE		=>	array(	"fire",		"ground",	"rock"		)),

		"flying press"
					=>	array(	self::IMMUNE				=>	array(	"ghost"		),
								self::NOT_VERY_EFFECTIVE	=>	array(	"electric",	"fairy",	"flying",	"poison",	"psychic"	),
								self::SUPER_EFFECTIVE		=>	array(	"dark",		"fighting",	"grass",	"ice",		"normal"	)),

		"freeze-dry"
					=>	array(	self::NOT_VERY_EFFECTIVE	=>	array(	"fire",		"ice",		"steel"		),
								self::SUPER_EFFECTIVE		=>	array(	"dragon",	"flying",	"grass",	"ground",	"water"		)));

	private static $defensiveAbilityEffects = array(
		"Volt Absorb"	=>	array(	"electric"	=>	-1		),
		"Water Absorb"	=>	array(	"water"		=>	-1		),
		"Flash Fire"	=>	array(	"fire"		=>	0		),
		"Levitate"		=>	array(	"ground"	=>	0		),
		"Lightning Rod"	=>	array(	"electric"	=>	0		),
		"Motor Drive"	=>	array(	"electric"	=>	0		),
		"Storm Drain"	=>	array(	"water"		=>	0		),
		"Herbivore"		=>	array(	"grass"		=>	0		),
		"Sap Sipper"	=>	array(	"grass"		=>	0		),
		"Heatproof"		=>	array(	"fire"		=>	0.5		),
		"Thick Fat"		=>	array(	"fire"		=>	0.5,	"ice"	=>	0.5		),
		"Dry Skin"		=>	array(	"water"		=>	-1,		"fire"	=>	1.25	),

		"special"		=>	array(
			"Filter"		=>	array(	"multiplier"	=>	0.75,	"condition"	=>	">",	"value" =>	1),
			"Solid Rock"	=>	array(	"multiplier"	=>	0.75,	"condition"	=>	">",	"value" =>	1),
			"Wonder Guard"	=>	array(	"multiplier"	=>	0,		"condition"	=>	"<",	"value"	=>	2)
		));


	private static $typeColors = array(
		"bug"			=> array("green", null),		"dark"		=> array("black", "white"),		"dragon"	=> array("teal", null),				"electric"		=> array("yellow", "black"),
		"fighting"		=> array("maroon", null),		"fire"		=> array("red", null),			"flying"	=> array("light gray", "black"),	"ghost"			=> array("purple", null),
		"grass"			=> array("lime", "black"),		"ground"	=> array("orange", null),		"ice"		=> array("aqua", "black"),			"normal"		=> array("white", "black"),
		"poison"		=> array("purple", null),		"psychic"	=> array("fuchsia", null),		"rock"		=> array("orange", null),			"steel"			=> array("gray", null),
		"water"			=> array("blue", null),			"fairy"		=> array("fuchsia", null),		"bird"		=> array("light gray", "black"),	"flying press"	=> array("maroon", null),
		"freeze-dry"	=> array("aqua", "black")
	);

	public static function getTypeList() {
		return self::$typeList;
	}

	/**
	 * Format a type or collection of types with IRC colors
	 *
	 * @param string|array $types A type or array of types
	 * @param bool $bold True to additionally bold each type
	 * @param string $delimeter If processing an array of types, they will be imploded on this
	 * @return string A delimeted string of all valid types
	 */
	public static function colorType($types, $bold = false, $delimeter = "/") {
		//	Normalize to an array for processing
		if (!is_array($types))
			$types = array($types);

		foreach ($types as $key => $type) {
			//	Omit invalid types
			if (!self::hasChart($type))
				continue;

			$typeName = strtolower($type);
			//	Call color function with our color names
			$types[$key] = self::color(ucfirst($typeName), self::$typeColors[$typeName][0], self::$typeColors[$typeName][1]);

			//	Optionally bold
			if ($bold)
				$types[$key] = self::bold($types[$key]);
		}

		return implode($delimeter, $types);
	}

	/**
	 * Check if a string is a main pokemon type
	 *
	 * @param string $type String to check
	 * @param bool $caseSensitive (Optional) enable to match only Capitalized Types
	 * @return bool True on success, false on failure
	 */
	public static function isType($type, $caseSensitive = false) {
		if (!$caseSensitive)
			$type = ucfirst(strtolower($type));
		return in_array($type, self::$typeList);
	}

	/**
	 * Check if a string has a type chart (main type or special type)
	 *
	 * @param string $type
	 * @param bool $caseSensitive
	 * @return bool True or false
	 */
	public static function hasChart($type, $caseSensitive = false) {
		if (!$caseSensitive)
			$type = strtolower($type);
		return isset(self::$typeChart[$type]);
	}
	/**
	 * Calculate type effectiveness for an attacking and defending type
	 *
	 * @param $type1 string The attacking type. Must be a main type or flying press or freeze-dry
	 * @param $type2 string The defending type. Must be a main type
	 * @return bool|float|int Returns the type multiplier for the matchup (0, .5, 1, or 2), or false if a parameter is invalid
	 * @throws TypesException If a type is invalid
	 */
	private static function typeEffectiveness($type1, $type2) {
		//	Case insensitive
		$type1 = strtolower($type1);
		$type2 = strtolower($type2);

		//	Type 1 must have a chart (can be flying press or freeze-dry), type 2 must be a type
		if (!self::hasChart($type1) || !self::isType($type2))
			throw new TypesException("Invalid type given.");

		//	Immune
		if (self::hasMatchup($type1, $type2, self::IMMUNE))
			return 0;

		//	Not very effective
		if (self::hasMatchup($type1, $type2, self::NOT_VERY_EFFECTIVE))
			return 0.5;

		//	Super effective
		if (self::hasMatchup($type1, $type2, self::SUPER_EFFECTIVE))
			return 2;

		//	Default matchup
		return 1;
	}

	/**
	 * Check if an attacking type has a particular multiplier vs. a defending type. Helper for typeEffectiveness
	 *
	 * @param string $type1 The attacking type/flying press/freeze-dry
	 * @param string $type2 The defending type
	 * @param int $matchup The class constant pertaining to the type of matchup (IMMUNE, NOT_VERY_EFFECTIVE, SUPER_EFFECTIVE)
	 * @return bool True if found, false otherwise
	 */
	private static function hasMatchup($type1, $type2, $matchup) {
		return (isset(self::$typeChart[$type1][$matchup]) && in_array($type2, self::$typeChart[$type1][$matchup]));
	}

	/**
	 * Perform a full multi-type matchup. Any number compound attacking types vs. any number of compound defending types
	 *
	 * @param string|array $attacking The attacking type or an array of attacking types
	 * @param string|array $defending The defending type or an array of defending types
	 * @return float The resulting total multiplier.
	 * @throws TypesException If an invalid type is given
	 */
	public static function typeMatchup($attacking, $defending) {
		//	Default 1x multiplier
		$mult = 1;

		$attacking = self::parseTypeParameter($attacking);
		$defending = self::parseTypeParameter($defending);

		//	Multiple attacking types, recursively compound
		if (is_array($attacking)) {
			foreach ($attacking as $type)
				$mult *= self::typeMatchup($type, $defending);
			return $mult;
		}

		//	Multiple defensive types, recursively compound
		if (is_array($defending)) {
			foreach ($defending as $type)
				$mult *= self::typeMatchup($attacking, $type);
			return $mult;
		}

		//	Down to a 1vs1 matchup, plug into typeEffectiveness
		if (is_numeric($result = self::typeEffectiveness($attacking, $defending)))
			$mult *= $result;

		return $mult;
	}

	/**
     * Calculate a type effectiveness chart for a given type
     *
	 * @param string $type
	 * @param string $mode Defensive or offensive
	 * @param int $chart Chart type. CHART_BASIC is basic types, CHART_SPECIAL includes flying press and freeze-dry, and CHART_BOTH includes both sets
	 * @return array An array of "type" => "effectiveness" entries
	 * @throws TypesException
	 */
	public static function typeChart($type, $mode = "defensive", $chart = self::CHART_BASIC) {
		$chart = self::parseTypeParameter($chart);
		if (!is_array($chart))
			throw new TypesException("Invalid chart name '$chart'.");

		$return = array();
		foreach ($chart as $entry) {
			if ($mode == "offensive")
				$return[$entry] = self::typeMatchup($type, $entry);
			elseif ($mode == "defensive")
				$return[$entry] = self::typeMatchup($entry, $type);
		}

		return $return;
	}

    /**
     * Convert input parameters to charts if applicable, or leave them alone
     *
     * @param mixed $parameter
     * @return mixed Chart array or $parameter
     */
	private static function parseTypeParameter($parameter) {
		//	Get list of types for full chart
		if ($parameter == self::CHART_BASIC)
			$parameter = self::$typeList;
		//	Check matchups for flying press and freeze-dry
		elseif ($parameter == self::CHART_SPECIAL)
			$parameter = self::$specialTypes;
		//	Check all available matchups
		elseif ($parameter == self::CHART_BOTH)
			$parameter = array_merge(self::$typeList, self::$specialTypes);

		return $parameter;
	}

	/**
	 * Match a pokemon object up against any number of individual or combinations of types. Additional matchup information will be given based on relevant pokemon abilities.
	 *
	 * @param mixed $attacking A type name or an array of type names/combinations. A combination must be an array within the array of types, e.g., array(array("fire", "flying"), "electric", "water")
	 * 							Some class constants can be used for predefined groups of types:
     *                          CHART_BASIC => All actual types, CHART_SPECIAL => Moves with their own chart, CHART_BOTH => Both of the aforementioned
	 * @param Pokemon $pokemon A pokemon object which will contain the relevant type and ability information
	 * @param int $depth Used internally to alter flow based on recursion depth
	 * @return array The matchup result, with type names as indexes and multipliers as values. Indices for compound types take the form of a string separated by "/", e.g. "fire/flying".
	 * 					An "abilities" index will be present if an ability modifies a matchup. The ability name(s) will be indexes of that array, each their own array with type name/combos as indexes.
	 */
	public static function pokemonMatchup($attacking, Pokemon $pokemon, $depth = 0) {
		//	Get type charts if necessary
		$attacking = self::parseTypeParameter($attacking);
		$result = array();

		//	Array of types, current recursion depth==0, so it's the list of matchups (depth==1 means components of a compound type, handled later)
		if (is_array($attacking) && $depth == 0) {
			foreach ($attacking as $type) {
				$matchup = self::pokemonMatchup($type, $pokemon, $depth+1);
				$result = array_merge_recursive($result, $matchup);
			}
			return $result;
		}

		//	If all type names are valid, this will be numeric
		if (is_numeric($effectiveness = self::typeMatchup($attacking, $pokemon->getType()))) {
			//	Can't have arrays as indexes, so form arrays (compound types) into slash/separated/strings
			$key = (is_array($attacking) ? implode("/", $attacking) : $attacking);
			$result[$key] = $effectiveness;

			//	Loop through pokemon abilities to check effectiveness for matchup
			$abilities = $pokemon->getAbility(0);
			foreach ($abilities as $ability) {
				//	A multiplier that's not 1 has an effect on the type matchup. Also, filter out matchups that don't change either way (e.g. Normal type vs Shedinja's Wonder Guard)
				if (($multiplier = self::defensiveAbilityEffect($attacking, $ability, $effectiveness)) != 1 && $effectiveness * $multiplier != $effectiveness) {
					//	Normalize Dry Skin, Water Absorb, and Volt Absorb
					if ($multiplier < 0)
						$result['abilities'][$ability][$key] = -1;
					else
						$result['abilities'][$ability][$key] = $effectiveness * $multiplier;
				}
			}
		}

		return $result;
	}

	/**
	 * Get an ability's effect on damage taken based on the attacking type (and in some cases, based on prior effectiveness multiplier)
	 *
	 * @param mixed $attacking The attacking type or an array of attacking types for compound matchups
	 * @param string $ability The name of the ability
	 * @param float|int $effectiveness The prior effectiveness (needed for some abilities like Filter, Solid Rock, and Wonder Guard)
	 * @return float|int The resultant multiplier to be applied to overall damage
	 */
	public static function defensiveAbilityEffect($attacking, $ability, $effectiveness = 1) {
		$mult = 1;
		if (is_array($attacking)) {
			foreach ($attacking as $type)
				$mult *= self::defensiveAbilityEffect($type, $ability, $effectiveness);
			return $mult;
		}
		$attacking = strtolower($attacking);

		//	Majority of abilities
		if (isset(self::$defensiveAbilityEffects[$ability][$attacking]))
			$mult = self::$defensiveAbilityEffects[$ability][$attacking];

		//	Filter, Solid Rock, Wonder Guard
		elseif (isset(self::$defensiveAbilityEffects['special'][$ability])) {
			$params = self::$defensiveAbilityEffects['special'][$ability];
			if (eval("return $effectiveness{$params['condition']}{$params['value']};"))
				$mult = $params['multiplier'];
		}

		return $mult;
	}




}



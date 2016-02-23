<?php
/**
 * MEGASSBOT - PokemonCommon.php
 * User: Benjamin
 * Date: 07/11/14
 */

namespace Pokemon;

/**
 * Trait PokemonCommon
 * Methods and members used by all substance classes of the pokemon extension (Pokemon, Ability, Nature, Item, Location)
 * @package Pokemon
 */

trait PokemonCommon {

	private $id = -1;
	private $generation = -1;
	private $names = array();

	/**
	 * Test if a search term matches this object
	 *
	 * @param int|string $search Term to search against (id number or any name)
	 * @param boolean $strict False to allow wildcard searches
	 * @return bool Search result
	 */
	public function search($search, $strict = false) {
		//	Numeric search
		if (is_int($search)) {
			if ($search == $this->id)
				return true;
		}

		//	String search
		else {
			//	Case insensitive
			$search = strtolower($search);

			//	Not strict, wildcard match
			if (!$strict) {
				//	Auto complete object name, or use custom shell wildcard pattern if user provided it
				#if (strpos($search, "?") === false && strpos($search, "*") === false && strpos($search, "[") === false)
				#	$search = "*$search*";

				//	Check search vs names
				foreach ($this->names as $name) {
					if (fnmatch($search, strtolower($name)) || fnmatch(str_replace(array(" ", "-"), "", $search), str_replace(array(" ", "-"), "", strtolower($name))))
						return true;
				}

			}
			//	Strict search, no wildcards
			else {
				foreach ($this->names as $name) {
					if ($search == strtolower($name))
						return true;
				}
			}
		}

		//	No match
		return false;
	}

	/**
	 * Get the Jaro-Winkler distance from a search to the closest 'name' this object has
	 *
	 * @param string $search Term to search against
	 * @param bool $englishOnly True to only consider the English name for this object
	 * @return float|bool The Jaro-Winkler distance, or false on failure
	 */
	public function looseSearch($search, $englishOnly = false) {
		$englishName = $this->getName();
		if ($englishOnly) {
			if ($englishName)
				return \IRCUtility::jaroWinklerDistance($englishName, $search);

			return false;
		}

		$return = 0;
		foreach ($this->names as $name) {
			if (($jaroWinkler = \IRCUtility::jaroWinklerDistance($name, $search)) > $return)
				$return = $jaroWinkler;
		}

		return $return;
	}

	/**
	 * Retieve this object's unique ID number
	 *
	 * @return int Id number
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Retrieve a name for this object
	 *
	 * @param string (Optional) $language Which language to retieve the name in. Defaults to english.
	 * @return string|boolean The respective name, or false if the name doesn't exist
	 */
	public function getName($language = "english") {
		$language = self::getLanguage($language);
		if ($language !== false && isset($this->names[$language]))
			return $this->names[$language];

		return false;
	}

	/**
	 * Return all saved names for this object
	 *
	 * @return array
	 */
	public function getNames() {
		return $this->names;
	}

	/**
	 * Get the first generation of games this object was introduced in
	 *
	 * @return int The generation number
	 */
	public function getGeneration() {
		return $this->generation;
	}

	/**
	 * Sets the id nunber for this object
	 *
	 * @param int $number A non-negative integer as the id number
	 * @return bool True on success, false on failure
	 */
	public function setId($number) {
		//	Must be a non-negative integer
		if (!self::intRange($number, 0))
			return false;

		//	Success
		$this->id = $number;
		return true;
	}

	/**
	 * Set one of this object's name values (english, japanese, french, german, etc.)
	 *
	 * @param array|string $name The name itself, or an array of 'language' => 'name' pairs
	 * @param null|string $language The language name, unnecessary if using array for $name
	 * @return bool True on success, false on any failure
	 */
	public function setName($name, $language = null) {
		//	Multiple names given as array
		if (is_array($name)) {
			$return = true;
			foreach ($name as $newLanguage => $newName) {
				if (!$this->setName($newName, $newLanguage))
					$return = false;
			}
			return $return;
		}

		//	Must be valid category
		if (($language = self::getLanguage($language)) === false)
			return false;

		//	Success
		$this->names[$language] = $name;
		return true;
	}

	/**
	 * Set the first generation of games this ability was introduced in
	 *
	 * @param int $generation The generation number
	 * @return bool True on success, false on failure
	 */
	public function setGeneration($generation) {
		if (!self::intRange($generation, 1, 6))
			return false;

		$this->generation = $generation;
		return true;
	}

	/**
	 * Test if value is an integer and within the given range (inclusive)
	 * Used for verifying indices and dex numbers
	 *
	 * @param mixed $num The value to test
	 * @param mixed $lower (Optional) The lower bound, false for no lower bound
	 * @param mixed $upper (Optional) The upper bound, false for no upper bound
	 * @return bool Test result
	 */
	public static function intRange($num, $lower = false, $upper = false) {
		//	Only digits, optionally negative
		if (!preg_match("/^-?(\\d+)$/", $num))
			return false;

		//	Check against lower bound, if given
		if (is_numeric($lower) && $num < $lower)
			return false;

		//	Check against upper bound, if given
		if (is_numeric($upper) && $num > $upper)
			return false;

		//	All tests passed
		return true;
	}

	//	Convert language identifiers
	private static $validLanguages	= array(
		"japanese",
		"official roomaji",
		"korean",
		"chinese",
		"french",
		"german",
		"spanish",
		"italian",
		"english",
		"czech"
	);

	private static $languagesShort	= array(
		'japanese'			=> array("ja", "jp"),
		'official roomaji'	=> array("ro", "or"),
		'korean'			=> array("ko", "kr"),
		'chinese'			=> array("cn", "zh"),
		'french'			=> array("fr"),
		'german'			=> array("de"),
		'spanish'			=> array("es"),
		'italian'			=> array("it"),
		'english'			=> array("en"),
		'czech'				=> array("cs", "cz")
	);

	private static $languageAliases	= array(
		'name'				=> "english",
		'romaji'			=> "official roomaji",
		'roumaji'			=> "official roomaji"
	);

	/**
	 * Get a language name based on the input string
	 *
	 * @param string $language A name or abbreviation of a language
	 * @return bool|string The language name, or false if invalid language
	 */
	public static function getLanguage($language) {
		//	Case insensitive
		$language = strtolower($language);

		//	Valid name passed
		if (array_search($language, self::$validLanguages) !== false)
			return $language;

		//	Alternate name for a name passed
		elseif (isset(self::$languageAliases[$language]))
			return self::$languageAliases[$language];

		//	Try and match vs abbreviation
		else {
			foreach (self::$languagesShort as $validLanguage => $abbreviations) {
				foreach ($abbreviations as $abbreviation) {
					//	Match found
					if ($abbreviation == $language)
						return $validLanguage;
				}
			}
		}

		//	Failure
		return false;
	}

}
<?php
/**
 * PHPBot - PokemonManager.php
 * User: Benjamin
 * Date: 24/05/14
 */

namespace Utsubot\Pokemon;
use Utsubot\{Manageable, ManagerException, ManagerSearchObject};


class JaroFilter extends \FilterIterator {
    private $search;

    public function __construct(\Iterator $iterator, $search) {
        parent::__construct($iterator);
        $this->search = $search;
    }

    public function accept() {
        $obj = $this->current();
        if ($obj instanceof Manageable)
            return $obj->search($this->search);
        return false;
    }
}

class PokemonManagerException extends ManagerException {}

class PokemonManager extends PokemonManagerBase {
	protected static $manages = "Utsubot\\Pokemon\\Pokemon";

	protected static $customOperators = array("hasabl");

	public function __construct(VeekunDatabaseInterface $interface) {
		parent::__construct($interface);
	}

	/**
	 * Search for a single or many of this manager's collection through an identifier
	 *
	 * @param string|int $index An identifier to search for (usu. name or id#)
	 * @return Manageable
	 */
	public function search($index): Manageable {
		//	Default search, exact or wildcard for all names, or id
		if ($results = parent::search($index))
			return $results;

		else {
			//	Search for similar strings (Jaro-Winkler distance) with english names
			if ($results = $this->getLooseSearchResults($index, true))
				return $results;

			//	Search for similar strings with all names
			else
				return $this->getLooseSearchResults($index);
		}

	}

	/**
	 * Utility for get() that gets the Jaro-Winkler distance between $search and all pokemon names, and gives the best match
	 *
	 * @param string $search The search term
	 * @param bool $englishOnly True to only search english names
	 * @return Pokemon|array|bool The Pokemon object with the closest matching name, an array of all pokemon objects, or false if no results
	 */
	private function getLooseSearchResults($search, $englishOnly = false) {
		//	Minimum result of the Jaro-Winkler algorithm for a pokemon to be considered
		$minimumSimilarity = 0.80;
		$results = array();

		//	Save each Jaro-Winkler distance
		foreach ($this->collection as $key => $item) {
			if (method_exists($item, "looseSearch") && $jaroWinkler = $item->looseSearch($search, $englishOnly))
				$results[$key] = array($jaroWinkler, $item);
		}

		//	Filter results to only contain results meeting the minimum similarity threshold
		$results = array_filter($results, function ($entry) use ($minimumSimilarity) { return $entry[0] >= $minimumSimilarity; });

		//	No results meeting threshold
		if (empty($results))
			return false;

		//	Sort results set in reverse order by Jaro-Winkler distance
		usort($results, function ($entry1, $entry2) {
			if ($entry1[0] < $entry2[0])
				return 1;
			elseif ($entry1[0] > $entry2[0])
				return -1;
			return 0;
		});

		return $results;
	}

	/**
	 * Given a $field to search against, this function returns info on how to get the field from a pokemon
	 *
	 * @param string $field The name of an aspect of one of a pokemon
	 * @param string $operator
	 * @param string $value The value being searched against, if relevant
	 * @return array array(method to get field, array(parameters,for,method), array(valid,comparison,operators))
	 */
	public function searchFields($field, $operator = "", $value = "") {
		if (in_array(strtolower($operator), self::$customOperators))
			return new ManagerSearchObject($this, "", array(), self::$customOperators);

		switch ($field) {
			case	"id":		case 	"pid":
				return new ManagerSearchObject($this, "getId", array(), self::$numericOperators);
			break;

			case	"hp":		case	"hit points":
			case	"atk":		case	"attack":
			case	"def":		case	"defense":
			case 	"spa":		case	"special attack":
			case	"spd":		case	"special defense":
			case	"spe":		case	"speed":
				return new ManagerSearchObject($this, "getBaseStat", array($field), self::$numericOperators);
			break;

			case "total":
			case "bst":
				return new ManagerSearchObject($this, "getBaseStatTotal", array(), self::$numericOperators);
			break;

			case	"name":		case	"english":
			case	"romaji":	case	"katakana":
			case	"french":	case	"german":
			case	"korean":	case	"italian":
			case	"chinese":	case	"spanish":
			case	"czech":	case	"official roomaji":
			case	"roumaji":	case	"japanese":
				return new ManagerSearchObject($this, "getName", array($field), self::$stringOperators);
			break;

			case "ability1":
			case "ability2":
			case "ability3":
			case "abl1":
			case "abl2":
			case "abl3":
				return new ManagerSearchObject($this, "getAbility", array(intval(substr($field, -1))), self::$stringOperators);
			break;

			case "abilities":
			case "ability":
				return new ManagerSearchObject($this, "getAbility", array(0), self::$arrayOperators);
			break;

			case "type1":
			case "type2":
				return new ManagerSearchObject($this, "getType", array(intval(substr($field, -1))), self::$stringOperators);
			break;

			case "type":
			case "types":
				return new ManagerSearchObject($this, "getType", array(0), self::$arrayOperators);
			break;

			case "species":
				return new ManagerSearchObject($this, "getSpecies", array(), self::$stringOperators);
			break;
		}

		return null;
	}

	protected function customComparison($pokemon, $field, $operator, $value) {
        if (!($pokemon instanceof $pokemon))
            throw new PokemonManagerException("Comparison object is not a Pokemon.");

		switch (strtolower($operator)) {
			case "hasabl":
				return in_array(strtolower($value), array_map("strtolower", $pokemon->getAbility()));
			break;

			case "hastype":
				return in_array(strtolower($value), array_map("strtolower", $pokemon->getType()));
			break;
		}

		return false;
	}

}
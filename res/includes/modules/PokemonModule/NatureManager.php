<?php
/**
 * Utsubot - NatureManager.php
 * User: Benjamin
 * Date: 03/12/2014
 */

namespace Pokemon;

class NatureManager extends \ManagerWithDatabase {
	protected static $manages = "Nature";
	protected static $managesNamespace = "Pokemon";

	protected static $validFields = array("increases", "decreases", "likes", "dislikes", "likesFlavor", "dislikesFlavor");

	public function searchFields($field, $operator = "", $value = "") {
		switch ($field) {
			case "increases":
				return new \ManagerSearchObject($this, "getIncreases", array(), self::$stringOperators);
			break;

			case "decreases":
				return new \ManagerSearchObject($this, "getDecreases", array(), self::$stringOperators);
			break;

			case "likes":
				return new \ManagerSearchObject($this, "getLikes", array(), self::$stringOperators);
			break;

			case "dislikes":
				return new \ManagerSearchObject($this, "getDislikes", array(), self::$stringOperators);
			break;

			case "likesFlavor":
				return new \ManagerSearchObject($this, "getLikes", array(), self::$stringOperators);
			break;

			case "dislikesFlavor":
				return new \ManagerSearchObject($this, "getDislikes", array(), self::$stringOperators);
			break;
		}

		return null;
	}
} 
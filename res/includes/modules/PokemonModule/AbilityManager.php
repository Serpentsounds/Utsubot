<?php
/**
 * MEGASSBOT - AbilityManager.php
 * User: Benjamin
 * Date: 06/11/14
 */

namespace Pokemon;

class AbilityManager extends PokemonManagerBase {
	protected static $manages = "Ability";
	protected static $managesNamespace = "Pokemon";

	protected static $validFields = array("effect");

	public function searchFields($field, $operator = "", $value = "") {
		switch ($field) {
			case "effect":
				return new \ManagerSearchObject($this, "getEffect", array(), self::$stringOperators);
			break;
		}

		return null;
	}

	public function customComparison($object, $field, $operator, $value) {
		// TODO: Implement customComparison() method.
	}

}
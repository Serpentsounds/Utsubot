<?php
/**
 * Utsubot - MoveManager.php
 * User: Benjamin
 * Date: 06/12/2014
 */

namespace Pokemon;

class MoveManager extends PokemonManagerBase {
	protected static $manages = "Move";
	protected static $managesNamespace = "Pokemon";

	public function searchFields($field, $operator = "", $value = ""){}

	public function customComparison($object, $field, $operator, $value) {
		// TODO: Implement customComparison() method.
	}
} 
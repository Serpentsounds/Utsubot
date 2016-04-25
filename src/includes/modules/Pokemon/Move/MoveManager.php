<?php
/**
 * Utsubot - MoveManager.php
 * User: Benjamin
 * Date: 06/12/2014
 */

namespace Utsubot\Pokemon\Move;
use Utsubot\Pokemon\PokemonManagerBase;

class MoveManager extends PokemonManagerBase {
	protected static $manages = "Utsubot\\Pokemon\\Move\\Move";

	public function load() {
		$this->collection = $this->interface->getMove();
	}

	public function searchFields($field, $operator = "", $value = ""){}

	public function customComparison($object, $field, $operator, $value) {
		// TODO: Implement customComparison() method.
	}
} 
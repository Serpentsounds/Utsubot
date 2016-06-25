<?php
/**
 * Utsubot - MetaTier.php
 * User: Benjamin
 * Date: 19/09/2015
 */

namespace Utsubot\Pokemon;
use Utsubot\{
    Manageable, ManagerException
};


class MetaTier extends PokemonManagerBase {
	protected static $manages = "Utsubot\\Pokemon\\MetaPokemon";

	protected static $customOperators = [ ];

	/** @var $interface MetaPokemonDatabaseInterface */
	protected $interface;

	private $usages = [ ];
	private $pokemonUsages = [ ];

	public function __construct(MetaPokemonDatabaseInterface $interface) {
		parent::__construct($interface);
	}

	/**
	 * Load competitive tier information
	 *
	 * @param string $tier Name of competitive tier
	 * @throws ManagerException
	 */
	public function load($tier = null) {
		parent::load($tier);
		$this->usages = $this->interface->getUsages($tier);
		$this->pokemonUsages = $this->interface->getPokemonUsages();
	}

	/**
	 * @param int|string $index Pokemon id or name
	 * @return array|bool Array of a MetaPokemon and usage statistics array, false if nothing found
	 */
	public function get(int $index): Manageable {
		if ($results = parent::get($index)) {
			/** @var $results MetaPokemon */
			$results = array($results, $this->usages[$results->getId()]);
		}

		return $results;
	}

	public function searchFields($field, $operator = "", $value = "") {}

	public function customComparison($object, $field, $operator, $value) {
		// TODO: Implement customComparison() method.
	}
}
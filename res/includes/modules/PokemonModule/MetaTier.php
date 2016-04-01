<?php
/**
 * Utsubot - MetaTier.php
 * User: Benjamin
 * Date: 19/09/2015
 */

namespace Pokemon;


class MetaTier extends PokemonManagerBase {
	protected static $manages = "MetaPokemon";
	protected static $managesNamespace = "Pokemon";

	protected static $customOperators = array();

	/** @var $interface MetaPokemonDatabaseInterface */
	protected $interface;

	private $usages = array();
	private $pokemonUsages = array();

	public function __construct(MetaPokemonDatabaseInterface $interface) {
		parent::__construct($interface);
	}

	/**
	 * Load competitive tier information
	 *
	 * @param string $tier Name of competitive tier
	 * @throws \ManagerException
	 */
	public function load($tier = null) {
		parent::load($tier);
		$this->usages = $this->interface->getUsages($tier);
		$this->pokemonUsages = $this->interface->getPokemonUsages();
	}

	/**
	 * @param int|string $search Pokemon id or name
	 * @param bool|false $all True to return all matching results, false to return first
	 * @return array|bool Array of a MetaPokemon and usage statistics array, false if nothing found
	 */
	public function get($search, $all = false) {
		if ($results = parent::get($search)) {
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
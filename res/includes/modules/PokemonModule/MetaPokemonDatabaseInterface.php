<?php
/**
 * Utsubot - MetaPokemonDatabaseInterface.php
 * User: Benjamin
 * Date: 22/09/2015
 */

namespace Pokemon;

class MetaPokemonDatabaseInterfaceException extends \DatabaseInterfaceException {}

class MetaPokemonDatabaseInterface extends \DatabaseInterface{

	private $tiers = array();
	private $fields = array();

	public function __construct() {
		parent::__construct("utsubot");
		$this->loadTiers();
		$this->loadFields();
	}

	/**
	 * Load the list of tier names/IDs
	 * @throws \DatabaseInterfaceException PDO error
	 */
	private function loadTiers() {
		$tiers = $this->query("SELECT * FROM `metagame_tiers`");
		foreach ($tiers as $tier)
			$this->tiers[$tier['id']] = $tier['name'];
	}

	/**
	 * Load the list of field names/IDs
	 * @throws \DatabaseInterfaceException PDO error
	 */
	private function loadFields() {
		$fields = $this->query("SELECT * FROM `metagame_fields`");
		foreach ($fields as $field)
			$this->fields[$field['id']] = $field['name'];
	}

	/**
	 * Validate and retrieve tier ID given a name
	 *
	 * @param string $tierName
	 * @return int Tier ID
	 * @throws MetaPokemonDatabaseInterfaceException Invalid tier name
	 */
	private function getTierID($tierName) {
		if (($tierID = array_search($tierName, $this->tiers)) !== false)
			return $tierID;

		throw new MetaPokemonDatabaseInterfaceException("Invalid tier '$tierName'.");
	}

	/**
	 * Get the whole tier list
	 *
	 * @return array
	 */
	public function getTiers() {
		return $this->tiers;
	}


	/**
	 * Gets pokemon pick percentages from database
	 *
	 * @param int $pokemon Specify a single pokemon ID, or blank to get all stats
	 * @return array array(pokemonId => array(tierName, pickPercent), ...)
	 * @throws \DatabaseInterfaceException
	 */
	public function getPokemonUsages($pokemon = null) {
		if ($pokemon === null) {
			$query = "SELECT * FROM `metagame_data` WHERE `field_id`=? ORDER BY `pokemon_id` ASC";
			$parameters = array(array_search("count", $this->fields));
		}
		else {
			$query = "SELECT * FROM `metagame_data` WHERE `field_id`=? AND `pokemon_id`=? ORDER BY `pokemon_id` ASC";
			$parameters = array(array_search("count", $this->fields), $pokemon);
		}
		$data = $this->query($query, $parameters);
		$pokemonUsages = array();
		foreach ($data as $row) {
			if (!isset($pokemonUsages[$row['pokemon_id']]) || $pokemonUsages[$row['pokemon_id']]['usage'] < $row['value'] * 100 / $row['entry'])
				$pokemonUsages[$row['pokemon_id']][$this->tiers[$row['tier_id']]] = round($row['value'] * 100 / $row['entry']);
		}

		return $pokemonUsages;
	}

	/**
	 * Poll database for statistics and create MetaPokemon objects out of them
	 *
	 * @param string $tier Tier name
	 * @return MetaPokemon[] Collection of resulting MetaPokemon
	 * @throws MetaPokemonDatabaseInterfaceException Invalid tier name
	 * @throws \DatabaseInterfaceException PDO error
	 */
	public function getMetaPokemon($tier) {
		$tierID = $this->getTierID($tier);

		/** @var $metaPokemon MetaPokemon[] */
		$metaPokemon = array();
		$data = $this->query("SELECT * FROM `metagame_data` WHERE `tier_id`=? ORDER BY `pokemon_id` ASC", array($tierID));
		foreach ($data as $row) {
			if (!isset($metaPokemon[$row['pokemon_id']]) || !($metaPokemon[$row['pokemon_id']] instanceof MetaPokemon)) {
				$metaPokemon[$row['pokemon_id']] = new MetaPokemon();
				$metaPokemon[$row['pokemon_id']]->setId($row['pokemon_id']);
			}

			$metaPokemon[$row['pokemon_id']]->add($this->fields[$row['field_id']], $row['entry'], $row['value']);
		}

		return $metaPokemon;
	}

	/**
	 * Gets usage percentages of abilities, items, and moves independent of pokemon
	 *
	 * @param string $tier Tier name
	 * @return array(field => array(entry => totalUsageCount, ...), ...)
	 * @throws MetaPokemonDatabaseInterfaceException Invalid tier name
	 * @throws \DatabaseInterfaceException PDO error
	 */
	public function getUsages($tier) {
		$tierID = $this->getTierID($tier);

		$fields = array("abilities", "moves", "items");
		$usages = array();
		$data = $this->query("SELECT * FROM `metagame_data` WHERE `tier_id`=? ORDER BY `pokemon_id` ASC", array($tierID));
		foreach ($data as $row) {
			$field = $this->fields[$row['field_id']];

			if ($field == "count")
				$usages['pokemon'][$row['pokemon_id']] = $row['value'];
			elseif (in_array($field, $fields))
				@$usages[$field][$row['entry']] += $row['value'];
		}

		foreach ($usages as $field => $entries) {
			$total = array_sum($entries);
			foreach ($entries as &$entry)
				$entry = round($entry * 100 / $total, 2);
		}

		return $usages;
	}

}
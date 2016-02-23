<?php
/**
 * Utsubot - MetaPokemon.php
 * User: Benjamin
 * Date: 22/09/2015
 */

namespace Pokemon;


class MetaPokemon {

	use PokemonCommon;

	private $abilities;
	private $moves;
	private $items;
	private $spreads;
	private $teammates;
	private $counters;

	public function __construct() {}

	/**
	 * Add a meta statistic to a field
	 *
	 * @param string $field
	 * @param string $value
	 * @param double $frequency
	 * @return bool
	 */
	public function add($field, $value, $frequency) {
		$field = strtolower($field);
		if (!property_exists($this, $field) || !is_string($value) || !is_numeric($frequency))
			return false;

		$this->{$field[$value]} = $frequency;
		return true;
	}

	/**
	 * Retrieve the array of all statistics for the given field
	 *
	 * @param string $field
	 * @return bool|array
	 */
	public function get($field) {
		$field = strtolower($field);
		if (!property_exists($this, $field))
			return false;

		return $this->{$field};
		#$sum = array_sum($this->{$field});
		#return array_map(function($f) use ($sum) { return round($f / $sum, 2); }, $this->{$field});
	}

	/**
	 * Return the frequency of a specific entry in a field
	 *
	 * @param string $field
	 * @param string $entry
	 * @return int
	 */
	public function getFrequency($field, $entry) {
		$field = strtolower($field);
		if (!property_exists($this, $entry) || !isset($this->{$field[$entry]}))
			return 0;

		return $this->{$field[$entry]} / array_sum($field);
	}
}
<?php
/**
 * MEGASSBOT - PokemonDatabaseInterface.php
 * User: Benjamin
 * Date: 03/11/14
 */

namespace Pokemon;

abstract class PokemonDatabaseInterface extends \DatabaseInterface {

	abstract public function getNameFromId($table, $id);
	abstract public function getPokemon($id = false);
	abstract public function getAbility($id);
	abstract public function getLocation($id);
	abstract public function getNature($id);
	abstract public function getItem($id);

	/**
	 * Use the $id passed in an SQL function to determine whether it needs to be matched against
	 * This makes the same routine reusable for fetching all rows or fetching a single row
	 *
	 * @param int $id The id to check against, or an invalid value for no constraint
	 * @param string $column The column name the id is to be checked against
	 * @param bool $first Whether or not this is the first constraint in the query, or false if you're tacking it on the end
	 * @return array array of $constraint being the SQL snippet, and $params being the parameter array
	 */
	protected static function addConstraint($id, $column, $first = false) {
		$constraint = "";
		$params = array();
		if (strlen($column) && is_int($id) && $id >= 0) {
			$constraint = " AND $column=?";
			if ($first)
				$constraint = "WHERE $column=?";
			$params = array($id);
		}
		return array($constraint, $params);
	}
}
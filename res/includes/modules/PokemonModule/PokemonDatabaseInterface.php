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


}
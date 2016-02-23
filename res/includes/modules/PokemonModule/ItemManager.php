<?php
/**
 * MEGASSBOT - ItemManager.php
 * User: Benjamin
 * Date: 09/11/14
 */

namespace Pokemon;

class ItemManager extends \ManagerWithDatabase {
	protected static $manages = "Item";
	protected static $managesNamespace = "Pokemon";

	public function searchFields($field, $operator = "", $value = ""){}
}
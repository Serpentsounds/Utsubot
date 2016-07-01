<?php
/**
 * MEGASSBOT - ItemManager.php
 * User: Benjamin
 * Date: 09/11/14
 */

namespace Utsubot\Pokemon\Item;

use Utsubot\Pokemon\PokemonManagerBase;


class ItemManager extends PokemonManagerBase {

    protected static $manages = "Utsubot\\Pokemon\\Item\\Item";

    protected static $populatorMethod = "getItems";


    public function searchFields($field, $operator = "", $value = "") {
    }


    public function customComparison($object, $field, $operator, $value) {
        // TODO: Implement customComparison() method.
    }
}
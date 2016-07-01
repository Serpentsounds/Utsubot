<?php
/**
 * PHPBot - PokemonManager.php
 * User: Benjamin
 * Date: 24/05/14
 */

namespace Utsubot\Pokemon\Pokemon;

use Utsubot\{
    Manageable,
    ManagerException,
    ManagerSearchObject
};
use Utsubot\Pokemon\{
    PokemonManagerBase,
    VeekunDatabaseInterface
};


class PokemonManagerException extends ManagerException {

}

class PokemonManager extends PokemonManagerBase {

    protected static $manages = "Utsubot\\Pokemon\\Pokemon\\Pokemon";
    protected static $customOperators = [ "hasabl" ];

    protected static $populatorMethod = "getPokemon";

    /**
     * Given a $field to search against, this function returns info on how to get the field from a pokemon
     *
     * @param string $field The name of an aspect of one of a pokemon
     * @param string $operator
     * @param string $value The value being searched against, if relevant
     * @return array array(method to get field, array(parameters,for,method), array(valid,comparison,operators))
     */
    public function searchFields($field, $operator = "", $value = "") {
        if (in_array(strtolower($operator), self::$customOperators))
            return new ManagerSearchObject($this, "", [ ], self::$customOperators);

        switch ($field) {
            case    "id":
            case    "pid":
                return new ManagerSearchObject($this, "getId", [ ], self::$numericOperators);
                break;

            case    "hp":
            case    "hit points":
            case    "atk":
            case    "attack":
            case    "def":
            case    "defense":
            case    "spa":
            case    "special attack":
            case    "spd":
            case    "special defense":
            case    "spe":
            case    "speed":
                return new ManagerSearchObject($this, "getBaseStat", [ $field ], self::$numericOperators);
                break;

            case "total":
            case "bst":
                return new ManagerSearchObject($this, "getBaseStatTotal", [ ], self::$numericOperators);
                break;

            case    "name":
            case    "english":
            case    "romaji":
            case    "katakana":
            case    "french":
            case    "german":
            case    "korean":
            case    "italian":
            case    "chinese":
            case    "spanish":
            case    "czech":
            case    "official roomaji":
            case    "roumaji":
            case    "japanese":
                return new ManagerSearchObject($this, "getName", [ $field ], self::$stringOperators);
                break;

            case "ability1":
            case "ability2":
            case "ability3":
            case "abl1":
            case "abl2":
            case "abl3":
                return new ManagerSearchObject($this, "getAbility", [ intval(substr($field, -1)) ], self::$stringOperators);
                break;

            case "abilities":
            case "ability":
                return new ManagerSearchObject($this, "getAbility", [ 0 ], self::$arrayOperators);
                break;

            case "type1":
            case "type2":
                return new ManagerSearchObject($this, "getType", [ intval(substr($field, -1)) ], self::$stringOperators);
                break;

            case "type":
            case "types":
                return new ManagerSearchObject($this, "getType", [ 0 ], self::$arrayOperators);
                break;

            case "species":
                return new ManagerSearchObject($this, "getSpecies", [ ], self::$stringOperators);
                break;
        }

        return null;
    }


    protected function customComparison($pokemon, $field, $operator, $value) {
        if (!($pokemon instanceof $pokemon))
            throw new PokemonManagerException("Comparison object is not a Pokemon.");

        switch (strtolower($operator)) {
            case "hasabl":
                return in_array(strtolower($value), array_map("strtolower", $pokemon->getAbility()));
                break;

            case "hastype":
                return in_array(strtolower($value), array_map("strtolower", $pokemon->getType()));
                break;
        }

        return false;
    }

}
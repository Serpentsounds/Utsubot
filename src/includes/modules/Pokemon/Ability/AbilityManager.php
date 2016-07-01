<?php
/**
 * MEGASSBOT - AbilityManager.php
 * User: Benjamin
 * Date: 06/11/14
 */

namespace Utsubot\Pokemon\Ability;

use Utsubot\Pokemon\PokemonManagerBase;
use Utsubot\ManagerSearchObject;


class AbilityManager extends PokemonManagerBase {

    protected static $manages = "Utsubot\\Pokemon\\Ability\\Ability";
    protected static $validFields = [ "effect" ];

    protected static $populatorMethod = "getAbilities";


    /**
     * @param string $field
     * @param string $operator
     * @param string $value
     * @return null|ManagerSearchObject
     */
    public function searchFields($field, $operator = "", $value = "") {
        switch ($field) {
            case "effect":
                return new ManagerSearchObject($this, "getEffect", [ ], self::$stringOperators);
                break;
        }

        return null;
    }


    /**
     * @param mixed $object
     * @param mixed $field
     * @param mixed $operator
     * @param mixed $value
     */
    public function customComparison($object, $field, $operator, $value) {
        // TODO: Implement customComparison() method.
    }

}
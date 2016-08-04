<?php
/**
 * Utsubot - MoveManager.php
 * User: Benjamin
 * Date: 06/12/2014
 */

namespace Utsubot\Pokemon\Move;


use Utsubot\Pokemon\{
    PokemonManagerBase,
    PokemonManagerBaseException,
    MethodInfo
};


/**
 * Class MoveManagerException
 *
 * @package Utsubot\Pokemon\Move
 */
class MoveManagerException extends PokemonManagerBaseException {

}


/**
 * Class MoveManager
 *
 * @package Utsubot\Pokemon\Move
 */
class MoveManager extends PokemonManagerBase {

    const Manages          = "Utsubot\\Pokemon\\Move\\Move";
    const Populator_Method = "getMoves";
    const TypedArray_Class = "Utsubot\\Pokemon\\Moves";


    /**
     * @param string $field
     * @param array $parameters
     * @return MethodInfo
     * @throws MoveManagerException
     */
    public function getMethodFor(string $field, array $parameters = [ ]): MethodInfo {

        switch (strtolower($field)) {

            case "pp":
                $return = new MethodInfo("getPP", [ ]);
                break;

            case "power":
            case "basepower":
            case "bp":
                $return = new MethodInfo("getPower", [ ]);
                break;

            case "accuracy":
            case "acc":
                $return = new MethodInfo("getAccuracy", [ ]);
                break;

            case "damagetype":
            case "damage":
                $return = new MethodInfo("getDamageType", [ ]);
                break;

            case "type":
                $return = new MethodInfo("getType", [ ]);
                break;

            case "target":
                $return = new MethodInfo("getTarget", [ ]);
                break;

            case "priority":
                $return = new MethodInfo("getPriority", [ ]);
                break;

            case "effect":
                $return = new MethodInfo("getEffect", [ ]);
                break;

            default:
                $return = parent::getMethodFor($field, $parameters);
                break;
        }

        return $return;
    }
}
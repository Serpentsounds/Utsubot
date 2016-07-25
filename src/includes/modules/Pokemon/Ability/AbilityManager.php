<?php
/**
 * MEGASSBOT - AbilityManager.php
 * User: Benjamin
 * Date: 06/11/14
 */

namespace Utsubot\Pokemon\Ability;


use Utsubot\Pokemon\{
    PokemonManagerBase, MethodInfo, PokemonManagerBaseException
};


/**
 * Class AbilityManagerException
 *
 * @package Utsubot\Pokemon\Ability
 */
class AbilityManagerException extends PokemonManagerBaseException {

}


/**
 * Class AbilityManager
 *
 * @package Utsubot\Pokemon\Ability
 */
class AbilityManager extends PokemonManagerBase {

    const Manages          = "Utsubot\\Pokemon\\Ability\\Ability";
    const Populator_Method = "getAbilities";
    const TypedArray_Class = "Utsubot\\Pokemon\\Abilities";


    /**
     * @param string $field
     * @return MethodInfo
     * @throws AbilityManagerException
     */
    public function getMethodFor(string $field): MethodInfo {

        switch ($field) {

            case "effect":
                $return = new MethodInfo("getEffect", [ ]);
                break;

            case "generation":
                $return = new MethodInfo("getGeneration", [ ]);
                break;

            default:
                throw new AbilityManagerException("Unsupported search field '$field'.");
                break;
        }

        return $return;
    }

}
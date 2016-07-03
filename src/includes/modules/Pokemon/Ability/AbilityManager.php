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

    protected static $manages     = "Utsubot\\Pokemon\\Ability\\Ability";
    protected static $validFields = [ "effect" ];

    protected static $populatorMethod = "getAbilities";


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
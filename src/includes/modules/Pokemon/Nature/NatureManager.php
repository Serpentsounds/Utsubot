<?php
/**
 * Utsubot - NatureManager.php
 * User: Benjamin
 * Date: 03/12/2014
 */

namespace Utsubot\Pokemon\Nature;

use Utsubot\Pokemon\{
    PokemonManagerBase, PokemonManagerBaseException, MethodInfo
};


/**
 * Class NatureManagerException
 *
 * @package Utsubot\Pokemon\Nature
 */
class NatureManagerException extends PokemonManagerBaseException {

}


/**
 * Class NatureManager
 *
 * @package Utsubot\Pokemon\Nature
 */
class NatureManager extends PokemonManagerBase {

    const Manages          = "Utsubot\\Pokemon\\Nature\\Nature";
    const Populator_Method = "getNatures";
    const TypedArray_Class = "Utsubot\\Pokemon\\Natures";


    /**
     * @param string $field
     * @param array  $parameters
     * @return MethodInfo
     * @throws NatureManagerException
     */
    public function getMethodFor(string $field, array $parameters = [ ]): MethodInfo {

        switch ($field) {
            case "increases":
                $return = new MethodInfo("getIncreases", [ ]);
                break;

            case "decreases":
                $return = new MethodInfo("getDecreases", [ ]);
                break;

            case "likes":
                $return = new MethodInfo("getLikes", [ ]);
                break;

            case "dislikes":
                $return = new MethodInfo("getDislikes", [ ]);
                break;

            case "likesFlavor":
                $return = new MethodInfo("getLikes", [ ]);
                break;

            case "dislikesFlavor":
                $return = new MethodInfo("getDislikes", [ ]);
                break;

            default:
                $return = parent::getMethodFor($field, $parameters);
                break;
        }

        return $return;
    }

}
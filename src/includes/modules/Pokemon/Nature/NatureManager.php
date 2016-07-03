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

    protected static $manages     = "Utsubot\\Pokemon\\Nature\\Nature";
    protected static $validFields = [ "increases", "decreases", "likes", "dislikes", "likesFlavor", "dislikesFlavor" ];

    protected static $populatorMethod = "getNatures";


    /**
     * @param string $field
     * @return MethodInfo
     * @throws NatureManagerException
     */
    public function getMethodFor(string $field): MethodInfo {

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
                throw new NatureManagerException("Unsupported search field '$field'.");
                break;
        }

        return $return;
    }

}
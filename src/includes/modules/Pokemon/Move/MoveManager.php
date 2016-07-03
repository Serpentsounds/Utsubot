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

    protected static $manages = "Utsubot\\Pokemon\\Move\\Move";

    protected static $populatorMethod = "getMoves";


    /**
     * @param string $field
     * @return MethodInfo
     * @throws MoveManagerException
     */
    public function getMethodFor(string $field): MethodInfo {
        throw new MoveManagerException("Unsupported search field '$field'.");
        // TODO: Implement getMethodFor() method.
    }
}
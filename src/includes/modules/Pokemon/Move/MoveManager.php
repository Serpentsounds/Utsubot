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
        throw new MoveManagerException("Unsupported search field '$field'.");
        // TODO: Implement getMethodFor() method.
    }
}
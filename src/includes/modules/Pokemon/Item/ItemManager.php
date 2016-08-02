<?php
/**
 * MEGASSBOT - ItemManager.php
 * User: Benjamin
 * Date: 09/11/14
 */

namespace Utsubot\Pokemon\Item;


use Utsubot\Pokemon\{
    PokemonManagerBase, MethodInfo, PokemonManagerBaseException
};


/**
 * Class ItemManagerException
 *
 * @package Utsubot\Pokemon\Item
 */
class ItemManagerException extends PokemonManagerBaseException {

}


/**
 * Class ItemManager
 *
 * @package Utsubot\Pokemon\Item
 */
class ItemManager extends PokemonManagerBase {

    const Manages = "Utsubot\\Pokemon\\Item\\Item";
    const Populator_Method = "getItems";
    const TypedArray_Class = "Utsubot\\Pokemon\\Items";


    /**
     * @param string $field
     * @param array $parameters
     * @return MethodInfo
     * @throws ItemManagerException
     */
    public function getMethodFor(string $field, array $parameters = [ ]): MethodInfo {
        throw new ItemManagerException("Invalid search field '$field'.");
    }
}
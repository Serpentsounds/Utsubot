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

    protected static $manages = "Utsubot\\Pokemon\\Item\\Item";

    protected static $populatorMethod = "getItems";


    /**
     * @param string $field
     * @return MethodInfo
     * @throws ItemManagerException
     */
    public function getMethodFor(string $field): MethodInfo {
        throw new ItemManagerException("Invalid search field '$field'.");
    }
}
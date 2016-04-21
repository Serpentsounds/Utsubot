<?php
/**
 * Utsubot - ClassList.php
 * Date: 06/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Includes;


/**
 * Class ClassList
 * Represents a list of user-defined classes, interfaces, or traits
 *
 * @package Utsubot\Includes
 */
class ClassList extends ItemList {

    /**
     * Add a class name to this list
     * Only user-defined classes will be added
     *
     * @param string $item
     */
    public function add(string $item) {
        if ((class_exists($item) || interface_exists($item) || trait_exists($item))
            && !(new \ReflectionClass($item))->isInternal())

            $this->list[] = $item;
    }

}
<?php
/**
 * Utsubot - TypeGrou.php
 * Date: 27/06/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon\Types;

use Iterator;


/**
 * Class TypeGroupException
 *
 * @package Utsubot\Pokemon\Types
 */
class TypeGroupException extends \Exception {

}

/**
 * Class TypeGroup
 *
 * @package Utsubot\Pokemon\Types
 */
class TypeGroup implements Iterator {

    private $list  = [ ];
    private $index = 0;


    /**
     * TypeGroup constructor.
     *
     * @param array $types
     * @throws TypeGroupException
     */
    public function __construct(array $types) {
        foreach ($types as $type)
            if (!($type instanceof Type))
                throw new TypeGroupException("Invalid Type object.");

        $this->list = array_values($types);
    }


    /**
     * @return array
     */
    public function getArray(): array {
        return $this->list;
    }


    /**
     * Reset Iterator position
     */
    public function rewind() {
        $this->index = 0;
    }


    /**
     * Get current object from Iterator
     *
     * @return Type
     */
    public function current(): Type {
        return $this->list[ $this->index ];
    }


    /**
     * Get current position from Iterator
     *
     * @return int
     */
    public function key() {
        return $this->index;
    }


    /**
     * Advance Iterator to next position
     */
    public function next() {
        ++$this->index;
    }


    /**
     * Check if Iterator has a valid item to give
     *
     * @return bool
     */
    public function valid(): bool {
        return isset($this->list[ $this->index ]);
    }

}
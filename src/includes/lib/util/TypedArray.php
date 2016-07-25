<?php
/**
 * Utsubot - TypedArray.php
 * Date: 28/06/2016
 */

declare(strict_types = 1);

namespace Utsubot;


use
    ArrayObject,
    Exception
;



/**
 * Class TypedArrayException
 *
 * @package Utsubot
 */
class TypedArrayException extends Exception {

}


/**
 * Class TypedArray
 *
 * @package Utsubot
 */
abstract class TypedArray extends ArrayObject {

    const Contains = "";


    /**
     * TypedArray constructor.
     * Verify each element matches the configured class.
     *
     * @param array  $input
     * @param int    $flags
     * @param string $iterator_class
     * @throws TypedArrayException
     */
    public function __construct(array $input = [ ], int $flags = 0, string $iterator_class = "ArrayIterator") {
        $contains = static::Contains;
        foreach ($input as $entry) {
            if (!($entry instanceof $contains))
                throw new TypedArrayException("Element passed to '".get_called_class()."' must be an instance of '$contains'.");
        }

        return parent::__construct($input, $flags, $iterator_class);
    }


    /**
     * Only allow items of the configured class to be added.
     * 
     * @param mixed $key
     * @param mixed $value
     * @throws TypedArrayException
     */
    public function offsetSet($key, $value) {
        $contains = static::Contains;
        if (!($value instanceof $contains))
            throw new TypedArrayException("Element passed to '".get_called_class()."' must be an instance of '$contains'.");

        parent::offsetSet($key, $value);
    }

}
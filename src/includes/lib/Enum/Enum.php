<?php
/**
 * Utsubot - Enum.php
 * Date: 15/04/2016
 */

declare(strict_types = 1);

namespace Utsubot;
use ReflectionClass;
use Exception;


/**
 * Class EnumException
 *
 * @package Utsubot
 */
class EnumException extends Exception {}

/**
 * Class Enum
 *
 * @package Utsubot
 */
abstract class Enum {

    protected static $constants = [ ];
    protected $value = null;

    /**
     * Enum constructor.
     *
     * @param mixed $value
     * @throws EnumException
     */
    public function __construct($value) {
        if (!static::isValidValue($value))
            throw new EnumException("Unable to create ". get_called_class(). " with value '$value'.");

        $this->value = $value;
    }

    /**
     * @return string
     */
    public function __toString(): string {
        return (string)$this->getValue();
    }

    /**
     * @return mixed
     */
    public function getValue() {
        return $this->value;
    }

    /**
     * @return string
     * @throws EnumException
     */
    public function getName(): string {
        return static::findName($this->getValue());
    }

    /**
     * Construct an Enum instance by passing a name instead of a value
     *
     * @param string $name
     * @return Enum
     * @throws EnumException
     */
    public static function fromName(string $name): Enum {
        return new static(static::findValue($name));
    }

    /**
     * Instantiate a reflection and cache constants
     */
    protected static function loadConstants() {
        $class = get_called_class();
        if (!isset(static::$constants[$class]))
            static::$constants[$class] = (new ReflectionClass(get_called_class()))->getConstants();
    }

    /**
     * Get the number of constants saved
     *
     * @return int
     */
    public static function count(): int {
        static::loadConstants();

        return count(static::$constants);
    }

    /**
     * Check if a value can be validly used as an instance
     *
     * @param mixed $value
     * @return bool
     */
    public static function isValidValue($value): bool {
        static::loadConstants();

        return array_search($value, static::$constants[get_called_class()], true) !== false;
    }

    /**
     * Get the constant value for a string matching an item
     *
     * @param string $name
     * @return mixed
     * @throws EnumException
     */
    public static function findValue(string $name) {
        static::loadConstants();

        //  Anonymous function to apply to searches for a loose comparison
        $normalize = function($item) {
            return strtolower(str_replace([ "_", "-", " " ], "", $item));
        };

        //  Grab and normalize keys
        $class = get_called_class();
        $keys = array_keys(static::$constants[$class]);
        $searchKeys = array_map(
            $normalize,
            $keys
        );

        if (($key = array_search($normalize($name), $searchKeys)) !== false)
            return static::$constants[$class][ $keys[$key] ];

        throw new EnumException("Invalid ". get_called_class(). " item name '$name'.");
    }

    /**
     * Get the name of a constant formatted as a string, given its corresponding value
     *
     * @param mixed $value
     * @return string
     * @throws EnumException
     */
    public static function findName($value): string {
        static::loadConstants();

        if (($key = array_search($value, static::$constants[get_called_class()], true)) !== false)
            return str_replace("_", " ", $key);

        throw new EnumException("Invalid ". get_called_class(). " item value '$value'.");
    }


    /**
     * Get the names of all valid constants as an array of strings
     * 
     * @return array
     */
    public static function listConstants(): array {
        return array_keys((new \ReflectionClass(static::class))->getConstants());
    }

}
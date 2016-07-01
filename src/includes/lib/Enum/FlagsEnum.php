<?php
/**
 * Utsubot - FlagsEnum.php
 * Date: 22/04/2016
 */

declare(strict_types = 1);

namespace Utsubot;


class FlagsEnumException extends EnumException {}

abstract class FlagsEnum extends Enum {

    protected static $highestPowers = [ ];

    /**
     * Check if this object has a given flag set
     *
     * @param int $flag
     * @return bool
     * @throws FlagsEnumException
     */
    public function hasFlag(int $flag): bool {
        if (!parent::isValidValue($flag))
            throw new FlagsEnumException("Invalid ". get_called_class(). " flag value '$flag'.");

        return (bool)($this->value & $flag);
    }

    /**
     * Get a comma separated list of the field names contained in the object's value
     *
     * @return string
     * @throws EnumException
     */
    public function getName(): string {

        //  Attempt to match exact value
        try {
            return parent::getName();
        }

        //  Value is a composite, list all flags instead
        catch (EnumException $e) {
            $flags = [ ];
            $class = get_called_class();

            //  Loop through powers of two
            for ($i = 0; (2**$i) <= static::$highestPowers[$class]; $i++) {
                if (2**$i & $this->value)
                    $flags[] = static::findName(2**$i);
            }

            return implode(", ", $flags);
        }
    }

    /**
     * Override loadConstants to automatically save highest power of 2 for faster repeated calls of isValidValue
     */
    protected static function loadConstants() {
        parent::loadConstants();

        $class = get_called_class();
        if (!isset(static::$highestPowers[$class])) {

            $highestPower = 0;
            foreach (static::$constants[$class] as $constant) {
                //  Only include powers of 2
                if (!($constant & ($constant - 1)) && $constant > $highestPower)
                    $highestPower = $constant;
            }

            static::$highestPowers[$class] = $highestPower;
        }

    }

    /**
     * Override isValidValue to allow composite values (flags)
     *
     * @param $value
     * @return bool
     */
    public static function isValidValue($value): bool {
        static::loadConstants();

        //  Largest possible value is bitshifted value of largest constant - 1, representing all flags on
        return is_int($value) && $value < (static::$highestPowers[get_called_class()] << 1);
    }
}
<?php
/**
 * MEGASSBOT - Conversion.php
 * User: Benjamin
 * Date: 01/11/14
 */

declare(strict_types = 1);

class ConversionException extends Exception {
}

trait Conversion {

    private static $metricPrefixes = array(
        'yocto' => -24,
        'zepto' => -21,
        'atto'  => -18,
        'femto' => -15,
        'pico'  => -12,
        'nano'  => -9,
        'micro' => -6,
        'milli' => -3,
        'centi' => -2,
        'deci'  => -1,
        'yotta' => 24,
        'zetta' => 21,
        'exa'   => 18,
        'peta'  => 15,
        'tera'  => 12,
        'giga'  => 9,
        'mega'  => 6,
        'kilo'  => 3,
        'hecto' => 2,
        'deca'  => 1
    );

    private static $metricShort = array(
        //  Deca must be first for proper regex parsing
        'deca'  => "da",
        'yocto' => "y",
        'zepto' => "z",
        'atto'  => "a",
        'femto' => "f",
        'pico'  => "p",
        'nano'  => "n",
        'micro' => "μ",
        'milli' => "m",
        'centi' => "c",
        'deci'  => "d",
        'yotta' => "Y",
        'zetta' => "Z",
        'exa'   => "E",
        'peta'  => "P",
        'tera'  => "T",
        'giga'  => "G",
        'mega'  => "M",
        'kilo'  => "k",
        'hecto' => "h"
    );

    private static $measures = array(

        'distance' => array(
            'meter'             => 1.0,
            'metre'             => 1.0,
            'planck length'     => 1.61619997E-35,
            'line'              => 2.117E-3,
            'inch'              => 0.0254,
            'foot'              => 0.3048,
            'yard'              => 0.9144,
            'mile'              => 1609.344,
            'nautical mile'     => 1852,
            'lunar distance'    => 3.844E5,
            'astronomical unit' => 1.495978707E11,
            'light-year'        => 9.4607304725808E15,
            'parsec'            => 3.0856776E16
        ),

        'mass' => array(
            'gram'             => 1.0,
            'atomic mass unit' => 1.66053892173E-24,
            'dalton'           => 1.66053892173E-24,
            'planck mass'      => 2.1765113E-5,
            'ounce'            => 28.349523125,
            'pound'            => 453.59237,
            'ton'              => 9.0718474E5,
            'long ton'         => 1.0160469088E6,
            'newton'           => 9.80665E3,
            'slug'             => 1.4593903E4,
            'tonne'            => 1E6,
            'troy ounce'       => 31.1034768,
            'troy pound'       => 373.2417216
        )

    );

    private static $measuresShort = array(
        'distance' => array(
            'meter'             => "m",
            'metre'             => "m",
            'plank length'      => "lp",
            'line'              => "li",
            'inch'              => "in",
            'foot'              => "ft",
            'yard'              => "yd",
            'mile'              => "mi",
            'nautical mile'     => "M",
            'lunar distance'    => "ld",
            'astronomical unit' => "au",
            'light-year'        => "ly",
            'parsec'            => "pc"
        ),

        'mass' => array(
            'gram'             => "g",
            'atomic mass unit' => "amu",
            "dalton"           => "Da",
            'planck mass'      => "mp",
            'ounce'            => "oz",
            'pound'            => "lb",
            'ton'              => "short ton",
            'long ton'         => "",
            'newton'           => "N",
            'slug'             => "",
            'tonne'            => "t",
            'troy ounce'       => "oz t",
            'troy pound'       => "lb t"
        )

    );


    /**
     * Perform a conversion between two units of measure
     *
     * @param string $measure The aspect to be measured (e.g., length)
     * @param float $value The starting quantity
     * @param string $unitsFrom The starting units
     * @param string $unitsTo The units to convert to
     * @return float Returns the new quantity
     * @throws ConversionException Invalid measure or units
     */
    public static function convert(string $measure, float $value, string $unitsFrom, string $unitsTo): float {
        $in  = self::parseUnits($unitsFrom, $measure);
        $out = self::parseUnits($unitsTo, $measure);

        //	Get unit measures for aspect
        $measure = strtolower($measure);
        if (!isset(self::$measures[$measure]))
            throw new ConversionException("Invalid measure '$measure'.");

        //	Convert to base units, e.g. meters
        $base = $value * self::$measures[$measure][$in->getUnit()] * pow(10, $in->getPower());
        $out  = $base / self::$measures[$measure][$out->getUnit()] / pow(10, $out->getPower());

        return (float)$out;
    }


    /**
     * Utility function used in conversion to parse a composite prefix+units or abbreviation into full unit name +
     * prefix exponent
     *
     * @param string $string The units string to parse (e.g., mm, kilometre, light-year)
     * @param string $measuring The aspect to be measured (e.g., length)
     * @return parsedUnit
     * @throws ConversionException Invalid measure or units
     */
    private static function parseUnits(string $string, string $measuring): ParsedUnit {
        $measuring = strtolower($measuring);
        if (!isset(self::$measures[$measuring]) || !isset(self::$measuresShort[$measuring]))
            throw new ConversionException("Invalid measure '$measuring'.");

        //  Initial values
        $power = 0;
        $unit  = "";

        //  Check metric prefixes with measure prefixes using a regex
        $prefixString = implode("|", self::$metricShort);
        $unitsString  = implode("|", self::$measuresShort[$measuring]);

        if (preg_match("/^($prefixString)($unitsString)$/", $string, $match)) {
            $power = self::$metricPrefixes[array_search($match[1], self::$metricShort)];
            $unit  = array_search($match[2], self::$measuresShort[$measuring]);
        }

        //	Check if only short unit is used with no prefix
        elseif ($measureKey = array_search($string, self::$measuresShort[$measuring]))
            $unit = $measureKey;
        //	Check if full unit name is used with no prefix
        elseif (isset(self::$measuresShort[$measuring][strtolower($string)]))
            $unit = strtolower($string);

        //	Check if full unit name is used with full prefix with a regex
        else {
            //	No need for case sensitivity any longer, since it's not an abbreviation
            $string = strtolower($string);

            $prefixString = implode("|", array_keys(self::$metricPrefixes));
            if (preg_match("/^($prefixString)(.+)/", $string, $match) && isset(self::$measures[$measuring][$match[2]])) {
                $power = self::$metricPrefixes[$match[1]];
                $unit  = $match[2];
            }
        }

        //	No matches found
        if (!$unit)
            throw new ConversionException("Invalid conversion unit '$string'.");

        return new ParsedUnit($unit, $power);
    }
}

/**
 * Class ParsedUnit
 *
 * Intermediate data structure for conversion methods
 */
class ParsedUnit {
    private $unit;
    private $power;

    public function __construct(string $unit, int $power) {
        $this->unit  = $unit;
        $this->power = $power;
    }

    public function getUnit(): string {
        return $this->unit;
    }

    public function getPower(): int {
        return $this->power;
    }
}
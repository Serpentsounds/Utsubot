<?php
/**
 * MEGASSBOT - Converter.php
 * User: Benjamin
 * Date: 01/11/14
 */

declare(strict_types = 1);

namespace Utsubot\Converter;

class ConverterException extends \Exception {}

class Converter {
    const METRIC_PREFIXES = array(
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

    const METRIC_SHORT = array(
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

    const MEASURES = array(

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

    const MEASURES_SHORT = array(

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


    /** @var string $measure */
    protected $measure;
    /** @var ParsedUnit $unitsIn */
    protected $unitsIn;
    /** @var ParsedUnit $unitsOut */
    protected $unitsOut;

    /**
     * Converter constructor.
     *
     * @param string $measure The aspect to be measured (e.g., length)
     * @param string $unitsFrom The starting units
     * @param string $unitsTo The units to convert to
     * @throws ConverterException Invalid measure or units
     */
    public function __construct(string $measure, string $unitsFrom, string $unitsTo) {
        $this->measure  = strtolower($measure);
        if (!array_key_exists($this->measure, self::MEASURES) || !array_key_exists($this->measure, self::MEASURES_SHORT))
            throw new ConverterException("Invalid measure '$measure'.");


        $this->unitsIn  = $this->parseUnits($unitsFrom);
        $this->unitsOut = $this->parseUnits($unitsTo);
    }

    /**
     * Perform a conversion between two units of measure
     *
     * @param float $value The starting quantity
     * @return float Returns the new quantity
     */
    public function convert(float $value): float {
        //	Convert to base units, e.g. meters
        $base = $value * self::MEASURES[$this->measure][$this->unitsIn->getUnit()] * pow(10, $this->unitsIn->getPower());

        $out  = $base / self::MEASURES[$this->measure][$this->unitsOut->getUnit()] / pow(10, $this->unitsOut->getPower());

        return (float)$out;
    }


    /**
     * Utility function used in conversion to parse a composite prefix+units or abbreviation into full unit name +
     * prefix exponent
     *
     * @param string $string The units string to parse (e.g., mm, kilometre, light-year)
     * @return ParsedUnit
     * @throws ConverterException Invalid measure or units
     */
    protected function parseUnits(string $string): ParsedUnit {
        //  Readability
        $measures = self::MEASURES[$this->measure];
        $measuresShort = self::MEASURES_SHORT[$this->measure];

        //  Initial values
        $power = 0;
        $unit  = "";

        //  Check metric prefixes with measure prefixes using a regex
        $prefixString = implode("|", self::METRIC_SHORT);
        $unitsString  = implode("|", $measuresShort);

        if (preg_match("/^($prefixString)($unitsString)$/", $string, $match)) {
            $power = self::METRIC_PREFIXES[array_search($match[1], self::METRIC_SHORT)];
            $unit  = array_search($match[2], $measuresShort);
        }

        //	Check if only short unit is used with no prefix
        elseif ($measureKey = array_search($string, $measuresShort))
            $unit = $measureKey;

        /*  Check if full unit name is used with no prefix
            No need for case sensitivity any longer, since it's not an abbreviation */
        elseif (($string = strtolower($string)) && array_key_exists($string, $measuresShort))
            $unit = strtolower($string);

        //	Check if full unit name is used with full prefix with a regex
        else {
            $prefixString = implode("|", array_keys(self::METRIC_PREFIXES));
            if (preg_match("/^($prefixString)(.+)/", $string, $match) && array_key_exists($match[2], $measures)) {
                $power = self::METRIC_PREFIXES[$match[1]];
                $unit  = $match[2];
            }
        }

        //	No matches found
        if (!$unit)
            throw new ConverterException("Invalid {$this->measure} unit '$string'.");

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
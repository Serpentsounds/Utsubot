<?php
/**
 * MEGASSBOT - Conversion.php
 * User: Benjamin
 * Date: 01/11/14
 */

class ConversionException extends Exception {}

class Conversion {

	private static $metricPrefixes = array(
		'yocto'	=> -24,	'zepto'	=> -21,	'atto'	=> -18,	'femto'	=> -15,	'pico'	=> -12,	'nano'	=> -9,	'micro'	=> -6,	'milli'	=> -3,	'centi'	=> -2,	'deci'	=> -1,
		'yotta'	=> 24,	'zetta'	=> 21,	'exa'	=> 18,	'peta'	=> 15,	'tera'	=> 12,	'giga'	=> 9,	'mega'	=> 6,	'kilo'	=> 3,	'hecto'	=> 2,	'deca'	=> 1
	);
	private static $metricShort = array(
		'yocto' => "y",	'zepto'	=> "z",	'atto'	=> "a",	'femto'	=> "f",	'pico'	=> "p",	'nano'	=> "n",	'micro'	=> "μ",	'milli'	=> "m",	'centi'	=> "c",	'deci'	=> "d",
		'yotta'	=> "Y",	'zetta'	=> "Z",	'exa'	=> "E",	'peta'	=> "P",	'tera'	=> "T",	'giga'	=> "G",	'mega'	=> "M",	'kilo'	=> "k",	'hecto'	=> "h",	'deca'	=> "da"
	);

	private static $measures = array(
		'distance'	=> array(
			'meter'	=> 1.0,			'metre'	=> 1.0,
			'planck length'					=> 1.61619997E-35,
			'line'	=> 2.117E-3,	'inch'	=> 0.0254,			'foot'	=> 0.3048,	'yard'		=> 0.9144,			'mile'			=> 1609.344,			'nautical mile'	=> 1852,
			'lunar distance'				=> 3.844E5,			'astronomical unit'				=> 1.495978707E11,	'light-year'	=> 9.4607304725808E15,	'parsec'		=> 3.0856776E16
		),
		'mass'		=> array(
			'gram'	=> 1.0,
			'atomic mass unit'				=> 1.66053892173E-24,					'dalton'	=>	1.66053892173E-24,										'planck mass'	=> 2.1765113E-5,
			'ounce'	=> 28.349523125,							'pound'	=> 453.59237,								'ton'			=> 9.0718474E5,			'long ton'		=> 1.0160469088E6,
			'newton'						=> 9.80665E3,		'slug'	=> 1.4593903E4,								'tonne'			=> 1E6,
			'troy ounce'					=> 31.1034768,		'troy pound'					=> 373.2417216
		)
	);

	private static $measuresShort = array(
		'distance'	=> array(
			'meter'	=> "m",			'metre'	=> "m",
			'plank length'					=> "lp",
			'line'	=> "li",		'inch'	=> "in",			'foot'	=> "ft",	'yard'		=> "yd",			'mile'			=> "mi",				'nautical mile'	=> "M",
			'lunar distance'				=> "ld",			'astronomical unit'				=> "au",			'light-year'	=> "ly",				'parsec'		=> "pc"
		),
		'mass'		=> array(
			'gram'	=>	"g",
			'atomic mass unit'				=> "amu",								"dalton"	=> "Da",													'planck mass'	=> "mp",
			'ounce'	=> "oz",									'pound'	=> "lb",									'ton'			=> "short ton",			'long ton'		=> "",
			'newton'						=> "N",				'slug'	=> "",										'tonne'			=> "t",
			'troy ounce'					=> "oz t",			'troy pound' 					=> "lb t"
		)
	);


	/**
	 * Perform a conversion between two units of measure
	 *
	 * @param string $measuring The aspect to be measured (e.g., length)
	 * @param float|int $value The starting quantity
	 * @param string $in The starting units
	 * @param string $out The units to convert to
	 * @return bool|float Returns the new quantity or false on failure
	 */
	public static function convert($measuring, $value, $in, $out) {
		$in = self::parseUnits($in, $measuring);
		$out = self::parseUnits($out, $measuring);

		//	Error parsing, invalid units or aspect to be measured
		if (!$in || !$out)
			return false;

		//	Get unit measures for aspect
		$measuring = strtolower($measuring);
		if (!isset(self::$measures[$measuring]))
			return false;

		//	Convert to base units, e.g. meters
		$base = $value * self::$measures[$measuring][$in[0]] * pow(10, $in[1]);
		$out = $base / self::$measures[$measuring][$out[0]] / pow(10, $out[1]);

		return $out;
	}


	/**
	 * Utility function used in conversion to parse a composite prefix+units or abbreviation into full unit name + prefix exponent
	 *
	 * @param string $string The units string to parse (e.g., mm, kilometre, light-year)
	 * @param string $measuring The aspect to be measured (e.g., length)
	 * @return array|bool Returns array(unit, power) or false on failure
	 */
	private static function parseUnits($string, $measuring) {
		$measuring = strtolower($measuring);
		if (!isset(self::$measures[$measuring]) || !isset(self::$measuresShort[$measuring]))
			return false;

		//	Prepare to check for single letter metric prefixes
		$first = substr($string, 0, 1);
		$rest = substr($string, 1);
		//	For deca (da)
		$first2 = substr($string, 0, 2);
		$rest2 = substr($string, 2);

		//	To be returned
		$power = 0;
		$measure = "";

		//	Check deca or future 2-letter prefixes
		if (($prefixKey = array_search($first2, self::$metricShort)) !== false && ($measureKey = array_search($rest2, self::$measuresShort[$measuring])) !== false) {
			$power = self::$metricPrefixes[$prefixKey];
			$measure = $measureKey;
		}
		//	Check single letter prefixes
		elseif (($prefixKey = array_search($first, self::$metricShort)) !== false && ($measureKey = array_search($rest, self::$measuresShort[$measuring])) !== false) {
			$power = self::$metricPrefixes[$prefixKey];
			$measure = $measureKey;
		}
		//	Check if only short unit is used with no prefix
		elseif ($measureKey = array_search($string, self::$measuresShort[$measuring]))
			$measure = $measureKey;
		//	Check if full unit name is used with no prefix
		elseif (isset(self::$measuresShort[$measuring][strtolower($string)]))
			$measure = strtolower($string);
		//	Check if full unit name is used with full prefix by looping
		else {
			//	No need for case sensitivity any longer, since it's not an abbreviation
			$string = strtolower($string);
			foreach (self::$metricPrefixes as $prefix => $newPower) {
				//	Check beginning of string and, if successful, remaining part
				if (strpos($string, $prefix) === 0 && isset(self::$measures[$measuring][substr($string, strlen($prefix))])) {
					$power = $newPower;
					$measure = substr($string, strlen($prefix));
				}
			}
		}

		//	No matches found
		if (!$measure)
			return false;

		return array($measure, $power);
	}
}
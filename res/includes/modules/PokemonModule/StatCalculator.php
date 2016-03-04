<?php
/**
 * Utsubot - StatCalculator.php
 * User: Benjamin
 * Date: 20/12/2014
 */

namespace Pokemon;

class StatCalculatorException extends \Exception {}

class StatCalculator {

	public static function baseToMax($base, $level = 100, $HP = false) {
		return self::calculateStat($base, 31, 252, $level, 1.1, $HP);
	}

	public static function maxToBase($max, $level = 100, $HP = false) {
		return self::calculateBase($max, 31, 252, $level, 1.1, $HP);
	}

	public static function calculateStat($base, $IV, $EV, $level, $natureModifier = 1.0, $HP = false) {
		self::validateParameters($base, $IV, $EV, $level, $natureModifier);

		if ($HP)
			$result = floor((($IV + (2 * $base) + floor($EV/4) + 100) * $level) / 100 + 10);
		else
			$result = floor(floor(((($IV + (2 * $base) + floor($EV/4)) * $level) / 100 + 5)) * $natureModifier);

		return $result;
	}

	public static function calculateBase($stat, $IV, $EV, $level, $natureModifier = 1.0, $HP = false) {
		self::validateParameters($stat, $IV, $EV, $level, $natureModifier);

		if ($HP)
			$base = ceil((($stat - 10) * (100/$level) - 100 - $IV - floor($EV/4)) / 2);
		else
			$base = ceil(((ceil($stat/$natureModifier) - 5) * (100/$level) - $IV - floor($EV/4)) / 2);

		return $base;
	}

	public static function calculateIVs($baseStats, $statValues, $EVs, $level, $natureModifiers) {
		$IVRange = array();
		for ($i = 0; $i <= 5; $i++) {
			self::validateParameters($statValues[$i], null, $EVs[$i], $level, $natureModifiers[$i]);

			for ($IV = 0; $IV <= 31; $IV++) {
				//	Stat formula with this IV plugged in
				$statWithIV = floor(floor((($IV + 2 * $baseStats[$i] + ($EVs[$i]/4)) * $level / 100 + 5)) * $natureModifiers[$i]);
				//	Adjust for HP formula
				if ($i == 0)
					$statWithIV = floor(($IV + 2 * $baseStats[$i] + ($EVs[$i]/4) + 100) * $level / 100 + 10);

				//	User supplied stat is lower than stat with 0 IVs, invalid
				if ($IV == 0 && $statValues[$i] < $statWithIV) {
					$IVRange[$i][0] = "Too low";
					break;
				}
				//	User supplied stat is higher than stat with 31 IVs, invalid
				elseif ($IV == 31 && $statValues[$i] > $statWithIV) {
					$IVRange[$i][0] = "TOO DAMN HIGH";
					break;
				}

				//	Stat matches, this is a possible IV match
				if ($statWithIV == $statValues[$i]) {
					//	Add lower bound if it doesn't exist
					if (!isset($IVRange[$i][0]))
						$IVRange[$i][0] = $IV;
					//	Update upper bound
					$IVRange[$i][1] = $IV;
				}

			}
			//	Remove range if bounds are the same
			if (isset($IVRange[$i][1]) && $IVRange[$i][0] == $IVRange[$i][1])
				unset($IVRange[$i][1]);
		}

		return $IVRange;
	}

	private static function validateParameters($stat, $IV, $EV, $level, $natureModifier) {
		if (!is_int($stat) && $stat !== null)
			throw new StatCalculatorException("Invalid stat value: $stat.");

		if ((!is_int($IV) || $IV < 0 || $IV > 31) && $IV !== null)
			throw new StatCalculatorException("Invalid IV value: $IV.");

		if ((!is_int($EV) || $EV < 0 || $EV > 255) && $EV !== null)
			throw new StatCalculatorException("Invalid EV value: $EV.");

		if ((!is_int($level) || $level < 0 || $level > 255) && $level !== null)
			throw new StatCalculatorException("Invalid level value: $level.");

		if ((!is_numeric($natureModifier) || ($natureModifier != 0.9 && $natureModifier != 1 && $natureModifier != 1.1)) && $natureModifier !== null)
			throw new StatCalculatorException("Invalid nature modifier value: $natureModifier.");
	}
} 
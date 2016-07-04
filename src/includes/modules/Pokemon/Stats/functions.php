<?php
/**
 * Utsubot - StatCalculator.php
 * User: Benjamin
 * Date: 20/12/2014
 */

namespace Utsubot\Pokemon\Stats;

/**
 * Class StatCalculatorException
 *
 * @package Utsubot\Pokemon\Stats
 */
class StatCalculatorException extends \Exception {

}


/**
 * @param int  $base
 * @param int  $level
 * @param bool $HP
 * @return int
 */
function baseToMax(int $base, int $level = 100, bool $HP = false): int {
    return calculateStat($base, 31, 252, $level, 1.1, $HP);
}

/**
 * @param int  $max
 * @param int  $level
 * @param bool $HP
 * @return int
 */
function maxToBase(int $max, int $level = 100, bool $HP = false): int {
    return calculateBase($max, 31, 252, $level, 1.1, $HP);
}

/**
 * @param int   $base
 * @param int   $IV
 * @param int   $EV
 * @param int   $level
 * @param float $natureModifier
 * @param bool  $HP
 * @return int
 */
function calculateStat(int $base, int $IV, int $EV, int $level, float $natureModifier = 1.0, bool $HP = false): int {
    if ($HP)
        $result = floor(
            (
                ($IV + (2 * $base) + floor($EV / 4) + 100)
                * $level
            )
            / 100 + 10
        );

    else
        $result = floor(
            floor(
                (
                    (
                        ($IV + (2 * $base) + floor($EV / 4))
                        * $level
                    )
                    / 100 + 5
                )
            )
            * $natureModifier
        );

    return $result;
}

/**
 * @param int   $stat
 * @param int   $IV
 * @param int   $EV
 * @param int   $level
 * @param float $natureModifier
 * @param bool  $HP
 * @return int
 */
function calculateBase(int $stat, int $IV, int $EV, int $level, float $natureModifier = 1.0, bool $HP = false): int {
    if ($HP)
        $base = ceil(
            (
                ($stat - 10) * (100 / $level) - 100 - $IV - floor($EV / 4)
            )
            / 2
        );

    else
        $base = ceil(
            (
                (ceil($stat / $natureModifier) - 5) * (100 / $level) - $IV - floor($EV / 4)
            )
            / 2
        );

    return $base;
}

/**
 * @param int   $baseStat
 * @param int   $statValue
 * @param int   $EV
 * @param int   $level
 * @param float $natureModifier
 * @param bool  $HP
 * @return array
 * @throws StatCalculatorException
 */
function getIVRange(int $baseStat, int $statValue, int $EV, int $level, float $natureModifier, bool $HP = false): array {
    $IVRange = [ ];

    if ($statValue < calculateStat($baseStat, 0, $EV, $level, $natureModifier, $HP))
        throw new StatCalculatorException("Stat value '$statValue' is too low for the given parameters.'", 0);
    if ($statValue > calculateStat($baseStat, 31, $EV, $level, $natureModifier, $HP))
        throw new StatCalculatorException("Stat value '$statValue' is too high for the given parameters.'", 1);

    for ($IV = 0; $IV <= 31; $IV++) {
        //  Stat matches, this is an IV match
        if ($statValue == calculateStat($baseStat, $IV, $EV, $level, $natureModifier, $HP)) {
            //  Add lower bound if it doesn't exist
            if (!isset($IVRange[ 0 ]))
                $IVRange[ 0 ] = $IV;
            //  Update upper bound
            $IVRange[ 1 ] = $IV;
        }
    }

    //  Remove range if bounds are the same
    if (isset($IVRange[ 1 ]) && $IVRange[ 0 ] == $IVRange[ 1 ])
        unset($IVRange[ 1 ]);

    return $IVRange;
}

/**
 * @param array $baseStats
 * @param array $statValues
 * @param array $EVs
 * @param int   $level
 * @param array $natureModifiers
 * @return array
 */
function calculateIVs(array $baseStats, array $statValues, array $EVs, int $level, array $natureModifiers): array {
    $IVRange = [ ];
    for ($i = 0; $i <= 5; $i++) {
        try {
            $IVRange[ $i ] = getIVRange($baseStats[ $i ], $statValues[ $i ], $EVs[ $i ], $level, $natureModifiers[ $i ], $i == 0);
        }

        catch (StatCalculatorException $e) {
            switch ($e->getCode()) {
                case 0:
                    $IVRange[ $i ] = "Too low";
                    break;
                case 1:
                    $IVRange[ $i ] = "TOO DAMN HIGH";
                    break;
            }
        }
    }

    return $IVRange;
}
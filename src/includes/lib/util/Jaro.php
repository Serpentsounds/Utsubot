<?php
/**
 * Utsubot - Jaro.php
 * Date: 04/03/2016
 *
 * Functions for calculating the Jaro-Winkler distance to determine the similary between 2 strings
 */

declare(strict_types = 1);

namespace Utsubot\Jaro;

/**
 * Utility function used in calculating the Jaro distance between two strings
 *
 * @param string $base       The first string
 * @param string $comparison The second string
 * @return string The common characters between them
 */
function getMatchingCharacters(string $base, string $comparison): string {
    $lengths     = [ strlen($base), strlen($comparison) ];
    $maxDistance = floor(max($lengths) / 2) - 1;
    $result      = "";

    for ($i = 0; $i < $lengths[ 0 ]; $i++) {
        $min = max(0, $i - $maxDistance);
        $max = min($i + $maxDistance, $lengths[ 1 ]);

        for ($j = intval($min); $j < $max; $j++) {
            if ($comparison[ $j ] == $base[ $i ]) {

                $result .= $base[ $i ];
                $comparison[ $j ] = "";
                break;
            }
        }

    }

    return $result;
}

/**
 * Calculate the Jaro distance between 2 strings. 1 = exact match, 0 = no match, similarities are somewhere in between
 *
 * @param string $base
 * @param string $comparison
 * @return float
 */
function jaroDistance(string $base, string $comparison): float {
    $lengths = [ strlen($base), strlen($comparison) ];

    $matchingCharacters = [
        getMatchingCharacters($base, $comparison),
        getMatchingCharacters($comparison, $base)
    ];
    $matchingLengths    = [ strlen($matchingCharacters[ 0 ]), strlen($matchingCharacters[ 1 ]) ];

    $matchingLengthsMinimum = min($matchingLengths);
    if ($matchingLengthsMinimum == 0)
        return 0;

    $swaps = 0;
    for ($i = 0; $i < $matchingLengthsMinimum; $i++) {
        if ($matchingCharacters[ 0 ][ $i ] != $matchingCharacters[ 1 ][ $i ])
            $swaps++;
    }
    $swaps /= 2;

    //	Jaro Calculation
    return ($matchingLengths[ 0 ] / $lengths[ 0 ] + $matchingLengths[ 0 ] / $lengths[ 1 ] + ($matchingLengths[ 0 ] - $swaps) / $matchingLengths[ 0 ]) / 3;
}

/**
 * Use the Winkler prefix weight in conjunction with the Jaro distance to calculate the Jaro-Winkler distance
 *
 * @param string $base
 * @param string $comparison
 * @param int    $prefixLength How many exactly matching characters to check for at the beginning of the strings. These
 *                             characters have more weight in the metric. Max of 4, default 4
 * @param float  $prefixScale  The weight to give to the matching prefix characters. Algorithm is defined for a max of
 *                             0.25, default 0.1
 * @return float
 */
function jaroWinklerDistance(string $base, string $comparison, int $prefixLength = 4, float $prefixScale = 0.1): float {
    $base       = strtolower($base);
    $comparison = strtolower($comparison);

    //	Prepare to calculate length of common prefix
    $check = min($prefixLength, strlen($base), strlen($comparison));

    $commonPrefix = 0;
    for ($i = 0; $i < $check; $i++) {
        //	Characters must be the same
        if ($base[ $i ] != $comparison[ $i ])
            break;

        $commonPrefix++;
    }

    $commonSuffix = 0;
    if ($check >= 6) {
        for ($i = $check - 1; $i >= 0; $i--) {
            //	Characters must be the same
            if ($base[ $i ] != $comparison[ $i ])
                break;

            $commonSuffix++;
        }
    }

    $jaroDistance = jaroDistance($base, $comparison);
    //	Jaro-Winkler Calculation
    $jaroWinklerDistance = $jaroDistance + $commonPrefix * $prefixScale * (1.0 - $jaroDistance);

    #return $jaroWinklerDistance;
    return $jaroWinklerDistance + $commonSuffix * $prefixScale * (1.0 - $jaroWinklerDistance);
}

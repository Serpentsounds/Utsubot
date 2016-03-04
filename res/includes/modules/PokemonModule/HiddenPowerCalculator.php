<?php

declare(strict_types=1);

namespace Pokemon;

/**
 * Class for calculating the type and damage of a pokemon's hidden power, given the IVs
 *
 * @package Pokemon
 */
class HiddenPowerCalculator {

    private static $types = array("Fighting", "Flying", "Poison", "Ground", "Rock", "Bug", "Ghost", "Steel", "Fire", "Water", "Grass", "Electric", "Psychic", "Ice", "Dragon", "Dark");

    private $IVs;

    /**
     * HiddenPowerCalculator constructor.
     *
     * @param int $hp HP IV
     * @param int $attack Attack IV
     * @param int $defense Defense IV
     * @param int $specialAttack Special Attack IV
     * @param int $specialDefense Special Defense IV
     * @param int $speed Speed IV
     */
    public function __construct(int $hp, int $attack, int $defense, int $specialAttack, int $specialDefense, int $speed) {
        //  Reorder speed IV to streamline binary calculations
        $this->IVs = array($hp, $attack, $defense, $speed, $specialAttack, $specialDefense);
    }

    /**
     * Calculate this object's hidden power parameters and return the result
     *
     * @return HiddenPowerCalculation
     */
    public function calculate(): HiddenPowerCalculation {
        //  Copy IV array for manipulation
        $typeTerms = $powerTerms = $this->IVs;

        /*	Hidden power type formula is given by (15/63)(a+2b+4c+8d+16e+32f), where a through f correspond to our reordered stats, and are 0 or 1 if the IV is even or odd (last binary bit)
         *	The floor()'d result is an index applied to a list of types
         *	This routine converts each IV to its term value in that equation
         */
        array_walk($typeTerms, function(&$iv, $key) {
            //	The 0-5 index corresponds with the term placement and coefficient, so we can use it calculate the term value
            $iv = ($iv % 2) * pow(2, $key);
        });

        //	Apply the final part of the type formula to the list of types
        $index = intval(array_sum($typeTerms) * 15 / 63);
        $type = self::$types[$index];


        /*	Hidden power base power formula is given by (40/63)(a+2b+4c+8d+16e+32f)+30, where a through f again correspond to the stats, but this time take on the 2nd to last binary bit
         *	This can be easily determined by checking if the value modulo 4 is greater than 1
         * 	The floor()'d result is the base power  */
        array_walk($powerTerms, function(&$iv, $key) {
            //	This operates similarly to the type routine
            $secondToLast = ($iv % 4 > 1) ? 1 : 0;
            $iv = $secondToLast * pow(2, $key);
        });

        //  Final bit of formula
        $power = intval(array_sum($powerTerms) * 40 / 63 + 30);

        return new HiddenPowerCalculation($type, $power);
    }


}

/**
 * The result set of a HiddenPowerCalculator's calculation
 *
 * @package Pokemon
 */
class HiddenPowerCalculation {

    private $type;
    private $power;

    /**
     * HiddenPowerCalculation constructor.
     *
     * @param string $type
     * @param int $power
     */
    public function __construct(string $type, int $power) {
        $this->type = $type;
        $this->power = $power;
    }

    public function getType() {
        return $this->type;
    }
    public function getPower() {
        return $this->power;
    }
}
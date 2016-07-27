<?php
/**
 * Utsubot - GOIVCalculator.php
 * Date: 26/07/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon\Stats;


use Utsubot\Color;
use Utsubot\Pokemon\Pokemon\Pokemon;
use function Utsubot\colorText;


/**
 * Class GOIVCalculatorException
 *
 * @package Utsubot\Pokemon\Stats
 */
class GOIVCalculatorException extends \Exception {

}


/**
 * Class GOIVCalculator
 *
 * @package Utsubot\Pokemon\Stats
 */
class GOIVCalculator {

    const Dust_Cost_Tiers = [
        1  => 200,
        5  => 400,
        9  => 600,
        13 => 800,
        17 => 1000,
        21 => 1300,
        25 => 1600,
        29 => 1900,
        33 => 2200,
        37 => 2500,
        41 => 3000,
        45 => 3500,
        49 => 4000,
        53 => 4500,
        57 => 5000,
        61 => 6000,
        65 => 7000,
        69 => 8000,
        73 => 9000,
        77 => 10000
    ];

    const CP_Multipliers = [
        1  => 0.0940000,
        2  => 0.1351374,
        3  => 0.1663979,
        4  => 0.1926509,
        5  => 0.2157325,
        6  => 0.2365727,
        7  => 0.2557201,
        8  => 0.2735304,
        9  => 0.2902499,
        10 => 0.3060574,
        11 => 0.3210876,
        12 => 0.3354450,
        13 => 0.3492127,
        14 => 0.3624578,
        15 => 0.3752356,
        16 => 0.3875924,
        17 => 0.3995673,
        18 => 0.4111936,
        19 => 0.4225000,
        20 => 0.4335117,
        21 => 0.4431076,
        22 => 0.4530600,
        23 => 0.4627984,
        24 => 0.4723361,
        25 => 0.4816850,
        26 => 0.4908558,
        27 => 0.4998584,
        28 => 0.5087018,
        29 => 0.5173940,
        30 => 0.5259425,
        31 => 0.5343543,
        32 => 0.5426358,
        33 => 0.5507927,
        34 => 0.5588306,
        35 => 0.5667545,
        36 => 0.5745692,
        37 => 0.5822789,
        38 => 0.5898879,
        39 => 0.5974000,
        40 => 0.6048188,
        41 => 0.6121573,
        42 => 0.6194041,
        43 => 0.6265671,
        44 => 0.6336492,
        45 => 0.6406530,
        46 => 0.6475810,
        47 => 0.6544356,
        48 => 0.6612193,
        49 => 0.6679340,
        50 => 0.6745819,
        51 => 0.6811649,
        52 => 0.6876849,
        53 => 0.6941437,
        54 => 0.7005429,
        55 => 0.7068842,
        56 => 0.7131691,
        57 => 0.7193991,
        58 => 0.7255756,
        59 => 0.7317000,
        60 => 0.7377735,
        61 => 0.7377695,
        62 => 0.7407856,
        63 => 0.7437894,
        64 => 0.7467812,
        65 => 0.7497610,
        66 => 0.7527291,
        67 => 0.7556855,
        68 => 0.7586304,
        69 => 0.7615638,
        70 => 0.7644861,
        71 => 0.7673972,
        72 => 0.7702973,
        73 => 0.7731865,
        74 => 0.7760650,
        75 => 0.7789328,
        76 => 0.7817901,
        77 => 0.7846370,
        78 => 0.7874736,
        79 => 0.7903000,
        80 => 0.7931164
    ];

    protected $pokemon;
    protected $CP;
    protected $HP;
    protected $dustCost;
    protected $minimumLevel;
    protected $trainerLevel;
    protected $isPowered;

    protected $results = [ ];


    /**
     * GOIVCalculator constructor.
     *
     * @param Pokemon $pokemon
     * @param int     $CP
     * @param int     $HP
     * @param int     $dustCost
     * @param int     $trainerLevel
     * @param bool    $isPowered
     * @throws GOIVCalculatorException
     */
    public function __construct(Pokemon $pokemon, int $CP, int $HP, int $dustCost, int $trainerLevel, bool $isPowered) {
        $this->pokemon = $pokemon;

        //  CP has a built-in minimum of 10
        if ($CP < 10)
            throw new GOIVCalculatorException("Invalid CP value '$CP'.");
        $this->CP = $CP;

        //  So does HP
        if ($HP < 10)
            throw new GOIVCalculatorException("Invalid HP value '$HP'.");
        $this->HP = $HP;

        //  Dust cost must be exactly one of the tiers
        if (($index = array_search($dustCost, self::Dust_Cost_Tiers)) === false)
            throw new GOIVCalculatorException("Invalid dust cost '$dustCost'.");
        $this->dustCost     = $dustCost;
        $this->minimumLevel = $index;

        //  Trainers start at level 1, and 40 is the current cap
        if ($trainerLevel < 1 || $trainerLevel > 40)
            throw new GOIVCalculatorException("Invalid trainer level '$trainerLevel'.");
        $this->trainerLevel = $trainerLevel;

        $this->isPowered = $isPowered;

    }


    /**
     * Parse and internally save the result set for possible IVs
     *
     * @throws GOIVCalculatorException
     */
    public function calculate() {
        $minimumLevel = $this->minimumLevel;
        $maximumLevel = min($this->minimumLevel + 3, 2 * ($this->trainerLevel + 1));

        //  Initialize stat enums
        $HPStat      = new GOStat(GOStat::HP);
        $staminaStat = new GOStat(GOStat::Stamina);
        $attackStat  = new GOStat(GOStat::Attack);
        $defenseStat = new GOStat(GOStat::Defense);

        $combinations = [ ];
        //  Get sets for all possible levels
        for ($level = $minimumLevel; $level <= $maximumLevel; $level++) {

            //  Pokemon are only encountered at odd levels, so unpowered Pokemon can't have an even level
            if (!$this->isPowered && $level % 2 == 0)
                continue;

            //  Loop through all stat possibilities
            for ($stamina = 0; $stamina <= 15; $stamina++) {

                //  Eliminate stamina values which don't all for this current HP
                if ($this->calculateStat($HPStat, $stamina, $level) != $this->HP)
                    continue;

                for ($attack = 0; $attack <= 15; $attack++) {
                    for ($defense = 0; $defense <= 15; $defense++) {

                        //  CP value matches
                        if (self::calculateCP(
                                $this->calculateStat($staminaStat, $stamina, $level),
                                $this->calculateStat($attackStat, $attack, $level),
                                $this->calculateStat($defenseStat, $defense, $level)
                            ) == $this->CP
                        ) {
                            //  Save combination
                            $combinations[] = [ $level, $stamina, $attack, $defense ];
                        }

                    }
                }

            }

        }

        $this->results = $combinations;

    }


    /**
     * @return string
     * @throws GOIVCalculatorException
     */
    public function formatResults(): string {
        if (!$this->results)
            throw new GOIVCalculatorException("No possible results.");

        $results = [ ];
        $color   = new Color(Color::Teal);
        foreach ($this->results as $stats)
            $results[] = colorText("[", $color).implode(", ", $stats).colorText("]", $color);

        return "Possible results [Lvl, Sta, Atk, Def]: ".implode("; ", $results);
    }


    /**
     * @param GOStat $stat
     * @param int    $iv
     * @param int    $level
     * @return float
     * @throws GOIVCalculatorException
     */
    protected function calculateStat(GOStat $stat, int $iv, int $level): float {
        switch ($stat->getValue()) {

            case GOStat::Stamina:
            case GOStat::HP:
                $base = $this->pokemon->getBaseGoStamina();
                break;

            case GOStat::Attack:
                $base = $this->pokemon->getBaseGoAttack();
                break;

            case GOStat::Defense:
                $base = $this->pokemon->getBaseGoDefense();
                break;

            default:
                throw new GOIVCalculatorException("Invalid GO stat '{$stat->getName()}'.");
                break;
        }

        //  Pokemon GO stat formula
        $return = ($base + $iv) * self::CP_Multipliers[ $level ];

        //  Apply HP formula if necessary
        if ($stat->getValue() == GOStat::HP)
            $return = max(10, floor($return));

        return $return;
    }


    /**
     * @param float $stamina
     * @param float $attack
     * @param float $defense
     * @return int
     */
    public static function calculateCP(float $stamina, float $attack, float $defense) {
        return (int)max(10, floor(($stamina ** 0.5) * $attack * ($defense ** 0.5) / 10));
    }

}
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
        2  => 200,
        6  => 400,
        10 => 600,
        14 => 800,
        18 => 1000,
        22 => 1300,
        26 => 1600,
        30 => 1900,
        34 => 2200,
        38 => 2500,
        42 => 3000,
        46 => 3500,
        50 => 4000,
        54 => 4500,
        58 => 5000,
        62 => 6000,
        66 => 7000,
        70 => 8000,
        74 => 9000,
        78 => 10000
    ];

    const CP_Multipliers = [
        2  => 0.0940000,
        3  => 0.1351374,
        4  => 0.1663979,
        5  => 0.1926509,
        6  => 0.2157325,
        7  => 0.2365727,
        8  => 0.2557201,
        9  => 0.2735304,
        10 => 0.2902499,
        11 => 0.3060574,
        12 => 0.3210876,
        13 => 0.3354450,
        14 => 0.3492127,
        15 => 0.3624578,
        16 => 0.3752356,
        17 => 0.3875924,
        18 => 0.3995673,
        19 => 0.4111936,
        20 => 0.4225000,
        21 => 0.4335117,
        22 => 0.4431076,
        23 => 0.4530600,
        24 => 0.4627984,
        25 => 0.4723361,
        26 => 0.4816850,
        27 => 0.4908558,
        28 => 0.4998584,
        29 => 0.5087018,
        30 => 0.5173940,
        31 => 0.5259425,
        32 => 0.5343543,
        33 => 0.5426358,
        34 => 0.5507927,
        35 => 0.5588306,
        36 => 0.5667545,
        37 => 0.5745692,
        38 => 0.5822789,
        39 => 0.5898879,
        40 => 0.5974000,
        41 => 0.6048188,
        42 => 0.6121573,
        43 => 0.6194041,
        44 => 0.6265671,
        45 => 0.6336492,
        46 => 0.6406530,
        47 => 0.6475810,
        48 => 0.6544356,
        49 => 0.6612193,
        50 => 0.6679340,
        51 => 0.6745819,
        52 => 0.6811649,
        53 => 0.6876849,
        54 => 0.6941437,
        55 => 0.7005429,
        56 => 0.7068842,
        57 => 0.7131691,
        58 => 0.7193991,
        59 => 0.7255756,
        60 => 0.7317000,
        61 => 0.7377735,
        62 => 0.7377695,
        63 => 0.7407856,
        64 => 0.7437894,
        65 => 0.7467812,
        66 => 0.7497610,
        67 => 0.7527291,
        68 => 0.7556855,
        69 => 0.7586304,
        70 => 0.7615638,
        71 => 0.7644861,
        72 => 0.7673972,
        73 => 0.7702973,
        74 => 0.7731865,
        75 => 0.7760650,
        76 => 0.7789328,
        77 => 0.7817901,
        78 => 0.7846370,
        79 => 0.7874736,
        80 => 0.7903000,
        81 => 0.7931164,
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
        $maximumLevel = $this->minimumLevel + 3;

        //  Potentially narrow down results for unpowered pokemon by limiting level to trainer level (Pokemon won't be encountered above this level)
        if (!$this->isPowered)
            $maximumLevel = min($maximumLevel, 2 * $this->trainerLevel);

        //  Initialize stat enums
        $HPStat      = new GOStat(GOStat::HP);
        $staminaStat = new GOStat(GOStat::Stamina);
        $attackStat  = new GOStat(GOStat::Attack);
        $defenseStat = new GOStat(GOStat::Defense);

        $combinations = [ ];
        //  Get sets for all possible levels
        for ($level = $minimumLevel; $level <= $maximumLevel; $level++) {

            //  Pokemon are only encountered at whole number levels (or even numbers for our internal scheme), so unpowered Pokemon can't have an odd level
            if (!$this->isPowered && $level % 2 == 1)
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
                            $combinations[ ] = [ $level/2.0, $stamina, $attack, $defense ];
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
        $color = new Color(Color::Teal);
        foreach ($this->results as $stats)
            $results[ ] = colorText("[", $color). implode(", ", $stats). colorText("]", $color);

        return "Possible results [Lvl, Sta, Atk, Def]: ". implode("; ", $results);
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
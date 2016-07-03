<?php
/**
 * Utsubot - Stat.php
 * Date: 16/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon;


use Utsubot\Enum;


/**
 * Class Stat
 *
 * @package Utsubot\Pokemon
 * @method static Stat fromName(string $name)
 */
class Stat extends Enum {

    const Hit_Points      = 0;
    const Attack          = 1;
    const Defense         = 2;
    const Special_Attack  = 3;
    const Special_Defense = 4;
    const Speed           = 5;

    const HP  = self::Hit_Points;
    const Atk = self::Attack;
    const Def = self::Defense;
    const SpA = self::Special_Attack;
    const SpD = self::Special_Defense;
    const Spe = self::Speed;

}
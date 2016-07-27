<?php
/**
 * Utsubot - GOStat.php
 * Date: 26/07/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon\Stats;


use Utsubot\Enum;


/**
 * Class GOStat
 *
 * @package Utsubot\Pokemon\Stats
 */
class GOStat extends Enum {

    const Stamina = 0;
    const Attack  = 1;
    const Defense = 2;
    const HP      = 3;

}
<?php
/**
 * Utsubot - Method.php
 * Date: 15/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon\Pokemon;


use Utsubot\Enum;


/**
 * Class EvolutionMethod
 *
 * @package Utsubot\Pokemon\Pokemon
 */
class EvolutionMethod extends Enum {

    const Level_Up = 0;
    const Trade    = 1;
    const Use      = 2;
    const Shed     = 3;

}
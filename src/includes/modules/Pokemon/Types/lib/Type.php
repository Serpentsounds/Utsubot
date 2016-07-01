<?php
/**
 * Utsubot - Types.php
 * Date: 12/05/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon\Types;
use Utsubot\Enum;


/**
 * Class Types
 *
 * @package Utsubot\Pokemon\Types
 */
class Type extends Enum {

    const Bug       = 0;
    const Dark      = 1;
    const Dragon    = 2;
    const Electric  = 3;
    const Fairy     = 4;
    const Fighting  = 5;
    const Fire      = 6;
    const Flying    = 7;
    const Ghost     = 8;
    const Grass     = 9;
    const Ground    = 10;
    const Ice       = 11;
    const Normal    = 12;
    const Poison    = 13;
    const Psychic   = 14;
    const Rock      = 15;
    const Steel     = 16;
    const Water     = 17;


    /**
     * Get the equivalent TypeChart object for this Type
     * 
*@return TypeEffectivenessChart
     */
    public function toChart(): TypeEffectivenessChart {
        return TypeEffectivenessChart::fromName($this->getName());
    }
}
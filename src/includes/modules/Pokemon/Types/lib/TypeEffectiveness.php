<?php
/**
 * Utsubot - TypeMultipliers.php
 * Date: 12/05/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon\Types;
use Utsubot\Enum;


/**
 * Class TypeEffectiveness
 *
 * @package Utsubot\Pokemon\Types\lib
 */
class TypeEffectiveness extends Enum {

    const Immune            = 0;
    const NotVeryEffective  = 1;
    const Effective         = 2;
    const SuperEffective    = 3;

}
<?php
/**
 * Utsubot - TypeEffectivenessMultiplier.php
 * Date: 22/06/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon\Types;
use Utsubot\Enum;


/**
 * Class TypeEffectivenessMultiplier
 *
 * @package Utsubot\Pokemon\Types
 */
class TypeEffectivenessMultiplier extends Enum {

    const Immune            = 0.0;
    const NotVeryEffective  = 0.5;
    const Effective         = 1.0;
    const SuperEffective    = 2.0;


    /**
     * @param TypeEffectiveness $effectiveness
     * @return TypeEffectivenessMultiplier
     */
    public static function fromTypeEffectiveness(TypeEffectiveness $effectiveness): TypeEffectivenessMultiplier {
        return self::fromName($effectiveness->getName());
    }

}
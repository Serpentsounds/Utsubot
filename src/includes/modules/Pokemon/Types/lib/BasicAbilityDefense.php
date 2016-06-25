<?php
/**
 * Utsubot - AbilityDefense.php
 * Date: 12/05/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon\Types;
use Utsubot\{
    Enum,
    EnumException
};


/**
 * Class BasicAbilityDefenseException
 *
 * @package Utsubot\Pokemon\Types
 */
class BasicAbilityDefenseException extends EnumException {}

/**
 * Class BasicAbilityDefense
 *
 * @package Utsubot\Pokemon\Types
 */
class BasicAbilityDefense extends Enum {

    const Volt_Absorb   = [ Type::Electric  => -1.0 ];
    const Water_Absorb  = [ Type::Water     => -1.0 ];
    const Flash_Fire    = [ Type::Fire      =>  0.0 ];
    const Levitate      = [ Type::Ground    =>  0.0 ];
    const Lightning_Rod = [ Type::Electric  =>  0.0 ];
    const Motor_Drive   = [ Type::Electric  =>  0.0 ];
    const Storm_Drain   = [ Type::Water     =>  0.0 ];
    const Herbivore     = [ Type::Grass     =>  0.0 ];
    const Sap_Sipper    = [ Type::Grass     =>  0.0 ];
    const Heatproof     = [ Type::Fire      =>  0.5 ];
    const Thick_Fat     = [
                            Type::Fire  =>  0.5,
                            Type::Ice   => 0.5
                        ];
    const Dry_Skin      = [
                            Type::Water => -1.0,
                            Type::Fire  => 1.25
                        ];

    /**
     * @param Type $type
     * @param float $value
     * @return float
     * @throws BasicAbilityDefenseException
     */
    public function apply(Type $type, float $value): float {
        if (!isset($this->value[$type->getValue()]))
            throw new BasicAbilityDefenseException("This ability does not affect the given type");

        return $value * $this->value[$type->getValue()];
    }

}
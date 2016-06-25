<?php
/**
 * Utsubot - AdvancedAbilityDefense.php
 * Date: 12/05/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon\Types;
use Utsubot\{
    Enum,
    EnumException
};


/**
 * Class AdvancedAbilityDefenseException
 *
 * @package Utsubot\Pokemon\Types
 */
class AdvancedAbilityDefenseException extends EnumException {}

/**
 * Class AdvancedAbilityDefense
 *
 * @package Utsubot\Pokemon\Types
 */
class AdvancedAbilityDefense extends Enum {
    
    const Filter      = array(
        "multiplier"    => 0.75,
        "condition"     => ">",
        "value"         => 1
    );
    
    const SolidRock   = array(
        "multiplier"    => 0.75,
        "condition"     => ">",
        "value"         => 1
    );
    
    const WonderGuard = array(
        "multiplier"    => 0,
        "condition"     => "<",
        "value"         => 2
    );

    /**
     * Attempt to apply this ability's effects to a running type matchup multiplier
     * 
     * @param float $value
     * @return float
     * @throws AdvancedAbilityDefenseException
     */
    public function apply(float $value): float {
        $newValue = $value;

        switch ($this->value['condition']) {
            case ">":
                if ($this->value['value'] > $value)
                    $value *= $this->value['multiplier'];
                break;

            case "<":
                if ($this->value['value'] < $value)
                    $value *= $this->value['multiplier'];
                break;
        }

        if ($newValue == $value)
            throw new AdvancedAbilityDefenseException("This ability can not trigger for the given value of '$value'.");

        return $value;
    }
    
}
<?php
/**
 * Utsubot - CalculatedChart.php
 * Date: 12/05/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon\Types;

/**
 * Class CalculatedChart
 *
 * @package Utsubot\Pokemon\Types
 */
class CalculatedChart {

    private $multipliers = [ ];

    /**
     * CalculatedChart constructor.
     *
     * @param TypeChart $typeChart
     * @param ChartMode $mode
     */
    public function __construct(TypeGroup $types, ChartMode $mode) {
        $allTypes = TypeGroup::fromStrings(Type::listConstants());
        
        foreach ($types as $type1) {
            foreach ($allTypes as $type2) {
                
                switch ($mode->getValue()) {
                    case ChartMode::Offensive:
                        $this->multipliers[ $type2->getValue() ] *= getCompoundEffectiveness($type1->toChart(), new TypeGroup([ $type2 ]));
                        break;

                    case ChartMode::Defensive:
                        $this->multipliers[ $type2->getValue() ] *= getCompoundEffectiveness($type2->toChart(), new TypeGroup([ $type1 ]));
                        break;
                }
            }
        }

    }

    /**
     * @return array
     */
    public function getMultipliers(): array {
        return $this->multipliers;
    }

}
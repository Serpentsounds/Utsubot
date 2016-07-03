<?php
/**
 * Utsubot - CalculatedChart.php
 * Date: 12/05/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon\Types;

/**
 * Class TypeChart
 *
 * @package Utsubot\Pokemon\Types
 */
class TypeChart {

    /** @var ChartMode $chartMode */
    private $chartMode;
    
    private $multipliers = [ ];


    /**
     * TypeChart constructor.
     *
     * @param TypeGroup $types
     * @param ChartMode $mode
     */
    public function __construct(TypeGroup $types, ChartMode $mode) {
        $this->chartMode = $mode;
        
        $allTypes = TypeGroup::fromStrings(array_keys(Type::listConstants()));

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


    /**
     * @return ChartMode
     */
    public function getMode(): ChartMode {
        return $this->chartMode;
    }

}
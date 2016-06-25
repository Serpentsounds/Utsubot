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
     * @param array     $typeCharts Array of valid Type or TypeChart constant names
     * @param ChartMode $mode
     */
    public function __construct(array $typeCharts, ChartMode $mode) {
        $typeNames = Type::getTypeNames();
        
        foreach ($typeNames as $name) {
            switch ($mode) {
                case ChartMode::Offensive:
                    $this->multipliers[ $name ] = getCompoundEffectiveness($typeCharts, [ $name ]);
                    break;

                case ChartMode::Defensive:
                    $this->multipliers[ $name ] = getCompoundEffectiveness([ $name ], $typeCharts);
                    break;
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
<?php
/**
 * Utsubot - Units.php
 * Date: 01/05/2016
 */

declare(strict_types = 1);

namespace Utsubot\Web;
use Utsubot\FlagsEnum;


/**
 * Class Units
 *
 * @package Utsubot\Web
 */
class Units extends FlagsEnum {
    
    const Imperial  = 1 << 0;
    const Metric    = 1 << 1;
    
    const Both      = self::Imperial | self::Metric;

}
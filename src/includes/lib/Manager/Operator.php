<?php
/**
 * Utsubot - Operator.php
 * Date: 03/07/2016
 */

declare(strict_types = 1);

namespace Utsubot\Manager;


use Utsubot\Enum;


/**
 * Class Operator
 *
 * @package Utsubot\Manager
 * @method static Operator fromName(string $name)
 */
class Operator extends Enum {

    const EqualsAlt       = "=";
    const Equals          = "==";
    const NotEquals       = "!=";
    const EqualsStrict    = "===";
    const NotEqualsStrict = "!==";

    const GreaterThan        = ">";
    const GreaterThanOrEqual = ">=";
    const LessThan           = "<";
    const LessThanOrEqual    = "<=";

    const Wildcard = "*=";

}
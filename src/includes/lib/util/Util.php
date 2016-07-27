<?php
/**
 * Utsubot - Util.php
 * Date: 26/07/2016
 */

declare(strict_types = 1);

namespace Utsubot\Util;

/**
 * Class UtilException
 *
 * @package Utsubot\Util
 */
class UtilException extends \Exception {}


/**
 * Make sure a passed value is an integer, then return it as an integer type
 *
 * @param $check
 * @return int
 * @throws UtilException
 */
function checkInt($check): int {
    if (!is_numeric($check) || (($intVal = intval($check)) != $check))
        throw new UtilException("Could not form an integer from '$check'.");

    return $intVal;
}
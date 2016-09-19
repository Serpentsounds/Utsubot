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

/**
 * Get the name of a class without the namespace prefixing it
 *
 * @param $class
 * @return string
 * @throws UtilException
 */
function getClassOnly($class): string {
    if (($className = get_class($class)) === false)
        throw new UtilException("Unable to get class for '$class'.");

    $parts = explode("\\", $className);
    return $parts[count($parts) - 1];
}
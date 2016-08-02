<?php
/**
 * Utsubot - MethodInfoWithParameters.php
 * Date: 02/08/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon;

/**
 * Class MethodInfoWithParametersException
 *
 * @package Utsubot\Pokemon
 */
class MethodInfoWithParametersException extends \Exception {

}


/**
 * Class MethodInfoWithParameters
 *
 * @package Utsubot\Pokemon
 */
class MethodInfoWithParameters extends MethodInfo {

    /**
     * MethodInfoWithParameters constructor.
     *
     * @param string $method
     * @param array  $parameters
     * @throws MethodInfoWithParametersException
     */
    public function __construct(string $method, array $parameters) {

        if (!$parameters)
            throw new MethodInfoWithParametersException("Parameters can not be empty for method '$method'.");

        parent::__construct($method, $parameters);
    }
    
}
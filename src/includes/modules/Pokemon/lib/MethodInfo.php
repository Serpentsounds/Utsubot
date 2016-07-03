<?php
/**
 * Utsubot - MethodInfo.php
 * Date: 03/07/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon;

/**
 * Class MethodInfo
 *
 * @package Utsubot\Pokemon\lib
 */
class MethodInfo {

    private $method;
    private $parameters;


    /**
     * MethodInfo constructor.
     *
     * @param string $method
     * @param array  $parameters
     */
    public function __construct(string $method, array $parameters) {
        $this->method     = $method;
        $this->parameters = $parameters;
    }


    /**
     * @return string
     */
    public function getMethod() {
        return $this->method;
    }


    /**
     * @return array
     */
    public function getParameters() {
        return $this->parameters;
    }

}
<?php
/**
 * Utsubot - ManagerSearchObject.php
 * Date: 21/04/2016
 */

declare(strict_types = 1);

namespace Utsubot;


/**
 * Class ManagerSearchObject
 *
 * @package Utsubot
 */
class ManagerSearchObject {
    private $manager;
    private $method;
    private $parameters;
    private $operators;

    public function __construct(Manager $manager, $method, $parameters, $operators) {
        $this->manager = $manager;

        $className = $this->manager->getManages();

        if (!method_exists($className, $method) && $operators != $manager::getCustomOperators())
            throw new ManagerException("'$className' does not contain method '$method'.");
        $this->method = $method;

        if (!is_array($parameters))
            throw new ManagerException("Parameters must be an array.");
        $this->parameters = $parameters;

        if ($operators != Manager::getStringOperators() && $operators != Manager::getNumericOperators() && $operators != Manager::getArrayOperators() && $operators != $manager::getCustomOperators())
            throw new ManagerException("Invalid operators array.");
        $this->operators = $operators;
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
    public function getOperators() {
        return $this->operators;
    }

    /**
     * @return array
     */
    public function getParameters() {
        return $this->parameters;
    }

}
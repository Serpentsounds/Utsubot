<?php
/**
 * Utsubot - ManagerSearchCriterion.php
 * Date: 21/04/2016
 */

declare(strict_types = 1);

namespace Utsubot;


/**
 * Class ManagerSearchCriterion
 *
 * @package Utsubot
 */
class ManagerSearchCriterion {
    private $manager;
    private $field;
    private $operator;
    private $value;
    private $searchObject;

    public function __construct(Manager $manager, $field, $operator, $value) {
        $this->manager = $manager;
        $this->searchObject = $manager->searchFields($field, $operator, $value);
        if (!($this->searchObject instanceof ManagerSearchObject))
            throw new ManagerException("Invalid search parameters (field, operator, value) '$field', '$operator', '$value'.");

        $this->field = $field;

        $operators = $this->searchObject->getOperators();
        if (!in_array($operator, $operators))
            throw new ManagerException("Invalid comparison operator '$operator' for field '$field'.");
        $this->operator = $operator;

        if (
            ($operators == Manager::getNumericOperators() && !is_numeric($value)) ||	//	If doing a numeric comparison, accept only numeric values
            ($operators == Manager::getStringOperators() && !is_string($value)) ||	// If doing a string comparison, accept only strings
            ($operators == Manager::getArrayOperators() && !is_array($value))	//	If doing an array comparison, accept only arrays
        )
            throw new ManagerException("Invalid search value '$value' for operator '$operator'.");
        $this->value = $value;

        //	Case-insensitive comparison
        if (is_string($this->field))
            $this->field = strtolower($this->field);
        if (is_string($this->value))
            $this->value = strtolower($this->value);
    }

    /**
     * @return string
     */
    public function getField() {
        return $this->field;
    }

    /**
     * @return Manager
     */
    public function getManager() {
        return $this->manager;
    }

    /**
     * @return string
     */
    public function getOperator() {
        return $this->operator;
    }

    /**
     * @return ManagerSearchObject
     */
    public function getSearchObject() {
        return $this->searchObject;
    }

    /**
     * @return mixed
     */
    public function getValue() {
        return $this->value;
    }
}
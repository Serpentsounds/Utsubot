<?php
/**
 * Utsubot - Criterion.php
 * Date: 03/07/2016
 */

declare(strict_types = 1);

namespace Utsubot\Manager;

/**
 * Class CriterionException
 *
 * @package Utsubot\Manager
 */
class CriterionException extends \Exception {

}


/**
 * Class Criterion
 *
 * @package Utsubot\Manager
 */
class SearchCriterion {

    private $getMethod           = "";
    private $getMethodParameters = [ ];

    /** @var Operator $comparisonOperator */
    private $comparisonOperator;
    private $comparisonValue;


    /**
     * Criterion constructor.
     *
     * @param string   $getMethod
     * @param array    $getMethodParameters
     * @param Operator $comparisonOperator
     * @param          $comparisonValue
     */
    public function __construct(string $getMethod, array $getMethodParameters, Operator $comparisonOperator, $comparisonValue) {
        $this->getMethod = $getMethod;
        $this->getMethodParameters = $getMethodParameters;

        $this->comparisonOperator = $comparisonOperator;
        $this->comparisonValue = $comparisonValue;
    }

    /**
     * @param $object
     * @return bool
     * @throws CriterionException
     */
    public function compare($object): bool {
        if (!method_exists($object, $this->getMethod))
            throw new CriterionException("Passed object does not contain method '{$this->getMethod}'.");

        $objectValue = call_user_func_array([ $object, $this->getMethod ], $this->getMethodParameters);

        switch ($this->comparisonOperator->getValue()) {

            //  Equality comparison
            case "=":
            case "==":
                $return = $objectValue == $this->comparisonValue;
                break;
            case "===":
                $return = $objectValue === $this->comparisonValue;
                break;
            case "!=":
                $return = $objectValue != $this->comparisonValue;
                break;
            case "!==":
                $return = $objectValue !== $this->comparisonValue;
                break;

            //  Magnitude comparison
            case ">":
                $return = $objectValue > $this->comparisonValue;
                break;
            case ">=":
                $return = $objectValue >= $this->comparisonValue;
                break;
            case "<":
                $return = $objectValue < $this->comparisonValue;
                break;
            case "<=":
                $return = $objectValue <= $this->comparisonValue;
                break;

            //  Special wildcard string comparison
            case "*=":
                if (!is_string($this->comparisonValue))
                    throw new CriterionException("Non-string comparison value passed for *= comparison.");
                if (!is_string($objectValue))
                    throw new CriterionException("Non-string object value returned for *= comparison.");

                if (!preg_match("/[?*\\[]/", $this->comparisonValue))
                    $this->comparisonValue = "*$this->comparisonValue*";

                $return = fnmatch($this->comparisonValue, $objectValue);
                break;

            //  Unknown Operator value, should never trigger
            default:
                throw new CriterionException("Unsupported Operator value '". $this->comparisonOperator->getValue(). "'.");
                break;
        }

        return (bool)$return;
    }

}
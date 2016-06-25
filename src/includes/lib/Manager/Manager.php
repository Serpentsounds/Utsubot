<?php
/**
 * MEGASSBOT - Manager.php
 * User: Benjamin
 * Date: 06/11/14
 */

namespace Utsubot;

/**
 * Class ManagerException
 *
 * @package Utsubot
 */
class ManagerException extends \Exception {

}

/**
 * Class Manager
 *
 * @package Utsubot
 */
abstract class Manager {

    //	Holds the manager's collection of objects
    protected $collection = [ ];

    protected static $manages = "";

    protected static $numericOperators = [ "=", "==", "!=", "<", "<=", ">", ">=" ];
    protected static $stringOperators  = [ "=", "==", "!=", "*=" ];
    protected static $arrayOperators   = [ "=", "==", "===", "!=", "!==" ];
    protected static $customOperators  = [ ];


    /**
     * Manager constructor.
     */
    public function __construct() {
        if (!class_exists(static::$manages))
            throw new ManagerException("Unable to create ".get_class($this)." because the managed class '".static::$manages."' does not exist.");
        if (!is_subclass_of(static::$manages, "Utsubot\\Manageable"))
            throw new ManagerException("Unable to create ".get_class($this)." because the managed class '".static::$manages."' is not Manageable.");
    }


    /**
     * Add a new item to the manager
     *
     * @param Manageable $item
     * @param bool       $unique True to prevent duplicate values from begin added
     * @return int Index of the new item, or -1 if item already exists
     * @throws ManagerException If item can not be managed by this manager type
     */
    public function addItem(Manageable $item, $unique = false): int {
        $keys  = array_keys($this->collection);
        $index = end($keys) + 1;

        return $this->setIndex($item, $index, $unique);
    }


    /**
     * Remove an item from the manager
     *
     * @param Manageable $item
     * @return int The key of the removed item
     * @throws ManagerException If item is not in the collection
     */
    public function removeItem(Manageable $item): int {
        if (($key = array_search($item, $this->collection, true)) !== false) {
            unset($this->collection[ $key ]);

            return $key;
        }

        throw new ManagerException("Item not found for removal in ".get_class($this).".");
    }


    /**
     * Add a new item to the manager and specify an index
     *
     * @param Manageable $item
     * @param int        $index  Array index for item to be added at (existing values will be overwritten)
     * @param bool       $unique True to prevent duplicate values from begin added
     * @return int Index of the new item, or -1 if item already exists (in unique mode)
     * @throws ManagerException If item can not be managed by this manager type
     */
    public function setIndex(Manageable $item, int $index, $unique = false): int {
        if (!($item instanceof static::$manages))
            throw new ManagerException("Unable to add item to ".get_class($this)." because it is not an instance of ".static::$manages.".");

        if (!$unique || !in_array($item, $this->collection, true)) {
            $this->collection[ $index ] = $item;

            return $index;
        }

        return -1;
    }


    /**
     * Re-index the collection
     */
    public function normalize() {
        $this->collection = array_values($this->collection);
    }


    /**
     * @return array All objects saved in this manager
     */
    public function collection() {
        return $this->collection;
    }


    /**
     * Get the item stored at the specified index
     *
     * @param int $index
     * @return Manageable
     * @throws ManagerException Item not found
     */
    public function get(int $index): Manageable {
        if (!isset($this->collection[ $index ]))
            throw new ManagerException("No items stored at index $index in ".get_class($this).".");

        return $this->collection[ $index ];
    }


    /**
     * Search for an item by comparing to the implemented Manageable search
     *
     * @param mixed $search
     * @return Manageable
     * @throws ManagerException No results
     */
    public function search($search): Manageable {
        $filter = new ManagerFilter(new \ArrayIterator($this->collection), $search);
        //  Return first item
        foreach ($filter as $item)
            return $item;

        throw new ManagerException("No results found for item $search in ".get_class($this).".");
    }


    /**
     * Search for all valid items using Manageable search
     *
     * @param $search
     * @return array
     * @throws ManagerException No results
     */
    public function searchAll($search): array {
        $filter  = new ManagerFilter(new \ArrayIterator($this->collection), $search);
        $results = [ ];

        foreach ($filter as $item)
            $results[] = $item;

        if (empty($results))
            throw new ManagerException("No results found for item $search in ".get_class($this).".");

        return $results;
    }


    /**
     * Search for a single or many items through a variety of criteria
     *
     * @param array $criteria An array of ManagerSearchCriterion
     * @param bool  $all      (Optional) Pass false to return only first result, rather than array of results. Default
     *                        true
     * @param bool  $strict   (Optional) Pass false to return results that match any criteria, rather than all
     *                        criteria. Default true
     * @return array|Object Return the resulting array of objects, a single object, or false on failure
     * @throws ManagerException
     */
    public function fullSearch($criteria, $all = true, $strict = true) {
        //	Criteria should be array of arrays
        if (!is_array($criteria))
            throw new ManagerException("Criteria must be given in an array.");

        $return = [ ];
        //	Check each criterion
        foreach ($criteria as $criterion) {
            if (!($criterion instanceof ManagerSearchCriterion))
                throw new ManagerException("Criteria must be instances of ManagerSearchCriterion.");

            if ($criterion->getManager() != $this)
                throw new ManagerException("Criteria was generated for the wrong Manager.");

            $field    = $criterion->getField();
            $operator = $criterion->getOperator();
            $value    = $criterion->getValue();

            $searchObject = $criterion->getSearchObject();
            $operators    = $searchObject->getOperators();
            $method       = $searchObject->getMethod();
            $parameters   = $searchObject->getParameters();

            //	Check all objects
            foreach ($this->collection as $object) {

                if ($operators == static::$customOperators)
                    $testResult = $this->customComparison($object, $field, $operator, $value);

                else {

                    if ($operator == "=")
                        $operator = "==";

                    //	Call saved method to retrieve value for comparison
                    $result = call_user_func_array([ $object, $method ], $parameters);
                    if (is_string($result))
                        $result = strtolower($result);

                    //	Use eval() to perform comparison and return true or false
                    $testResult = false;
                    //	String comparison
                    if ($operators == self::$stringOperators) {
                        //	"*=" operator as an interface for wildcard matching
                        if ($operator == "*=") {
                            //	Apply wildcards if none exists
                            if (!preg_match("/[?*\\[]/", $value))
                                $value = "*$value*";

                            $testResult = fnmatch($value, $result);
                        }

                        //	Or, regular comparison with escaped strings
                        else {
                            $testResult = ($result == $value);
                            if ($operator == "!=")
                                $testResult = !$testResult;
                        }
                    }

                    //	Numeric comparison
                    elseif ($operators == self::$numericOperators)
                        $testResult = eval("return $result $operator $value;");

                    //	Array comparison
                    elseif ($operators == self::$arrayOperators) {
                        switch ($operator) {
                            case "==":
                                $testResult = ($result == $value);
                                break;
                            case "!=":
                                $testResult = ($result != $value);
                                break;
                            case "===":
                                $testResult = ($result === $value);
                                break;
                            case "!==":
                                $testResult = ($result !== $value);
                                break;
                        }
                    }

                }
                if ($testResult)
                    $return[] = $object;

            }
        }

        $required = count($criteria);
        $matched  = [ ];
        foreach ($return as $object) {
            //	List of all keys containing that exact object, to determine number of appearances
            $keys = array_keys($return, $object, true);

            //	If strict mode is enabled, check number of matched criteria vs. total number
            if (!$strict || count($keys) == $required) {
                $matched[] = $object;
                //	Remove all instance of $object from $return to avoid duplicate processing
                $return = array_filter($return,
                    function ($element) use ($object) {
                        return $element !== $object;
                    });
            }
        }
        $return = $matched;

        //	Return first result if "all" disabled
        if (!$all)
            return $return[ 0 ];

        //	Return all results
        return $return;
    }


    /**
     * Given a $field to search against, this function should return info on how to get the field
     *
     * @param string $field The name of an aspect of one of this manager's collection
     * @param string $operator
     * @param string $value The value being searched against, if relevant
     * @return ManagerSearchObject
     */
    abstract public function searchFields($field, $operator = "", $value = "");


    /**
     * Given a member of the collection, the field to search against, the operator to evaluate, and the value to
     * compare against, perform a comparison
     *
     * @param mixed $object   A member of this manager's collection
     * @param mixed $field    Field name
     * @param mixed $operator Custom operator
     * @param mixed $value    Value to compare against
     * @return bool True or false depending on comparison result
     */
    abstract protected function customComparison($object, $field, $operator, $value);


    /**
     * @return string
     */
    public static function getManages() {
        return static::$manages;
    }


    /**
     * @return array
     */
    public static function getArrayOperators() {
        return self::$arrayOperators;
    }


    /**
     * @return array
     */
    public static function getNumericOperators() {
        return self::$numericOperators;
    }


    /**
     * @return array
     */
    public static function getStringOperators() {
        return self::$stringOperators;
    }


    /**
     * @return array
     */
    public static function getCustomOperators() {
        return static::$customOperators;
    }
}
<?php
/**
 * MEGASSBOT - Manager.php
 * User: Benjamin
 * Date: 06/11/14
 */

class ManagerException extends Exception {}

abstract class Manager {
	//	Holds the manager's collection of objects
	protected $collection = array();

	protected static $manages = "";
	protected static $managesNamespace = "";

	protected static $numericOperators = array("=", "==", "!=", "<", "<=", ">", ">=");
	protected static $stringOperators = array("=", "==", "!=", "*=");
	protected static $arrayOperators = array("=", "==", "===", "!=", "!==");
	protected static $customOperators = array();

	public function __construct() {}

	public function addItem($item, bool $unique = false): bool {
		if (!$unique || !in_array($item, $this->collection, true)) {
			$this->collection[] = $item;
			$keys = array_keys($this->collection);
			return end($keys);
		}

		return -1;
	}

	public function removeItem($item) {
		if (($key = array_search($item, $this->collection, true)) !== false) {
			unset($this->collection[$key]);
			return $key;
		}

		return false;
	}

	public function normalizeItems() {
		$this->collection = array_values($this->collection);
	}

	/**
	 * Search for a single or many of this manager's collection through an identifier
	 *
	 * @param string|int $search An identifier to search for (usu. name or id#)
	 * @param bool $all (Optional) Pass true to search for all matching items. Default false returns only first object found
	 * @return Object|array|bool The found object or an array of all found objects will returned on search success, or false on failure
	 */
	public function get($search, $all = false) {
		$ret = array();

		//	Check for id search to avoid looping if possible
		if (is_numeric($search) && !$all && isset($this->collection[intval($search)]))
			return $this->collection[$search];

		//	No search specified, return random item
		elseif ($search === null || $search === false || $search === "")
			return $this->collection[array_rand($this->collection)];

		//	Perform search on all objects
		foreach ($this->collection as $item) {
			if (method_exists($item, "search") && $item->search($search)) {
				//	Not returning all, return first result
				if (!$all)
					return $item;

				$ret[] = $item;
			}
		}

		//	Return collection of results. If $ret has results, $all must be true to reach this point
		if ($ret)
			return $ret;

		//	No results
		return false;
	}

	/**
	 * @return array All objects saved in this manager
	 */
	public function collection() {
		return $this->collection;
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
	 * Given a member of the collection, the field to search against, the operator to evaluate, and the value to compare against, perform a comparison
	 *
	 * @param Object $object A member of this manager's collection
	 * @param mixed $field Field name
	 * @param mixed $operator Custom operator
	 * @param mixed $value Value to compare against
	 * @return bool True or false depending on comparison result
	 */
	protected function customComparison($object, $field, $operator, $value) {
		return false;
	}

	/**
	 * Search for a single or many pokemon through a variety of criteria
	 *
	 * @param array $criteria An array of ManagerSearchCriterion
	 * @param bool $all (Optional) Pass false to return only first result, rather than array of results. Default true
	 * @param bool $strict (Optional) Pass false to return results that match any criteria, rather than all criteria. Default true
	 * @return array|Object Return the resulting array of objects, a single object, or false on failure
	 * @throws ManagerException
	 */
	public function search($criteria, $all = true, $strict = true) {
		//	Criteria should be array of arrays
		if (!is_array($criteria))
			throw new ManagerException("Criteria must be given in an array.");

		$return = array();
		//	Check each criterion
		foreach ($criteria as $criterion) {
			if (!($criterion instanceof ManagerSearchCriterion))
				throw new ManagerException("Criteria must be instances of ManagerSearchCriterion.");

			if ($criterion->getManager() != $this)
				throw new ManagerException("Criteria was generated for the wrong Manager.");

			$field = $criterion->getField();
			$operator = $criterion->getOperator();
			$value = $criterion->getValue();

			$searchObject = $criterion->getSearchObject();
			$operators = $searchObject->getOperators();
			$method = $searchObject->getMethod();
			$parameters = $searchObject->getParameters();

			//	Check all objects
			foreach ($this->collection as $object) {

				if ($operators == static::$customOperators)
					$testResult = $this->customComparison($object, $field, $operator, $value);

				else {

					if ($operator == "=")
						$operator = "==";

					//	Call saved method to retrieve value for comparison
					$result = call_user_func_array(array($object, $method), $parameters);
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
							case "==":	$testResult = ($result == $value);	break;
							case "!=":	$testResult = ($result != $value);	break;
							case "===":	$testResult = ($result === $value);	break;
							case "!==":	$testResult = ($result !== $value);	break;
						}
					}

				}
				if ($testResult)
					$return[] = $object;

			}
		}


		$required = count($criteria);
		$matched = array();
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
			return $return[0];

		//	Return all results
		return $return;
	}

	/**
	 * @return string
	 */
	public static function getManages() {
		return static::$manages;
	}

	/**
	 * @return string
	 */
	public static function getManagesNamespace() {
		return static::$managesNamespace;
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

class ManagerSearchObject {
	private $manager;
	private $method;
	private $parameters;
	private $operators;

	public function __construct(Manager $manager, $method, $parameters, $operators) {
		$this->manager = $manager;

		$className = $this->manager->getManages();
		$namespace = $this->manager->getManagesNamespace();
		if ($namespace)
			$className = "$namespace\\$className";

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

class ManagerSearchCriterion {
	private $manager;
	private $field;
	private $operator;
	private $value;
	private $searchObject;

	public function __construct(Manager $manager, $field, $operator, $value) {
		$this->manager = $manager;
		$this->searchObject = $manager->searchFields($field, $operator, $value);
		if (!($this->searchObject instanceof \ManagerSearchObject))
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
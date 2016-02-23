<?php
/**
 * Utsubot - ManagerWithDatabase.php
 * User: Benjamin
 * Date: 19/11/14
 */

abstract class ManagerWithDatabase extends Manager {

	protected $interface;

	/**
	 * Create the Manager and save the database interface for later use
	 *
	 * @param DatabaseInterface $interface An instance of a class extending DatabaseInteface
	 */
	public function __construct(DatabaseInterface $interface) {
		$this->interface = $interface;
	}

	/**
	 * Load objects from the database into the collection, if needed
	 *
	 * @param null|int|array $item The id of a the collection item to load, an array of ids, or null to load all available items form the database
	 * @throws ManagerException If $this->manages does not resolve to a class, or the interface doesn't have a get function for $this->manages
	 */
	public function load($item = null) {
		//	Mandate definition of class in $this->manages (include namespace), and make sure the database interface has the method to retrieve the relevant information
		$class = (static::$managesNamespace) ? static::$managesNamespace . "\\". static::$manages : static::$manages;
		if (!class_exists($class))
			throw new ManagerException(get_class($this)."::load: $class is not a defined class.");
		elseif (!method_exists($this->interface, "get".static::$manages))
			throw new ManagerException(get_class($this)."::load: Interface does not have a get". static::$manages. " method.");

		$load = array();
		$method = "get". static::$manages;
		//	Load a single object
		if (is_int($item))
			$load = $this->interface->{$method}($item);

		//	Load a set of objects
		elseif (is_array($item)) {
			foreach ($item as $id) {
				if (is_int($id))
					$load[$id] = $this->interface->{$method}($id);
			}
		}

		//	Load all available objects
		elseif (!$item)
			$load = $this->interface->{$method}();

		//	Create and save objects
		foreach ($load as $id => $array)
			$this->collection[$id] = new $class($array);
	}

}
<?php
/**
 * MEGASSBOT - Manager.php
 * User: Benjamin
 * Date: 06/11/14
 */

namespace Utsubot\Manager;


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

    //  Holds the manager's collection of objects
    protected $collection = [ ];

    protected static $manages = "";

    /**
     * Manager constructor.
     */
    public function __construct() {
        if (!class_exists(static::$manages))
            throw new ManagerException("Unable to create ".get_class($this)." because the managed class '".static::$manages."' does not exist.");
        if (!is_subclass_of(static::$manages, "Utsubot\\Manager\\Manageable"))
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
     * Search for the first matching item by comparing to the implemented Manageable search
     *
     * @param mixed $search
     * @return Manageable
     * @throws ManagerException No results
     */
    public function findFirst($search): Manageable {
        $filter = new BasicFilter(new \ArrayIterator($this->collection), $search);
        //  Return first item
        foreach ($filter as $item)
            return $item;

        throw new ManagerException("No results found for item $search in ".get_class($this).".");
    }


    /**
     * Search for all matching items by comparing to the implemented Manageable search
     *
     * @param $search
     * @return array
     * @throws ManagerException No results
     */
    public function basicSearch($search): array {
        $filter  = new BasicFilter(new \ArrayIterator($this->collection), $search);
        $results = [ ];

        foreach ($filter as $item)
            $results[] = $item;

        if (empty($results))
            throw new ManagerException("No results found for item $search in ".get_class($this).".");

        return $results;
    }


    /**
     * @param SearchCriteria $criteria
     * @param SearchMode     $searchMode
     * @param int            $numberOfResults
     * @return array
     * @throws ManagerException
     */
    public function advancedSearch(SearchCriteria $criteria, SearchMode $searchMode, int $numberOfResults = 0): array {
        $filter  = new AdvancedFilter(new \ArrayIterator($this->collection), $criteria, $searchMode);
        $results = [ ];

        foreach ($filter as $item) {
            $results[] = $item;

            //  Stop searching early because we have enough results
            if ($numberOfResults && count($results) >= $numberOfResults)
                break;
        }

        if (empty($results))
            throw new ManagerException("No results found for the given criteria in ".get_class($this).".");

        return $results;
    }


    /**
     * @return string
     */
    public static function getManages() {
        return static::$manages;
    }

}
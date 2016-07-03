<?php
/**
 * Utsubot - ManagerWithDatabase.php
 * User: Benjamin
 * Date: 19/11/14
 */

namespace Utsubot\Pokemon;


use Utsubot\Manager\{
    Manager,
    ManagerException
};


/**
 * Class PokemonManagerBaseException
 *
 * @package Utsubot\Pokemon
 */
class PokemonManagerBaseException extends \Exception {

}


/**
 * Class PokemonManagerBase
 *
 * @package Utsubot\Pokemon
 */
abstract class PokemonManagerBase extends Manager {

    /** @var PokemonObjectPopulator[] $populators */
    protected $populators;
    protected $populatorCollections = [ ];

    protected static $populatorMethod;


    /**
     * PokemonManagerBase constructor.
     *
     * @throws PokemonManagerBaseException
     */
    public function __construct() {
        if (!strlen(static::$populatorMethod))
            throw new PokemonManagerBaseException("Populator method not configured for class '".get_class($this)."'.");

        parent::__construct();
    }


    /**
     * Add a new interface to load items from
     *
     * @param PokemonObjectPopulator $populator
     * @return int The assigned index of the added interface
     * @throws PokemonManagerBaseException
     */
    public function addPopulator(PokemonObjectPopulator $populator): int {
        if (!method_exists($populator, static::$populatorMethod))
            throw new PokemonManagerBaseException(
                "The populator supplied to '".get_class($this).
                "' does not support the populator method '".static::$populatorMethod."'.");

        $this->populators[] = $populator;

        return count($this->populators) - 1;
    }


    /**
     * Begin population from one or all added sources
     *
     * @param int|null $index
     * @throws PokemonManagerBaseException
     */
    public function populate(int $index = null) {
        //  Populate for single index
        if (is_int($index)) {
            if (!isset($this->populators[ $index ]))
                throw new PokemonManagerBaseException("There is no PokemonObjectPopulator set at index $index.");

            $this->doPopulate($index);
        }

        //  Null passed, populate all indexes
        else {
            for ($i = 0, $numberOfPopulators = count($this->populators); $i < $numberOfPopulators; $i++)
                $this->doPopulate($i);
        }

        //  Update main manager collection with composite array
        $collection = [ ];
        foreach ($this->populatorCollections as $populatorCollection)
            //  Array addition to preserve indexes
            $collection = $collection + $populatorCollection;

        $this->collection = $collection;
    }


    /**
     * Populator helper method
     *
     * @param int $index
     * @throws PokemonManagerBaseException
     */
    protected function doPopulate(int $index) {
        $collection = call_user_func([ $this->populators[ $index ], static::$populatorMethod ]);

        //  Convert ArrayObjects to just data
        if ($collection instanceof \ArrayObject)
            $collection = $collection->getArrayCopy();

        //  Collection must be an array
        if (!is_array($collection))
            throw new PokemonManagerBaseException("Populator method '".static::$populatorMethod."' did not return an array.");

        $this->populatorCollections[ $index ] = $collection;
    }


    /**
     * Search for items in the collection with typo forgiveness using Jaro-Winkler distance
     *
     * @param string   $search
     * @param Language $language
     * @return array
     * @throws ManagerException
     */
    public function jaroSearch(string $search, Language $language): array {
        $filter = new JaroFilter(new \ArrayIterator($this->collection), $search, $language);

        $return = [ ];
        foreach ($filter as $item)
            $return[] = $item;

        if (!$return)
            throw new ManagerException("No results found for item $search in ".get_class($this).".");

        return $return;
    }


    /**
     * Translate the name of a search field for an end user to a method name with parameters to grab the relevant data
     * from the Manager's objects
     * 
     * @param string $field
     * @return MethodInfo
     */
    abstract public function getMethodFor(string $field): MethodInfo;

}
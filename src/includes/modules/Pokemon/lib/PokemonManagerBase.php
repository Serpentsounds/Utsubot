<?php
/**
 * Utsubot - ManagerWithDatabase.php
 * User: Benjamin
 * Date: 19/11/14
 */

namespace Utsubot\Pokemon;

use Utsubot\{
    Manager,
    ManagerException,
    ManagerFilter
};


/**
 * Class JaroFilter
 *
 * @package Utsubot\Pokemon
 */
class JaroFilter extends ManagerFilter {

    const MINIMUM_SIMILARITY = 0.80;

    protected $search;
    protected $language;


    /**
     * JaroFilter constructor.
     *
     * @param \Iterator $iterator
     * @param mixed     $search
     * @param Language  $language
     */
    public function __construct(\Iterator $iterator, $search, Language $language) {
        parent::__construct($iterator, $search);

        $this->language = $language;
    }


    /**
     * @return bool
     */
    public function accept(): bool {
        $obj = $this->current();
        if ($obj instanceof PokemonBase)
            return self::MINIMUM_SIMILARITY <= $obj->jaroSearch($this->search, $this->language);

        return false;
    }
}

/**
 * Class PokemonManagerBase
 *
 * @package Utsubot\Pokemon
 */
abstract class PokemonManagerBase extends Manager {

    /** @var VeekunDatabaseInterface $interface */
    protected $interface;


    /**
     * Create the Manager and save the database interface for later use
     *
     * @param VeekunDatabaseInterface $interface An instance of a class extending DatabaseInteface
     */
    public function __construct(VeekunDatabaseInterface $interface) {
        parent::__construct();
        $this->interface = $interface;
    }


    /**
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


    abstract public function load();


    /**
     * Load objects from the database into the collection, if needed
     *
     * @param null|int|array $item The id of a the collection item to load, an array of ids, or null to load all
     *                             available items form the database
     * @throws ManagerException If $this->manages does not resolve to a class, or the interface doesn't have a get
     *                          function for $this->manages
     */
    public function oldload($item = null) {
        //	Mandate definition of class in $this->manages (include namespace), and make sure the database interface has the method to retrieve the relevant information
        $class     = (strpos(static::$manages, "\\") !== false) ? substr(strrchr(static::$manages, "\\"), 1) : static::$manages;
        $fullClass = static::$manages;
        if (!class_exists($fullClass))
            throw new ManagerException("(".get_class($this).") $class is not a defined class.");
        elseif (!method_exists($this->interface, "get$class"))
            throw new ManagerException("(".get_class($this).") Interface does not have a get$class method.");

        $load   = [ ];
        $method = "get$class";
        //	Load a single object
        if (is_int($item))
            $load = $this->interface->{$method}($item);

        //	Load a set of objects
        elseif (is_array($item)) {
            foreach ($item as $id) {
                if (is_int($id))
                    $load[ $id ] = $this->interface->{$method}($id);
            }
        }

        //	Load all available objects
        elseif (!$item)
            $load = $this->interface->{$method}();

        //	Create and save objects
        foreach ($load as $id => $array)
            $this->collection[ $id ] = new $fullClass($array);
    }

}
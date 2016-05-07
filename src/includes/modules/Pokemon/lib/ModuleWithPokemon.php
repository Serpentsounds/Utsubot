<?php
/**
 * ModuleWithPokemon is a base class for sub-Modules of the Pokemon suite to extend
 * It provides safe access to other Managers from other loaded Modules in the pokemon suite
 *
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon;
use Utsubot\Permission\ModuleWithPermission;
use Utsubot\Help\{
    IHelp,
    THelp
};
use Utsubot\{
    ModuleException,
    ManagerException
};
use function Utsubot\bold;

/**
 * Class ModuleWithPokemonException
 *
 * @package Utsubot\Pokemon
 */
class ModuleWithPokemonException extends ModuleException {}


/**
 * Class ModuleWithPokemon
 *
 * @package Utsubot\Pokemon
 */
abstract class ModuleWithPokemon extends ModuleWithPermission implements IHelp {
    
    use THelp;

    private static $managers;
    
    /** @var PokemonManagerBase $manager */
    private $manager;

    /**
     * Register a Manager for this class under a given name
     *
     * @param string $name
     * @param PokemonManagerBase $manager
     * @throws \Utsubot\ManagerException
     */
    protected final function registerManager(string $name, PokemonManagerBase $manager) {
        $this->manager = $manager;
        self::$managers[$name] = &$this->manager;
    }

    /**
     * Get this object's saved Manager
     * 
     * @return PokemonManagerBase
     * @throws ModuleWithPokemonException
     */
    public function getManager(): PokemonManagerBase {
        if ($this->manager instanceof PokemonManagerBase)
            return $this->manager;
        
        throw new ModuleWithPokemonException("A manager has not been loaded for ". get_class($this). ".");
    }

    /**
     * Get a registered Manager from another ModuleWithPokemon
     * 
     * @param string $manager
     * @return PokemonManagerBase
     * @throws ModuleWithPokemonException Unregistered Manager
     */
    protected final function getOutsideManager(string $manager): PokemonManagerBase {
        if (!isset(self::$managers[$manager]))
            throw new ModuleWithPokemonException("Pokemon suite Manager '$manager' has not been registered by any Modules.");

        return self::$managers[$manager];
    }

    /**
     * Pursue all search routes to get an item of this object's manager from user input
     *
     * @param string $parameterString
     * @param bool $allowSpellcheck
     * @return PokemonObjectResult
     * @throws ModuleWithPokemonException
     */
    protected function getObject(string $parameterString, bool $allowSpellcheck = true): PokemonObjectResult {
        $result = new PokemonObjectResult();
        $firstWord = explode(" ", $parameterString)[0];

        //  Number of search modes to check
        $maxMode = ($allowSpellcheck) ? 3 : 1;
        
        //  Set up loop to try multiple fetch angles while catching exceptions for no results
        for ($mode = 0; $mode <= $maxMode; $mode++) {
            try {
                switch ($mode) {
                    //  Attempt to grab item at an index
                    case 0:
                        //  Make sure int was specified
                        if (is_numeric($firstWord) && intval($firstWord) == $firstWord) {
                            /** @var PokemonBase $item */
                            $item = $this->getManager()->get(intval($firstWord));
                            $result->addItem($item);
                        }
                        //  Throw control to catch block to stay in loop
                        else
                            throw new ManagerException();
                        break;

                    //  Attempt to match string to the name of a Pokemon
                    case 1:
                        /** @var PokemonBase $item */
                        $item = $this->getManager()->search($parameterString);
                        $result->addItem($item);
                        break;

                    //  Attempt to spell check parameters vs. English names of Pokemon
                    case 2:
                        $result->addItems($this->getManager()->jaroSearch($parameterString, new Language(Language::English)));
                        $result->jaroSort();
                        break;

                    //  Attempt to spell check parameters vs. names of Pokemon in all languages
                    case 3:
                        $result->addItems($this->getManager()->jaroSearch($parameterString, new Language(Language::All)));
                        $result->jaroSort();
                        break;

                }

                //  Switch exited successfully, item data should be populated
                break;
            }
            
            //  Item lookup failed, try next mode
            catch (ManagerException $e) {}
        }

        //  Still no results, end with error
        if (!$result->itemCount())
            throw new ModuleWithPokemonException("No items matching '$parameterString' were found.");

        return $result;
    }
    
}

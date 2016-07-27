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
use Utsubot\Manager\ManagerException;
use Utsubot\Util\UtilException;
use Utsubot\ModuleException;
use function Utsubot\bold;
use function Utsubot\Util\checkInt;


/**
 * Class ModuleWithPokemonException
 *
 * @package Utsubot\Pokemon
 */
class ModuleWithPokemonException extends ModuleException {

}


/**
 * Class ModuleWithPokemon
 *
 * @package Utsubot\Pokemon
 */
abstract class ModuleWithPokemon extends ModuleWithPermission implements IHelp {

    use THelp;

    private static $managers;

    /** @var PokemonManagerBase $manager
     *  Private so subclass must registerManager */
    private $manager;


    /**
     * Register a Manager for this class under a given name
     *
     * @param string             $name
     * @param PokemonManagerBase $manager
     * @throws ManagerException
     */
    protected final function registerManager(string $name, PokemonManagerBase $manager) {
        $this->manager                       = $manager;
        self::$managers[ strtolower($name) ] = &$this->manager;
    }


    /**
     * Get a list of Managers that have been registered
     *
     * @return array
     */
    public function listManagers(): array {
        return array_keys(self::$managers);
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

        throw new ModuleWithPokemonException("A manager has not been loaded for ".get_class($this).".");
    }


    /**
     * Get a registered Manager from another ModuleWithPokemon
     *
     * @param string $manager
     * @return PokemonManagerBase
     * @throws ModuleWithPokemonException Unregistered Manager
     */
    protected final function getOutsideManager(string $manager): PokemonManagerBase {
        $manager = strtolower($manager);
        if (!isset(self::$managers[ $manager ]))
            throw new ModuleWithPokemonException("Pokemon suite Manager '$manager' has not been registered by any Modules.");

        return self::$managers[ $manager ];
    }


    /**
     * Pursue all search routes to get an item of this object's manager from user input
     *
     * @param string             $parameterString
     * @param bool               $allowSpellcheck
     * @param PokemonManagerBase $manager Optionally inject an external manager to use, rather than the one saved by
     *                                    the calling module
     * @return PokemonObjectResult
     * @throws ModuleWithPokemonException
     */
    protected function getObject(string $parameterString, bool $allowSpellcheck = true, PokemonManagerBase $manager = null): PokemonObjectResult {
        if ($manager === null)
            $manager = $this->getManager();

        $result    = new PokemonObjectResult();
        $firstWord = explode(" ", $parameterString)[ 0 ];

        //  Number of search modes to check
        $maxMode = ($allowSpellcheck) ? 3 : 1;

        //  Set up loop to try multiple fetch angles while catching exceptions for no results
        for ($mode = 0; $mode <= $maxMode; $mode++) {
            try {
                switch ($mode) {
                    //  Attempt to grab item at an index
                    case 0:
                        try {
                            //  Make sure int was specified
                            $search = checkInt($firstWord);
                            /** @var PokemonBase $item */
                            $item = $manager->get($search);
                            $result->append($item);
                        }
                            //  Throw control to catch block to stay in loop
                        catch (UtilException $e) {
                            throw new ManagerException();
                        }
                        break;

                    //  Attempt to match string to the name of a Pokemon
                    case 1:
                        /** @var PokemonBase $item */
                        $item = $manager->findFirst($parameterString);
                        $result->append($item);
                        break;

                    //  Attempt to spell check parameters vs. English names of Pokemon
                    case 2:
                        $result->addItems($manager->jaroSearch($parameterString, new Language(Language::English)));
                        $result->jaroSort();
                        break;

                    //  Attempt to spell check parameters vs. names of Pokemon in all languages
                    case 3:
                        $result->addItems($manager->jaroSearch($parameterString, new Language(Language::All)));
                        $result->jaroSort();
                        break;

                }

                //  Switch exited successfully, item data should be populated
                break;
            }

                //  Item lookup failed, try next mode
            catch (ManagerException $e) {
            }
        }

        //  Still no results, end with error
        if (!$result->count())
            throw new ModuleWithPokemonException("No items matching '$parameterString' were found.");

        return $result;
    }

}

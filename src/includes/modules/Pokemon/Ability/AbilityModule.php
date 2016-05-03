<?php
/**
 * Ability Module
 * Adds an ability lookup function to the Pokemon suite
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon\Ability;
use Utsubot\{
    IRCBot,
    IRCMessage,
    Trigger,
    ManagerSearchCriterion
};
use Utsubot\Pokemon\{
    ModuleWithPokemon,
    ModuleWithPokemonException,
    VeekunDatabaseInterface,
    Language
};
use function Utsubot\bold;


/**
 * Class AbilityModuleException
 *
 * @package Utsubot\Pokemon\Ability
 */
class AbilityModuleException extends ModuleWithPokemonException {}

/**
 * Class AbilityModule
 *
 * @package Utsubot\Pokemon\Ability
 */
class AbilityModule extends ModuleWithPokemon {

    /**
     * AbilityModule constructor.
     *
     * @param IRCBot $IRCBot
     */
    public function __construct(IRCBot $IRCBot) {
        parent::__construct($IRCBot);
        
        $abilityManager = new AbilityManager(new VeekunDatabaseInterface());
        $abilityManager->load();
        $this->registerManager("Ability", $abilityManager);


        $this->addTrigger(new Trigger("pability",   array($this, "ability")));
        $this->addTrigger(new Trigger("pabl",       array($this, "ability")));
    }

    /**
     * Ability lookup function
     *
     * @param IRCMessage $msg
     * @throws ModuleWithPokemonException Attempting to use PokemonManager without PokemonModule
     * @throws AbilityModuleException Invalid ability specified by user
     * @throws \Utsubot\ManagerException Error creating search criterion
     */
    public function ability(IRCMessage $msg) {
        //  Empty parameters
        $parameters = $msg->getCommandParameters();
        if (!count($parameters))
            throw new AbilityModuleException("No ability given.");

        //  Check if switches were applied
        $copy = $parameters;
        $switch = null;
        $firstWord = strtolower(array_shift($copy));
        //  Switch detected, save it and remove from parameters
        if (substr($firstWord, 0, 1) == "-") {
            $switch = substr($firstWord, 1);
            $parameters = $copy;
        }

        $result = $this->getObject(implode(" ",  $parameters));
        /** @var Ability $ability */
        $ability = $result->current();
        $abilityInfo = new AbilityInfoFormat($ability);
        $return = $abilityInfo->parseFormat();
        
        //  Parse switches
        switch ($switch) {
            //  Verbose mode, change format to be more descriptive
            case "v":
            case "verbose":
                $return = $abilityInfo->parseFormat(AbilityInfoFormat::getVerboseFormat());
                break;

            //  Pokemon mode, replace return with list the pokemon that can obtain the ability
            case "p":
            case "pokemon":
                $abilityName = $ability->getName(new Language(Language::English));

                //  Grab PokemonManager from the Pokemon Module. Exception if it's not loaded
                $pokemonManager = $this->getOutsideManager("Pokemon");
                $criteria = array(
                    new ManagerSearchCriterion($pokemonManager, "ability1", "==", $abilityName),
                    new ManagerSearchCriterion($pokemonManager, "ability2", "==", $abilityName),
                    new ManagerSearchCriterion($pokemonManager, "ability3", "==", $abilityName)
                );
                
                 // Perform a loose search to match any criteria
                $pokemon = $pokemonManager->fullSearch($criteria, true, false);                

                $return = sprintf(
                    "These pokemon can have %s: %s.",
                    bold($abilityName),
                    implode(", ", $pokemon)
                );
                break;
        }

        $this->respond($msg, $return);

        //  Output spell check suggestions if they are available
        if ($suggestions = $result->formatSuggestions())
            $this->respond($msg, $suggestions);
    }
    
}
<?php
/**
 * Ability Module
 * Adds an ability lookup function to the Pokemon suite
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon\Ability;


use Utsubot\Help\HelpEntry;
use Utsubot\Pokemon\{
    Gen7DatabaseInterface, ModuleWithPokemon, ModuleWithPokemonException, VeekunDatabaseInterface, Language
};
use Utsubot\Manager\{
    SearchCriteria, SearchCriterion, Operator, SearchMode
};
use Utsubot\{
    IRCBot,
    IRCMessage,
    Trigger
};
use function Utsubot\bold;


/**
 * Class AbilityModuleException
 *
 * @package Utsubot\Pokemon\Ability
 */
class AbilityModuleException extends ModuleWithPokemonException {

}


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

        //  Create and register manager with base module
        $abilityManager = new AbilityManager();
        $abilityManager->addPopulator(new VeekunDatabaseInterface());
        $abilityManager->addPopulator(new Gen7DatabaseInterface());
        $abilityManager->populate();

        $this->registerManager("Ability", $abilityManager);

        //  Command triggers
        $ability = new Trigger("pability", [ $this, "ability" ]);
        $ability->addAlias("pabl");
        $this->addTrigger($ability);

        //  Help entries
        $help = new HelpEntry("Pokemon", $ability);
        $help->addParameterTextPair("ABILITY", "Look up information about the Pokemon ability ABILITY.");
        $help->addParameterTextPair("-verbose ABILITY", "Look up information about the Pokemon ability ABILITY, with mechanics explained in-depth.");
        $help->addParameterTextPair("-pokemon ABILITY", "Look up a list of Pokemon who can have ABILITY.");
        $this->addHelp($help);
    }


    /**
     * Ability lookup function
     *
     * @param IRCMessage $msg
     * @throws ModuleWithPokemonException Attempting to use PokemonManager without PokemonModule
     * @throws AbilityModuleException Invalid ability specified by user
     * @throws \Utsubot\Manager\ManagerException Error creating search criterion
     */
    public function ability(IRCMessage $msg) {
        $this->requireParameters($msg, 1);

        //  Check if switches were applied
        $copy      = $parameters = $msg->getCommandParameters();
        $switch    = null;
        $firstWord = strtolower(array_shift($copy));
        //  Switch detected, save it and remove from parameters
        if (substr($firstWord, 0, 1) == "-") {
            $switch     = substr($firstWord, 1);
            $parameters = $copy;
        }

        $result = $this->getObject(implode(" ", $parameters));
        /** @var Ability $ability */
        $ability     = $result->offsetGet(0);
        $abilityInfo = new AbilityInfoFormat($ability);
        $return      = $abilityInfo->parseFormat();

        //  Parse switches
        switch ($switch) {
            //  Verbose mode, change format to be more descriptive
            case "v":
            case "verbose":
                $return = $abilityInfo->parseFormat(AbilityInfoFormat::Verbose_Format);
                break;

            //  Pokemon mode, replace return with list the pokemon that can obtain the ability
            case "p":
            case "pokemon":
                $abilityName = $ability->getName(new Language(Language::English));

                //  Grab PokemonManager from the Pokemon Module. Exception if it's not loaded
                $pokemonManager = $this->getOutsideManager("Pokemon");
                $criteria       = new SearchCriteria(
                    [
                        new SearchCriterion("getAbility", [ 0 ], new Operator("=="), $abilityName),
                        new SearchCriterion("getAbility", [ 1 ], new Operator("=="), $abilityName),
                        new SearchCriterion("getAbility", [ 2 ], new Operator("=="), $abilityName)
                    ]);

                // Perform a loose search to match any criteria
                $pokemon = $pokemonManager->advancedSearch($criteria, SearchMode::fromName("Any"));

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
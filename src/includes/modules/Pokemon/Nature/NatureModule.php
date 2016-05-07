<?php
/**
 * Utsubot - NatureModule.php
 * Date: 14/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon\Nature;
use Utsubot\Help\HelpEntry;
use Utsubot\Pokemon\{
    ModuleWithPokemon,
    ModuleWithPokemonException,
    VeekunDatabaseInterface,
    Stat
};
use Utsubot\{
    IRCBot,
    IRCMessage,
    Trigger,
    ManagerSearchCriterion
};
use function Utsubot\bold;


/**
 * Class NatureModuleException
 *
 * @package Utsubot\Pokemon\Nature
 */
class NatureModuleException extends ModuleWithPokemonException {}

/**
 * Class NatureModule
 *
 * @package Utsubot\Pokemon\Nature
 */
class NatureModule extends ModuleWithPokemon {

    /**
     * NatureModule constructor.
     *
     * @param IRCBot $IRCBot
     */
    public function __construct(IRCBot $IRCBot) {
        parent::__construct($IRCBot);

        //  Create and register manager with base module
        $natureManager = new NatureManager(new VeekunDatabaseInterface());
        $natureManager->load();
        $this->registerManager("Nature", $natureManager);

        //  Command triggers
        $nature = new Trigger("pnature", array($this, "nature"));
        $nature->addAlias("pnat");
        $this->addTrigger($nature);
        
        //  Help entries
        $help = new HelpEntry("Pokemon", $nature);
        $help->addParameterTextPair("NATURE",   "Look up information about the Pokemon nature NATURE.");
        $help->addParameterTextPair("STATS",    "Search for a nature using STATS, e.g., +atk -def.");
        $this->addHelp($help);
    }

    /**
     * @param IRCMessage $msg
     * @throws ModuleWithPokemonException
     * @throws NatureModuleException Invalid parameters
     * @throws \Utsubot\EnumException Invalid stat name
     * @throws \Utsubot\ManagerException No search results
     */
    public function nature(IRCMessage $msg) {
        $this->requireParameters($msg, 1);
       $parameters = $msg->getCommandParameters();

        //	Searching for nature given affected stats, need exactly 2 stats
        if (preg_match_all("/([+\-])([a-z]+)/i", $msg->getCommandParameterString(), $match, PREG_SET_ORDER) == 2) {

            //	Validate both stats
            $increases = $decreases = null;
            foreach ($match as $set) {
                $stat = new Stat(Stat::findValue($set[2]));

                if ($set[1] == "+")
                    $increases = Stat::findName($stat->getValue());
                elseif ($set[1] == "-")
                    $decreases = Stat::findName($stat->getValue());

            }

            if (!$increases || !$decreases)
                throw new NatureModuleException("An increased and decreased stat must both be given.");

            $criteria = array(
                new ManagerSearchCriterion($this->getManager(), "increases", "==", $increases),
                new ManagerSearchCriterion($this->getManager(), "decreases", "==", $decreases)
            );

            $nature = $this->getManager()->fullSearch($criteria, false);
            if (!($nature instanceof Nature))
                throw new NatureModuleException("Invalid nature.");
        }

        else
            $nature = $this->getObject($parameters[0])->current();

        $natureInfo = new NatureInfoFormat($nature);
        $this->respond($msg, $natureInfo->parseFormat());
    }

}
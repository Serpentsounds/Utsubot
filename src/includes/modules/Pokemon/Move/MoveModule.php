<?php
/**
 * Utsubot - MoveModule.php
 * Date: 20/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon\Move;
use Utsubot\Help\HelpEntry;
use Utsubot\Pokemon\{
    ModuleWithPokemon,
    ModuleWithPokemonException,
    VeekunDatabaseInterface
};
use Utsubot\{
    IRCBot,
    IRCMessage,
    Trigger
};


/**
 * Class MoveModuleException
 *
 * @package Utsubot\Pokemon\Move
 */
class MoveModuleException extends ModuleWithPokemonException {}

/**
 * Class MoveModule
 *
 * @package Utsubot\Pokemon\Move
 */
class MoveModule extends ModuleWithPokemon {

    /**
     * MoveModule constructor.
     *
     * @param IRCBot $IRCBot
     */
    public function __construct(IRCBot $IRCBot) {
        parent::__construct($IRCBot);

        //  Create and register manager with base module
        $moveManager = new MoveManager();
        $moveManager->addPopulator(new VeekunDatabaseInterface());
        $moveManager->populate();
        
        $this->registerManager("Move", $moveManager);

        //  Command triggers
        $move = new Trigger("pmove",      [$this, "move"]);
        $move->addAlias("pattack");
        $move->addAlias("patk");
        $this->addTrigger($move);

        //  Help entries
        $help = new HelpEntry("Pokemon", $move);
        $help->addParameterTextPair("MOVE",             "Look up information about the Pokemon move MOVE.");
        $help->addParameterTextPair("-verbose MOVE",    "Look up information about the Pokemon move MOVE, with mechanics explained in-depth.");
        $help->addParameterTextPair("-contest MOVE",    "Look up contest statistics about the Pokemon move MOVE.");
        $this->addHelp($help);
    }

    /**
     * Look up information about a move
     *
     * @param IRCMessage $msg
     * @throws ModuleWithPokemonException No item found
     * @throws MoveModuleException No parameters
     */
    public function move(IRCMessage $msg) {
        $this->requireParameters($msg, 1);

        //  Check if switches were applied
        $copy = $parameters = $msg->getCommandParameters();
        $switch = null;
        $firstWord = strtolower(array_shift($copy));
        //  Switch detected, save it and remove from parameters
        if (substr($firstWord, 0, 1) == "-") {
            $switch = substr($firstWord, 1);
            $parameters = $copy;
        }

        $result = $this->getObject(implode(" ",  $parameters));
        /** @var Move $move */
        $move = $result->offsetGet(0);
        $moveInfo = new MoveInfoFormat($move);
        $return = $moveInfo->parseFormat();

        //  Change output based on switches
        switch ($switch) {
            case "verbose":
                $return = $moveInfo->parseFormat(MoveInfoFormat::getVerboseFormat());
                break;
            case "contest":
                $return = $moveInfo->parseFormat(MoveInfoFormat::getContestformat());
                break;
        }

        $this->respond($msg, $return);

        //  Output spell check suggestions if they are available
        if ($suggestions = $result->formatSuggestions())
            $this->respond($msg, $suggestions);
    }
}
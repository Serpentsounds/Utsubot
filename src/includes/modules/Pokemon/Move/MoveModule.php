<?php
/**
 * Utsubot - MoveModule.php
 * Date: 20/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon\Move;
use Utsubot\{
    IRCBot,
    IRCMessage,
    Trigger
};
use Utsubot\Pokemon\{
    ModuleWithPokemon,
    ModuleWithPokemonException,
    VeekunDatabaseInterface
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

        $moveManager = new MoveManager(new VeekunDatabaseInterface());
        $moveManager->load();
        $this->registerManager("Move", $moveManager);

        $this->addTrigger(new Trigger("pmove",      array($this, "move")));
        $this->addTrigger(new Trigger("pattack",    array($this, "move")));
        $this->addTrigger(new Trigger("patk",       array($this, "move")));
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
        $move = $result->current();
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
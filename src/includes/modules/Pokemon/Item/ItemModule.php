<?php
/**
 * Utsubot - ItemModule.php
 * Date: 14/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon\Item;

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
use function Utsubot\bold;


/**
 * Class ItemModuleExceptions
 *
 * @package Utsubot\Pokemon\Item
 */
class ItemModuleException extends ModuleWithPokemonException {

}

/**
 * Class ItemModule
 *
 * @package Utsubot\Pokemon\Item
 */
class ItemModule extends ModuleWithPokemon {

    /**
     * ItemModule constructor.
     *
     * @param IRCBot $IRCBot
     */
    public function __construct(IRCBot $IRCBot) {
        parent::__construct($IRCBot);

        //  Create and register manager with base module
        $itemManager = new ItemManager();
        $itemManager->addPopulator(new VeekunDatabaseInterface());
        $itemManager->populate();
        
        $this->registerManager("Item", $itemManager);

        //  Command triggers
        $item = new Trigger("pitem", [ $this, "item" ]);
        $this->addTrigger($item);

        //  Help entries
        $help = new HelpEntry("Pokemon", $item);
        $help->addParameterTextPair("ITEM", "Look up information about the effect and location of the item ITEM.");
        $help->addParameterTextPair("-verbose ITEM", "Look up information about the effect and location of the item ITEM, with mechanics explained in-depth.");
        $this->addHelp($help);
    }


    /**
     * @param IRCMessage $msg
     * @throws ItemModuleException
     */
    public function item(IRCMessage $msg) {
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
        /** @var Item $item */
        $item     = $result->offsetGet(0);
        $itemInfo = new ItemInfoFormat($item);
        $return   = $itemInfo->parseFormat();

        //  Parse switches
        switch ($switch) {
            //  Verbose mode, change format to be more descriptive
            case "v":
            case "verbose":
                $return = $itemInfo->parseFormat(ItemInfoFormat::getVerboseFormat());
                break;
        }

        $this->respond($msg, $return);

        //  Output spell check suggestions if they are available
        if ($suggestions = $result->formatSuggestions())
            $this->respond($msg, $suggestions);
    }

}
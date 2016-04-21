<?php
/**
 * Utsubot - ItemModule.php
 * Date: 14/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon\Item;
use Utsubot\{
    IRCBot, IRCMessage, ManagerSearchCriterion
};
use Utsubot\Pokemon\{
    ModuleWithPokemon, ModuleWithPokemonException, VeekunDatabaseInterface
};
use function Utsubot\bold;


/**
 * Class ItemModuleExceptions
 *
 * @package Utsubot\Pokemon\Item
 */
class ItemModuleException extends ModuleWithPokemonException {}

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

        $itemManager = new ItemManager(new VeekunDatabaseInterface());
        $itemManager->load();
        $this->registerManager("Item", $itemManager);

        $this->triggers = array(
            'pitem' => "item"
        );
    }

    /**
     * @param IRCMessage $msg
     * @throws ItemModuleException
     */
    public function item(IRCMessage $msg) {
        $parameters = $msg->getCommandParameters();
        if (!count($parameters))
            throw new ItemModuleException("No item given.");

        $copy = $parameters;
        $switch = null;
        $firstWord = strtolower(array_shift($copy));
        //  Switch detected, save it and remove from parameters
        if (substr($firstWord, 0, 1) == "-") {
            $switch = substr($firstWord, 1);
            $parameters = $copy;
        }

        $result = $this->getObject(implode(" ", $parameters));
        /** @var Item $item */
        $item = $result->current();
        $itemInfo = new ItemInfoFormat($item);
        $return = $itemInfo->parseFormat();

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
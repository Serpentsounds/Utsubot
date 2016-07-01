<?php
/**
 * Utsubot - CommandCreator.php
 * User: Benjamin
 * Date: 01/02/2015
 */

namespace Utsubot\CommandCreator;

use Utsubot\Permission\ModuleWithPermission;
use Utsubot\Help\{
    HelpEntry,
    IHelp,
    THelp
};
use Utsubot\{
    IRCBot,
    IRCMessage,
    Trigger,
    ModuleException,
    DatabaseInterface,
    MySQLDatabaseCredentials
};


/**
 * Class CommandCreatorException
 *
 * @package Utsubot\CommandCreator
 */
class CommandCreatorException extends ModuleException {

}

/**
 * Class CommandCreator
 *
 * @package Utsubot\CommandCreator
 */
class CommandCreator extends ModuleWithPermission implements IHelp {

    use THelp;

    private $interface;
    private $customTriggers = [ ];


    /**
     * CommandCreator constructor.
     *
     * @param IRCBot $irc
     */
    public function __construct(IRCBot $irc) {
        $this->_require("Utsubot\\DatabaseInterface");
        $this->_require("Utsubot\\MySQLDatabaseCredentials");

        parent::__construct($irc);

        $this->interface = new DatabaseInterface(MySQLDatabaseCredentials::createFromConfig("utsubot"));
        $this->updateCustomTriggerCache();

        //  Command triggers
        $triggers = [ ];

        $triggers[ 'addcommand' ] = new Trigger("addcommand", [ $this, "addCommand" ]);
        $triggers[ 'addcommand' ]->addAlias("acommand");
        $triggers[ 'addcommand' ]->addAlias("acmd");

        $triggers[ 'removecommand' ] = new Trigger("removecommand", [ $this, "removeCommand" ]);
        $triggers[ 'removecommand' ]->addAlias("rcommand");
        $triggers[ 'removecommand' ]->addAlias("rcmd");

        $triggers[ 'editcommand' ] = new Trigger("editcommand", [ $this, "editCommand" ]);
        $triggers[ 'editcommand' ]->addAlias("ecommand");
        $triggers[ 'editcommand' ]->addAlias("ecmd");

        $triggers[ 'viewcommand' ] = new Trigger("viewcommand", [ $this, "viewCommand" ]);
        $triggers[ 'viewcommand' ]->addAlias("vcommand");
        $triggers[ 'viewcommand' ]->addAlias("vcmd");

        foreach ($triggers as $trigger)
            $this->addTrigger($trigger);

        //  Help entries
        $help     = [ ];
        $category = "Command Creator";

        $help[ 'addcommand' ] = new HelpEntry($category, $triggers[ 'addcommand' ]);
        $help[ 'addcommand' ]->addParameterTextPair(
            "NAME TYPE FORMAT",
            "Add a custom command with the identifier NAME. TYPE should be 'message' or 'action'. FORMAT may contain literal characters, and special escape sequences."
        );
        $help[ 'addcommand' ]->addNotes("%i, where i is a slot number, will fill in a random item from the list in slot i.");
        $help[ 'addcommand' ]->addNotes("%n inserts the calling user's nickname.");
        $help[ 'addcommand' ]->addNotes("%c inserts the channel the command is called on.");
        $help[ 'addcommand' ]->addNotes("%t inserts the time the command is called (12-hour + minutes + am/pm).");

        $help[ 'removecommand' ] = new HelpEntry($category, $triggers[ 'removecommand' ]);
        $help[ 'removecommand' ]->addParameterTextPair("NAME", "Permanently delete custom command NAME, deleting all saved triggers and list items.");

        $help[ 'editcommand' ] = new HelpEntry($category, $triggers[ 'editcommand' ]);
        $help[ 'editcommand' ]->addParameterTextPair("NAME type set TYPE", "Set a new type ('message' or 'action') for custom command NAME.");
        $help[ 'editcommand' ]->addParameterTextPair("NAME format set FORMAT", "Set a new format for custom command NAME. See help for addcommand for format information.");
        $help[ 'editcommand' ]->addParameterTextPair("NAME trigger [add|remove|clear] [TRIGGER]", "Add or remove TRIGGER as a trigger to custom command NAME, or clear all triggers.");
        $help[ 'editcommand' ]->addParameterTextPair(
            "NAME list [add|remove|clear] SLOT ITEM",
            "Add or remove ITEM as a random list item in slot SLOT for custom command NAME, or clear all list items for slot SLOT."
        );

        $help[ 'viewcommand' ] = new HelpEntry($category, $triggers[ 'viewcommand' ]);
        $help[ 'viewcommand' ]->addParameterTextPair("NAME type|format|trigger", "View the exisiting type, format, or triggers for custom command NAME.");
        $help[ 'viewcommand' ]->addParameterTextPair("NAME list SLOT", "View the existing items in list slot SLOT for custom command NAME.");

        /** @var HelpEntry $entry */
        foreach ($help as $entry) {
            $entry->addNotes("This command requires level 50.");
            $this->addHelp($entry);
        }
    }


    /**
     * @param IRCMessage $msg
     */
    public function privmsg(IRCMessage $msg) {
        parent::privmsg($msg);

        if ($msg->isCommand()) {

            foreach ($this->customTriggers as $commandID => $triggers) {
                if (array_search($msg->getCommand(), $triggers) !== false) {
                    try {
                        $this->triggerCommand($commandID, $msg);
                    }
                    catch (\Exception $e) {
                        $response = $this->parseException($e, $msg);
                        $this->respond($msg, $response);
                    }
                }

            }

        }
    }


    /**
     * @param IRCMessage $msg
     * @throws CommandCreatorException
     * @throws \Utsubot\Accounts\ModuleWithAccountsException
     */
    public function addCommand(IRCMessage $msg) {
        $this->requireLevel($msg, 50);

        $parameters = $msg->getCommandParameters();

        $command = array_shift($parameters);

        $type = strtolower(array_shift($parameters));
        if (!($type == "message" || $type == "action"))
            throw new CommandCreatorException("Invalid type '$type'.");

        $format = implode(" ", $parameters);
        if (!$format)
            throw new CommandCreatorException("Invalid format '$format'.");

        $this->createCommand($command, $type, $format);
        $this->respond($msg, "Command '$command' has been successfully created. Use !editcommand to begin creating lists and triggers for it.");
    }


    /**
     * @param IRCMessage $msg
     * @throws CommandCreatorException
     * @throws \Utsubot\Accounts\ModuleWithAccountsException
     */
    public function removeCommand(IRCMessage $msg) {
        $this->requireLevel($msg, 50);

        $command = $msg->getCommandParameters()[ 0 ];

        $this->destroyCommand($command);
        $this->respond($msg, "Command '$command' has been destroyed. All associated lists and triggers have been deleted.");
    }


    /**
     * @param IRCMessage $msg
     * @throws CommandCreatorException
     * @throws \Utsubot\Accounts\ModuleWithAccountsException
     */
    public function viewCommand(IRCMessage $msg) {
        $this->requireLevel($msg, 50);

        $parameters = $msg->getCommandParameters();
        $command    = array_shift($parameters);
        $property   = array_shift($parameters);

        switch ($property) {
            case "type":
                $type = $this->getType($command);
                $this->respond($msg, "Type of command '$command' is '$type'.");
                break;
            case "format":
                $format = $this->getFormat($command);
                $this->respond($msg, "Format of command '$command' is '$format'.");
                break;
            case "trigger":
            case "triggers":
                $triggers = $this->getCustomTriggers($command);
                $this->respond($msg, "Trigger(s) for '$command': ".implode(", ", $triggers));
                break;
            case "list":
                $slot = array_shift($parameters);
                if (!preg_match("/^[1-9]+[0-9]*$/", $slot))
                    throw new CommandCreatorException("Invalid slot number '$slot'.");

                $slot  = intval($slot);
                $items = $this->getListItems($command, $slot);
                $this->respond($msg, "Items in list slot $slot for command '$command': ".implode(", ", array_column($items, "value")));
                break;
            default:
                throw new CommandCreatorException("Invalid property '$property'.");
                break;
        }
    }


    /**
     * @param IRCMessage $msg
     * @throws CommandCreatorException
     * @throws \Utsubot\Accounts\ModuleWithAccountsException
     */
    public function editCommand(IRCMessage $msg) {
        $this->requireLevel($msg, 50);
        //	!editcommand bartender list add 1 beer
        //	!editcommand bartender trigger add !drink

        $parameters = $msg->getCommandParameters();
        list($command, $property, $mode) = $parameters;
        $parameters = array_slice($parameters, 3);

        switch ($property) {
            case "type":
                $type = strtolower(array_shift($parameters));
                if (!($type == "message" || $type == "action") && $mode == "set")
                    throw new CommandCreatorException("Invalid type '$type'.");

                switch ($mode) {
                    case "set":
                        $this->setType($command, $type);
                        $this->respond($msg, "Type of command '$command' has been changed to '$type'.");
                        break;
                    default:
                        throw new CommandCreatorException("Invalid mode '$mode' for property '$property'.");
                        break;
                }
                break;

            case "format":
                $format = implode(" ", $parameters);

                switch ($mode) {
                    case "set":
                        $this->setFormat($command, $format);
                        $this->respond($msg, "Format of command '$command' has been changed to '$format'.");
                        break;
                    default:
                        throw new CommandCreatorException("Invalid mode '$mode' for property '$property'.");
                        break;
                }
                break;

            case "trigger":
                $trigger = array_shift($parameters);
                switch ($mode) {
                    case "add":
                        $this->addCustomTrigger($command, $trigger);
                        $this->respond($msg, "Trigger '$trigger' has been added for command '$command'.");
                        break;
                    case "remove":
                        $this->removeCustomTrigger($command, $trigger);
                        $this->respond($msg, "Trigger '$trigger' has been removed from command '$command'.");
                        break;
                    case "clear":
                        $cleared = $this->clearCustomTriggers($command);
                        $this->respond($msg, "$cleared trigger(s) were removed from command '$command'.");
                        break;
                    default:
                        throw new CommandCreatorException("Invalid mode '$mode' for property '$property'.");
                        break;
                }
                break;

            case "list":
                $slot = array_shift($parameters);
                $item = implode(" ", $parameters);

                if (!preg_match("/^[1-9]+[0-9]*$/", $slot))
                    throw new CommandCreatorException("Invalid slot number '$slot'.");
                $slot = intval($slot);

                switch ($mode) {
                    case "add":
                        $this->addListItem($command, $item, $slot);
                        $this->respond($msg, "Item '$item' has been added to list slot $slot for command '$command'.");
                        break;
                    case "remove":
                        $this->removeListItem($command, $item, $slot);
                        $this->respond($msg, "Item '$item' has been removed from list slot $slot for command '$command'.");
                        break;
                    case "clear":
                        $cleared = $this->clearListItems($command, $slot);
                        $this->respond($msg, "$cleared item(s) were removed from list slot $slot on command '$command'.");
                        break;
                    default:
                        throw new CommandCreatorException("Invalid mode '$mode' for property '$property'.");
                        break;
                }
                break;
        }
    }


    /**
     *
     */
    public function updateCustomTriggerCache() {
        $results = $this->interface->query(
            "SELECT * FROM `custom_commands_triggers`",
            [ ]);

        $this->customTriggers = [ ];
        foreach ($results as $row)
            $this->customTriggers[ $row[ 'custom_commands_id' ] ][] = $row[ 'value' ];
    }


    /**
     * @param            $commandID
     * @param IRCMessage $msg
     * @throws CommandCreatorException
     */
    private function triggerCommand($commandID, IRCMessage $msg) {
        $commandInfo = $this->interface->query(
            "SELECT * FROM `custom_commands` WHERE `id`=? LIMIT 1",
            [ $commandID ]
        );

        if (!$commandInfo)
            throw new CommandCreatorException("Unable to retrieve format for command ID '$commandID'.");
        $commandInfo = $commandInfo[ 0 ];

        $results = $this->interface->query(
            "SELECT * FROM `custom_commands_parameters` WHERE `custom_commands_id`=?",
            [ $commandID ]
        );

        #if (!$results)
        #	throw new CommandCreatorException("There are no list items for command '{$commandInfo['name']}'.");

        $listItems = [ ];
        foreach ($results as $row)
            $listItems[ $row[ 'slot' ] ][] = $row[ 'value' ];

        $subParameters = function ($match) use ($commandID, $listItems, $msg) {
            if (is_numeric($match[ 1 ]) && isset($listItems[ $match[ 1 ] ]))
                return $listItems[ $match[ 1 ] ][ array_rand($listItems[ $match[ 1 ] ]) ];

            switch ($match[ 1 ]) {
                case "n":
                    return $msg->getNick();
                    break;
                case "c":
                    return $msg->getResponseTarget();
                    break;
                case "t":
                    return date("g:ia");
                    break;
            }

            return $match[ 1 ];
        };

        $output = preg_replace_callback("/%(\d+|[nct])/", $subParameters, $commandInfo[ 'format' ]);
        if ($commandInfo[ 'type' ] == "message")
            $this->respond($msg, $output);
        elseif ($commandInfo[ 'type' ] == "action")
            $this->IRCBot->action($msg->getResponseTarget(), $output);
    }


    /**
     * @param $command
     * @param $type
     * @param $format
     * @return bool
     * @throws CommandCreatorException
     */
    private function createCommand($command, $type, $format) {
        $rowCount = $this->interface->query(
            "INSERT INTO `custom_commands` (`name`, `type`, `format`) VALUES (?, ?, ?)",
            [ $command, $type, $format ]
        );

        if (!$rowCount)
            throw new CommandCreatorException("Command '$command' already exists.");

        return true;
    }


    /**
     * @param $command
     * @return bool
     * @throws CommandCreatorException
     */
    private function destroyCommand($command) {
        $rowCount = $this->interface->query(
            "DELETE FROM `custom_commands` WHERE `name`=? LIMIT 1",
            [ $command ]
        );

        if (!$rowCount)
            throw new CommandCreatorException("Command '$command' was not found.");

        $this->updateCustomTriggerCache();

        return true;
    }


    /**
     * @param $command
     * @return mixed
     * @throws CommandCreatorException
     */
    private function getCommandId($command) {
        $results = $this->interface->query(
            "SELECT `id` FROM `custom_commands` WHERE `name`=? LIMIT 1",
            [ $command ]
        );

        if (!$results)
            throw new CommandCreatorException("ID lookup for command '$command' failed.");

        return $results[ 0 ][ 'id' ];
    }


    /**
     * @param $command
     * @param $format
     * @return bool
     * @throws CommandCreatorException
     */
    private function setFormat($command, $format) {
        $id       = $this->getCommandId($command);
        $rowCount = $this->interface->query(
            "UPDATE `custom_commands` SET `format`=? WHERE `id`=? LIMIT 1",
            [ $format, $id ]
        );

        if (!$rowCount)
            throw new CommandCreatorException("Format '$format' of command '$command' unchanged.");

        return true;
    }


    /**
     * @param $command
     * @return mixed
     * @throws CommandCreatorException
     */
    private function getFormat($command) {
        $results = $this->interface->query(
            "SELECT `format` FROM `custom_commands` WHERE `name`=? LIMIT 1",
            [ $command ]
        );

        if (!$results)
            throw new CommandCreatorException("Command '$command' not found.");

        return $results[ 'format' ];
    }


    /**
     * @param $command
     * @param $type
     * @return bool
     * @throws CommandCreatorException
     */
    private function setType($command, $type) {
        $id       = $this->getCommandId($command);
        $rowCount = $this->interface->query(
            "UPDATE `custom_commands` SET `type`=? WHERE `id`=? LIMIT 1",
            [ $type, $id ]
        );

        if (!$rowCount)
            throw new CommandCreatorException("Type '$type' for command '$command' unchanged.");

        return true;
    }


    /**
     * @param $command
     * @return mixed
     * @throws CommandCreatorException
     */
    private function getType($command) {
        $results = $this->interface->query(
            "SELECT `type` FROM `custom_commands` WHERE `name`=? LIMIT 1",
            [ $command ]
        );

        if (!$results)
            throw new CommandCreatorException("Command '$command' not found.");

        return $results[ 'type' ];
    }


    /**
     * @param $command
     * @param $trigger
     * @return bool
     * @throws CommandCreatorException
     */
    private function addCustomTrigger($command, $trigger) {
        if (isset($this->getTriggers()[ strtolower($trigger) ]))
            throw new CommandCreatorException("Trigger '$trigger' is reserved for a CommandCreator command.");

        $id = $this->getCommandId($command);
        try {
            $this->interface->query(
                "INSERT INTO `custom_commands_triggers` (`custom_commands_id`, `value`) VALUES (?, ?)",
                [ $id, $trigger ]
            );

            $this->updateCustomTriggerCache();
        }
        catch (\PDOException $e) {
            throw new CommandCreatorException("Trigger '$trigger' for command '$command' already exists.");
        }

        return true;
    }


    /**
     * @param $command
     * @param $trigger
     * @return bool
     * @throws CommandCreatorException
     */
    private function removeCustomTrigger($command, $trigger) {
        $id       = $this->getCommandId($command);
        $rowCount = $this->interface->query(
            "DELETE FROM `custom_commands_triggers` WHERE `custom_commands_id`=? AND `value`=? LIMIT 1",
            [ $id, $trigger ]
        );

        if (!$rowCount)
            throw new CommandCreatorException("Trigger '$trigger' for command '$command' was not found.");

        $this->updateCustomTriggerCache();

        return true;
    }


    /**
     * @param $command
     * @return array|bool|int
     * @throws CommandCreatorException
     */
    private function clearCustomTriggers($command) {
        $id       = $this->getCommandId($command);
        $rowCount = $this->interface->query(
            "DELETE FROM `custom_commands_triggers` WHERE `custom_commands_id`=?",
            [ $id ]
        );

        if (!$rowCount)
            throw new CommandCreatorException("No triggers found for command '$command'.");

        $this->updateCustomTriggerCache();

        return $rowCount;
    }


    /**
     * @param $command
     * @return mixed
     * @throws CommandCreatorException
     */
    public function getCustomTriggers($command) {
        $commandID = $this->getCommandId($command);
        if (isset($this->customTriggers[ $commandID ]))
            $return = $this->customTriggers[ $commandID ];
        else
            throw new CommandCreatorException("No triggers found for command '$command'.");

        return $return;
    }


    /**
     * @param     $command
     * @param     $item
     * @param int $slot
     * @return bool
     * @throws CommandCreatorException
     */
    private function addListItem($command, $item, $slot = 1) {
        $id       = $this->getCommandId($command);
        $rowCount = $this->interface->query(
            "INSERT INTO `custom_commands_parameters` (`custom_commands_id`, `slot`, `value`) VALUES (?, ?, ?)",
            [ $id, $slot, $item ]
        );

        if (!$rowCount)
            throw new CommandCreatorException("An error occured trying to add '$item' to '$command' slot $slot.");

        return true;
    }


    /**
     * @param     $command
     * @param     $item
     * @param int $slot
     * @return bool
     * @throws CommandCreatorException
     */
    private function removeListItem($command, $item, $slot = 1) {
        $id       = $this->getCommandId($command);
        $rowCount = $this->interface->query(
            "DELETE FROM `custom_commands_parameters` WHERE `custom_commands_id`=? AND `slot`=? AND `value`=? LIMIT 1",
            [ $id, $slot, $item ]
        );

        if (!$rowCount)
            throw new CommandCreatorException("Item '$item' for command '$command' in slot $slot was not found.");

        return true;
    }


    /**
     * @param     $command
     * @param int $slot
     * @return array|bool|int
     * @throws CommandCreatorException
     */
    private function clearListItems($command, $slot = 1) {
        $id       = $this->getCommandId($command);
        $rowCount = $this->interface->query(
            "DELETE FROM `custom_commands_parameters` WHERE `custom_commands_id`=? AND `slot`=?",
            [ $id, $slot ]
        );

        if (!$rowCount)
            throw new CommandCreatorException("No list items found in slot $slot for command '$command'.");

        return $rowCount;
    }


    /**
     * @param     $command
     * @param int $slot
     * @return array|bool|int
     * @throws CommandCreatorException
     */
    private function getListItems($command, $slot = 1) {
        $id      = $this->getCommandId($command);
        $results = $this->interface->query(
            "SELECT `value` FROM `custom_commands_parameters` WHERE `custom_commands_id`=? AND `slot`=?",
            [ $id, $slot ]
        );

        if (!$results)
            throw new CommandCreatorException("No items found for command '$command' in slot $slot.");

        return $results;
    }

}
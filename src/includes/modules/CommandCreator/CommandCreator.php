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
    ModuleException
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
        parent::__construct($irc);

        $this->interface = new CommandCreatorDatabaseInterface();
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
     * Override to check for custom command triggers
     *
     * @param IRCMessage $msg
     */
    public function privmsg(IRCMessage $msg) {
        parent::privmsg($msg);

        if ($msg->isCommand()) {

            foreach ($this->customTriggers as $commandID => $triggers) {

                //  Trigger found
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
     * Register a new custom command entry
     * Usage: !addcommand <name> <type> <format>
     *
     * @param IRCMessage $msg
     * @throws CommandCreatorException
     * @throws \Utsubot\Accounts\ModuleWithAccountsException
     * @throws CommandCreatorDatabaseInterfaceException
     */
    public function addCommand(IRCMessage $msg) {
        $this->requireLevel($msg, 50);

        $parameters = $msg->getCommandParameters();

        $command = array_shift($parameters);

        $type = CommandType::fromName(array_shift($parameters));

        $format = implode(" ", $parameters);
        if (!$format)
            throw new CommandCreatorException("Format can not be empty.");

        $this->interface->createCommand($command, $type, $format);
        $this->respond($msg, "Command '$command' has been successfully created. Use !editcommand to begin creating lists and triggers for it.");
    }


    /**
     * Delete an existing custom command entry
     * Usage: !removecommand <name>
     *
     * @param IRCMessage $msg
     * @throws CommandCreatorException
     * @throws \Utsubot\Accounts\ModuleWithAccountsException
     */
    public function removeCommand(IRCMessage $msg) {
        $this->requireLevel($msg, 50);

        $command = $msg->getCommandParameters()[ 0 ];

        $this->interface->destroyCommand($command);
        $this->updateCustomTriggerCache();

        $this->respond($msg, "Command '$command' has been destroyed. All associated lists and triggers have been deleted.");
    }


    /**
     * View existing attributes of a command without editing anything
     * Usage: !viewcommand <name> type|format|triggers|
     *        !viewcommand <name> list <slot>
     *
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
                $type = $this->interface->getCommandType($command);
                $this->respond($msg, "Type of command '$command' is '{$type->getName()}'.");
                break;

            case "format":
                $format = $this->interface->getCommandFormat($command);
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
                $items = $this->interface->getListItems($command, $slot);
                $this->respond($msg, "Items in list slot $slot for command '$command': ".implode(", ", array_column($items, "value")));
                break;

            default:
                throw new CommandCreatorException("Invalid property '$property'.");
                break;

        }
    }


    /**
     * Edit an existing command entry to change the type, manage triggers, or manage list items
     * Usage: !editcommand <name> type|format set <newvalue>
     *        !editcommand <name> trigger add|remove <trigger>
     *        !editcommand <name> trigger clear
     *        !editcommand <name> list <slot> add|remove <item>
     *        !editcommand <name> list <slot> clear
     *
     * @param IRCMessage $msg
     * @throws CommandCreatorException
     * @throws \Utsubot\Accounts\ModuleWithAccountsException
     */
    public function editCommand(IRCMessage $msg) {
        $this->requireLevel($msg, 50);
        //  !editcommand bartender list add 1 beer
        //  !editcommand bartender trigger add !drink

        $parameters = $msg->getCommandParameters();
        list($command, $property, $mode) = $parameters;
        $parameters = array_slice($parameters, 3);

        switch ($property) {
            case "type":
                switch ($mode) {
                    case "set":
                        $type = CommandType::fromName(array_shift($parameters));
                        $this->interface->setCommandType($command, $type);
                        $this->respond($msg, "Type of command '$command' has been changed to '{$type->getName()}'.");
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
                        $this->interface->setCommandFormat($command, $format);
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
                        if (isset($this->getTriggers()[ strtolower($trigger) ]))
                            throw new CommandCreatorException("Trigger '$trigger' is reserved for a CommandCreator command.");

                        $this->interface->addCommandTrigger($command, $trigger);
                        $this->updateCustomTriggerCache();

                        $this->respond($msg, "Trigger '$trigger' has been added for command '$command'.");
                        break;

                    case "remove":
                        $this->interface->removeCommandTrigger($command, $trigger);
                        $this->updateCustomTriggerCache();

                        $this->respond($msg, "Trigger '$trigger' has been removed from command '$command'.");
                        break;

                    case "clear":
                        $cleared = $this->interface->clearCommandTriggers($command);
                        if ($cleared > 0)
                            $this->updateCustomTriggerCache();

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
                        $this->interface->addListItem($command, $item, $slot);
                        $this->respond($msg, "Item '$item' has been added to list slot $slot for command '$command'.");
                        break;

                    case "remove":
                        $this->interface->removeListItem($command, $item, $slot);
                        $this->respond($msg, "Item '$item' has been removed from list slot $slot for command '$command'.");
                        break;

                    case "clear":
                        $cleared = $this->interface->clearListItems($command, $slot);
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
     * Activate a saved command from a trigger word, from the PRIVMSG routine
     *
     * @param            $commandID
     * @param IRCMessage $msg
     * @throws CommandCreatorException
     */
    protected function triggerCommand(int $commandID, IRCMessage $msg) {
        $commandInfo = $this->interface->getCommandByID($commandID);
        $parameters  = $this->interface->getParametersByID($commandID);

        //  Build array of list items per slot from command parameters
        $listItems = [ ];
        foreach ($parameters as $row)
            $listItems[ $row[ 'slot' ] ][] = $row[ 'value' ];

        /**
         * Function used with regex replacement to substitute in randomized or calculated parameters
         *
         * @param array $match Generated by preg_replace_callback
         * @return string
         */
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

        //  Perform substitution with saved function
        $output = preg_replace_callback("/%(\d+|[nct])/", $subParameters, $commandInfo[ 'format' ]);

        //  Output depending on command type
        switch ((int)$commandInfo[ 'type ' ]) {
            case CommandType::Message:
                $this->respond($msg, $output);
                break;

            case CommandType::Action:
                $this->IRCBot->action($msg->getResponseTarget(), $output);
                break;

        }
    }


    /**
     * Cache trigger information from the database
     */
    public function updateCustomTriggerCache() {
        $triggers = $this->interface->getTriggers();

        $this->customTriggers = [ ];
        foreach ($triggers as $row)
            $this->customTriggers[ $row[ 'custom_commands_id' ] ][] = $row[ 'value' ];
    }


    /**
     * Read trigger list for a command from local cache
     *
     * @param string $command
     * @return array
     * @throws CommandCreatorDatabaseInterfaceException
     * @throws CommandCreatorException
     */
    protected function getCustomTriggers(string $command): array {
        $commandID = $this->interface->getCommandID($command);

        if (isset($this->customTriggers[ $commandID ]))
            $return = $this->customTriggers[ $commandID ];
        else
            throw new CommandCreatorException("No triggers found for command '$command'.");

        return $return;
    }

}
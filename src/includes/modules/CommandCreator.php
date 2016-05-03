<?php
/**
 * Utsubot - CommandCreator.php
 * User: Benjamin
 * Date: 01/02/2015
 */

namespace Utsubot\CommandCreator;
use Utsubot\Permission\ModuleWithPermission;
use Utsubot\{
    IRCBot,
    IRCMessage,
    Trigger,
    ModuleException,
    DatabaseInterface,
    MySQLDatabaseCredentials
};


class CommandCreatorException extends ModuleException {}

class CommandCreator extends ModuleWithPermission {

	private $interface;
	private $customTriggers = array();

	public function __construct(IRCBot $irc) {
		$this->_require("Utsubot\\DatabaseInterface");
		$this->_require("Utsubot\\MySQLDatabaseCredentials");

		parent::__construct($irc);

		$this->interface = new DatabaseInterface(MySQLDatabaseCredentials::createFromConfig("utsubot"));
		$this->updateCustomTriggerCache();

		$this->addTrigger(new Trigger("addcommand",     array($this, "addCommand"       )));
        $this->addTrigger(new Trigger("acommand",       array($this, "addCommand"       )));
        $this->addTrigger(new Trigger("acmd",           array($this, "addCommand"       )));
        $this->addTrigger(new Trigger("ac",             array($this, "addCommand"       )));

        $this->addTrigger(new Trigger("removecommand",  array($this, "removeCommand"    )));
        $this->addTrigger(new Trigger("rcommand",       array($this, "removeCommand"    )));
        $this->addTrigger(new Trigger("rcmd",           array($this, "removeCommand"    )));
        $this->addTrigger(new Trigger("rc",             array($this, "removeCommand"    )));

        $this->addTrigger(new Trigger("editcommand",    array($this, "editCommand"      )));
        $this->addTrigger(new Trigger("ecommand",       array($this, "editCommand"      )));
        $this->addTrigger(new Trigger("ecmd",           array($this, "editCommand"      )));
        $this->addTrigger(new Trigger("ec",             array($this, "editCommand"      )));

        $this->addTrigger(new Trigger("viewcommand",    array($this, "viewCommand"      )));
        $this->addTrigger(new Trigger("vcommand",       array($this, "viewCommand"      )));
        $this->addTrigger(new Trigger("vcmd",           array($this, "viewCommand"      )));
        $this->addTrigger(new Trigger("vc",             array($this, "viewCommand"      )));

	}

	public function privmsg(IRCMessage $msg) {
		parent::privmsg($msg);

		if ($msg->isCommand()) {

			foreach ($this->customTriggers as $commandID => $triggers) {
				if (array_search($msg->getCommand(), $triggers) !== FALSE) {
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

	public function removeCommand(IRCMessage $msg) {
		$this->requireLevel($msg, 50);

		$command = $msg->getCommandParameters()[0];

		$this->destroyCommand($command);
		$this->respond($msg, "Command '$command' has been destroyed. All associated lists and triggers have been deleted.");
	}

	public function viewCommand(IRCMessage $msg) {
		$this->requireLevel($msg, 50);

		$parameters = $msg->getCommandParameters();
		$command = array_shift($parameters);
		$property = array_shift($parameters);

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
				$this->respond($msg, "Trigger(s) for '$command': ". implode(", ", $triggers));
			break;
			case "list":
				$slot = array_shift($parameters);
				if (!preg_match("/^[1-9]+[0-9]*$/", $slot))
					throw new CommandCreatorException("Invalid slot number '$slot'.");

				$slot = intval($slot);
				$items = $this->getListItems($command, $slot);
				$this->respond($msg, "Items in list slot $slot for command '$command': ". implode(", ", array_column($items, "value")));
			break;
			default:
				throw new CommandCreatorException("Invalid property '$property'.");
			break;
		}
	}

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

	public function updateCustomTriggerCache() {
		$results = $this->interface->query(
			"SELECT * FROM `custom_commands_triggers`",
			array());

		$this->customTriggers = array();
		foreach ($results as $row)
			$this->customTriggers[$row['custom_commands_id']][] = $row['value'];
	}


	private function triggerCommand($commandID, IRCMessage $msg) {
		$commandInfo = $this->interface->query(
			"SELECT * FROM `custom_commands` WHERE `id`=? LIMIT 1",
			array($commandID)
		);

		if (!$commandInfo)
			throw new CommandCreatorException("Unable to retrieve format for command ID '$commandID'.");

		$results = $this->interface->query(
			"SELECT * FROM `custom_commands_parameters` WHERE `custom_commands_id`=?",
			array($commandID));

		#if (!$results)
		#	throw new CommandCreatorException("There are no list items for command '{$commandInfo['name']}'.");

		$listItems = array();
		foreach ($results as $row)
			$listItems[$row['slot']][] = $row['value'];


		$subParameters = function($match) use ($commandID, $listItems, $msg) {
			if (is_numeric($match[1]) && isset($listItems[$match[1]]))
				return $listItems[$match[1]][array_rand($listItems[$match[1]])];

			switch ($match[1]) {
				case "n":	return $msg->getNick();				break;
				case "c":	return $msg->getResponseTarget();	break;
				case "t":	return date("g:ia");				break;
			}

			return $match[1];
		};

		$output = preg_replace_callback("/%(\d+|[nct])/", $subParameters, $commandInfo['format']);
		if ($commandInfo['type'] == "message")
			$this->respond($msg, $output);
		elseif ($commandInfo['type'] == "action")
			$this->IRCBot->action($msg->getResponseTarget(), $output);
	}

	private function createCommand($command, $type, $format) {
		$rowCount = $this->interface->query(
			"INSERT INTO `custom_commands` (`name`, `type`, `format`) VALUES (?, ?, ?)",
			array($command, $type, $format));

		if (!$rowCount)
			throw new CommandCreatorException("Command '$command' already exists.");

		return true;
	}

	private function destroyCommand($command) {
		$rowCount = $this->interface->query(
			"DELETE FROM `custom_commands` WHERE `name`=? LIMIT 1",
			array($command));

		if (!$rowCount)
			throw new CommandCreatorException("Command '$command' was not found.");

		$this->updateCustomTriggerCache();
		return true;
	}

	private function getCommandId($command) {
		$results = $this->interface->query(
			"SELECT `id` FROM `custom_commands` WHERE `name`=? LIMIT 1",
			array($command));

		if (!$results)
			throw new CommandCreatorException("ID lookup for command '$command' failed.");

		return $results['id'];
	}

	private function setFormat($command, $format) {
		$id = $this->getCommandId($command);
		$rowCount = $this->interface->query(
			"UPDATE `custom_commands` SET `format`=? WHERE `id`=? LIMIT 1",
			array($format, $id));

		if (!$rowCount)
			throw new CommandCreatorException("Format '$format' of command '$command' unchanged.");

		return true;
	}

	private function getFormat($command) {
		$results = $this->interface->query(
			"SELECT `format` FROM `custom_commands` WHERE `name`=? LIMIT 1",
			array($command));

		if (!$results)
			throw new CommandCreatorException("Command '$command' not found.");

		return $results['format'];
	}

	private function setType($command, $type) {
		$id = $this->getCommandId($command);
		$rowCount = $this->interface->query(
			"UPDATE `custom_commands` SET `type`=? WHERE `id`=? LIMIT 1",
			array($type, $id));

		if (!$rowCount)
			throw new CommandCreatorException("Type '$type' for command '$command' unchanged.");

		return true;
	}

	private function getType($command) {
		$results = $this->interface->query(
			"SELECT `type` FROM `custom_commands` WHERE `name`=? LIMIT 1",
			array($command));

		if (!$results)
			throw new CommandCreatorException("Command '$command' not found.");

		return $results['type'];
	}

	private function addCustomTrigger($command, $trigger) {
		if (isset($this->getTriggers()[strtolower($trigger)]))
			throw new CommandCreatorException("Trigger '$trigger' is reserved for a CommandCreator command.");

		$id = $this->getCommandId($command);
		try {
			$this->interface->query(
				"INSERT INTO `custom_commands_triggers` (`custom_commands_id`, `value`) VALUES (?, ?)",
				array($id, $trigger));

			$this->updateCustomTriggerCache();
		}
		catch (\PDOException $e) {
			throw new CommandCreatorException("Trigger '$trigger' for command '$command' already exists.");
		}

		return true;
	}

	private function removeCustomTrigger($command, $trigger) {
		$id = $this->getCommandId($command);
		$rowCount = $this->interface->query(
			"DELETE FROM `custom_commands_triggers` WHERE `custom_commands_id`=? AND `value`=? LIMIT 1",
			array($id, $trigger));

		if (!$rowCount)
			throw new CommandCreatorException("Trigger '$trigger' for command '$command' was not found.");

		$this->updateCustomTriggerCache();
		return true;
	}

	private function clearCustomTriggers($command) {
		$id = $this->getCommandId($command);
		$rowCount = $this->interface->query(
			"DELETE FROM `custom_commands_triggers` WHERE `custom_commands_id`=?",
			array($id));

		if (!$rowCount)
			throw new CommandCreatorException("No triggers found for command '$command'.");

		$this->updateCustomTriggerCache();
		return $rowCount;
	}

	public function getCustomTriggers($command) {
		$commandID = $this->getCommandId($command);
		if (isset($this->customTriggers[$commandID]))
			$return = $this->customTriggers[$commandID];
		else
			throw new CommandCreatorException("No triggers found for command '$command'.");

		return $return;
	}

	private function addListItem($command, $item, $slot = 1) {
		$id = $this->getCommandId($command);
		$rowCount = $this->interface->query(
			"INSERT INTO `custom_commands_parameters` (`custom_commands_id`, `slot`, `value`) VALUES (?, ?, ?)",
			array($id, $slot, $item));

		if (!$rowCount)
			throw new CommandCreatorException("An error occured trying to add '$item' to '$command' slot $slot.");

		return true;
	}

	private function removeListItem($command, $item, $slot = 1) {
		$id = $this->getCommandId($command);
		$rowCount = $this->interface->query(
			"DELETE FROM `custom_commands_parameters` WHERE `custom_commands_id`=? AND `slot`=? AND `value`=? LIMIT 1",
			array($id, $slot, $item));

		if (!$rowCount)
			throw new CommandCreatorException("Item '$item' for command '$command' in slot $slot was not found.");

		return true;
	}

	private function clearListItems($command, $slot = 1) {
		$id = $this->getCommandId($command);
		$rowCount = $this->interface->query(
			"DELETE FROM `custom_commands_parameters` WHERE `custom_commands_id`=? AND `slot`=?",
			array($id, $slot));

		if (!$rowCount)
			throw new CommandCreatorException("No list items found in slot $slot for command '$command'.");

		return $rowCount;
	}

	private function getListItems($command, $slot = 1) {
		$id = $this->getCommandId($command);
		$results = $this->interface->query(
			"SELECT `value` FROM `custom_commands_parameters` WHERE `custom_commands_id`=? AND `slot`=?",
			array($id, $slot));

		if (!$results)
			throw new CommandCreatorException("No items found for command '$command' in slot $slot.");

		return $results;
	}

} 
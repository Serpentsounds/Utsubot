<?php
/**
 * Utsubot - Help.php
 * User: Benjamin
 * Date: 17/12/2014
 */

namespace Utsubot;
use Utsubot\Permission\ModuleWithPermission;


class HelpException extends ModuleException {}

class Help extends ModuleWithPermission {

	const Help_Directory = "../help";
	private $help = array();

	public function __construct(IRCBot $irc) {
		parent::__construct($irc);
		$this->updateHelpCache();

		$this->addTrigger(new Trigger("help",       array($this, "help"         )));
        $this->addTrigger(new Trigger("phelp",      array($this, "help"         )));
        $this->addTrigger(new Trigger("showhelp",   array($this, "help"         )));

        $this->addTrigger(new Trigger("updatehelp", array($this, "updateHelp"   )));
	}

	/**
	 * Search for help on the given topic, or list all available commands if no topic given
	 *
	 * @param IRCMessage $msg
	 * @throws HelpException If no help is available
	 */
	public function help(IRCMessage $msg) {
        //  !showhelp to publicly message help, otherwise it is privately shown
		if (strtolower($msg->getCommand()) == "showhelp")
			$responseTarget = $msg->getResponseTarget();
		else
			$responseTarget = $msg->getNick();

		//	$parameters is the help topic to search for
		$parameters = strtolower($msg->getCommandParameterString());
		$return = array();

		//	Save command prefix to attach commands to
		$commandPrefix = $this->IRCBot->getIRCNetwork()->getCommandPrefixes()[0] ?? "";
        //  Local function for array_map calls
		$addPrefix = function ($command) use ($commandPrefix) {
			return $commandPrefix . $command;
		};

		//	If a topic was specified
		if (strlen($parameters)) {
			//	Get help array (may throw exception)
			$help = $this->findHelp($parameters);

			//	Single command
			if (isset($help['text'])) {

				//	Loop through help text and format it
				for ($i = 0, $count = count(@$help['text']); $i < $count; $i++) {
					$string = array(bold($commandPrefix . $parameters));

					//	Optional: parameters
					if (strlen(@$help['params'][$i]))
						$string[] = $help['params'][$i];

					//	Separator and text
					$string[] = "-";
					$string[] = @$help['text'][$i];

					$return[] = implode(" ", $string);
				}

				//	Format and add command aliases, if applicable
				if (isset($help['aliases']))
					$return[] = bold("Aliases: ") . implode(" ", array_map($addPrefix, $help['aliases']));

				//	Suffix notes, if applicable
				if (isset($help['notes']))
					$return[] = bold("Notes: ") . implode("\n", $help['notes']);

				$this->IRCBot->message($responseTarget, implode("\n", $return));
			}

			//	Command category
			else {
				$category = array_keys($help)[0];
				$commands = array_map($addPrefix, array_keys($help[$category]));
				$return = bold($category). ": ". implode(" ", $commands);
				$this->IRCBot->message($responseTarget, bold("Available commands: "). $return);
			}
		}

		//	No command given, list available help
		else {
			foreach ($this->help as $file => $help) {
				//	Format commands and put under under file header group
				$commands = array_map($addPrefix, array_keys($help));
				$return[] = bold($file). ": ". implode(" ", $commands);
			}

			$this->IRCBot->message($responseTarget, bold("Available commands: "). implode(" :: ", $return));
		}

	}

	/**
     * Reload help information from file
     *
	 * @param IRCMessage $msg
	 */
    public function updateHelp(IRCMessage $msg) {
        $this->requireLevel($msg, 100);
        $this->updateHelpCache();
        $this->respond($msg, "Help cache has been updated.");
    }

	/**
	 * Get the array of help information for a topic (command or category) from the internal cache
	 *
	 * @param string $topic
	 * @return array Array in help format
	 * @throws ModuleException If no help is available
	 */
	private function findHelp($topic) {
		foreach ($this->help as $file => $help) {
			//	Help available for command
			if (isset($help[$topic]))
				return $help[$topic];

			//	Help available for category
			elseif (strtolower($file) == $topic)
				return array($file => $this->help[$file]);
		}

		throw new HelpException("No help available for '$topic'");
	}

	/**
	 * Reload help information from ini files
	 */
	public function updateHelpCache() {
		$this->help = array();

		$directory = self::Help_Directory;
		$files = scandir($directory);

		foreach ($files as $file) {
            //  Skip non files
			if ($file == "." || $file == "..")
				continue;

            //  Ini parsing successful
			if (($help = parse_ini_file("$directory/$file", true)) !== false) {
				$category = basename($file, ".ini");
				$this->help[$category] = $help;
			}
		}
	}
} 
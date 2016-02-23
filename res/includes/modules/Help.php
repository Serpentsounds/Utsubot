<?php
/**
 * Utsubot - Help.php
 * User: Benjamin
 * Date: 17/12/2014
 */

class Help extends Module {
	private static $helpDirectory = "help";
	private $help = array();

	public function __construct(IRCBot $irc) {
		parent::__construct($irc);
		$this->updateHelpCache();

		$this->triggers = array(
			'help'		=> "help",
			'phelp'		=> "help",
			'showhelp'	=> "help"
		);
	}

	/**
	 * Search for help on the given topic, or list all available commands if no topic given
	 *
	 * @param IRCMessage $msg
	 * @throws ModuleException If no help is available
	 */
	public function help(IRCMessage $msg) {
		if (strtolower($msg->getCommand()) == "showhelp")
			$responseTarget = $msg->getResponseTarget();
		else
			$responseTarget = $msg->getNick();


		//	$parameters is the help topic to search for
		$parameters = strtolower($msg->getCommandParameterString());
		$return = array();

		//	Save command prefix to attach commands to
		$commandPrefix = @$this->IRCBot->getCommandPrefix()[0];
		$addPrefix = function ($command) use ($commandPrefix) {
			return $commandPrefix . $command;
		};

		//	If a topic was specified
		if (strlen($parameters)) {
			//	Get help array (may throw exception)
			$help = $this->findHelp($parameters);

			//	Loop through help text and format it
			for ($i = 0, $count = count(@$help['text']); $i < $count; $i++) {
				$string = array(IRCUtility::bold($commandPrefix . $parameters));

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
				$return[] = IRCUtility::bold("Aliases: "). implode(" ", array_map($addPrefix, $help['aliases']));

			//	Suffix notes, if applicable
			if (isset($help['notes']))
				$return[] = IRCUtility::bold("Notes: "). implode("\n", $help['notes']);

			$this->IRCBot->message($responseTarget, implode("\n", $return));
		}

		//	No command given, list available help
		else {
			foreach ($this->help as $file => $help) {
				//	Format commands and put under under file header group
				$commands = array_map($addPrefix, array_keys($help));
				$return[] = IRCUtility::bold($file). ": ". implode(" ", $commands);
			}

			$this->IRCBot->message($responseTarget, IRCUtility::bold("Available commands: "). implode(" :: ", $return));
		}

	}

	/**
	 * Get the array of help information for a topic
	 *
	 * @param string $topic
	 * @return array Array in help format
	 * @throws ModuleException If no help is available
	 */
	private function findHelp($topic) {
		foreach ($this->help as $file => $help) {
			if (isset($help[$topic]))
				return $help[$topic];
		}

		throw new ModuleException("Help::help: No help available for '$topic'");
	}

	/**
	 * Reload help information from ini files
	 */
	public function updateHelpCache() {
		$this->help = array();

		$directory = self::$helpDirectory;
		$files = scandir($directory);

		foreach ($files as $file) {
			if ($file == "." || $file == "..")
				continue;

			if (($help = parse_ini_file("$directory/$file", true)) !== false) {
				$category = basename($file, ".ini");
				$this->help[$category] = $help;
			}
		}
	}
} 
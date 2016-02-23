<?php
/**
 * MEGASSBOT - IRCMessage.php
 * User: Benjamin
 * Date: 12/11/14
 */

class IRCMessageException extends Exception {}

class IRCMessage {
	private $type = "";
	private $raw = 0;
	private $fullString = "";

	private $nick = "";
	private $ident = "";
	private $fullHost = "";
	private $trailingHost = "";

	private $target = "";
	private $ctcp = "";
	private $parameters = array();
	private $parameterString = "";

	private $inChannel = false;
	private $inQuery = false;

	private $isAction = false;

	//	Used by modules to determine if this is a command issued to the bot
	private $isCommand = false;
	private $command = "";
	private $commandParameters = array();
	private $commandParameterString = "";
	private $responseTarget = "";
	private $responded = null;

	/**
	 * Create this IRCMessage by parsing an unedited line from the irc server
	 *
	 * @param string $raw A full line from the server
	 * @throws IRCMessageException If passed string is determined to be invalid
	 */
	public function __construct($raw) {
		//	First check against invalid parameter
		if (!is_string($raw) || !$raw)
			throw new IRCMessageException("Parameter must be a non-empty string.");

		//	Save string and prepare word array
		$this->fullString = $raw;
		$words = explode(" ", $raw);

		//	PING and ERROR have different structures, so parse them separately
		if ($words[0] == "PING" || $words[0] == "ERROR") {
			$this->type = strtolower($words[0]);
			$this->setParameters(self::removeColon(self::restOfString($words, 1)));
		}

		//	Parse all other messages
		else {
			//	Fill in source, target, and default parameters
			$this->parseSource($words[0]);
			$this->parseTarget($words[2]);
			$this->setParameters(self::removeColon(self::restOfString($words, 3)));

			switch ($words[1]) {
				case "PRIVMSG":
					$this->type = "privmsg";

					//	A PRIVMSG of this form is either a /me command or a CTCP request, adjust accordingly
					if (preg_match('/:\x01(\S+) ?(.*)\x01$/', $raw, $match)) {
						if ($match[1] == "ACTION")
							$this->isAction = true;
						else {
							$this->type = "ctcp";
							//	Separate first word of params as the CTCP request
							$this->ctcp = $match[1];
							$this->setParameters($match[2]);
						}
					}
				break;

				case "NOTICE":
					$this->type = "notice";

					//	A NOTICE of this form is responding to a CTCP request
					if (preg_match('/:\x01(\S+) ?(.*)\x01$/', $raw, $match)) {
						$this->type = "ctcpResponse";
						//	Separate just like with a CTCP request
						$this->ctcp = $match[1];
						$this->setParameters($match[2]);
					}
				break;

				case "QUIT":
				case "NICK":
					$this->type = strtolower($words[1]);
					//	QUIT and NICK will have the param string start one word earlier, since it doesn't target a single channel/user
					$this->setParameters(self::removeColon(self::restOfString($words, 2)));
				break;


				default:
					//	Raw numeric command, save the numeric
					if (is_numeric($words[1])) {
						$this->type = "raw";
						$this->raw = intval($words[1]);
					}
					//	MODEs, JOINs, PARTs, and NICKs, all other relevant info should already be parsed
					else
						$this->type = strtolower($words[1]);
				break;

			}
		}

	}

	/**
	 * Examine the source of an irc command to deteremine who or what it came from, and adjust properties accordingly
	 *
	 * @param string $source The 1st word of a complete irc message containing the source
	 */
	public function parseSource($source) {
		if (preg_match('/^:([^!]+)!([^@]+)@([^.]+\.?)((?:[^.]+\.?)*)/', $source, $match)) {
			$this->nick = $match[1];
			$this->ident = $match[2];
			$this->fullHost = $match[3]. $match[4];
			$this->trailingHost = $match[4];
		}
		else
			$this->nick = $source;
	}

	/**
	 * Examine the target of an irc command to determine where or to whom it was directed, and adjust properties accordingly
	 *
	 * @param string $target The 3rd word of a comnplete irc message containing the target
	 */
	public function parseTarget($target) {
		$this->target = self::removeColon($target);
		if (substr($this->target, 0, 1) == "#") {
			$this->inChannel = true;
			$this->inQuery = false;
			$this->responseTarget = $this->target;
		}
		else {
			$this->inChannel = false;
			$this->inQuery = true;
			$this->responseTarget = $this->nick;
		}
	}


	/**
	 * Given valid command prefixes, parse this message to determine if it's an issued command
	 *
	 * @param array $prefixes An array of command prefixes/triggers (e.g., ! for !command)
	 * @return bool True if this message IS a command and properties were updated, false if not
	 */
	public function parseCommand($prefixes = array("!")) {
		if (!is_array($prefixes))
			return false;

		//	Check each command prefix with the beginning of string
		foreach ($prefixes as $prefix) {
			//	Command found, update properties
			if (strlen($this->parameterString) > strlen($prefix) && substr($this->parameterString, 0, strlen($prefix)) == $prefix) {
				$this->isCommand = true;
				$parameters = explode(" ", substr($this->parameterString, strlen($prefix)));
				$this->command = array_shift($parameters);
				$this->setParameters(implode(" ", $parameters), true);
				return true;
			}
		}

		//	All query commands should be evaluated as a command. No need to strip the prefix, the loop would have caught it already if it had one
		if ($this->inQuery) {
			$this->isCommand = true;
			$parameters = $this->parameters;
			$this->command = array_shift($parameters);
			$this->setParameters(implode(" ", $parameters), true);
			return true;
		}

		//	No commands to be evaluated in this message
		return false;
	}

	/**
	 * Internal function to automatically update the parameters string and array at the same time
	 *
	 * @param string $parameterString The part of the irc message following the command and target
	 * @param bool $command True to set the command parameters instead
	 * @return bool True on success, false on failure
	 */
	private function setParameters($parameterString, $command = false) {
		if (!is_string($parameterString))
			return false;

		if ($command) {
			$this->commandParameterString = $parameterString;
			$this->commandParameters = array_filter(explode(" ", $parameterString), "strlen");
		}
		else {
			$this->parameterString = $parameterString;
			$this->parameters = array_filter(explode(" ", $parameterString), "strlen");
		}

		return true;
	}

	/**
	 * Given an array of words in a complete irc message, return a string starting at the nth word
	 *
	 * @param array $words The array of words
	 * @param int $position The first word to include
	 * @return string The joined string
	 */
	private static function restOfString($words, $position) {
		if (!isset($words[$position]))
			return "";

		return implode(" ", array_slice($words, $position));
	}

	/**
	 * Strip the leading colon from the parameters of an irc message, if present
	 *
	 * @param string $string The irc message
	 * @return string $string with no leading colon
	 */
	public static function removeColon($string) {
		if (substr($string, 0, 1) == ":")
			$string = substr($string, 1);
		return $string;
	}

	public function getType() {
		return $this->type;
	}

	public function getRaw() {
		return $this->raw;
	}

	public function getFullString() {
		return $this->fullString;
	}

	public function getNick() {
		return $this->nick;
	}

	public function getIdent() {
		return $this->ident;
	}

	public function getFullHost() {
		return $this->fullHost;
	}

	public function getTrailingHost() {
		return $this->trailingHost;
	}

	public function getTarget() {
		return $this->target;
	}

	public function getCTCP() {
		return $this->ctcp;
	}

	public function getParameters() {
		return $this->parameters;
	}

	public function getParameterString() {
		return $this->parameterString;
	}

	public function inChannel() {
		return $this->inChannel;
	}

	public function inQuery() {
		return $this->inQuery;
	}

	public function isAction() {
		return $this->isAction;
	}

	public function isCommand() {
		return $this->isCommand;
	}

	public function getCommand() {
		return $this->command;
	}

	public function getCommandParameters() {
		return $this->commandParameters;
	}

	public function getCommandParameterString() {
		return $this->commandParameterString;
	}

	public function getResponseTarget() {
		return $this->responseTarget;
	}

	public function responded() {
		return $this->responded;
	}

	public function respond($cmd) {
		$this->responded = $cmd;
	}

}
<?php
/**
 * MEGASSBOT - IRCMessage.php
 * User: Benjamin
 * Date: 12/11/14
 */

declare(strict_types = 1);

namespace Utsubot;

class IRCMessageException extends \Exception {}

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
	private $responded = "";

	/**
	 * Create this IRCMessage by parsing an unedited line from the irc server
	 *
	 * @param string $raw A full line from the server
	 * @throws IRCMessageException If passed string is determined to be invalid
	 */
	public function __construct(string $raw) {
		//	First check against invalid parameter
		if (!strlen($raw))
			throw new IRCMessageException("IRCMessage can not be constructed from an empty string.");

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
			$this->type = strtolower($words[1]);

			switch ($words[1]) {
				case "PRIVMSG":
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
					//	QUIT and NICK will have the param string start one word earlier, since it doesn't target a single channel/user
					$this->setParameters(self::removeColon(self::restOfString($words, 2)));
				break;


				default:
					//	Raw numeric command, save the numeric
					if (is_numeric($words[1])) {
						$this->type = "raw";
						$this->raw = intval($words[1]);
					}
				break;

			}
		}

	}

	/**
	 * Examine the source of an irc command to deteremine who or what it came from, and adjust properties accordingly
	 *
	 * @param string $source The 1st word of a complete irc message containing the source
	 */
	public function parseSource(string $source) {
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
	public function parseTarget(string $target) {
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
	public function parseCommand(array $prefixes = array("!")) {
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
	 */
	private function setParameters(string $parameterString, bool $command = false) {
		if ($command) {
			$this->commandParameterString = $parameterString;
			$this->commandParameters = array_filter(explode(" ", $parameterString), "strlen");
		}
		else {
			$this->parameterString = $parameterString;
			$this->parameters = array_filter(explode(" ", $parameterString), "strlen");
		}
	}


    /**
     * Mark this message as having triggered a command
     *
     * @param string $cmd
     */
    public function respond(string $cmd) {
        $this->responded = $cmd;
    }

    /**
     * Check if a module has triggered a command for this message
     *
     * @return string
     */
    public function responded(): string {
        return $this->responded;
    }

	/**
	 * Given an array of words in a complete irc message, return a string starting at the nth word
	 *
	 * @param array $words The array of words
	 * @param int $position The first word to include
	 * @return string The joined string
	 */
	private static function restOfString(array $words, int $position): string {
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
	public static function removeColon(string $string): string {
		if (substr($string, 0, 1) == ":")
			$string = substr($string, 1);

		return $string;
	}

    /**
     * @return string
     */
	public function getType(): string {
		return $this->type;
	}

    /**
     * @return int
     */
	public function getRaw(): int {
		return $this->raw;
	}

    /**
     * @return string
     */
	public function getFullString(): string {
		return $this->fullString;
	}

    /**
     * @return string
     */
	public function getNick(): string {
		return $this->nick;
	}

    /**
     * @return string
     */
	public function getIdent(): string {
		return $this->ident;
	}

    /**
     * @return string
     */
	public function getFullHost(): string {
		return $this->fullHost;
	}

    /**
     * @return string
     */
	public function getTrailingHost(): string {
		return $this->trailingHost;
	}

    /**
     * @return string
     */
	public function getTarget(): string {
		return $this->target;
	}

    /**
     * @return string
     */
	public function getCTCP(): string {
		return $this->ctcp;
	}

    /**
     * @return array
     */
	public function getParameters(): array {
		return $this->parameters;
	}

    /**
     * @return string
     */
	public function getParameterString(): string {
		return $this->parameterString;
	}

    /**
     * @return bool
     */
	public function inChannel(): bool {
		return $this->inChannel;
	}

    /**
     * @return bool
     */
	public function inQuery(): bool {
		return $this->inQuery;
	}

    /**
     * @return bool
     */
	public function isAction(): bool {
		return $this->isAction;
	}

    /**
     * @return bool
     */
	public function isCommand(): bool {
		return $this->isCommand;
	}

    /**
     * @return string
     */
	public function getCommand(): string {
		return $this->command;
	}

    /**
     * @return array
     */
	public function getCommandParameters(): array {
		return $this->commandParameters;
	}

    /**
     * @return string
     */
	public function getCommandParameterString(): string {
		return $this->commandParameterString;
	}

    /**
     * @return string
     */
	public function getResponseTarget(): string {
		return $this->responseTarget;
	}

}
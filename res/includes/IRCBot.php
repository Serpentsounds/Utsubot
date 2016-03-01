<?php
/**
 * MEGASSBOT - IRCBot.php
 * User: Benjamin
 * Date: 28/06/14
 */

class IRCBotException extends Exception {}

class IRCBot {

	use EasySetters;

	const SOCKET_POLL_TIME = 100000;
	const RECONNECT_TIMEOUT = 7;
	const RECONNECT_DELAY = 10;

	const PING_FREQUENCY = 90;
	const ACTIVITY_TIMEOUT = 150;

	private $socket;

	private $network = "";
	private $host = "";
	private $port = 0;
	private $server = "";
	private $defaultChannels = array();
	private $onConnect = array();

	private $nickname = "";
	private $alternateNickname = "";
	private $address = "";

	private $users;
	private $channels;

	private $modules = array();
	private $commandPrefix = array();

	/**
	 * Load up the config for this IRCBot
	 *
	 * @param Array $config An array of $field => $value configuration options. Many are required to connect, like host, port, and nickname
	 * @throws IRCBotException If invalid config is supplied
	 */
	public function __construct($config) {
		if (!is_array($config))
			throw new IRCBotException("IRCBot::__construct: Configuration must be an array.");

		foreach ($config as $field => $value) {
			switch ($field) {
				case "network":				$this->setNetwork($value);				break;
				case "host":				$this->setHost($value);					break;
				case "port":				$this->setPort($value);					break;
				case "nickname":			$this->setNickname($value);				break;
				case "alternateNickname":	$this->setAlternateNickname($value);	break;
				case "channels":			$this->setDefaultChannels($value);		break;
				case "onConnect":			$this->setOnConnect($value);			break;
				case "commandPrefix":		$this->setCommandPrefix($value);		break;
			}
		}

		if (!$this->host || !$this->port || !$this->nickname)
			throw new IRCBotException("Configuration must have at least a server, port, and nickname to connect.");

		$this->users = new Users($this);
		$this->channels = new Channels($this);
	}

	/**
	 * Reset the server connection and register with the server
	 */
	public function connect() {
		//	Reset socket
		if ($this->socket)
			$this->socket = null;

		//	Suppress error on fsockopen, and handle it later
		$this->console("Attempting to connect to $this->host:$this->port...");
		$this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, self::RECONNECT_TIMEOUT);

		//	Connection was unsuccessful
		if (!$this->socket) {
			$this->reconnectCountdown();
			return false;
		}

		//	Full speed ahead
		else {
			$this->console("Connection successful.");
			$this->raw("USER Utsubot 0 * :$this->nickname");
			$this->raw("NICK :$this->nickname");
			return true;
		}
	}

	public function disconnect() {
		$this->socket = null;
	}

	/**
	 * Play an update to the console while delaying reconnection
	 */
	public function reconnectCountdown() {
		fwrite(STDOUT, "Error connecting, retrying in");

		for ($seconds = self::RECONNECT_DELAY; $seconds > 0; $seconds--) {
			fwrite(STDOUT, " $seconds...");
			sleep(1);
		}
		fwrite(STDOUT, "\n\n");
	}

	/**
	 * @return bool True or false if the socket is active or not
	 */
	public function connected() {
		if (is_resource($this->socket) && get_resource_type($this->socket) == "stream" && !feof($this->socket))
			return true;

		return false;
	}

	/**
	 * Poll the socket for changes for a time, then returns a line if there is data to read
	 *
	 * @return string The line or an empty string
	 */
	public function read() {
		$arr = array($this->socket);
		$write = $except = null;
		if (($changed = stream_select($arr, $write, $except, 0, self::SOCKET_POLL_TIME)) > 0)
			return trim(fgets($this->socket, 512));

		return "";
	}

	/**
	 * Send raw data to the server and log it to the console
	 *
	 * @param string $msg Message(s) to log. If line breaks are found, the message will be split and each message will be processed separately
	 */
	public function raw($msg) {
		$lines = explode("\n", $msg);
		foreach ($lines as $line) {
			$send = fputs($this->socket, "$line\n");
			$this->console(" -> $line");

			if (!$send)
				$this->connect();
		}
	}

	/**
	 * Joins the irc channel $channel
	 *
	 * @param $channel
	 */
	public function join($channel) {
		$this->raw("JOIN :$channel");
	}

	/**
	 * Save a new network name for this bot
	 *
	 * @param string $network
	 * @return bool True on success, false on failure
	 */
	public function setNetwork($network) {
		return $this->setProperty("network", $network, "is_string");
	}

	/**
	 * Save the irc server address for this bot
	 *
	 * @param string $host
	 * @return bool True on success, false on failure
	 */
	public function setHost($host) {
		return $this->setProperty("host", $host, "is_string");
	}

	/**
	 * @param string $server The name of the server the bot ended up connecting to
	 * @return bool True on success, false on failure
	 */
	public function setServer($server) {
		return $this->setProperty("server", $server, "is_string");
	}

	/**
	 * Save the irc server port for this bot
	 *
	 * @param int $port
	 * @return bool True on success, false on failure
	 */
	public function setPort($port) {
		$validPort = function ($port) {
			return is_numeric($port) && $port > 0;
		};
		return $this->setProperty("port", $port, $validPort);
	}

	/**
	 * Save the main nickname for this bot
	 * Note: This does not change the bot's nickname on the server
	 *
	 * @param string $nickname
	 * @return bool True on success, false on failure
	 */
	public function setNickname($nickname) {
		return $this->setProperty("nickname", $nickname, "is_string");
	}

	/**
	 * Save the alternate nickname for this bot. This will be used if the main nickname is taken
	 * Note: This does not change the bot's nickname on the server
	 *
	 * @param string $alternateNickname
	 * @return bool True on success, false on failure
	 */
	public function setAlternateNickname($alternateNickname) {
		return $this->setProperty("alternateNickname", $alternateNickname, "is_string");
	}

	/**
	 * Save this bot's address on the irc server
	 *
	 * @param string $address
	 * @return bool True on success, false on failure
	 */
	public function setAddress($address) {
		return $this->setProperty("address", $address, "is_string");
	}

	/**
	 * Set the list of channels this bot autojoins on connect
	 * Note: This will not immediately join any new channels
	 *
	 * @param Array $channels An array of channel names
	 * @return bool True on success, false on failure
	 */
	public function setDefaultChannels($channels) {
		return $this->setPropertyArray("defaultChannels", $channels, "is_string");
	}

	/**
	 * Set the list of commands this bot sends to the server upon connecting
	 * Note: This will not immediately execute any commands
	 *
	 * @param Array $onConnect An array of command strings
	 * @return bool True on success, false on failure
	 */
	public function setOnConnect($onConnect) {
		return $this->setPropertyArray("onConnect", $onConnect, "is_string");
	}

	/**
	 * Set the list of command prefixes this bot responds to, e.g. "!" in "!command"
	 *
	 * @param Array $commandPrefix An array of prefixes
	 * @return bool True on success, false on failure
	 */
	public function setCommandPrefix($commandPrefix) {
		return $this->setPropertyArray("commandPrefix", $commandPrefix, "is_string");
	}

	/**
	 *
	 * @return string The network name set for this bot
	 */
	public function getNetwork() {
		return $this->network;
	}

	/**
	 * @return string The server address this bot is set to connect to
	 */
	public function getHost() {
		return $this->host;
	}

	/**
	 * @return string The server address the bot connected to
	 */
	public function getServer() {
		return $this->server;
	}

	/**
	 * @return int The port to connect to with $this->host
	 */
	public function getPort() {
		return $this->port;
	}

	/**
	 * @return string The bot's main nickname
	 */
	public function getNickname() {
		return $this->nickname;
	}

	/**
	 * @return string The bot's alternate nickname, to be used if the main nickname is taken
	 */
	public function getAlternateNickname() {
		return $this->alternateNickname;
	}

	/**
	 * @return string This bot's address on the irc server
	 */
	public function getAddress() {
		return $this->address;
	}

	/**
	 * @return array An array of channels this bot joins upon connecting
	 */
	public function getDefaultChannels() {
		return $this->defaultChannels;
	}

	/**
	 * @return array An array of irc commands this bot executes upon connecting
	 */
	public function getOnConnect() {
		return $this->onConnect;
	}

	/**
	 * @return string An array of command prefixes that this bot responds to
	 */
	public function getCommandPrefix() {
		return $this->commandPrefix;
	}

	/**
	 * @return Users
	 */
	public function getUsers() {
		return $this->users;
	}

	/**
	 * @return Channels
	 */
	public function getChannels() {
		return $this->channels;
	}

	/**
	 * Gets one of this bot's modules for external use
	 *
	 * @param string $module The class name of the module to get
	 * @return Module|bool The module class matching $module, or false on failure
	 */
	public function getModule($module) {
		if (isset($this->modules[$module]) && $this->modules[$module] instanceof Module)
			return $this->modules[$module];

		return false;
	}

	/**
	 * Instantiate a new instance of a module and save it
	 *
	 * @param string $class The class name of the module
	 * @param string $namespace If applicable, the class's namespace
	 * @throws IRCBotException If the class doesn't exist or is not a subclass of Module
	 */
	public function loadModule($class, $namespace = "") {
		if (!is_subclass_of("$namespace\\$class", "\\Module"))
			throw new IRCBotException("loadModule: $namespace\\$class does not exist or does not extend \\Module.");

		$qualifiedName = "$namespace\\$class";
		$module = new $qualifiedName($this, $class);
		$this->modules[$class] = $module;
	}

	/**
	 * This method is called on any IRC event to send the info to modules and give them the proper chance to respond
	 *
	 * @param string $function The name of the function relevant to the IRC event, e.g. "privmsg"
	 * @param Object|float|null $msg If available, send the IRCMessage object that was created from this event, or other relevant information
	 */
	public function sendToModules($function, $msg = null) {
		//	These modules will receive the information first, if any relevant pre-processing needs to be done
		$priority = array("Core");

		//	Send event to priority modules
		foreach ($priority as $module) {
			if (isset($this->modules[$module]))
				$this->sendToModule($this->modules[$module], $function, $msg);
		}

		//	Send event to all other modules
		foreach ($this->modules as $name => $module) {
			//	Skip the priority modules we already called
			if (!in_array($name, $priority))
				$this->sendToModule($module, $function, $msg);
		}
	}

	/**
	 * A helper for sendToModules that handles an individual module
	 *
	 * @param Module $module
	 * @param string $function The name of the function relevant to the IRC event, e.g. "privmsg"
	 * @param Object|float|null $msg If available, send the IRCMessage object that was created from this event, or other relevant information
	 */
	private function sendToModule($module, $function, $msg = null) {
		//	These events don't require an IRCMessage
		$noParameters = array("connect", "shutdown");

		//	Attempt to call the method while handling errors
		if (method_exists($module, $function)) {
			try {
				if (in_array($function, $noParameters))
					$module->{$function}();
				elseif ($msg)
					$module->{$function}($msg);
			}

			catch (Exception $exception) {
				$this->console(sprintf("%s: %s", get_class($exception), $exception->getMessage()));
			}
		}

	}

	/**
	 * Output a string to the console for display
	 *
	 * @param string $string
	 */
	public function console($string) {
		fwrite(STDOUT, "$string\n\n");
	}

	/**
	 * Restart the bot program
	 *
	 * @param string $message Optional quit message
	 */
	public function restart($message = "") {
		$this->raw("QUIT :$message");
		sleep(1);
		pclose(popen("start php -f Utsubot.php $this->network", "r"));
		exit;
	}

	/**
	 * Messages the target on IRC with text. Splits text up into multiple messages if total data length exceeds 512 bytes (maximum transferable).
	 * Also splits line breaks into multiple messages, and will carry over incomplete control codes.
	 *
	 * @param string $target User or channel to send message to
	 * @param string|array $text Lines or collection of lines to send
	 * @param bool $action Pass true to send message as an IRC Action (/me)
	 */
	public function message($target, $text, $action = false) {
		//	Split array into multiple messages
		if (is_array($text)) {
			foreach ($text as $newText)
				$this->message($target, $newText, $action);
			return;
		}

		//	Empty line
		if (strlen(trim(IRCUtility::stripControlCodes($text))) == 0)
			return;

		/*	Maximum size of irc command is 512 bytes
		 * 	Subtract 1 for leading ":", nicknam length, 1 for "!", address length, 9 for " PRIVMSG ", target length, 2 for " :", 2 for "\r\n"	*/
		$maxlen = 512 - 1 - strlen($this->nickname) - 1 - strlen($this->address) - 9 - strlen($target) - 2 - 2;
		//	9 extra characters for \x01ACTION \x01
		if ($action)
			$maxlen -= 9;

		//	Split line breaks into multiple messages
		if (strpos($text, "\n") !== false) {
			$textArray = explode("\n", $text);
			$this->message($target, $textArray, $action);
			return;
		}

		$words = explode(" ", $text);
		$builtString = "";

		//	Loop through words
		for ($i = 0, $wordCount = count($words); $i < $wordCount; $i++) {
			//	Build string
			$builtString .= $words[$i]. " ";

			//	If the next word would break the 512 byte limit, or we're out of words, output the string and clear it
			if ((isset($words[$i+1]) && strlen($builtString. $words[$i+1]) > $maxlen) || !isset($words[$i+1])) {
				//	Cut off trailing space
				$sendString = substr($builtString, 0, -1);

				//	Send data for action or regular message, and log to console
				if ($action) {
					fputs($this->socket, "PRIVMSG $target :\x01ACTION $sendString\x01\n");
					$this->console(" *-> $target: * $sendString");
				}
				else {
					fputs($this->socket, "PRIVMSG $target :$sendString\n");
					$this->console(" -> $target: $sendString");
				}

				//	Start next line with control codes continued
				$builtString = $this->getNextLinePrefix($sendString);
			}
		}

	}

	/**
	 * Calls $this->message, but sends the command as a CTCP ACTION (/me)
	 *
	 * @param string $target User or channel to send message to
	 * @param string|array $text Lines or collection of lines to send
	 */
	public function action($target, $text) {
		$this->message($target, $text, true);
	}

	public function notice($target, $text) {
		$this->raw("NOTICE $target :$text");
	}

	public function ctcp($target, $text) {
		$this->message($target, "\x01$text\x01");
	}

	public function ctcpReply($target, $type, $response) {
		$this->notice($target, "\x01$type $response\x01");
	}

	/**
	 * When forced to split an IRC message up across multiple lines, this function will determine which control codes need to be continued
	 *
	 * @param string $message The IRC message
	 * @return string The set of control codes that represent the state of the message at the end of the string
	 */
	private function getNextLinePrefix($message) {
		//	Bold, reverse, italic, underline respectively
		$controlCodes = array(2 => false, 22 => false, 29 => false, 31 => false);
		//	Denotes colored text
		$colorCode = chr(3);
		//	Clears all formatting
		$clearCode = chr(15);

		//	Initialize vars
		$colorPrefix = false;
		$nextLinePrefix = $background = $foreground = "";

		//	Loop through every character
		for ($j = 0, $length = strlen($message); $j < $length; $j++) {
			$character = mb_substr($message, $j, 1);

			//	Clear all formatting
			if ($character == $clearCode) {
				$clear = true;
				$colorPrefix = false;
				$foreground = $background = "";
			}
			//	No clearing for this iteration
			else
				$clear = false;

			//	Loop through control codes to activate or deactivate
			foreach ($controlCodes as $code => $toggle) {
				if ($clear)
					$controlCodes[$code] = false;

				elseif ($character == chr($code))
					$controlCodes[$code] = !($toggle);
			}

			//	Begin to parse colors
			if ($character == $colorCode) {
				//	Default to the code signifying the end of color
				if ($colorPrefix)
					$colorPrefix = false;

				$foreground = $background = "";
				//	Check the next character for a number to represent a color index for the foreground
				if ($j + 1 < $length && is_numeric($next = mb_substr($message, $j + 1, 1))) {
					$foreground = $next;
					//	Advance character pointer
					$j++;

					//	Same for next potential foreground digit
					if ($j + 1 < $length && is_numeric($next = mb_substr($message, $j + 1, 1))) {
						$foreground .= $next;
						$j++;

						//	Check for background, if present, separated from foreground by comma
						if ($j + 2 < $length && mb_substr($message, $j + 1, 1) == "," && is_numeric($next = mb_substr($message, $j + 2, 1))) {
							$background = $next;
							$j += 2;

							//	Next potential background digit
							if ($j + 1 < $length && is_numeric($next = mb_substr($message, $j + 1, 1))) {
								$background .= $next;
								$j++;
							}

						}
					}

					//	Matched at least one digit, so enable colors
					$colorPrefix = true;
				}
			}

		}

		//	Whichever codes ended up as true will continue to the next line
		foreach ($controlCodes as $code => $toggle) {
			if ($toggle)
				$nextLinePrefix .= chr($code);
		}

		//	Include color if necessary
		if ($colorPrefix && is_numeric($foreground)) {
			$nextLinePrefix .= $colorCode. sprintf("%02d", $foreground);

			//	Optionally include background if necessary
			if (is_numeric($background))
				$nextLinePrefix .= sprintf(",%02d", $background);
		}

		return $nextLinePrefix;
	}

	/**
	 * Print a variable's contents to a file (for debug purposes)
	 *
	 * @param string $filename
	 * @param mixed $data
	 */
	public function saveToFile($filename, $data) {
		ob_start();
		print_r($data);

		if (file_put_contents($filename, str_replace("\n", "\r\n", ob_get_contents())))
			$this->console("Contents successfully written to $filename.");
		else
			$this->console("Error writing contents to $filename.");

		ob_end_clean();
	}
}
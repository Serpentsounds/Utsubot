<?php
/**
 * MEGASSBOT - Module.php
 * User: Benjamin
 * Date: 10/11/14
 */

namespace Utsubot;


class ModuleException extends \Exception {}

/** @property IRCBot */
abstract class Module {

	protected $IRCBot;
	protected $triggers = array();
	protected $timerQueue = array();
	protected $path = "";

	protected static $settingsFile = "../conf/settings.ini";

	public function __construct(IRCBot $IRCBot) {		
		$this->IRCBot = $IRCBot;
	}

	/**
	 * Log a message from this module to the console
	 *
	 * @param string $msg
	 */
	protected function status(string $msg) {
		$this->IRCBot->console(get_class($this). ": $msg\n");
	}

	/**
	 * Shorthand function to reply to channel/user in commands
	 *
	 * @param IRCMessage $msg
	 * @param $text
	 */
	protected function respond(IRCMessage $msg, string $text) {
		$this->IRCBot->message($msg->getResponseTarget(), $text);
	}

	/**
	 * Shorthand function to require the existence of classes before executing a command
	 *
	 * @param string $class
	 * @throws ModuleException
	 */
	protected function _require(string $class) {
		if (!class_exists($class))
            throw new ModuleException("This action requires $class to be loaded.");
	}

    /**
     * Shorthand function to require a certain number of parameters before proceeding with command processing
     *
     * @param IRCMessage $msg
     * @param int        $count
     * @throws ModuleException
     */
    protected function requireParameters(IRCMessage $msg, int $count = 1) {
        if (count($msg->getCommandParameters()) < $count)
            throw new ModuleException("This command requires at least $count parameter(s).");
    }

	/**
	 * Return a reference to another Module object also loaded into the bot
	 *
	 * @param string $module Name of module class
	 * @return Module
	 * @throws ModuleException If the specified module is not loaded
	 */
	protected function externalModule(string $module): Module {
		if (class_exists($module) && ($instance = $this->IRCBot->getModule($module)) instanceof $module)
			return $instance;

		throw new ModuleException("Module $module is not loaded.");
	}

	/**
	 * Parse the format of module exception to output the error to the user
	 *
	 * @param \Exception $e
	 * @param IRCMessage $msg Needed to determine the nature of the command, and if a nickname needs to be addressed
	 * @return string The formatted response
	 */
	protected function parseException(\Exception $e, IRCMessage $msg): string {
		#$response = sprintf("A %s error occured (%s).", get_class($e), $e->getMessage());
        $response = $e->getMessage();
        if (preg_match("/(.*?)Exception$/", get_class($e), $match))
            $response = "({$match[1]}) $response";

		//	If the error occured in a public channel, address the user directly for clarity
		if (!$msg->inQuery())
			$response = "{$msg->getNick()}: $response";

		return $response;
	}

	/**
	 * Given an IRCMessage and command triggers, call the necessary methods and process errors
	 *
	 * @param IRCMessage $msg
	 */
	protected function parseTriggers(IRCMessage $msg) {
		if (!$msg->isCommand())
			return;

		$triggers = $this->triggers;
		$cmd = strtolower($msg->getCommand());
		//	Triggered a command
		if (isset($triggers[$cmd]) && method_exists($this, $triggers[$cmd])) {
			try {
				//	Call command
				call_user_func(array($this, $triggers[$cmd]), $msg);
				$msg->respond($triggers[$cmd]);
			}
			//	Output error
			catch (\Exception $e) {
				$response = $this->parseException($e, $msg);
				$this->respond($msg, $response);
			}
		}
	}

	/**
	 * (Re)load an API key from file
	 *
	 * @param string $type
	 * @return string
	 * @throws ModuleException
	 */
	public static function loadAPIKey(string $type): string {
		if (!file_exists(static::$settingsFile))
			throw new ModuleException("Unable to retrieve $type API Key because ". static::$settingsFile. " does not exist.");

		$settings = parse_ini_file(static::$settingsFile, true);
		if (!isset($settings['APIKeys']))
			throw new ModuleException("Unable to retrieve $type API Key because there are no API Keys saved.");

		if (!isset($settings['APIKeys'][$type]))
			throw new ModuleException("There is no API Key entry for $type.");

		return $settings['APIKeys'][$type];
	}

    /**
     * Triggered when module is loaded, but before connecting
     */
	public function startup(){}
	public function shutdown(){}
	public function connect(){}
	public function disconnect(){}

    /**
     * Execute timed commands
     * Triggered every time the bot polls for data
     *
     * @param $time
     */
	public function time(float $time){
		foreach ($this->timerQueue as $key => $timer) {
			if ($time >= $timer['time']) {
				eval($timer['command']);
				unset($this->timerQueue[$key]);
			}
		}
	}

	public function ping(IRCMessage $msg){}
	public function error(IRCMessage $msg){}

    /**
     * Parse commands
     *
     * @param IRCMessage $msg
     */
	public function privmsg(IRCMessage $msg) {
		$this->parseTriggers($msg);
	}

	public function notice(IRCMessage $msg){}
	public function ctcp(IRCMessage $msg){}
	public function ctcpResponse(IRCMessage $msg){}

	public function mode(IRCMessage $msg){}
	public function topic(IRCMessage $msg){}
	public function join(IRCMessage $msg){}
	public function part(IRCMessage $msg){}
	public function quit(IRCMessage $msg){}
	public function nick(IRCMessage $msg){}

	public function raw(IRCMessage $msg){}

	public function user(User $user){}
	public function channel(Channel $channel){}

}
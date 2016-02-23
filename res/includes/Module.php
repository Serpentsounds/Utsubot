<?php
/**
 * MEGASSBOT - Module.php
 * User: Benjamin
 * Date: 10/11/14
 */

class ModuleException extends Exception {}

/** @property IRCBot */
abstract class Module {

	protected $IRCBot;
	protected $triggers = array();
	protected $timerQueue = array();
	protected $path = "";

	public function __construct(IRCBot $IRCBot, $path = "") {
		if (!$path)
			$path = "includes/modules/". get_class($this). "/";

		$this->IRCBot = $IRCBot;
		$this->path = $path;
	}

	/**
	 * Log a message from this module to the console
	 *
	 * @param string $msg
	 */
	public function status($msg) {
		$this->IRCBot->console(get_class($this). ": $msg\n");
	}

	/**
	 * Return a reference to another Module object also loaded into the bot
	 *
	 * @param string $module Name of module class
	 * @param string $namespace Namespace of module, if applicable
	 * @return bool|Module Module object, or false if it doesn't exist
	 */
	protected function externalModule($module, $namespace = "") {
		$name = ($namespace) ? "$namespace\\$module" : $module;
		if (class_exists($name) && ($instance = $this->IRCBot->getModule($module)) instanceof $name)
			return $instance;

		return false;
	}

	/**
	 * Parse the format of module exception to output the error to the user
	 *
	 * @param Exception $e
	 * @param IRCMessage $msg Needed to determine the nature of the command, and if a nickname needs to be addressed
	 * @return string The formatted response
	 */
	protected function parseException(Exception $e, IRCMessage $msg) {
		$response = $e->getMessage();
		//	Extract message from 'Class::method: Response' format
		if (preg_match('/^([^:]+)::([^:]+): (.+)/', $response, $match))
			$response = $match[3];

		//	If the error occured in a public channel, address the user directly for clarity
		if (!$msg->inQuery())
			$response = $msg->getNick(). ": ". $response;

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

				$permission = $this->externalModule("Permission");
				if ($permission instanceof Permission && !($permission->hasPermission($msg, $triggers[$cmd])))
					return;

				//	Call command
				call_user_func(array($this, $triggers[$cmd]), $msg);
				$msg->respond($triggers[$cmd]);
			}
			//	Output error
			catch (Exception $e) {
				$response = $this->parseException($e, $msg);
				$this->IRCBot->message($msg->getResponseTarget(), $response);
			}
		}
	}

	public static function getAPIKey($type) {
		if (!file_exists("settings.ini"))
			throw new ModuleException("Unable to retrieve $type API Key because settings.ini does not exist.");

		$settings = parse_ini_file("settings.ini", true);
		if (!isset($settings['APIKeys']))
			throw new ModuleException("Unable to retrieve $type API Key because there are no API Keys saved.");

		if (!isset($settings['APIKeys'][$type]))
			throw new ModuleException("There is no API Key entry for $type.");

		return $settings['APIKeys'][$type];
	}

	public function startup(){}
	public function shutdown(){}
	public function connect(){}
	public function disconnect(){}
	public function time($time){
		foreach ($this->timerQueue as $key => $timer) {
			if ($time >= $timer['time']) {
				eval($timer['command']);
				unset($this->timerQueue[$key]);
			}
		}
	}

	public function ping(IRCMessage $msg){}
	public function error(IRCMessage $msg){}

	public function privmsg(IRCMessage $msg) {
		$this->parseTriggers($msg);
	}

	public function notice(IRCMessage $msg){}
	public function ctcp(IRCMessage $msg){}
	public function ctcpResponse(IRCMessage $msg){}

	public function mode(IRCMessage $msg){}
	public function join(IRCMessage $msg){}
	public function part(IRCMessage $msg){}
	public function quit(IRCMessage $msg){}
	public function nick(IRCMessage $msg){}

	public function raw(IRCMessage $msg){}

	public function user(User $user){}
	public function channel(Channel $channel){}

}
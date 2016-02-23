<?php
/**
 * Utsubot - Logger.php
 * User: Benjamin
 * Date: 30/11/2014
 */

Class LoggerException extends Exception {}

Class Logger extends Module {
	private $interface;

	/**
	 * Create interface and triggers upon construct
	 *
	 * @param IRCBot $irc
	 */
	public function __construct(IRCBot $irc) {
		parent::__construct($irc);

		$this->interface = new DatabaseInterface("utsubot");
		$this->triggers = array(
			'logs' => "logs"
		);
	}

	/**
	 * Log commands that have triggered another module
	 *
	 * @param IRCMessage $msg
	 * @throws LoggerException If log() fails
	 */
	public function privmsg(IRCMessage $msg) {
		parent::privmsg($msg);

		//	Log command
		if ($msg->isCommand() && $msg->responded()) {
			$channel = ($msg->inChannel()) ? $msg->getResponseTarget() : "";

			$userID = null;
			$users = $this->IRCBot->getUsers();

			//	Get user ID if applicable, to put into database
			$user = $users->confirmUser($msg->getNick(). "!". $msg->getIdent(). "@". $msg->getFullHost());
			$accounts = $this->externalModule("Accounts");
			if ($accounts instanceof Accounts)
				$userID = $accounts->getAccountIDByUser($user);

			$this->log(strtolower($msg->responded()), $userID, $channel);
		}
	}

	/**
	 * Log use of a oommand. User and Channel are optional.
	 *
	 * @param string $command
	 * @param int|string $user Account name or ID number
	 * @param string $channel
	 * @return bool True on success
	 * @throws LoggerException If no command is given, or database insert fails
	 */
	private function log($command, $user, $channel) {
		//	Command name is the only requirement
		if (!strlen($command))
			throw new LoggerException("Logger::log: Command can not be blank.");

		//	Part of SQL query
		$values = array("?");
		$parameters = array($command);

		//	Specify User, if applicable
		if (strlen($user)) {
			$values[] = "?";
			$parameters[] = $user;
		}
		else
			$values[] = "null";

		//	Specify channel, if applicable
		if ($channel) {
			$values[] = "?";
			$parameters[] = $channel;
		}
		else
			$values[] = "null";

		$value = implode(", ", $values);
		$query = "INSERT INTO `command_logs` (`command`, `user_id`, `channel`) VALUES ($value)";
		
		$rowCount = $this->interface->query($query, $parameters);

		if (!$rowCount)
			throw new LoggerException("Logger::log: Error logging '$command' by '$user' in '$channel'.");

		return true;
	}

	/**
	 * Get a list of logged uses matching given parameters. All parameter values are optional, and can be ignored by passing an empty string
	 *
	 * @param string $command
	 * @param int|string $user Account ID number
	 * @param string $channel
	 * @return array An array of matching instances, where each element is an array containing command, user, and channel
	 */
	public function getLogs($command, $user, $channel) {
		$constraints = $parameters = array();
		//	Search for a command
		if (strlen($command)) {
			$constraints[] = "`command`=?";
			$parameters[] = $command;
		}
		//	Search for command(s) done by a specific account
		if (strlen($user)) {
			$constraints[] = "`user_id`=?";
			$parameters[] = $user;
		}
		//	Search for commands done in a specific channel
		if ($channel) {
			$constraints[] = "`channel`=?";
			$parameters[] = $channel;
		}

		$query = "SELECT * FROM `command_logs`";
		if (count($constraints))
			$query .= " WHERE ". implode(" AND ", $constraints);

		return $this->interface->query($query, $parameters);
	}

	/**
	 * User interface for getting log statistics
	 *
	 * @param IRCMessage $msg
	 * @throws ModuleException If parameters are invalid
	 */
	public function logs(IRCMessage $msg) {
		$parameters = $msg->getCommandParameters();

		//	Get login if available, some command modes require it
		$userID = null;
		$users = $this->IRCBot->getUsers();
		$user = $users->confirmUser($msg->getNick(). "!". $msg->getIdent(). "@". $msg->getFullHost());

		$accounts = $this->externalModule("Accounts");
		if ($accounts instanceof Accounts)
			$userID = $accounts->getAccountIDByUser($user);

		//	No parameters specified, return all logged commands by this user
		if (!isset($parameters[0]) || !strlen($parameters[0])) {
			if (!is_int($userID))
				throw new ModuleException("Misc::logs: You must be logged in to show your personal log stats.");

			$logs = $this->getLogs("", $userID, "");
			if (!count($logs))
				throw new ModuleException("Misc::logs: You have no logs on record.");

			//	Count each command separately
			$count = array();
			foreach ($logs as $log)
				@$count[$log['command']]++;

			//	Sort and format array
			arsort($count);
			array_walk($count, function (&$val, $key) {
					$val = "$key: $val";
				});

			$this->IRCBot->message($msg->getResponseTarget(), "Usage summary for ". IRCUtility::bold($msg->getNick()). ": ". implode(", ", $count));
		}

		//	A parameter was given
		else {
			$cmd = strtolower($parameters[0]);

			//	'all' was appended to the command, get public usage stats
			$all = false;
			if (isset($parameters[1]) && strtolower($parameters[1]) == "all")
				$all = true;

			//	Personal usage stats
			if (!$all) {
				if (!is_int($userID))
					throw new ModuleException("Misc::logs: You must be logged in to show your personal log stats.");

				$logs = $this->getLogs($cmd, $userID, "");
				if (!is_array($logs))
					throw new ModuleException("Misc::logs: You have no logs on record for '$cmd'.");

				$this->IRCBot->message($msg->getResponseTarget(), $msg->getNick(). " has used '$cmd' ". IRCUtility::bold(count($logs)). " times.");
			}

			//	Public usage stats
			else {
				if (!($users instanceof Users))
					throw new ModuleException("Misc::logs: Users module not loaded, unable to list usages.");

				$logs = $this->getLogs($cmd, "", "");
				if (!count($logs))
					throw new ModuleException("Misc::logs: There are no logs on record for '$cmd'.");

				//	Count each user separately
				$count = array();
				foreach ($logs as $log)
					@$count[$log['user_id']]++;

				//	Sort users and prepare to get nicknames
				arsort($count);
				$return = array();

				//	Get "Top online users" by attempting to match the list of user IDs to logged in accounts
				foreach ($count as $userID => $used) {
					if ($accounts instanceof Accounts && ($user = $accounts->getUserByAccountID($userID)) && $user instanceof User)
						$return[] = $user->getNick(). ": $used";

					//	Max 5 users on leaderboard
					if (count($return) == 5)
						break;
				}

				$this->IRCBot->message($msg->getResponseTarget(), IRCUtility::bold($cmd). " has been used ". IRCUtility::bold(count($logs)). " times.");
				//	There were some nick matching results
				if (count($return))
					$this->IRCBot->message($msg->getResponseTarget(), "Top online users: ". implode(", ", $return));
			}
		}
	}
}
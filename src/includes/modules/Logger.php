<?php
/**
 * Utsubot - Logger.php
 * User: Benjamin
 * Date: 30/11/2014
 */

declare(strict_types = 1);

namespace Utsubot\Logger;
use Utsubot\Accounts\ModuleWithAccounts;
use Utsubot\{
    IRCBot,
    IRCMessage,
    Trigger,
    Users,
    User,
    ModuleException,
    DatabaseInterface,
    MySQLDatabaseCredentials
};
use function Utsubot\bold;

Class LoggerException extends ModuleException {}

Class Logger extends ModuleWithAccounts {

	private $interface;

	/**
	 * Create interface and triggers upon construct
	 *
	 * @param IRCBot $irc
	 */
	public function __construct(IRCBot $irc) {
		$this->_require("Utsubot\\DatabaseInterface");
		$this->_require("Utsubot\\MySQLDatabaseCredentials");

		parent::__construct($irc);

		$this->interface = new DatabaseInterface(MySQLDatabaseCredentials::createFromConfig("utsubot"));
		
		$this->addTrigger(new Trigger("logs", array($this, "logs")));
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

			$users = $this->IRCBot->getUsers();

			//	Get user ID if applicable, to put into database
			$user = $users->createIfAbsent($msg->getNick() . "!" . $msg->getIdent() . "@" . $msg->getFullHost());
            $userID = $this->getAccountIDByUser($user);

			$this->log(strtolower($msg->responded()), $userID, $channel);
		}
	}

	/**
	 * Log use of a oommand. User and Channel are optional.
	 *
	 * @param string $command
	 * @param int $user Account name or ID number
	 * @param string $channel
	 * @return bool True on success
	 * @throws LoggerException If no command is given, or database insert fails
	 */
	private function log(string $command, int $user = null, string $channel = null): bool {
		//	Command name is the only requirement
		if (!strlen($command))
			throw new LoggerException("Command can not be blank.");

		//	Part of SQL query
		$values = array("?");
		$parameters = array($command);

		//	Specify User, if applicable
		if (is_int($user)) {
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
			throw new LoggerException("Error logging '$command' by '$user' in '$channel'.");

		return true;
	}

	/**
	 * Get a list of logged uses matching given parameters. All parameter values are optional, and can be ignored by passing an empty string
	 *
	 * @param string $command
	 * @param int $user Account ID number
	 * @param string $channel
	 * @return array An array of matching instances, where each element is an array containing command, user, and channel
	 */
	public function getLogs(string $command, int $user, string $channel): array {
		$constraints = $parameters = array();
		//	Search for a command
		if (strlen($command)) {
			$constraints[] = "`command`=?";
			$parameters[] = $command;
		}
		//	Search for command(s) done by a specific account
		if (is_int($user)) {
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
	 * @throws LoggerException If parameters are invalid
	 */
	public function logs(IRCMessage $msg) {
		$parameters = $msg->getCommandParameters();

		//	Get login if available, some command modes require it
		$users = $this->IRCBot->getUsers();
		$user = $users->createIfAbsent($msg->getNick() . "!" . $msg->getIdent() . "@" . $msg->getFullHost());
        $userID = $this->getAccountIDByUser($user);

		//	No parameters specified, return all logged commands by this user
		if (!isset($parameters[0]) || !strlen($parameters[0])) {
			if (!is_int($userID))
				throw new LoggerException("You must be logged in to show your personal log stats.");

			$logs = $this->getLogs("", $userID, "");
			if (!count($logs))
				throw new LoggerException("You have no logs on record.");

			//	Count each command separately
			$count = array();
			foreach ($logs as $log)
				@$count[$log['command']]++;

			//	Sort and format array
			arsort($count);
			array_walk($count, function (&$val, $key) {
					$val = "$key: $val";
				});

			$this->respond($msg, "Usage summary for ". bold($msg->getNick()). ": ". implode(", ", $count));
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
					throw new LoggerException("You must be logged in to show your personal log stats.");

				$logs = $this->getLogs($cmd, $userID, "");
				if (!is_array($logs))
					throw new LoggerException("You have no logs on record for '$cmd'.");

				$this->respond($msg, $msg->getNick(). " has used '$cmd' ". bold(count($logs)). " times.");
			}

			//	Public usage stats
			else {
				if (!($users instanceof Users))
					throw new LoggerException("Users module not loaded, unable to list usages.");

				$logs = $this->getLogs($cmd, "", "");
				if (!count($logs))
					throw new LoggerException("There are no logs on record for '$cmd'.");

				//	Count each user separately
				$count = array();
				foreach ($logs as $log)
					@$count[$log['user_id']]++;

				//	Sort users and prepare to get nicknames
				arsort($count);
				$return = array();

				//	Get "Top online users" by attempting to match the list of user IDs to logged in accounts
				foreach ($count as $userID => $used) {
					if (($user = $this->getAccounts()->getUserByAccountID($userID)) instanceof User)
						$return[] = $user->getNick(). ": $used";

					//	Max 5 users on leaderboard
					if (count($return) == 5)
						break;
				}

				$this->respond($msg, bold($cmd). " has been used ". bold(count($logs)). " times.");
				//	There were some nick matching results
				if (count($return))
					$this->respond($msg, "Top online users: ". implode(", ", $return));
			}
		}
	}
}
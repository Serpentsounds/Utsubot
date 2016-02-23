<?php
/**
 * Utsubot - Permission.php
 * User: Benjamin
 * Date: 02/12/2014
 */

class Permission extends Module {
	use AccountAccess;

	private $interface;

	/**
	 * Create interface upon construct
	 *
	 * @param IRCBot $irc
	 */
	public function __construct(IRCBot $irc) {
		parent::__construct($irc);

		$this->interface = new DatabaseInterface("utsubot");
		$this->triggers = array(
			'allow'		=> "allow",
			'deny'		=> "deny",
			'unallow'	=> "unallow",
			'undeny'	=> "undeny",
		);
	}

	/**
	 * Add an allow line
	 *
	 * @param IRCMessage $msg
	 * @throws ModuleException If any parameters are invalid, or if allow line exists
	 */
	public function allow(IRCMessage $msg) {
		$this->requireLevel($msg, 75);
		$this->addPermission("allow", $msg);
	}

	/**
	 * Add a deny line
	 *
	 * @param IRCMessage $msg
	 * @throws ModuleException If any parameters are invalid, or if deny line already exists
	 */
	public function deny(IRCMessage $msg) {
		$this->requireLevel($msg, 75);
		$this->addPermission("deny", $msg);
	}

	/**
	 * Remove an allow line
	 *
	 * @param IRCMessage $msg
	 * @throws ModuleException If any parameters are invalid, or if allow line doesn't exist
	 */
	public function unallow(IRCMessage $msg) {
		$this->requireLevel($msg, 75);
		$this->removePermission("allow", $msg);
	}

	/**
	 * Remove a deny line
	 *
	 * @param IRCMessage $msg
	 * @throws ModuleException If any parameters are invalid, or if deny line doesn't exist
	 */
	public function undeny(IRCMessage $msg) {
		$this->requireLevel($msg, 75);
		$this->removePermission("deny", $msg);
	}

	/**
	 * Internal function to translate a user-supplied parameter string into database values
	 *
	 * @param string $type "allow" or "deny"
	 * @param array $parameters Array of command parameters (words)
	 * @return array array(array of query parameters matching up with values, array of sql statement values as either "?" or null)
	 * @throws ModuleException
	 * @throws Exception
	 */
	private function parseParameters($type, $parameters) {
		//	Grab command in question from the front of parameters
		$trigger = array_shift($parameters);
		$channelField = $userField = $nickField = $addressField = $parametersField = "";

		foreach ($parameters as $parameter) {
			//	Parameters should be passed as field:value
			$parts = explode(":", $parameter);
			//	Malformed parameter
			if (count($parts) != 2)
				continue;

			list($constraint, $value) = $parts;

			switch ($constraint) {
				//	Restrict based on channel
				case "channel":
					if (substr($value, 0, 1) == "#")
						$channelField = $value;
					break;

				//	Restrict based on account
				case "user":
					$id = null;

					//	Access Users to get account name
					$users = $this->IRCBot->getUsers();
					$user = $users->get($value);

					//	Find account User is logged into
					if ($user instanceof User) {
						$userField = $this->getAccountIDByUser($user);

						if (!is_int($userField))
							throw new Exception();
					}

					//	User is invalid or not logged in, send control to catch block
					else
						throw new ModuleException("'$value' is not a logged in user.");

				break;

				//	Restrict based on nickname
				case "nickname":
					$nickField = $value;
				break;
				//	Restrict based on address
				case "address":
					$addressField = $value;
				break;
				//	Restrict based on parameters
				case "parameters":
					$parametersField = $value;
				break;

				//	Abort if any parameters are invalid
				default:
					throw new ModuleException("Permission::parseParameters: Not all constraints are valid.");
				break;
			}
		}

		//	Start with all parameters, and weed out blank ones
		$queryParameters = array($trigger, $type);
		$values = array("?", "?", "?", "?", "?", "?", "?");

		//	For every field, either replace the sql query placeholder if blank, or add a value to the parameters if it's there
		if (!$channelField)
			$values[2] = null;
		else
			$queryParameters[] = $channelField;

		if (!strlen($userField))
			$values[3] = null;
		else
			$queryParameters[] = $userField;

		if (!strlen($nickField))
			$values[4] = null;
		else
			$queryParameters[] = $nickField;

		if (!$addressField)
			$values[5] = null;
		else
			$queryParameters[] = $addressField;

		if (!$parametersField)
			$values[6];
		else
			$queryParameters[] = $parametersField;


		return array($queryParameters, $values);
	}

	/**
	 * Used by allow() and deny() to add a row to the db
	 *
	 * @param string $type "allow" or "deny"
	 * @param IRCMessage $msg
	 * @throws ModuleException If any parameters are invalid, or if line exists
	 */
	private function addPermission($type, IRCMessage $msg) {
		list($queryParameters, $values) = $this->parseParameters($type, $msg->getCommandParameters());

		//	Replace null values with "null" to put into database
		array_walk($values, function(&$element) {
			if ($element === null)
				$element = "null";
		});
		$value = implode(", ", $values);

		$rowCount = $this->interface->query(
			"INSERT INTO `command_permission` (`trigger`, `type`, `channel`, `user_id`, `nickname`, `address`, `parameters`) VALUES ($value)",
			$queryParameters);

		if (!$rowCount)
			throw new ModuleException("Permission already exists.");

		$this->IRCBot->message($msg->getResponseTarget(), "Permission has been added.");
	}

	/**
	 * Used by unallow() and undeny() to remove a row from the db
	 *
	 * @param string $type
	 * @param IRCMessage $msg
	 * @throws ModuleException If any parameters are invalid, or if line doesn't exist
	 */
	private function removePermission($type, IRCMessage $msg) {
		list($queryParameters, $values) = $this->parseParameters($type, $msg->getCommandParameters());

		//	Form conditionals for each column
		$columns = array("`trigger`", "`type`", "`channel`", "`user_id`", "`nickname`", "`address`, `parameters`");
		array_walk($values, function(&$element, $key) use ($columns) {
			if ($element === null)
				$element = $columns[$key]. " IS NULL";
			else
				$element = $columns[$key]. "=?";
		});
		$value = implode(" AND ", $values);

		$rowCount = $this->interface->query(
			"DELETE FROM `command_permission` WHERE $value LIMIT 1",
			$queryParameters);

		if (!$rowCount)
			throw new ModuleException("Permission does not exist.");

		$this->IRCBot->message($msg->getResponseTarget(), "Permission has been removed.");
	}

	/**
	 * Check if a user (determined through an IRCMessage) has permission to use a command
	 *
	 * @param IRCMessage $msg
	 * @param string $trigger Command function name
	 * @return bool True or false
	 */
	public function hasPermission(IRCMessage $msg, $trigger) {
		$permission = true;

		$results = $this->interface->query(
			"SELECT * FROM `command_permission` WHERE `trigger`=?",
			array($trigger));

		//	No rows affecting this command
		if (!count($results))
			return $permission;

		//	Sort results to put allows at the end, so they trump denies
		usort($results, function($row1, $row2) {
			if ($row1['type'] == $row2['type'])
				return 0;
			elseif ($row1['type'] == "allow")
				return 1;
			return -1;
		});

		//	Info from IRCMessage
		$inChannel = $msg->inChannel();
		$channel = $msg->getResponseTarget();
		$nick = $msg->getNick();
		$address = "$nick!". $msg->getIdent(). "@". $msg->getFullHost();
		$parameters = $msg->getParameterString();

		//	Attempt to grab user ID for comparison
		$id = null;
		try {
			$users = $this->IRCBot->getUsers();
			$accounts = $this->externalModule("Accounts");
			if ($accounts instanceof Accounts) {
				$user = $users->confirmUser($address);
				$id = $accounts->getAccountIDByUser($user);
			}
		}
		catch (Exception $e) {}

		//	Apply rows 1 by 1
		foreach ($results as $row) {
			//	All 4 of these must be true for the rule to apply. If the db value is NULL, it will automatically apply
			$channelMatch = $userMatch = $nickMatch = $addressMatch = $parameterMatch = false;

			if (!$row['channel'] || ($inChannel && $row['channel'] == $channel))
				$channelMatch = true;

			if (!$row['nickname'] || fnmatch(strtolower($row['nickname']), strtolower($nick)))
				$nickMatch = true;

			if (!$row['address'] || fnmatch($row['address'], $address))
				$addressMatch = true;

			if (!$row['user_id'] || $row['user_id'] == $id)
				$userMatch = true;

			if (!$row['parameters'] || $row['parameters'] != $parameters)
				$parameterMatch = true;

			//	Enforce passing of all 4 checks
			if (!$channelMatch || !$userMatch || !$nickMatch || !$addressMatch || !$parameterMatch)
				continue;

			//	Adjust permission accordingly
			if ($row['type'] == "allow")
				$permission = true;
			elseif ($row['type'] == "deny")
				$permission = false;
		}

		return $permission;
	}

} 
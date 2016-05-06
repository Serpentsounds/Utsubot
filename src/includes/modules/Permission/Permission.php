<?php
/**
 * Utsubot - Permission.php
 * User: Benjamin
 * Date: 02/12/2014
 */

namespace Utsubot\Permission;
use Utsubot\{
    Accounts\AccountsException, ModuleException, IRCBot, IRCMessage, Trigger, DatabaseInterface, MySQLDatabaseCredentials, User
};


class PermissionException extends ModuleException {}

class Permission extends ModuleWithPermission {

    const PERMISSION_ALLOW = 0;
    const PERMISSION_DENY = 1;

    private $interface;

	/**
	 * Create interface upon construct
	 *
	 * @param IRCBot $irc
	 */
	public function __construct(IRCBot $irc) {
        $this->_require("Utsubot\\DatabaseInterface");
		$this->_require("Utsubot\\MySQLDatabaseCredentials");

		parent::__construct($irc);

		$this->interface = new DatabaseInterface(MySQLDatabaseCredentials::createFromConfig("utsubot"));
		
		$this->addTrigger(new Trigger("allow",      array($this, "allow"    )));
		$this->addTrigger(new Trigger("deny",       array($this, "deny"     )));
		$this->addTrigger(new Trigger("unallow",    array($this, "unallow"  )));
		$this->addTrigger(new Trigger("undeny",     array($this, "undeny"   )));
	}

	/**
	 * Add an allow line
	 *
	 * @param IRCMessage $msg
	 * @throws PermissionException If any parameters are invalid, or if allow line exists
	 */
	public function allow(IRCMessage $msg) {
		$this->requireLevel($msg, 75);
		$this->addPermission(self::PERMISSION_ALLOW, $msg);
	}

	/**
	 * Add a deny line
	 *
	 * @param IRCMessage $msg
	 * @throws PermissionException If any parameters are invalid, or if deny line already exists
	 */
	public function deny(IRCMessage $msg) {
		$this->requireLevel($msg, 75);
		$this->addPermission(self::PERMISSION_DENY, $msg);
	}

	/**
	 * Remove an allow line
	 *
	 * @param IRCMessage $msg
	 * @throws PermissionException If any parameters are invalid, or if allow line doesn't exist
	 */
	public function unallow(IRCMessage $msg) {
		$this->requireLevel($msg, 75);
		$this->removePermission(self::PERMISSION_ALLOW, $msg);
	}

	/**
	 * Remove a deny line
	 *
	 * @param IRCMessage $msg
	 * @throws PermissionException If any parameters are invalid, or if deny line doesn't exist
	 */
	public function undeny(IRCMessage $msg) {
		$this->requireLevel($msg, 75);
		$this->removePermission(self::PERMISSION_DENY, $msg);
	}

	/**
	 * Internal function to translate a user-supplied parameter string into database values
	 *
	 * @param int $type allow or deny
	 * @param array $parameters Array of command parameters (words)
	 * @return array array(array of query parameters matching up with values, array of sql statement values as either "?" or null)
	 * @throws PermissionException
	 * @throws \Exception
	 */
	private function parseParameters(int $type, array $parameters) {
        //  Validate type
        switch ($type) {
            case self::PERMISSION_ALLOW:
                $type = "allow";
            break;
            case self::PERMISSION_DENY:
                $type = "deny";
            break;
            default:
                throw new PermissionException("Invalid permission type constant '$type'.");
            break;
        }

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
					$user = $users->search($value);

					//	Find account User is logged into
					if ($user instanceof User) {
						$userField = $this->getAccountIDByUser($user);

						if (!is_int($userField))
							throw new \Exception();
					}

					//	User is invalid or not logged in, send control to catch block
					else
						throw new PermissionException("'$value' is not a logged in user.");

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
					throw new PermissionException("Not all constraints are valid.");
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
			$values[6] = null;
		else
			$queryParameters[] = $parametersField;


		return array($queryParameters, $values);
	}

	/**
	 * Used by allow() and deny() to add a row to the db
	 *
	 * @param int $type
	 * @param IRCMessage $msg
	 * @throws PermissionException If any parameters are invalid, or if line exists
	 */
	private function addPermission(int $type, IRCMessage $msg) {
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
			throw new PermissionException("Permission already exists.");

		$this->respond($msg, "Permission has been added.");
	}

	/**
	 * Used by unallow() and undeny() to remove a row from the db
	 *
	 * @param int $type
	 * @param IRCMessage $msg
	 * @throws PermissionException If any parameters are invalid, or if line doesn't exist
	 */
	private function removePermission(int $type, IRCMessage $msg) {
		list($queryParameters, $values) = $this->parseParameters($type, $msg->getCommandParameters());

		//	Form conditionals for each column
		$columns = array("`trigger`", "`type`", "`channel`", "`user_id`", "`nickname`", "`address`", "`parameters`");
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
			throw new PermissionException("Permission does not exist.");

		$this->respond($msg, "Permission has been removed.");
	}

	/**
	 * Check if a user (determined through an IRCMessage) has permission to use a command
	 *
	 * @param IRCMessage $msg
	 * @param string $trigger Command function name
	 * @return bool True or false
	 */
	public function hasPermission(IRCMessage $msg, string $trigger): bool {
		$permission = true;

		$results = $this->interface->query(
			"SELECT * FROM `command_permission` WHERE `trigger`=?",
			array($trigger));

		//	No rows affecting this command
		if (!$results)
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
		$address = "$nick!{$msg->getIdent()}@{$msg->getFullHost()}";
		$parameters = $msg->getParameterString();

		//	Attempt to grab user ID for comparison
        $users = $this->IRCBot->getUsers();
        $user = $users->createIfAbsent($address);
        try {
            $id = $this->getAccountIDByUser($user);
        }
        catch (AccountsException $e) {
            $id = null;
        }


		//	Apply rows 1 by 1
		foreach ($results as $row) {
			//	All of these must be true for the rule to apply. If the db value is NULL, it will automatically apply
			$channelMatch = $userMatch = $nickMatch = $addressMatch = $parameterMatch = false;

            //  Channel name (exact match)
			if (!$row['channel'] || ($inChannel && $row['channel'] == $channel))
				$channelMatch = true;

            //  Nickname (wildcard match)
			if (!$row['nickname'] || fnmatch(strtolower($row['nickname']), strtolower($nick)))
				$nickMatch = true;

            //  Address (wildcard match)
			if (!$row['address'] || fnmatch($row['address'], $address))
				$addressMatch = true;

            //  Account id (exact match)
			if (!$row['user_id'] || $row['user_id'] == $id)
				$userMatch = true;

            //  Command parameters (wildcard match)
			if (!$row['parameters'] || fnmatch(strtolower($row['parameters']), strtolower($parameters)))
				$parameterMatch = true;

			//	Enforce passing of all checks
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
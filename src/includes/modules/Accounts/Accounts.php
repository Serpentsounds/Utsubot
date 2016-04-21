<?php
/**
 * Utsubot - Account.php
 * User: Benjamin
 * Date: 28/04/2015
 */

namespace Utsubot\Accounts;
use Utsubot\{Module, ModuleException, IRCBot, IRCMessage, User};
use function Utsubot\bold;

class AccountsException extends ModuleException {}

class Accounts extends Module {

	private $interface;
	private $defaultNickCheck = array();
	private $loggedIn = array();

	public function __construct(IRCBot $IRCBot) {
		$this->_require("Utsubot\\Accounts\\AccountsDatabaseInterface");
		$this->_require("Utsubot\\MySQLDatabaseCredentials");

		parent::__construct($IRCBot);
		$this->interface = new AccountsDatabaseInterface();

		$this->triggers = array(
			'login'		=> "login",
			'logout'	=> "logout",
			'register'	=> "register",
			'set'		=> "set",
			'unset'		=> "_unset",
			'settings'	=> "settings",
			'access'	=> "access"
		);
	}

	public function getInterface() {
		return $this->interface;
	}

	/**
	 * Given an IRCMessage and command triggers, call the necessary methods and process errors
	 *
	 * @param IRCMessage $msg
	 */
	protected function parseTriggers(IRCMessage $msg) {
		//	Account modification should only be done in private message
		if (!$msg->inQuery())
			return;

		parent::parseTriggers($msg);
	}

	public function raw(IRCMessage $msg) {
		switch ($msg->getRaw()) {

			/*
			 *	Logged into NickServ, used when verifying default nickname
			 */
			case 307:
			case 330:
				$parameters = $msg->getParameters();
				//	Adjust to format for different raws
				$nick = ($msg->getRaw() == 307) ? $parameters[0] : $parameters[1];

				//	Make sure user is in the verification process
				if (isset($this->defaultNickCheck[$nick])) {
					//	Remove user from list of pending verifications
					$info = $this->defaultNickCheck[$nick];
					unset($this->defaultNickCheck[$nick]);

					//	Make sure that this response corresponds to the recent request, but allow 5 seconds for server latency
					if (time() - $info['time'] <= 5) {
						try {
							$this->interface->setSettings($info['accountID'], "nick", $nick);
							$this->IRCBot->message($nick, "Your default nickname has been saved as ". bold($nick). ".");
						}
						catch (\Exception $e) {
							$this->IRCBot->message($nick, "Unable to save your default nickname in the database. Is it already set?");
						}

					}
				}
			break;

			/*
			 *	End of /WHOIS, report default nickname verification failure
			 */
			case 318:
				$nick = $msg->getParameters()[0];
				//	Make sure user is in the verification process
				if (isset($this->defaultNickCheck[$nick])) {
					unset($this->defaultNickCheck[$nick]);
					$this->IRCBot->message($nick, "Unable to save your default nickname because you are not identified with NickServ.");
				}
			break;
		}
	}

	/**
	 * Search for auto-login entries that can be applied to a User when one is created, and attempt to log them in
	 *
	 * @param User $user
	 * @throws AccountsException
	 */
	public function user(User $user) {
		try {
			//	Account ID to be logged in to
			$accountID = $this->interface->getAutoLogin("{$user->getNick()}!{$user->getAddress()}");
			$this->loginUser($user, $accountID);

			if (!($this->interface->getSettings($accountID, "disablenotify"))) {
				$level = $this->interface->getAccessByID($accountID);
				$username = $this->interface->getUsername($accountID);

				$this->IRCBot->message($user->getNick(), "You have automatically been logged into your account '$username'. Your access level is $level. To disable these notifications, use the command 'set disablenotify'.");
			}
		}
		catch (AccountsException $e) {}
	}

	/**
	 * Attempt to log user in with given credentials
	 *
	 * @param IRCMessage $msg
	 * @throws AccountsException
	 */
	public function login(IRCMessage $msg) {
		$users = $this->IRCBot->getUsers();
		$user = $users->createIfAbsent($msg->getNick() . "!" . $msg->getIdent() . "@" . $msg->getFullHost());
		$parameters = $msg->getCommandParameters();

		//	Not enough parameters
		if (count($parameters) < 2)
			throw new AccountsException("Syntax: LOGIN <username> <password>");
		list($username, $password) = $parameters;

		//	Attempt login, exception thrown if unsuccessful
		$this->interface->verifyPassword($username, $password);
		$accountID = $this->interface->getAccountID($username);
		$this->loginUser($user, $accountID);

		$level = $this->interface->getAccessByID($accountID);
		$this->respond($msg, "Login successful. Your access level is $level.");
	}

	/**
	 * Log out of current account
	 *
	 * @param IRCMessage $msg
	 * @throws AccountsException
	 */
	public function logout(IRCMessage $msg) {
		$users = $this->IRCBot->getUsers();
		$user = $users->createIfAbsent($msg->getNick() . "!" . $msg->getIdent() . "@" . $msg->getFullHost());

		//	Exception if user is not logged in to begin with
		$this->confirmLogin($user);

		if (!$this->logoutUser($user))
			throw new AccountsException("An error occured while logging you out. Please try again.");

		$this->respond($msg, "You have logged out of your account.");
	}

	/**
	 * Register a new account
	 *
	 * @param IRCMessage $msg
	 * @throws AccountsException
	 */
	public function register(IRCMessage $msg) {
		$users = $this->IRCBot->getUsers();
		$user = $users->createIfAbsent($msg->getNick() . "!" . $msg->getIdent() . "@" . $msg->getFullHost());
		$parameters = $msg->getCommandParameters();

		//	Cannot be logged in
		if (is_int($this->getAccountIDByUser($user)))
			throw new AccountsException("You are already registered!");

		//	Not enough parameters
		if (count($parameters) < 2)
			throw new AccountsException("Syntax: REGISTER <username> <password>");
		list($username, $password) = $parameters;

		//	Validate credentials
		if (!is_string($username) || preg_match('/\s/', $username))
			throw new AccountsException("Invalid username format. Pass a string with no whitespace.");
		if (!is_string($password) || preg_match('/\s/', $password))
			throw new AccountsException("Invalid password format. Pass a string with no whitespace.");

		//	Attempt registration
		try {
			$this->interface->register($username, $password);
		}
			//	Insert failed, duplicate username
		catch (\PDOException $e) {
			throw new AccountsException("Username '$username' already exists!");
		}

		//	Success
		$this->respond($msg,"Registration successful. Please remember your username and password for later use: '$username', '$password'. You will now be automatically logged in.");

		//	Add autologin host
		$autoLogin =  "*!*{$msg->getIdent()}@{$msg->getFullHost()}";
		$accountID = $this->interface->getAccountID($username);
		$this->interface->setSettings($accountID, "autologin", $autoLogin);
		//	Automatically login upon registration
		$this->loginUser($user, $accountID);
		$this->respond($msg, "$autoLogin has been added as an autologin host for this account. You will automatically be logged in when connecting from this host. To remove this, please use 'unset autologin'.");


		//	Attempt to automatically set nickname, if it's not already set
		$settings = $this->interface->searchSettings("nick", $msg->getNick());
		if (count($settings))
			$this->respond($msg, "Your nickname could not be linked to your account because it is already linked to another account.");
		else
			$this->setDefaultNick($accountID, $msg->getNick());
	}

	/**
	 * Update account settings
	 *
	 * @param IRCMessage $msg
	 * @throws AccountsException
	 */
	public function set(IRCMessage $msg) {
		$users = $this->IRCBot->getUsers();
		$user = $users->createIfAbsent($msg->getNick() . "!" . $msg->getIdent() . "@" . $msg->getFullHost());

		//	Must be logged in
		$accountID = $this->confirmLogin($user);
		$parameters = $msg->getCommandParameters();

		//	Not enough parameters
		if (!count($parameters))
			throw new AccountsException("Syntax: SET <option> [<value>]");
		$option = $parameters[0];

		//	Setting default nickname
		if ($option == "nick")
			$this->setDefaultNick($accountID, $msg->getNick());

		//	Changing password
		elseif ($option == "password") {
			//	Not enough parameters
			if (!(count($parameters) >= 3))
				throw new AccountsException("Syntax: SET PASSWORD <old password> <new password>");
			list(, $password, $newPassword) = $parameters;

			if (!is_string($newPassword) || preg_match('/\s/', $newPassword))
				throw new AccountsException("Invalid password format. Pass a string with no whitespace.");

			$username = $this->interface->getUsername($accountID);
			$this->interface->verifyPassword($username, $password);
			$this->interface->setPassword($username, $newPassword);
			$this->respond($msg, "Your password has been saved.");
		}

		//	Change another account setting
		else {
			//	Parse <value>
			$value = (count($parameters) > 1) ? implode(" ", array_slice($parameters, 1)) : "";

			//	Exception thrown if settings are invalid or unsuccessful
			$this->interface->setSettings($accountID, $parameters[0], $value);

			$this->respond($msg, "Settings have been saved.");
		}
	}

	/**
	 * Remove account settings
	 *
	 * @param IRCMessage $msg
	 * @throws AccountsException
	 */
	public function _unset(IRCMessage $msg) {
		$users = $this->IRCBot->getUsers();
		$user = $users->createIfAbsent($msg->getNick() . "!" . $msg->getIdent() . "@" . $msg->getFullHost());

		//	Must be logged in
		$accountID = $this->confirmLogin($user);
		$parameters = $msg->getCommandParameters();

		//	Not enough parameters. <value> is an optional parameter, for an exact removal match
		if (!count($parameters))
			throw new AccountsException("Syntax: UNSET <option> [<value>]");

		//	Parse <value>
		$entry = (count($parameters) > 1) ? implode(" ", array_slice($parameters, 1)) : "";

		//	Exception thrown if settings are invalid or unsuccessful
		$this->interface->removeSettings($accountID, $parameters[0], $entry);

		$this->respond($msg, "Settings have been removed.");
	}

	/**
	 * View current account settings
	 *
	 * @param IRCMessage $msg
	 * @throws AccountsException
	 */
	public function settings(IRCMessage $msg) {
		$users = $this->IRCBot->getUsers();
		$user = $users->createIfAbsent($msg->getNick() . "!" . $msg->getIdent() . "@" . $msg->getFullHost());

		//	Must be logged in
		$login = $this->confirmLogin($user);
		$parameters = $msg->getCommandParameters();

		//	Exception thrown if all settings are invalid
		$settings = $this->interface->getSettings($login, $parameters);

		//	Empty set of settings
		if (!$settings) {
			if (!count($parameters))
				throw new AccountsException("You have no account settings saved.");

			throw new AccountsException("You have no account settings saved under those categories.");
		}

		//	Construct reply
		$response = $responseString = array();
		//	List each setting under name of setting
		foreach ($settings as $setting) {
			$key = "{$setting['name']} ({$setting['display']})";
			$response[$key][] = (strlen($setting['value'])) ? $setting['value'] : "enabled";
		}
		//	Convert name => settings[] entries into readable format
		foreach ($response as $setting => $values)
			$responseString[] = "$setting: ". implode(", ", $values);

		$this->respond($msg, implode("; ", $responseString));
	}

	/**
	 * Manage or view account access
	 *
	 * @param IRCMessage $msg
	 * @throws AccountsException
	 */
	public function access(IRCMessage $msg) {
		$users = $this->IRCBot->getUsers();
		$user = $users->createIfAbsent($msg->getNick() . "!" . $msg->getIdent() . "@" . $msg->getFullHost());
		$parameters = $msg->getCommandParameters();

		//	No parameters, return user's access level
		if (empty($parameters)) {
			$level = $this->getAccessByUser($user);
			$this->respond($msg, "Your current level is $level.");
		}
		else {

			switch (strtolower($parameters[0])) {

				/**
				 * 	Give a new account access
				 */
				case "add":
					$userLevel = $this->requireLevel($user, 90);

					//	Require a 3rd parameter as level for add
					if (count($parameters) < 3)
						throw new AccountsException("Syntax: ACCESS ADD <user> <value>");

					list(, $nickname, $level) = $parameters;

					//	Make sure target user is online. Access can only be modified through nickname, not account name.
					if (!(($targetUser = $users->search($nickname)) && $targetUser instanceof User))
						throw new AccountsException("Unable to find user '$nickname'.");

					//	Make sure target user is logged in to an account
					$accountID = $this->getAccountIDByUser($targetUser);
					if (!is_int($accountID))
						throw new AccountsException("User '{$targetUser->getNick()}' is not logged in.");

					//	Prevent modifying somebody with higher access than you
					$targetUserLevel = $this->interface->getAccessByID($accountID);
					if ($targetUserLevel >= $userLevel)
						throw new AccountsException("You do not have permission to modify settings for user '{$targetUser->getNick()}'.");

					if ($level >= $userLevel)
						throw new AccountsException("You do not have permission to grant level $level.");

					//	Attempt to set level. A malformed $level will thrown an exception
					$this->interface->setAccess($accountID, intval($level));

					$this->respond($msg, "Access has been updated for '{$targetUser->getNick()}'.");
				break;

				/**
				 * 	Remove access from an account (reset to level 0)
				 */
				case "remove":
					$userLevel = $this->requireLevel($user, 90);

					//	Not enough parameters
					if (count($parameters) < 2)
						throw new AccountsException("Syntax: ACCESS REMOVE <user>");

					$nickname = $parameters[1];

					//	Make sure target user is online. Access can only be modified through nickname, not account name.
					if (!(($targetUser = $users->search($nickname)) && $targetUser instanceof User))
						throw new AccountsException("Unable to find user '$nickname'.");

					//	Make sure target user is logged in to an account
					$accountID = $this->getAccountIDByUser($targetUser);
					if (!is_int($accountID))
						throw new AccountsException("User '{$targetUser->getNick()}' is not logged in.");

					//	Prevent modifying somebody with higher access than you
					$targetUserLevel = $this->interface->getAccessByID($accountID);
					if ($targetUserLevel >= $userLevel)
						throw new AccountsException("You do not have permission to modify settings for user '{$targetUser->getNick()}'.");

					//	Attempt to set level. A malformed $level will thrown an exception
					$this->interface->setAccess($accountID, 0);

					$this->respond($msg, "Access has been updated for '{$targetUser->getNick()}'.");
				break;

				/**
				 * 	Get the access of an online user
				 */
				case "list":
					//	Not enough parameters, default to user
					if (count($parameters) < 2)
						$nickname = $msg->getNick();
					else
						$nickname = $parameters[1];

					//	Make sure target user is online. Access can only be modified through nickname, not account name.
					if (!(($targetUser = $users->search($nickname)) && $targetUser instanceof User))
						throw new AccountsException("Unable to find user '$nickname'.");

					//	Make sure target user is logged in to an account
					$accountID = $this->getAccountIDByUser($targetUser);
					if (!is_int($accountID))
						throw new AccountsException("User '{$targetUser->getNick()}' is not logged in.");

					$targetUserLevel = $this->interface->getAccessByID($accountID);

					$this->respond($msg, "Access level for '{$targetUser->getNick()}' is $targetUserLevel.");
				break;

				/**
				 * 	Malformed query
				 */
				default:
					//	Not enough parameters
					if (count($parameters) < 2)
						throw new AccountsException("Syntax: ACCESS [ADD|REMOVE|LIST] [<user>] [<value>]");
				break;
			}

		}
	}

	/**
	 * Helper function to begin the default nickname verification upon registration or manual setting
	 *
	 * @param $accountID
	 * @param $nick
	 */
	public function setDefaultNick($accountID, $nick) {
		$this->defaultNickCheck[$nick] = array('time' => time(), 'accountID' => $accountID);
		$this->IRCBot->raw("WHOIS $nick");
	}

	/**
	 * Confirm that a User meets an access level requirement, or abort via exception
	 *
	 * @param User $user
	 * @param int $level Minimum level
	 * @return int The User's level
	 * @throws AccountsException If the level does not meet the minimum, or if the user is not logged in
	 */
	public function requireLevel(User $user, $level) {
		//	Confirm login and get level. May throw exception
		$userLevel = $this->getAccessByUser($user);

		if ($userLevel < $level)
			throw new AccountsException("You need level $level to access that command. Your current access level is $userLevel.");

		return $userLevel;
	}

	/**
	 * Get the access level for a User object. Default 0, unregistered has a level of -1
	 *
	 * @param User $user
	 * @return int -1 if account ID doesn't exist, or their access level otherwise
	 */
	public function getAccessByUser(User $user) {
		$accountID = $this->getAccountIDByUser($user);
		if ($accountID === false)
			return -1;

		return $this->interface->getAccessByID($accountID);
	}

	/**
	 * Fetch the account ID of a User
	 *
	 * @param User $user
	 * @return int|null Account ID, or null if they're not logged in
	 */
	public function getAccountIDByUser(User $user) {
		$id = $user->getId();
		if (isset($this->loggedIn[$id]))
			return $this->loggedIn[$id];

		return null;
	}

	/**
	 * Fetch the nickname of the user current logged in to an account
	 *
	 * @param string $accountID
	 * @return User|bool Nickname or false on failure
	 */
	public function getUserByAccountID($accountID) {
		$index = array_search($accountID, $this->loggedIn);
		if ($index !== false) {
			$users = $this->IRCBot->getUsers();
			return $users->get($index);
		}

		return false;
	}

	/**
	 * Mark a User as being logged in to an account
	 *
	 * @param User $user
	 * @param int $accountID
	 */
	public function loginUser(User $user, $accountID) {
		$this->loggedIn[$user->getId()] = $accountID;
	}

	/**
	 * Logs a User out of their account
	 *
	 * @param User $user
	 * @return bool True on success, false if they're not logged in
	 */
	public function logoutUser(User $user) {
		$id = $user->getId();
		if (isset($this->loggedIn[$id])) {
			unset($this->loggedIn[$id]);
			return true;
		}

		return false;
	}


	/**
	 * Confirm that a User is logged in
	 *
	 * @param User $user
	 * @return int The User's account ID
	 * @throws AccountsException If the User is not logged in
	 */
	public function confirmLogin(User $user) {
		$id = $this->getAccountIDByUser($user);
		if (!is_int($id))
			throw new AccountsException("You are not logged in.");

		return $id;
	}

}
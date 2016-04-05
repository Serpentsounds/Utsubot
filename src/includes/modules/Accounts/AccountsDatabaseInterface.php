<?php
/**
 * Utsubot - AccountsDatabaseInterface.php
 * User: Benjamin
 * Date: 01/05/2015
 */

namespace Utsubot\Accounts;
use Utsubot\{DatabaseInterface, MySQLDatabaseCredentials, ModuleException, DatabaseInterfaceException};
use Utsubot\Web\Weather;

class AccountsDatabaseInterface extends DatabaseInterface {
	//	Settings names => Max number of entries (0 for no limit)
	private static $validSettings = array("autologin" => 5, "disablenotify" => 1, "pinfo" => 1, "weather" => 1, "units" => 1, "nick" => 1, "results" => 1, "safesearch" => 1);

	private $autoLoginCache = array();

	public function __construct() {
		parent::__construct(MySQLDatabaseCredentials::createFromConfig("utsubot"));
		$this->updateAutoLoginCache();
	}

	public function register($username, $password) {
		return $this->query(
			"INSERT INTO `users` (`user`, `password`) VALUES (?, ?)",
			array($username, md5($password)));
	}

	public function setPassword($username, $newPassword) {
		return $this->query(
			"UPDATE `users` SET `password`=? WHERE `user`=? LIMIT 1",
			(array(md5($newPassword), $username)));
	}

	/**
	 * Verify a user/password combination, to perform password protection actions like logging in or changing your password
	 *
	 * @param string $username
	 * @param string $password
	 * @return bool Returns true on success
	 * @throws ModuleException If username doesn't exist, or password is invalid
	 */
	public function verifyPassword($username, $password) {
		$results = $this->query(
			"SELECT `password` FROM `users` WHERE `user`=? LIMIT 1",
			array($username));

		//	No row in database
		if (!count($results))
			throw new ModuleException("Invalid username.");

		//	Password hashes don't match
		elseif ($results[0]['password'] != md5($password))
			throw new ModuleException("Invalid password.");

		return true;
	}

	/**
	 * Update internal list of valid auto-logins
	 * To be performed upon initialization and any change of auto-login settings
	 *
	 * @return bool True if successful, false if no entries
	 * @throws DatabaseInterfaceException
	 */
	public function updateAutoLoginCache() {
		$results = $this->query(
			"	SELECT `uas`.`value`, `u`.`id`
				FROM `users` `u`, `account_settings` `as`, `users_account_settings` `uas`
				WHERE `as`.`name`='autologin' AND `u`.`id`=`uas`.`user_id` AND `as`.`id`=`uas`.`account_settings_id`",
			array());

		if ($results) {
			//	Normalize integers
			foreach ($results as $entry)
				$entry['id'] = intval($entry['id']);

			$this->autoLoginCache = $results;
			return true;
		}

		return false;
	}

	/**
	 * Given a hostname, return an account which permits autologins on that host
	 *
	 * @param string $host The hostname to match
	 * @return int Account id
	 * @throws ModuleException If no entries exist
	 */
	public function getAutoLogin($host) {
		$results = array();
		//	Test wildcard match vs every host
		foreach ($this->autoLoginCache as $row) {
			if (fnmatch($row['value'], $host)) {
				/*	Associate the account with the number of non-wildcard characters in a host. In the event that more than 1 host matches, the account with the highest value is logged in to
					This is to help prevent a user from accidentally being logged into someone else's account, if that person has an ambiguous auto-login mask	*/
				$significantCharacters = strlen(str_replace(array("*", "?", "[", "]"), "", $row['value']));

				//	Only overwrite an account's entry with a higher value
				if (!isset($results[$row['id']]) || $results[$row['id']] < $significantCharacters)
					$results[$row['id']] = $significantCharacters;
			}
		}

		if (!$results)
			throw new ModuleException("No auto-login entries found matching '$host'.");

		//	Place the highest value of significant characters at the front of the array
		arsort($results);
		return array_keys($results)[0];
	}

	/**
	 * Change the bot access level for a username
	 *
	 * @param int $accountID
	 * @param int $level Maximum of 99
	 * @return bool True on success, false if level stayed the same (or other database error)
	 * @throws ModuleException If $level isn't an integer or is above 99
	 */
	public function setAccess($accountID, $level) {
		//	Make sure level is an int, and don't give anyone else root access
		if (!is_int($level) || $level >= 100)
			throw new ModuleException("Level must be an integer below 99.");

		$rowCount = $this->query(
			"UPDATE `users` SET `level`=? WHERE `id`=? LIMIT 1",
			array($level, $accountID));

		if (!$rowCount)
			throw new ModuleException("Access level unchanged.");

		return true;
	}

	/**
	 * Get the bot access level for an account ID
	 *
	 * @param int $accountID
	 * @return int -1 if account ID doesn't exist, or their access level otherwise
	 */
	public function getAccessByID($accountID) {
		$results = $this->query(
			"SELECT `level` FROM `users` WHERE `id`=? LIMIT 1",
			array($accountID));

		if (count($results))
			return $results[0]['level'];

		//	Unregisterd users have a level of -1
		return -1;
	}

	/**
	 * Get the username an account logs in with, given account ID
	 *
	 * @param $accountID
	 * @return string
	 * @throws ModuleException If account doesn't exist
	 */
	public function getUsername($accountID) {
		$results = $this->query(
			"SELECT `user` FROM `users` WHERE `user`=? OR `id`=? LIMIT 1",
			array($accountID, $accountID));

		if (!count($results))
			throw new ModuleException("Invalid account.");

		return $results[0]['user'];
	}

	/**
	 * Get an username's account ID in the database
	 *
	 * @param string $username
	 * @return int|bool Account ID, or false on failure
	 */
	public function getAccountID($username) {
		$results = $this->query(
			"SELECT `id` FROM `users` WHERE `user`=?",
			array($username));

		if (!count($results))
			return false;

		return intval($results[0]['id']);
	}


	/**
	 * Return a list of users who has the given setting set to the given value
	 *
	 * @param $setting
	 * @param $value
	 * @return array Array of account IDs
	 * @throws ModuleException If settings fail validation
	 */
	public function searchSettings($setting, $value) {
		#if (!isset(self::$validSettings[strtolower($setting)]))
		#	throw new ModuleException("Invalid settings passed.");

		return $this->query(
			"	SELECT `uas`.`user_id`
				FROM `users_account_settings` `uas`, `account_settings` `as`
				WHERE `uas`.`value`=? AND `as`.`id`=`uas`.`account_settings_id` AND `as`.`name`=?
				ORDER BY `uas`.`user_id` ASC",
			array($value, $setting));

	}

	/**
	 * Retrieve the account settings for this user
	 *
	 * @param int $accountID
	 * @param string|array $settings The setting to retrieve, or an array of them. If omitted, all settings will be returned
	 * @return array Returns the array of settings (can be empty)
	 * @throws ModuleException If setting names are passed and none of them are valid
	 */
	public function getSettings($accountID, $settings = array()) {
		//	Save constraint variable to be appended to the SQL query
		$constraint = "";

		//	Function must act on an array
		if (!is_array($settings))
			$settings = array($settings);

		//	If there are any settings entries, validate them all
		if (!empty($settings)) {
			$this->validateSettings($settings);
			//	We're looking for a subset of settings, so put them into an SQL constraint
			$constraint = sprintf(" AND `as`.`name` IN (%s)", implode(", ", array_fill(0, count($settings), "?")));
		}

		return $this->query(
			"	SELECT `uas`.`value`, `as`.`name`, `as`.`display`
				FROM `users_account_settings` `uas`, `account_settings` `as`
				WHERE `uas`.`user_id`=? AND `as`.`id`=`uas`.`account_settings_id`$constraint
				ORDER BY `as`.`id` ASC",
			array_merge(array($accountID), $settings)
		);
	}

	/**
	 * Add a settings entry for a user. Call setSettings() instead, and this will be used if necessary.
	 *
	 * @param int $accountID
	 * @param string|array $settings The setting name or array
	 * @param string|array $newSettings The value or array of values
	 * @return bool True on success
	 * @throws ModuleException If the add failed (database error), or if an ID can't be retrieved for $user or $settings
	 */
	private function addSettings($accountID, $settings, $newSettings) {
		//	Get IDs to put into relationship table
		$settingID = $this->getSettingsID($settings);
		$rowCount = $this->query(
			"INSERT INTO `users_account_settings` (`user_id`, `account_settings_id`, `value`) VALUES (?, ?, ?)",
			array($accountID, $settingID, $newSettings));

		//	Database error
		if (!$rowCount)
			throw new ModuleException("Failed to add setting '$settings' as '$newSettings' to user ID $accountID.");

		//	Update autologin cache if necessary
		if ($settings == "autologin")
			$this->updateAutoLoginCache();

		return true;
	}

	/**
	 * Change a user's settings. Absent settings will be added, and settings with only 1 entry will be overwritten.
	 * Settings that allow an arbitrary number of entries will have one added. Settings that allow n entries will have one added, but only if there is space.
	 *
	 * @param int $accountID
	 * @param string|array $settings The setting name or array
	 * @param string|array $newSettings The value or array of values
	 * @return bool True on success, false if update for existing setting failed (database error), or false if the max values parameter in self::$validSettings is malformed
	 * @throws ModuleException If the add fails (database error), or if the add fails because the maximum # of entries are already present
	 */
	public function setSettings($accountID, $settings, $newSettings) {
		$this->validateSettings($settings, $newSettings);

		//	Recursive loop to support arrays
		if (is_array($settings)) {
			$return = true;
			foreach ($settings as $key => $setting) {
				if (!$this->setSettings($accountID, $setting, $newSettings[$key]))
					$return = false;
			}
			return $return;
		}

		$maxValues = self::$validSettings[$settings];

		//	Max value of 0 means an indefinite # of entries are allowed, add a new one
		if ($maxValues === 0)
			return $this->addSettings($accountID, $settings, $newSettings);

		elseif ($maxValues > 0) {

			//	If entries for $settings exist for this user
			if ($arr = $this->getSettings($accountID, $settings)) {
				//	Not a single-entry setting, but a cap exists
				if ($maxValues > 1) {
					//	This user is already at or over the limit
					if ($maxValues <= count($arr))
						throw new ModuleException("Cannot add more entries for '$settings'.");
					//	There is space, add a new one
					else
						return $this->addSettings($accountID, $settings, $newSettings);
				}

				$settingID = $this->getSettingsID($settings);

				//	Single-entry setting and entry exists, overwrite it
				$rowCount = $this->query(
					"UPDATE `users_account_settings` SET `value`=? WHERE `user_id`=? AND `account_settings_id`=? LIMIT 1",
					array($newSettings, $accountID, $settingID));

				if (!$rowCount)
					throw new ModuleException("Unable to overwrite entry for '$settings'.");

				return true;
			}

			//	No existing entries, add a new one
			else
				return $this->addSettings($accountID, $settings, $newSettings);
		}

		return false;
	}

	/**
	 * Delete a settings entry for a user
	 *
	 * @param int $accountID
	 * @param string|array $settings The setting name or array
	 * @param string|array $entry	Pass to delete only entries matching a certain value. If absent, all values will be deleted.
	 *	 							Must match $settings if it $settings is an array. Omission will construct the blank array for you.
	 * @return bool True on success, false on any failure
	 * @throws ModuleException If a setting is invalid, $settings doesn't match $entry, or an ID lookup for $user or $settings fails
	 */
	public function removeSettings($accountID, $settings, $entry = null) {
		if ($entry)
			$this->validateSettings($settings, $entry, true);
		else
			$this->validateSettings($settings);

		//	Recursive loop to support arrays
		if (is_array($settings)) {
			$return = true;
			foreach ($settings as $key => $setting) {
				if (!$this->removeSettings($accountID, $setting, (($entry) ? $entry[$key] : $entry)))
					$return = false;
			}
			return $return;
		}

		//	Get IDs to put into relationship table
		$settingsID = $this->getSettingsID($settings);

		//	Default to deleting all entries
		$constraint = "";
		$parameters = array($accountID, $settingsID);
		//	Looking to delete a specific value, update query and parameters
		if ($entry) {
			$constraint = " AND `value`=?";
			$parameters[] = $entry;
		}

		$rowCount = $this->query(
			"DELETE FROM `users_account_settings` WHERE `user_id`=? AND `account_settings_id`=?$constraint",
			$parameters);

		if (!$rowCount)
			throw new ModuleException("Unable to remove entry for '$settings'.");

		if ($settings == "autologin")
			$this->updateAutoLoginCache();

		return true;
	}

	/**
	 * Helper function to get an ID number for an account setting
	 *
	 * @param $setting
	 * @return bool|int ID, or false if it doesn't exist
	 * @throws DatabaseInterfaceException If query fails
	 */
	private function getSettingsID($setting) {
		$results = $this->query(
			"SELECT `id` FROM `account_settings` WHERE `name`=?",
			array($setting));

		if (!count($results))
			return false;

		return intval($results[0]['id']);
	}

	/**
	 * Validate a pair of settings name and value for changing/deleting. They must both be arrays with the same # of elements and same keys, or scalar, and all settings must be defined in self::$validSettings
	 *
	 * @param string|array $settings A setting name or an array of them
	 * @param mixed $newSettings A setting value or an array of them
	 * @param bool $validateParameters True if unsetting settings, so parameter validation can be ignored
	 * @throws ModuleException If any of the described requirements are not met
	 */
	private function validateSettings($settings, $newSettings = null, $validateParameters = false) {

		if (!$validateParameters) {
			if (is_array($settings)) {
				foreach ($settings as $setting)
					$this->validateSettings($setting);
			}

			else {
				$settings = strtolower($settings);
				if (!isset(self::$validSettings[$settings]))
					throw new ModuleException("Invalid settings passed: '$settings'.");
			}

			return;
		}

		if (is_array($settings)) {
			//	Both or neither must be arrays
			if (!is_array($newSettings))
				throw new ModuleException("Invalid parameters passed. Settings list and values must both be either an array or scalar.");

			//	Arrays must be the same length
			if (count($settings) != count($newSettings))
				throw new ModuleException("Invalid parameters passed. Settings list and values arrays must have the same number of elements.");

			//	Array keys must match
			if (array_keys($settings) != array_keys($newSettings))
				throw new ModuleException("Invalid parameters passed. Settings list and values arrays must have the same keys.");

			//	Validate individual setting names
			foreach ($settings as $key => $setting)
				$this->validateSettings($setting, $newSettings[$key], true);
		}

		//	Both or neither must be arrays
		elseif (is_array($newSettings))
			throw new ModuleException("Invalid parameters passed. Settings list and values must both be either an array or scalar.");

		//	Setting name must be defined in self::$validSettings
		$settings = strtolower($settings);
		if (!isset(self::$validSettings[$settings]))
			throw new ModuleException("Invalid settings passed.");

		//	Settings-specific constraints
		switch ($settings) {
			case "autologin":
				//	Require a certain number of significant characters for auto-login
				if (strlen(str_replace(array("*", "?", "[", "]", ".", "@", "!"), "", $newSettings)) < 10)
					throw new ModuleException("Autologin host is not specific enough.");
				break;

			case "weather":
				if (class_exists("Weather"))
					//	Look up and discard new location, but throw an exception if location isn't found
					Weather::weatherLocation($newSettings);
				break;

			case "units":
				if (!in_array(strtolower($newSettings), array("imperial", "metric", "both")))
					throw new ModuleException("Units must be 'metric', 'imperial', or 'both'.");
				break;

			//	No value required for these, toggle on or off
			case "disablenotify":
				break;

			default:
				if (!strlen($newSettings))
					throw new ModuleException("Option '$settings' requires a value.");
				break;
		}
	}

}
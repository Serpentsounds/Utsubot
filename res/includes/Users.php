<?php
/**
 * Utsubot - Users.php
 * User: Benjamin
 * Date: 19/11/14
 */

class Users extends Manager {

	protected static $manages = "User";
	private $IRCBot;

	/** @var $collection User[] */
	protected $collection = array();

	protected static $customOperators = array("on", "voice", "hop", "halfop", "op", "sop", "protect", "owner", "founder", "loggedIn");

	public function __construct(IRCBot $irc) {
		$this->IRCBot = $irc;
	}

	/**
	 * Add a user object to the collection
	 *
	 * @param Manageable $user User object
	 * @param bool $unique
	 * @return bool True on success, false if it already exists
	 * @throws ManagerException
	 */
	public function addItem(Manageable $user, $unique = true) {
		if (!$user instanceof User)
			throw new ManagerException("Cannot add item because it is not a User.");

		if (($key = parent::addItem($user, true)) !== false) {
			$user->setId($key);
			return true;
		}

		return false;
	}

	/**
	 * Ensure a User object exists, and get that object. If necessary, creates it and performs initialization.
	 * This method should be used in all cases when a new User may need to be created.
	 *
	 * @param string $fullAddress nick!user@host
	 * @return User The new or existing user object
	 */
	public function confirmUser($fullAddress) {
		list($nick, $address) = explode("!", $fullAddress);

		//	New user needs to be created
		if (!($user = $this->get($nick))) {
			//	Create user
			$user = new User($nick, $address);
			$this->addItem($user);

			//	Creating user for the bot itself, update IRCBot properties with perceived address
			if ($nick == $this->IRCBot->getNickname() && $address)
				$this->IRCBot->setAddress($address);

			//	Attempt to auto-login to relevant account
			$this->IRCBot->sendToModules("user", clone $user);
		}
		//	User exists, just do an address update
		elseif ($user instanceof User) {
			if ($address && $address != $user->getAddress()) {
				$user->setAddress($address);
				$this->IRCBot->sendToModules("user", clone $user);
			}
		}

		return $user;
	}

	/**
	 * Given a $field to search against, this function should return info on how to get the field
	 *
	 * @param string $field The name of an aspect of one of this manager's collection
	 * @param string $operator
	 * @param string $value The value being searched against, if relevant
	 * @return ManagerSearchObject|null
	 */
	public function searchFields($field, $operator = "", $value = "") {
		switch ($field) {
			case "nick":	case "nickname":
				return new ManagerSearchObject($this, "getNick", array(), self::$stringOperators);
			break;

			case "address":
				return new ManagerSearchObject($this, "getAddress", array(), self::$stringOperators);
			break;

			case "idle":	case "timeIdle":
				return new ManagerSearchObject($this, "getTimeIdle", array(), self::$numericOperators);
			break;
		}

		return null;
	}

	/**
	 * Perform custom comparisons on a User, for checking channel presence and modes
	 *
	 * @param User $object A User object in this manager
	 * @param string $field Field name
	 * @param string $operator Custom operator
	 * @param string $value Value to compare against
	 * @return bool True or false depending on comparison result
	 */
	protected function customComparison($object, $field, $operator, $value) {
		$isOn = $object->isOn($value);
		$status = $object->status($value);

		switch ($operator) {
			case "on":
				return $isOn;
			break;

			case "voice":	case "vop":
			case "halfop":	case "hop":
			case "op":
			case "sop":
			case "protect":
			case "owner":	case "founder":
				if (!$status)
					return false;

				$modes = array(
					'voice'		=> "v",	'vop'		=> "v",
					'halfop'	=> "h",	'hop'		=> "h",
					'op' 		=> "o",
					'sop' 		=> "a",
					'protect' 	=> "a",
					'owner' 	=> "q",	'founder'	=> "q"
				);
				if (strpos($status, $modes[$operator]) !== false)
					return true;

				return false;
			break;
		}

		return false;
	}

}
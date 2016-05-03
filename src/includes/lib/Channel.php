<?php

namespace Utsubot;

class Channel implements Manageable {
	private $name = "";
	/**	@var $users User[]	*/
	private $users = array();
	private $modes = array();
	private $bans = array();

	private static $userModes = "qaohv";
	private static $banModes = "beI";
	private static $parameterModes = "fjkLl";

	public function __construct($name) {
		$this->name = $name;

		$banTypes = str_split(self::$banModes);
		foreach ($banTypes as $banType)
			$this->bans[$banType] = array();
	}

	public function join(&$user) {
		if ($user instanceof User && !isset($this->users[$user->getNick()])) {
			$this->users[$user->getNick()] = $user;
			return true;
		}

		return false;
	}

	public function part($user) {
		if (gettype($user) == "string" && isset($this->users[$user])) {
			unset($this->users[$user]);
			return true;
		}

		elseif ($user instanceof User && isset($this->users[$user->getNick()])) {
			unset($this->users[$user->getNick()]);
			return true;
		}

		return false;
	}

	public function nick($oldNick, $newNick) {
		if (isset($this->users[$oldNick])) {
			$this->users[$newNick] = $this->users[$oldNick];
			unset($this->users[$oldNick]);
			return true;
		}

		return false;
	}

	public function ban($mask, $type = "b") {
		if (strpos(self::$banModes, $type) === false)
			return false;

		elseif (in_array($mask, $this->bans[$type]))
			return false;

		else {
			$this->bans[$type][] = $mask;
			return true;
		}
	}

	public function unban($mask, $type = "b") {
		if (strpos(self::$banModes, $type) === false)
			return false;

		$key = array_search($mask, $this->bans[$type]);
		if ($key === false)
			return false;

		else {
			unset($this->bans[$type][$key]);
			return true;
		}
	}

	public function mode($modeString) {
		$modeParameters = array();
		if (preg_match_all("/^\S+ (.+)/", $modeString, $match))
			$modeParameters = explode(" ", $match[1]);

		if (!preg_match_all("/^([+-])([a-zA-Z])/", $modeString, $match, PREG_SET_ORDER))
			return false;

		foreach ($match as $set) {
			$sign = $set[0];
			$modes = str_split($set[1]);

			foreach ($modes as $mode) {
				if (strpos(self::$userModes, $mode) !== false) {
					$user = array_shift($modeParameters);

					if (isset($this->users[$user]))
						$this->users[$user]->mode($this->name, $sign.$mode);
				}


				elseif (strpos(self::$banModes, $mode) !== false) {
					if ($sign == "+")
						$this->ban(array_shift($modeParameters), $mode);
					elseif ($sign == "-")
						$this->unban(array_shift($modeParameters), $mode);
				}

				else {
					if (strpos(self::$parameterModes, $mode) !== false)
						$set = array_shift($modeParameters);
					else
						$set = true;

					if ($sign == "+")
						$this->modes[$mode] = $set;
					elseif ($sign == "-" && isset($this->modes[$mode]))
						unset($this->modes[$mode]);
				}

			}
		}

		return true;
	}

	public function getUser($search) {
		if (preg_match("/^[^@]+@.+/", $search))
			$searchType = "address";
		elseif ($search instanceof User)
			$searchType = "user";
		elseif (is_string($search) && strlen($search) > 0)
			$searchType = "nickname";
		else
			return false;

		foreach ($this->users as $nickname => $user) {
			switch ($searchType) {
				case "address":
					if (strtolower($user->getAddress()) == strtolower($search))
						return $user;
				break;

				case "nickname":
					if (strtolower($user->getNick()) == strtolower($search))
						return $user;
				break;

				case "user":
					if ($user === $search)
						return $user;
				break;
			}
		}

		return false;
	}

	public function getName() {
		return $this->name;
	}

	public function __toString() {
		return $this->name;
	}

	public function search($search): bool {
		return strtolower($search) == strtolower($this->name);
	}
}
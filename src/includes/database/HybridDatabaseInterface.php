<?php
/**
 * Utsubot - HybridDatabaseInterface.php
 * User: Benjamin
 * Date: 18/04/2015
 */

namespace Utsubot;
use Utsubot\Accounts\Accounts;

class HybridDatabaseInterfaceException extends DatabaseInterfaceException {}

abstract class HybridDatabaseInterface extends DatabaseInterface {
	/** @var $users Users */
	protected $users;
	/** @var $accounts Accounts */
	protected $accounts;

	protected static $table = "";

	protected static $userIDColumn = "";
	protected static $nicknameColumn = "";

	public function __construct(DatabaseCredentials $credentials, Users &$users, Accounts &$accounts) {
		parent::__construct($credentials);
		$this->users = $users;
		$this->accounts = $accounts;
	}

	public function migrate($nickname) {
		//	Get User object
		$user = $this->users->search($nickname);
		if (!($user instanceof User))
			throw new HybridDatabaseInterfaceException("Error getting User object.");

		//	Get account associated with nickname
		$userList = $this->accounts->getInterface()->searchSettings("nick", $nickname);
		if (!count($userList))
			throw new HybridDatabaseInterfaceException("Your nickname is not linked with an account.");

		//	Check accounts against eachother
		$accountID = $this->accounts->confirmLogin($user);
		$targetID = $userList[0]['user_id'];
		if ($accountID != $targetID)
			throw new HybridDatabaseInterfaceException("You are not logged in to the account your nickname is linked with.");

		//	Check codes still filed under the nickname
		$results = $this->query(
			sprintf("SELECT * FROM `%s` WHERE `%s`=?", static::$table, static::$nicknameColumn),
			array($nickname)
		);
		$resultCount = count($results);
		if (!$resultCount)
			throw new HybridDatabaseInterfaceException("You have no items to migrate.");

		//	Update codes to be filed under account
		$rowCount = $this->query(
			sprintf(	"UPDATE `%s` SET `%s`=?, `%s`=NULL WHERE `%s` IS NULL AND `%s`=? LIMIT $resultCount",
						static::$table, static::$userIDColumn, static::$nicknameColumn, static::$userIDColumn, static::$nicknameColumn),
			array($accountID, $nickname)
		);

		//	No rows updated
		if (!$rowCount)
			throw new HybridDatabaseInterfaceException("An error occured while trying to migrate your codes.");

		return $rowCount;
	}

	public function analyze($nickname) {
		$analysis = new HybridAnalysis();

		$user = $this->users->search($nickname);
		if (!($user instanceof User)) {
			$analysis->setMode("nickname");
			$analysis->setNickname($nickname);
		}

		//	Valid User, attempt to get account
		else {
			$accountID = $this->accounts->getAccountIDByUser($user);
			//	Look up codes by account
			if (is_int($accountID)) {
				$analysis->setMode("account");
				$analysis->setAccountID($accountID);
				$analysis->setNickname($user->getNick());
			}

			//	User is not logged in, use nickname
			else {
				$analysis->setMode("nickname");
				$analysis->setNickname($user->getNick());
			}
		}

		//	Attempt to convert nickname to a default nickname
		if ($analysis->getMode() == "nickname") {
			$userList = $this->accounts->getInterface()->searchSettings("nick", $analysis->getNickname());

			if (count($userList))
				$analysis->setLinkedAccountID($userList[0]['user_id']);
		}

		return $analysis;
	}

	protected function parseMode(HybridAnalysis $analysis, $secure = true) {
		$mode = $analysis->getMode();
		$user = null;
		if ($mode == "account") {
			$column = static::$userIDColumn;
			$user = $analysis->getAccountID();
		}
		elseif ($mode == "nickname") {
			$column = static::$nicknameColumn;
			$user = $analysis->getNickname();
		}
		else
			throw new HybridDatabaseInterfaceException("Invalid mode '$mode'.");

		if (!$secure && $analysis->getLinkedAccountID() !== null) {
			$column = static::$userIDColumn;
			$user = $analysis->getLinkedAccountID();
		}

		return array($column, $user);
	}

	protected function checkNicknameLink(HybridAnalysis $analysis) {
		if ($analysis->getMode() == "nickname") {
			$nickname = $analysis->getNickname();
			$settings = $this->accounts->getInterface()->searchSettings("nick", $nickname);
			if (count($settings))
				throw new HybridDatabaseInterfaceException("Your nickname is already linked to an account, but you are not logged in to one.");
		}
	}

	public function getNicknameFor($accountID) {
		$nickname = null;

		//	Check for current logins
		$user = $this->accounts->getUserByAccountID($accountID);

		if ($user === false) {
			$settings = $this->accounts->getInterface()->getSettings($accountID, "nick");
			//	Default nickname exists
			if (count($settings))
				$nickname = $settings[0]['value'];
		}
		elseif ($user instanceof User)
			$nickname = $user->getNick();

		return $nickname;
	}


}

class HybridAnalysis {
	private $mode;
	private $accountID;
	private $nickname;
	private $linkedAccountID;

	public function __construct($mode = null, $accountID = null, $nickname = null, $linkedAccountID = null) {
		$this->setMode($mode);
		$this->setAccountID($accountID);
		$this->setNickname($nickname);
		$this->setLinkedAccountID($linkedAccountID);
	}

	public function setMode($mode) {
		$mode = strtolower($mode);
		if ($mode != "account" && $mode != "nickname" && $mode !== null)
			return false;

		$this->mode = $mode;
		return true;
	}

	public function setAccountID($accountID) {
		if ((!is_int($accountID) || $accountID < 0) && $accountID !== null)
			return false;

		$this->accountID = $accountID;
		return true;
	}

	public function setNickname($nickname) {
		if ((!is_string($nickname) || !strlen($nickname)) && $nickname !== null)
			return false;

		$this->nickname = $nickname;
		return true;
	}

	public function setLinkedAccountID($linkedAccountID) {
		if ((!is_int($linkedAccountID) || $linkedAccountID < 0) && $linkedAccountID !== null)
			return false;

		$this->linkedAccountID = $linkedAccountID;
		return true;
	}

	public function getMode() {
		return $this->mode;
	}

	public function getAccountID() {
		return $this->accountID;
	}

	public function getNickname() {
		return $this->nickname;
	}

	public function getLinkedAccountID() {
		return $this->linkedAccountID;
	}
}
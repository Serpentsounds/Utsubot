<?php
/**
 * Utsubot - FriendSafariDatabaseInterface.php
 * User: Benjamin
 * Date: 22/04/2015
 */

namespace Pokemon;

class FriendSafariDatabaseInterface extends \HybridDatabaseInterface {

	protected static $table = "users_friendsafari";

	protected static $userIDColumn = "user_id";
	protected static $nicknameColumn = "nickname";

	protected static $typeColumn = "type";
	protected static $slot1Column = "slot_1";
	protected static $slot2Column = "slot_2";
	protected static $slot3Column = "slot_3";

	public function insert($nickname, $type, $slot1, $slot2, $slot3 = null) {
		$hybridAnalysis = $this->analyze($nickname);
		list($column, $user) = $this->parseMode($hybridAnalysis);

		//	Don't allow adding in nickname mode for linked nicknames (the user forgot to log in) to prevent stray database records
		$this->checkNicknameLink($hybridAnalysis);

		if ($slot3 === null)
			return $this->query(
				sprintf("INSERT INTO `%s` (`$column`, `%s`, `%s`, `%s`) VALUES (?, ?, ?, ?)", self::$table, self::$typeColumn, self::$slot1Column, self::$slot2Column),
				array($user, $type, $slot1, $slot2)
			);

		else
			return $this->query(
				sprintf("INSERT INTO `%s` (`$column`, `%s`, `%s`, `%s`, `%s`) VALUES (?, ?, ?, ?, ?)", self::$table, self::$typeColumn, self::$slot1Column, self::$slot2Column, self::$slot3Column),
				array($user, $type, $slot1, $slot2, $slot3)
		);
	}

	public function delete($nickname, $slot1 = null, $slot2 = null, $slot3 = null) {
		$hybridAnalysis = $this->analyze($nickname);
		list($column, $user) = $this->parseMode($hybridAnalysis);

		//	Don't allow deleting in nickname mode for linked nicknames
		$this->checkNicknameLink($hybridAnalysis);

		if ($slot3 === null) {
			//	Multiple nulls, remove all entries
			if ($slot1 === null || $slot2 === null)
				return $this->query(
					sprintf("DELETE FROM `%s` WHERE `$column`=?", self::$table),
					array($user)
				);

			//	Only slot 3 null, remove exact 2-pokemon entry
			else
				return $this->query(
					sprintf("DELETE FROM `%s` WHERE `$column`=? AND `%s`=? AND `%s`=? AND `%s` IS NULL LIMIT 1", self::$table, self::$slot1Column, self::$slot2Column, self::$slot3Column),
					array($user, $slot1, $slot2)
				);
		}

		else
			//	No nulls, remove exact 3-pokemon entry
			return $this->query(
				sprintf("DELETE FROM `%s` WHERE `$column`=? AND `%s`=? AND `%s`=? AND `%s`=? LIMIT 1", self::$table, self::$slot1Column, self::$slot2Column, self::$slot3Column),
				array($user, $slot1, $slot2, $slot3)
			);
	}

	public function select($nickname, $slot1 = null, $slot2 = null, $slot3 = null) {
		$hybridAnalysis = $this->analyze($nickname);
		//	Insecure mode, allow account mode retrieval through linked nickname even if not logged in
		list($column, $user) = $this->parseMode($hybridAnalysis, false);

		if ($slot3 === null) {
			//	Multiple nulls, select all entries
			if ($slot1 === null || $slot2 === null)
				return $this->query(
					sprintf("SELECT * FROM `%s` WHERE `$column`=?", self::$table),
					array($user)
				);

			//	Only slot 3 null, select exact 2-pokemon entry
			else
				return $this->query(
					sprintf("SELECT * FROM `%s` WHERE `$column`=? AND `%s`=? AND `%s`=? AND `%s` IS NULL LIMIT 1", self::$table, self::$slot1Column, self::$slot2Column, self::$slot3Column),
					array($user, $slot1, $slot2)
				);
		}

		else
			//	No nulls, select exact 3-pokemon entry
			return $this->query(
				sprintf("SELECT * FROM `%s` WHERE `$column`=? AND `%s`=? AND `%s`=? AND `%s`=? LIMIT 1", self::$table, self::$slot1Column, self::$slot2Column, self::$slot3Column),
				array($user, $slot1, $slot2, $slot3)
			);

	}

} 
<?php
/**
 * PHPBot - NetworkingManager.php
 * User: Benjamin
 * Date: 06/06/14
 */

class GameNetworkingException extends ModuleException {}

class GameNetworking extends ModuleWithPermission {

	/**
	 * Stores database entries for code types and formats
	 * @var $validCodes GameNetworkingCode[]
	 */
	private $validCodes = array();

	//	GameNetworkingDatabase interface
	private $interface;

	/**
	 * @param IRCBot $irc
	 * @throws GameNetworkingException If Users module isn't loaded (required for hybrid nickname/account storage features)
	 */
	public function __construct(IRCBot $irc) {
		parent::__construct($irc);

		$this->interface = new GameNetworkingDatabaseInterface("utsubot", $users = $this->IRCBot->getUsers(), $accounts = $this->getAccounts());
		$this->updateValidCodeCache();

		$this->triggers = array(
			'code'       => "code",
			'fc'         => "code",
			'friendcode' => "code"
		);
	}

	/**
	 * Cache code IDs and formats from the database
	 *
	 * @return bool Success/failure (or empty set)
	 * @throws DatabaseInterfaceException PDO error
	 */
	public function updateValidCodeCache(): bool {
		$this->validCodes = array();

		$results = $this->interface->query(
			"SELECT * FROM `codes`",
			array());

		foreach ($results as $row)
			$this->validCodes[$row['id']] = new GameNetworkingCode(intval($row['id']), $row['title'], $row['valid'], $row['name']);

		if (count($this->validCodes))
			return true;

		return false;
	}

	/**
	 * Check if a string can be matched to the title of a code
	 *
	 * @param string $check
	 * @return GameNetworkingCode|bool Matching code object or false on failure
	 */
	private function getCode($check) {
		foreach ($this->validCodes as $code) {
			if (preg_match($code->getValidTitleRegex(), $check))
				return $code;
		}

		return false;
	}

	/**
	 * The only triggered command, split off to relevant functionality depending on parameters
	 *
	 * @param IRCMessage $msg
	 * @throws GameNetworkingException If a subroutine errors
	 */
	public function code(IRCMessage $msg) {
		$action = array_shift($msg->getCommandParameters());

		switch ($action) {
			case "add":
				$this->addCode($msg);
			break;

			case "remove":
			case "rem":
			case "delete":
			case "del":
				$this->removeCode($msg);
			break;

			case "note":
				$this->editNotes($msg);
			break;

			case "lock":
			case "unlock":
				$this->lockCode($msg);
			break;

			case "migrate":
				$this->migrateCode($msg);
			break;

			default:
				$this->findCode($msg);
			break;

		}
	}

	/**
	 * If a user is logged in to an account but has codes stored in nickname mode, this command will transfer the entries over to the account
	 *
	 * @param IRCMessage $msg
	 * @throws GameNetworkingException User object retrieval fail, no registered nickname, not logged in, no codes to migrate, or database error
	 */
	public function migrateCode(IRCMessage $msg) {
		$rowCount = $this->interface->migrate($msg->getNick());

		$this->respond($msg,
	   		sprintf(	"%d codes were migrated to %s's account.",
						self::bold($rowCount), self::bold($msg->getNick()))
		);
	}

	/**
	 * Retrieve a code or codes
	 *
	 * @param IRCMessage $msg
	 * @throws GameNetworkingException Invalid parameters or empty lookup
	 * @throws DatabaseInterfaceException PDO error
	 */
	public function findCode(IRCMessage $msg) {
		//	Shave off first word to see what we're looking up
		$parameters = $msg->getCommandParameters();
		$action = array_shift($parameters);
		$parameterString = implode(" ", $parameters);

		//	Bool to ignore or request verification of code type, if limiting lookup to one type
		$requireCodeID = false;
		$nick = $msg->getNick();

		//	Valid code type given, look up codes of that type for calling user
		if ($this->getCode($msg->getCommandParameterString())) {
			//	Re-include code title for parameter parsing
			$parameterString = $msg->getCommandParameterString();
			$requireCodeID = true;
		}

		//	User given, look up codes of that user instead
		elseif ($action !== null) {
			$nick = $action;
			//	Valid code type also given, restrict output
			if ($this->getCode($parameterString))
				$requireCodeID = true;
		}

		//	Parse input and get relevant codes from the db
		$input = $this->parseInput($parameterString, $requireCodeID, false);
		$codes = $this->interface->select($nick, $input['codeID'], $input['code'], $msg->getNick());

		//	No results
		if (!$codes) {
			if (is_int($input['codeID']))
				throw new GameNetworkingException("There are no codes for '$nick' for '". $this->validCodes[$input['codeID']]->getTitle(). "'.");
			throw new GameNetworkingException("There are no codes for '$nick'.");
		}

		//	Organize codes by type for output
		$codeTypes = array();
		foreach ($codes as $row) {
			$value = $row['value'];
			if ($row['notes'] && $row['value'] != "(Private)")
				$value .= " ({$row['notes']})";
			$codeTypes[$row['code_id']][] = $value;
		}

		$output = "Codes for " . self::bold($nick);
		//	Output single type of code if it was requested (Type: code1, code2)
		if (is_int($input['codeID']))
			$output .= sprintf(" for %s: %s", self::bold($this->validCodes[$input['codeID']]->getTitle()), implode(", ", $codeTypes[$input['codeID']]));

		//	Output all codes by type ([Type: code1, code2] [Type2: code1, code2])
		else {
			$response = array();
			foreach ($codeTypes as $codeType => $list)
				$response[] = sprintf("[%s: %s]", self::bold($this->validCodes[$codeType]->getTitle()), implode(", ", $list));

			$output .= ": " . implode(" ", $response);
		}

		$this->respond($msg, $output);
	}

	/**
	 * Add a new code
	 *
	 * @param IRCMessage $msg
	 * @throws GameNetworkingException Invalid parameters or duplicate code
	 * @throws DatabaseInterfaceException PDO error
	 */
	public function addCode(IRCMessage $msg) {
		//	Shave off "add" from user input
		$parameters = $msg->getCommandParameters();
		array_shift($parameters);
		//	Verify input
		$input = $this->parseInput(implode(" ", $parameters));

		//	Attempt insertion
		try {
			$rowCount = $this->interface->insert($msg->getNick(), $input['codeID'], $input['code']);
		}
		//	Insert failed with valid input, so unique constraint on table is the culprit
		catch (PDOException $e) {
			$rowCount = 0;
		}

		//	Duplicate entry, retrieve original owner (helpful if user forgot they added a code in the past)
		if (!$rowCount) {
			$results = $this->interface->selectValue($input['codeID'], $input['code']);

			//	Owner found
			if (isset($results[0]) && $row = $results[0]) {
				$nickname = null;
				//	Nickname entry
				if ($row['nickname'])
					$nickname = $row['nickname'];

				//	Account entry
				else
					$nickname = $this->interface->getNicknameFor($row['user_id']);

				if (strlen($nickname))
					throw new GameNetworkingException("'{$input['code']}' already exists as a(n) ". $this->validCodes[$input['codeID']]->getTitle(). " code for '$nickname'.");
				//	Offline user account with no default nick
				throw new GameNetworkingException("'{$input['code']}' already exists as a(n) ". $this->validCodes[$input['codeID']]->getTitle(). " code under a user account.");
			}

			//	No existing entry, unknown error
			throw new GameNetworkingException("An error occured while attempting to add your code.");
		}

		$this->respond($msg,
			sprintf(	"%s has been added as a(n) %s code for %s.",
						self::bold($input['code']), self::bold($this->validCodes[$input['codeID']]->getTitle()), self::bold($msg->getNick()))
		);
	}

	/**
	 * Remove an existing code or codes
	 *
	 * @param IRCMessage $msg
	 * @throws GameNetworkingException Invalid parameters or no matching code(s) to remove
	 * @throws DatabaseInterfaceException PDO error
	 */
	public function removeCode(IRCMessage $msg) {
		//	Shave off "remove" from user input
		$parameters = $msg->getCommandParameters();
		array_shift($parameters);
		//	Parse input
		$input = $this->parseInput(implode(" ", $parameters), true, false);

		//	Abort if the user has no codes to remove
		$currentCode = $this->interface->select($msg->getNick(), $input['codeID'], $input['code']);
		if (!$currentCode) {
			if ($input['code'])
				throw new GameNetworkingException("You have no " . $this->validCodes[$input['codeID']]->getTitle() . " codes matching '{$input['code']}'.");
			throw new GameNetworkingException("You have no " . $this->validCodes[$input['codeID']]->getTitle() . " codes.");
		}

		//	Delete codes
		$rowCount = $this->interface->delete($msg->getNick(), $input['codeID'], $input['code']);
		if (!$rowCount)
			throw new GameNetworkingException("An error occured while attempting to remove your code.");

		$this->respond($msg,
			sprintf(	"%s matching codes were deleted from %s.",
						self::bold($rowCount), self::bold($msg->getNick()))
		);
	}

	/**
	 * Parse user input string to extract code ID and code content, if applicable
	 *
	 * @param string $userInput
	 * @param bool $requireCodeID If code type is required (add, remove, find [optional])
	 * @param bool $requireCode If code value is required (add, remove [optional])
	 * @return array Array(Code ID, Code Value), null values if absent
	 * @throws GameNetworkingException Validation failed
	 */
	protected function parseInput($userInput, $requireCodeID = true, $requireCode = true) {
		//	Get this out of the way
		if (!is_string($userInput))
			throw new GameNetworkingException("Invalid user input.");

		$newCode = $codeID = $code = $newCode = null;
		//	Loop through valid code types to see if code string matches any
		foreach ($this->validCodes as $currentCode) {
			//	Check if entered string matches current code type
			if (preg_match($currentCode->getValidTitleRegex(), $userInput, $match)) {
				$codeID = $currentCode->getId();

				//	Get remaining part of code string
				$newCode = trim(substr($userInput, strlen($match[0])));

				//	Check if remaining part matches proper code format
				if (preg_match($currentCode->getValidFormatRegex(), $newCode, $match)) {

					//	In case of multiple capture groups, join all with a space to normalize entries
					$construct = array();
					for ($i = 1; $i < count($match); $i++)
						$construct[] = $match[$i];

					$code = implode(" ", $construct);
				}

				break;
			}
		}

		//	Code type required but not found
		if ($codeID === null && $requireCodeID)
			throw new GameNetworkingException("Invalid code type specified. Valid codes are: ". implode(", ",
			   array_map(function($entry) {
					   if ($entry instanceof GameNetworkingCode)
						   return $entry->getTitle();
					   return null;
				   },
				   $this->validCodes)));

		//	Code value required but not found
		if ($code === null && ($requireCode || $newCode))
			throw new GameNetworkingException("Invalid code format for '{$this->validCodes[$codeID]->getTitle()}'.");

		return array('codeID' => $codeID, 'code' => $code, 'restOfString' => $newCode);
	}

	/**
	 * Add or remove notes from a code entry
	 *
	 * @param IRCMessage $msg
	 * @throws DatabaseInterfaceException If interface->update method encounters an error
	 * @throws GameNetworkingException If a matching code entry is not found
	 */
	public function editNotes(IRCMessage $msg) {
		//	Shave off "note" from user input
		$parameters = $msg->getCommandParameters();
		array_shift($parameters);
		//	Get add or remove function
		$mode = array_shift($parameters);
		//	Parse input
		$input = $this->parseInput(implode(" ", $parameters), true, true);

		$note = null;
		/*	Grab existing codes to try and match one against the user input string
			We have to do a custom match, because parseInput generalizes using the valid code format, and may not be able to correctly separate the code from the user note
		*/
		$codes = $this->interface->select($msg->getNick(), $input['codeID']);
		//	Sort longer codes first to prevent greedy duplicate matches
		usort($codes, function($a, $b) { return strlen($b['value']) - strlen($a['value']); });
		//	If code matches, grab the remaining part of the string as the note
		foreach ($codes as $key => $code) {
			if (stripos($input['restOfString'], $code['value']) === 0) {
				$input['code'] = $code['value'];
				$note = trim(substr($input['restOfString'], strlen($code['value'])));
				break;
			}

			//	Out of codes to match
			if ($key + 1 == count($codes))
				throw new GameNetworkingException("You have no " . $this->validCodes[$input['codeID']]->getTitle(). " codes matching '{$input['code']}'.");
		}

		if (!$codes)
			throw new GameNetworkingException("You have no " . $this->validCodes[$input['codeID']]->getTitle(). " codes.");

		switch ($mode) {
			//	Add or overwrite note
			case "add":
			case "set":
				$this->interface->update($msg->getNick(), $input['codeID'], $input['code'], "notes", $note);
				$this->respond($msg, self::bold($note). " has been added as a note for ". self::bold($msg->getNick()). "'s code.");
			break;

			//	Remove note
			case "remove":
			case "rem":
			case "delete":
			case "del":
				$this->interface->update($msg->getNick(), $input['codeID'], $input['code'], "notes", null);
				$this->respond($msg, "Note has been removed from ". self::bold($msg->getNick()). "'s code.");
			break;

			//	Invalid parameters
			default:
				throw new GameNetworkingException("Invalid notes action '$mode'.");
			break;
		}
	}

	/**
	 * Mark a code as public or private
	 *
	 * @param IRCMessage $msg
	 * @throws DatabaseInterfaceException If interface->update method encounters an error
	 * @throws GameNetworkingException If a matching code entry is not found
	 */
	public function lockCode(IRCMessage $msg) {
		//	Shave off "lock" or "unlock" from user input
		$parameters = $msg->getCommandParameters();
		$mode = array_shift($parameters);

		//	Parse input
		$input = $this->parseInput(implode(" ", $parameters), true, true);

		//	Verify specified code exists
		$currentCode = $this->interface->select($msg->getNick(), $input['codeID'], $input['code']);
		if (!$currentCode)
			throw new GameNetworkingException("You have no " . $this->validCodes[$input['codeID']]->getTitle() . " codes matching '{$input['code']}'.");

		switch ($mode) {
			//	Mark code as private
			case "lock":
				$this->interface->update($msg->getNick(), $input['codeID'], $input['code'], "locked", 1);
				$this->respond($msg, "Code for ". self::bold($msg->getNick()). " has been locked.");
			break;

			//	Mark code as public
			case "unlock":
				$this->interface->update($msg->getNick(), $input['codeID'], $input['code'], "locked", null);
				$this->respond($msg, "Code for ". self::bold($msg->getNick()). " has been unlocked.");
			break;

			//	Invalid parameters
			default:
				throw new GameNetworkingException("Invalid lock action '$mode'.");
			break;
		}

	}

}

/**
 * Class GameNetworkingCode
 *
 * Represents a set of constraints that comprise a valid networking code (but is not itself a specific entry)
 */
class GameNetworkingCode {
	private $id;
	private $title;
	private $validFormatRegex;
	private $validTitleRegex;

	/**
	 * @param $id int
	 * @param $title string
	 * @param $validFormatRegex string
	 * @param $validTitleRegex string
	 */
	public function __construct($id, $title, $validFormatRegex, $validTitleRegex) {
		$this->setId($id);
		$this->setTitle($title);
		$this->setValidFormatRegex($validFormatRegex);
		$this->setValidTitleRegex($validTitleRegex);
	}

	/**
	 * @param $id int
	 * @return bool Success/failure
	 */
	public function setId($id): bool {
		if (!is_int($id) || $id < 0)
			return false;

		$this->id = $id;
		return true;
	}

	/**
	 * @param $title string
	 * @return bool Success/failure
	 */
	public function setTitle($title): bool {
		if (!is_string($title) || !strlen($title))
			return false;

		$this->title = $title;
		return true;
	}

	/**
	 * @param $validFormatRegex string
	 * @return bool Success/failure
	 */
	public function setValidFormatRegex($validFormatRegex): bool {
		if (@preg_match($validFormatRegex, null) === false)
			return false;

		$this->validFormatRegex = $validFormatRegex;
		return true;
	}

	/**
	 * @param $validTitleRegex string
	 * @return bool Success/failure
	 */
	public function setValidTitleRegex($validTitleRegex): bool {
		if (@preg_match($validTitleRegex, null) === false)
			return false;

		$this->validTitleRegex = $validTitleRegex;
		return true;
	}

	/**
	 * @return string
	 */
	public function getTitle(): string {
		return $this->title;
	}

	/**
	 * @return int
	 */
	public function getId(): int {
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function getValidFormatRegex(): string {
		return $this->validFormatRegex;
	}

	/**
	 * @return string
	 */
	public function getValidTitleRegex(): string {
		return $this->validTitleRegex;
	}

}
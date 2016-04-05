<?php
/**
 * Utsubot - FriendSafari.php
 * User: Benjamin
 * Date: 11/12/2014
 */

namespace Utsubot\Pokemon;
use Utsubot\Permission\ModuleWithPermission;
use Utsubot\{IRCBot, IRCMessage, MySQLDatabaseCredentials, ModuleException};
use function Utsubot\bold;
use function Utsubot\Pokemon\Types\colorType;

class FriendSafariException extends ModuleException {}

class FriendSafari extends ModuleWithPermission {
	private $interface;
	private $validPokemon = array();

	public function __construct(IRCBot $irc) {
		parent::__construct($irc);

		$users = $this->IRCBot->getUsers();
		$this->interface = new FriendSafariDatabaseInterface(MySQLDatabaseCredentials::createFromConfig("utsubot"), $users = $this->IRCBot->getUsers(), $accounts = $this->getAccounts());
		$this->updateValidPokemonCache();

		$this->triggers = array(
			"fs"			=> "friendSafari",
			"friendsafari"	=> "friendSafari"
		);
	}

	public function updateValidPokemonCache() {
		$this->validPokemon = array();

		$results = $this->interface->query(
			"SELECT * FROM `friendsafari_pokemon`",
			array());

		foreach ($results as $row)
			$this->validPokemon[$row['dexnum']][] = $row;

		if (count($this->validPokemon))
			return true;

		return false;
	}

	public function friendSafari(IRCMessage $msg) {
		$parameters = $msg->getCommandParameters();

		$action = null;
		if (count($parameters))
			$action = array_shift($parameters);

		switch ($action) {
			case "add":
				$this->addSafari($msg);
			break;

			case "rem":
			case "remove":
			case "del":
			case "delete":
				$this->removeSafari($msg);
			break;

			case "search":
				$this->searchSafari($msg);
			break;

			case "list":
				$this->listSafariPokemon($msg);
			break;

			case "migrate":
				$this->migrateSafari($msg);
			break;

			default:
				$this->getSafari($msg);
			break;
		}
	}



	/**
	 * If a user is logged in to an account but has Friend Safari entries stored in nickname mode, this command will
	 * transfer the entries over to the account
	 *
	 * @param IRCMessage $msg
	 * @throws FriendSafariException User object retrieval fail, no registered nickname, not logged in, no entries to
	 *     migrate, or database error
	 */
	public function migrateSafari(IRCMessage $msg) {
		$rowCount = $this->interface->migrate($msg->getNick());
		if (!$rowCount)
			throw new FriendSafariException("An error occured while trying to migrate your Friend Safari entries.");

		$this->respond($msg,
            sprintf(
                "%d Friend Safari entries were migrated to %s's account.",
                bold($rowCount),
                bold($msg->getNick()))
		);
	}

	public function listSafariPokemon(IRCMessage $msg) {
		//	Shave off "list" from user input
		$parameters = $msg->getCommandParameters();
		array_shift($parameters);

		$type = ucfirst(strtolower(@$parameters[0]));
		if (!$type)
			throw new FriendSafariException("No type given.");

		$pokemon = array();
		foreach ($this->validPokemon as $dexnum => $rows) {
			foreach ($rows as $row) {
				if ($row['type'] == $type)
					$pokemon[$row['slot']][] = $row['name'];
			}
		}

		if (!count($pokemon))
			throw new FriendSafariException("Invalid type '$type'.");

		ksort($pokemon);
		$response = array();
		foreach ($pokemon as $slot => $pokemonList)
			$response[] = sprintf(
                "[%s: %s]",
                bold("Slot $slot"),
                implode(", ", $pokemonList)
            );


		$this->respond($msg,
			sprintf(	"Possible %s-type Friend Safari pokemon: %s",
						colorType($type, true), implode(" ", $response))
		);
	}

	public function searchSafari(IRCMessage $msg) {
		//	Shave off "search" from user input
		$parameters = $msg->getCommandParameters();
		array_shift($parameters);
		$pokemon = $this->getValidPokemon(implode(" ", $parameters))[0]['name'];

		$results = $this->interface->query(
			"SELECT `nickname`, `user_id` FROM `users_friendsafari` WHERE `slot_1`=? OR `slot_2`=? OR `slot_3`=?",
			array($pokemon, $pokemon, $pokemon));

		if (!count($results))
			throw new FriendSafariException("There is nobody with a '$pokemon' in his or her Friend Safari.");

		$return = array();
		foreach ($results as $row) {
			if (isset($row['nickname']))
				$return[] = $row['nickname'];

			elseif (isset($row['user_id'])) {
				$nickname = $this->interface->getNicknameFor($row['user_id']);
				if ($nickname !== false)
					$return[] = $nickname;
			}
		}

		$this->respond($msg,
	   		sprintf(
                "These users have a Friend Safari containing %s: %s",
                bold($pokemon),
                implode(", ", $return))
		);
	}

	public function getSafari(IRCMessage $msg) {
		//	Shave off target from user input
		$parameters = $msg->getCommandParameters();
		$nick = array_shift($parameters);
		//	No nick given, check all safaris for user
		if (!$nick)
			$nick = $msg->getNick();

		$results = $this->interface->select($nick);

		if (!$results)
			throw new FriendSafariException("No Friend Safari entries found for '$nick'.");

		$response = array();
		foreach ($results as $row) {
			$response[] = sprintf(
				"[%s: %s]",
				colorType($row['type'], true),
                implode(
                    ", ",
                    array_map(
                        array("self", "bold"),
                        array_filter(array($row['slot_1'], $row['slot_2'], $row['slot_3']))
                    )
                )
			);
		}

		$this->respond($msg,
		   	sprintf(
                "%s's Friend Safaris: %s",
                bold($nick),
                implode(" ", $response))
		);

	}

	public function addSafari(IRCMessage $msg) {
		//	Shave off "add" from user input
		$parameters = $msg->getCommandParameters();
		array_shift($parameters);
		//	Verify input
		$input = $this->verifyUserPokemon(implode(" ", $parameters));

		$rowCount = $this->interface->insert($msg->getNick(), $input['type'], $input['pokemon'][0], $input['pokemon'][1], $input['pokemon'][2]);

		if (!$rowCount)
			throw new FriendSafariException("An error occured while attempting to add your Friend Safari.");

		$this->respond($msg,
	   		sprintf(
                "%s has been added as a %s-type Friend Safari for %s.",
                implode(
                    ", ",
                    array_map(
                        array("self", "bold"),
                        array_filter($input['pokemon'])
                    )
                ),
                colorType($input['type'], true),
                bold($msg->getNick()))
		);
	}

	public function removeSafari(IRCMessage $msg) {
		//	Shave off "remove" from user input
		$parameters = $msg->getCommandParameters();
		array_shift($parameters);

		//	Verify input
		if (count($parameters))
			$pokemon = $this->verifyUserPokemon(implode(" ", $parameters))['pokemon'];
		else
			$pokemon = array(null, null, null);

		//	Abort if the user doesn't have any matching safari set
		if (!$this->interface->select($msg->getNick(), $pokemon[0], $pokemon[1], $pokemon[2]))
			throw new FriendSafariException("No matching safari entries found.");

		$rowCount = $this->interface->delete($msg->getNick(), $pokemon[0], $pokemon[1], $pokemon[2]);

		if (!$rowCount)
			throw new FriendSafariException("An error occured while attempting to remove your friend safari.");

		$this->respond($msg,
	   		sprintf(
                "%s matching Friend Safaris were removed from %s.",
                bold($rowCount),
                bold($msg->getNick()))
		);
	}

	/**
	 * @param $pokemon
	 * @return array|bool
	 */
	private function getValidPokemon($pokemon) {
		foreach ($this->validPokemon as $rows) {
			if (strtolower($pokemon) == strtolower($rows[0]['name']))
				return $rows;
		}
		return false;
	}

	private function verifyUserPokemon($pokemonString) {
		//	No friend safari pokemon are multi-word pokemon, so word by word checking is sufficient
		$userPokemon = explode(" ", $pokemonString);
		$pokemon = $safaris = array();
		$type = null;

		foreach ($userPokemon as $slot => $currentPokemon) {
			//	Pokemon should be given in order: slot 1, slot 2, [slot 3]
			$slot += 1;

			$pokemonInfo = $this->getValidPokemon($currentPokemon);

			//	No valid pokemon entry found
			if ($pokemonInfo === false)
				throw new FriendSafariException("$currentPokemon is not a Friend Safari pokemon.");

			if ($slot == 1) {
				if ($pokemonInfo[0]['slot'] == 1) {
					//	No dual-type safari pokemon appear in slot 1, so we can safely assume type from the first pokemon
					$type = $pokemonInfo[0]['type'];
					$pokemon[] = $pokemonInfo[0]['name'];
				}
				else
					throw new FriendSafariException("{$pokemonInfo[0]['name']} appears in slot {$pokemonInfo[0]['slot']}, not slot 1.");
			}

			else {
				foreach ($pokemonInfo as $key => $row) {

					if ($type == $row['type']) {
						if ($slot == $row['slot']) {
							$pokemon[] = $row['name'];
							break;
						}
						else
							throw new FriendSafariException("{$row['name']} appears in slot {$row['slot']}, not slot $slot.");
					}

					//	Type didn't match and we've checked all possible types for this pokemon
					elseif ($key + 1 == count($pokemonInfo))
						throw new FriendSafariException(
							sprintf(
                                "{$row['name']} (%s) does not appear in the same type Friend Safari as $pokemon[0] (%s).",
                                colorType(array_column($pokemonInfo, "type"), true),
                                colorType($type, true)
                            )
						);
				}

			}
		}

		if (count($pokemon) < 2)
			throw new FriendSafariException("Not enough pokemon given.");

		if (count($pokemon) > 3)
			throw new FriendSafariException("Too many pokemon given.");

		if (count($pokemon) < 3)
			$pokemon[] = null;

		return array('pokemon' => $pokemon, 'type' => $type);
	}

} 
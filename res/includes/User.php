<?php
/**
 * Utsubot - User.php
 * User: Benjamin
 * Date: 19/11/14
 */

class User implements Manageable {
	private $id;
	private $nick = "";
	private $address = "";
	private $channels = array();
	private $idle = 0;

	public function __construct($nick, $address) {
		$this->nick = $nick;
		$this->address = $address;
		$this->idle = time();
	}

	public function join($channel) {
		//	Adds a common channel (one shared with the bot) to the list.
		if (!isset($this->channels[$channel]))
			$this->channels[$channel] = array();
	}

	public function part($channel) {
		//	Removes a common channel from the list.
		if (isset($this->channels[$channel]))
			unset($this->channels[$channel]);
	}

	public function mode($channel, $mode) {
		//	Updates the information for the user's status on the given channel (e.g., op, voice, etc).
		if (preg_match_all('/([+-])([qaohv]+)/i', $mode, $match, PREG_PATTERN_ORDER)) {

			//	For each + or - group
			foreach ($match[1] as $key => $set) {
				//	Split into individual mode letters
				$modes = str_split(strtolower($match[2][$key]));
				
				foreach ($modes as $mode) {
					//	Setting mode and mode isn't already present, add it
					if ($set == '+' && !in_array($mode, $this->channels[$channel]))
						$this->channels[$channel][] = $mode;

					//	Removing mode and mode exists, clear it
					elseif ($set == '-' && ($index = array_search($mode, $this->channels[$channel])) !== FALSE)
						unset($this->channels[$channel][$index]);
				}
			}

			//	Normalize indices
			usort($this->channels[$channel], function($a, $b) {
				$modes = "qaohv";
				return strpos($modes, $a) - strpos($modes, $b);
			});
		}
	}

	//	Returns true if this user is on the given channel.
	public function isOn($channel) {
		return array_key_exists($channel, $this->channels);
	}

	//	Returns the user's modes on the given channel.
	public function status($channel, $str = false) {
		if (!$this->isOn($channel))
			return false;

		return ($str) ? implode("", $this->channels[$channel]) : $this->channels[$channel];
	}

	public function setId($id) {
		$this->id = $id;
	}

	public function getId() {
		return $this->id;
	}

	public function setNick($nick) {
		$this->nick = $nick;
	}

	public function getNick() {
		return $this->nick;
	}

	public function setAddress($address) {
		$this->address = $address;
	}

	public function getAddress() {
		return $this->address;
	}

	public function activity() {
		$this->idle = microtime(true);
	}

	public function getTimeIdle() {
		return microtime(true) - $this->idle;
	}

	public function getChannels() {
		return $this->channels;
	}


	//	Conversion of the user object to a String yields the current nickname.
	public function __toString() {
		return $this->nick;
	}

	public function search($term): bool {
		return (strtolower($term) == strtolower($this->nick));
	}
}
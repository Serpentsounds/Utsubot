<?php
/**
 * Utsubot - Channels.php
 * User: Benjamin
 * Date: 27/04/2015
 */

class Channels extends Manager {
	protected static $manages = "Channel";

	/**
	 * @param $name
	 * @return Channel
	 */
	public function confirmChannel($name) {
		if (!($channel = $this->get($name))) {
			//	Create user
			$channel = new Channel($name);
			$this->addItem($channel, true);

			#//	Attempt to auto-login to relevant account
			#$this->IRCBot->sendToModules("channel", clone $channel);
		}

		return $channel;
	}

	public function searchFields($field, $operator = "", $value = "") {
		return null;
	}
} 
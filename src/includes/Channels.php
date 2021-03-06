<?php
/**
 * Utsubot - Channels.php
 * User: Benjamin
 * Date: 27/04/2015
 */

namespace Utsubot;

class Channels extends Manager {
	protected static $manages = "Utsubot\\Channel";

	/**
	 * @param $name
	 * @return Channel
	 */
	public function confirmChannel($name) : Channel {
        try {
            $channel = $this->search($name);
        }
        catch (ManagerException $e) {
            $channel = new Channel($name);
            $this->addItem($channel, true);
        }

        #//	Attempt to auto-login to relevant account
        #$this->IRCBot->sendToModules("channel", clone $channel);

		return $channel;
	}

	public function searchFields($field, $operator = "", $value = "") {
		return null;
	}

	public function customComparison($object, $field, $operator, $value) {
		// TODO: Implement customComparison() method.
	}
} 
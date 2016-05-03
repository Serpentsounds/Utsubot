<?php
/**
 * Utsubot - Core.php
 * User: Benjamin
 * Date: 23/11/14
 */

namespace Utsubot;


class Core extends Module {
    
    const VersionResponse = "Utsubot by Serpentsounds: https://github.com/Serpentsounds/Utsubot";
    
	public function connect() {
		$this->IRCBot->raw("PROTOCTL NAMESX");
		$this->IRCBot->raw("PROTOCTL UHNAMES");
		$this->IRCBot->raw("MODE {$this->IRCBot->getNickname()} +B");

		if (!empty($commands = $this->IRCBot->getIRCNetwork()->getOnConnect()))
		foreach ($commands as $command)
			$this->IRCBot->raw($command);

		if (!empty($channels = $this->IRCBot->getIRCNetwork()->getDefaultChannels()))
		foreach ($channels as $channel)
			$this->IRCBot->join($channel);

	}

	public function disconnect() {
		$this->IRCBot->connect();
	}

	public function ping(IRCMessage $msg) {
		$this->IRCBot->raw("PONG :{$msg->getParameterString()}");
	}

    public function ctcp(IRCMessage $msg) {
        switch (strtolower($msg->getCTCP())) {
            case "version":
                $this->IRCBot->ctcpReply($msg->getResponseTarget(), $msg->getCTCP(), self::VersionResponse);
            break;
            case "time":
                $this->IRCBot->ctcpReply($msg->getResponseTarget(), $msg->getCTCP(), date("D M j H:i:s Y"));
            break;
            case "ping":
                $this->IRCBot->ctcpReply($msg->getResponseTarget(), $msg->getCTCP(), $msg->getParameterString());
            break;
        }
    }

	/**
	 * Update User objects on nick changes
	 *
	 * @param IRCMessage $msg
	 */
	public function nick(IRCMessage $msg) {
		$users = $this->IRCBot->getUsers();
		$user = $users->createIfAbsent($msg->getNick() . "!" . $msg->getIdent() . "@" . $msg->getFullHost());
		$user->setNick($msg->getParameters()[0]);
	}

	/**
	 * On join, create new User object if necessary
	 *
	 * @param IRCMessage $msg
	 */
	public function join(IRCMessage $msg) {
		$users = $this->IRCBot->getUsers();
		$user = $users->createIfAbsent($msg->getNick() . "!" . $msg->getIdent() . "@" . $msg->getFullHost());

		$channels = $this->IRCBot->getChannels();
		//	Bot is joining a channel, add channel to list and /WHO the channel to supplement /NAMES user info
		if ($msg->getNick() == $this->IRCBot->getNickname()) {
			$channels->confirmChannel($msg->getResponseTarget());
			$this->IRCBot->raw("WHO " . $msg->getResponseTarget());
		}

		$channel = $channels->search($msg->getResponseTarget());
		if ($channel instanceof Channel) {
			$channel->join($user);
			$user->join($msg->getResponseTarget());
		}
	}

	/**
	 * On quit, destroy User object
	 *
	 * @param IRCMessage $msg
	 */
	public function quit(IRCMessage $msg) {
		//	Destroy user object
		$users = $this->IRCBot->getUsers();
		$user = $users->createIfAbsent($msg->getNick() . "!" . $msg->getIdent() . "@" . $msg->getFullHost());
        $msg->setQuitUser(clone $user);
		$users->removeItem($user);

		//	Remove user from channels userlist
		$channels = $this->IRCBot->getChannels();
		/** @var $allChannels Channel[] */
		$allChannels = $channels->collection();
		foreach ($allChannels as $channel)
			$channel->part($user);

		//	Reclaim main bot nick if ghost quit
        if ($msg->getNick() == $this->IRCBot->getIRCNetwork()->getNicknameCycle()->getPrimary())
            $this->IRCBot->nick($msg->getNick());
	}

	public function part(IRCMessage $msg) {
		$users = $this->IRCBot->getUsers();
		$channels = $this->IRCBot->getChannels();

		$user = $users->createIfAbsent($msg->getNick() . "!" . $msg->getIdent() . "@" . $msg->getFullHost());
		$channel = $channels->confirmChannel($msg->getResponseTarget());

		$user->part($channel->getName());
		$channel->part($user);

		$userChannels = array_keys($user->getChannels());
		if (!$userChannels)
			$users->removeItem($user);
	}

	public function raw(IRCMessage $msg) {
		$parameters = $msg->getParameters();

		switch ($msg->getRaw()) {
			case 001:
				$this->IRCBot->setAddress(end($parameters));
			break;

			case 004:
				$this->IRCBot->setHost($parameters[0]);
			break;

			/*
 			 *	/WHO response, create new users
 			 */
			case 352:
				//	/WHO response format
				list($channelName, $ident, $host, $server, $nick, $flags, $hops, $realname) = $msg->getParameters();

				//	Ensure User object exists and has channel listed
				$users = $this->IRCBot->getUsers();
				$channels = $this->IRCBot->getChannels();

				$user = $users->createIfAbsent("$nick!$ident@$host");
				$channel = $channels->confirmChannel($channelName);

				$user->join($channelName);
				$channel->join($user);

				//	Check flags for channel modes
				if (preg_match("/([~&@%+]+)/", $flags, $match)) {
					$modes = str_split($match[1]);
					$modeLetters = array('~' => "q", '&' => "a", '@' => "o", '%' => "h", '+' => "v");

					//	Update channel status on User object
					foreach ($modes as $mode) {
						if (isset($modeLetters[$mode]))
							$user->mode($channelName, "+{$modeLetters[$mode]}");
					}
				}
			break;

			/*
			 *	/NAMES response, create new users, with UHNAMES support
			 */
			case 353:
				//	Names responds with "= #channel :person1 person2 ...", cut "=" and save channel
				$parameters = $msg->getParameters();
				array_shift($parameters);
				$channelName = array_shift($parameters);

				$users = $this->IRCBot->getUsers();
				$channels = $this->IRCBot->getChannels();
				$channel = $channels->confirmChannel($channelName);

				//	Process each return
				foreach ($parameters as $fullAddress) {
					//	Match modes/name, and optionally host if UHNAMES is enabled
					if (preg_match("/([:~&@%+]*)([^!]+)(?:!([^@]+)@(.+))?/", $fullAddress, $match)) {
						@list(, $modes, $nick, $ident, $host) = $match;

						//	Confirm user and create with address if applicable; join channel
						$address = (strlen($ident)) ? "$ident@$host" : "";
						$user = $users->createIfAbsent("$nick!$address");
						$user->join($channelName);
						$channel->join($user);

						//	Check prefixes for channel modes
						$modes = str_split($modes);
						$modeLetters = array('~' => "q", '&' => "a", '@' => "o", '%' => "h", '+' => "v");

						//	Update channel status on User object
						foreach ($modes as $mode) {
							if (isset($modeLetters[$mode]))
								$user->mode($channelName, "+{$modeLetters[$mode]}");
						}
					}
				}
			break;

			case "433":
				$this->IRCBot->getIRCNetwork()->getNicknameCycle()->cycle();
				$this->IRCBot->raw("NICK :{$this->IRCBot->getIRCNetwork()->getNicknameCycle()->get()}");
			break;


		}
	}

	public function user(User $user) {
		$channelNames = array_keys($user->getChannels());
		$channels = $this->IRCBot->getChannels();
		foreach ($channelNames as $channelName) {
			$channel = $channels->confirmChannel($channelName);
			$channel->join($user);
		}
	}

}
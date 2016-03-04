<?php
/**
 * Utsubot - Web.php
 * User: Benjamin
 * Date: 25/11/14
 */

class Web extends ModuleWithPermission {

    use WebAccess;

	public static $separator = "";
    private static $APIServices = array("youtube", "soundcloud");

    private $APIKeys = array();
    private $URLParser;

	public function __construct(IRCBot $irc) {
		parent::__construct($irc);

        //  Set formatting separator
        self::$separator = " ". self::bold(self::color("¦", "red")). " ";

        $this->loadAPIKeys();

        $this->URLParser = new URLParser($this);

		$this->triggers = array(
			'weather'			=> "weather",
			'w'					=> "weather",
			'forecast'			=> "weather",
			'conditions'		=> "weather",

			'google'			=> "google",
			'g'					=> "google",

			'dns'				=> "dns",

			'rdns'				=> "rdns",

			'ud'				=> "dictionary",
			'urban'				=> "dictionary",
			'urbandictionary'	=> "dictionary",

			'd'					=> "dictionary",
			'def'				=> "dictionary",
			'define'			=> "dictionary",
			'dic'				=> "dictionary",
			'dictionary'		=> "dictionary",

			'yt'				=> "youtubeSearch",
			'yts'				=> "youtubeSearch",
			'youtube'			=> "youtubeSearch",
			'youtubesearch'		=> "youtubeSearch",
		);
	}

	public function privmsg(IRCMessage $msg) {
		parent::privmsg($msg);

		//	Not a command, parse URLs if applicable
		if (!$msg->isCommand() && class_exists("URLParser")) {

            if (!$this->hasPermission($msg, "urlparser"))
                return;

			$return = array();
			if (preg_match_all('/https?:\/\/[^\s\x01\x02\x03\x0F\x1D\x1F]+|(?:https?:\/\/)?www\.[^\s\x01\x02\x03\x0F\x1D\x1F]+/i', $msg->getParameterString(), $match, PREG_PATTERN_ORDER)) {

				foreach ($match[0] as $url) {
					try {
						$return[] = $this->URLParser->search($url, $msg);
					}
					catch (Exception $e) {
						$this->status($e->getMessage());
					}
				}

				$this->respond($msg, $return);
			}

			//	The message wasn't a command, so no need to continue parsing
			return;
		}

	}


    /**
     * Reload web service API keys from file
     */
    private function loadAPIKeys() {
        foreach (self::$APIServices as $service) {
            try {
                $this->APIKeys[$service] = Module::loadAPIKey($service);
            }
            catch (ModuleException $e) {
                continue;
            }
        }
    }

    /**
     * Return an API key from the cache
     *
     * @param string $service
     * @return string
     * @throws ModuleException Key not loaded
     */
    public function getAPIKey($service) {
        if (isset($this->APIKeys[$service]))
            return $this->APIKeys[$service];

        throw new ModuleException("No API key cached for '$service'.");
    }

	/**
	 * Output weather conditions information
	 *
	 * @param IRCMessage $msg
	 * @throws ModuleException If no location is given
	 * @throws WeatherException If location is invalid, or lookup fails
	 */
	public function weather(IRCMessage $msg) {
		$this->_require("Weather");

		//	Try and get default location from user settings
		$location = $this->getSetting($msg->getNick(), "weather");
		//	If the user specifies a location, that should be used instead
		if ($msg->getCommandParameterString())
			$location = $msg->getCommandParameterString();

		//	Need a location to continue
		if (!$location)
			throw new ModuleException("Please specify a location or register a default location to your account.");

		//	Try and fetch units preferences from user settings
		$unitsConversion = array('imperial' => 0, 'metric' => 1, 'both' => 2);
		$units = $this->getSetting($msg->getNick(), "units");

		//	Default to both metric and imperial
		if (!$units)
			$unitsInteger = 2;
		else {
			$units = strtolower($units);
			if (isset($unitsConversion[$units]))
				$unitsInteger = $unitsConversion[$units];
			else
				$unitsInteger = 2;
		}

		/*	Create and set options
		 *	If the user specified "forecast" or "conditions" rather than just "weather" for both, we exclude the other one	*/
		$options = array('measurementSystem' => $unitsInteger);
		$command = strtolower($msg->getCommand());
		if ($command == "forecast")
			$options['conditions'] = 0;
		elseif ($command == "conditions")
			$options['forecast'] = 0;

		//	Get weather info. WeatherException may be thrown if an error occurs
		$result = Weather::search($location, $options);

		$this->respond($msg, $result);
	}

	/**
	 * Perform a google search and output
	 *
	 * @param IRCMessage $msg
	 * @throws ModuleException If search is blank
	 * @throws GoogleException If there are no results for the search
	 */
	public function google(IRCMessage $msg) {
		$this->_require("Google");

		//	We need something to search for
		$parameters = $msg->getCommandParameters();
		if (!$parameters)
			throw new ModuleException("No search given.");

		//	Try and get user's safe-search settings. Default to on
		$safe = true;
		if ($this->getSetting($msg->getNick(), "safesearch") == "off")
			$safe = false;

		//	Try and get user's # of results settings. Default 1
		$results = $this->getSetting($msg->getNick(), "results");
		if (!($results > 1))
			$results = 1;

		//	Optionally accept an override for result count
		if (preg_match('/^results:(\d+)$/i', $parameters[0], $match)) {
			$results = $match[1];
			array_shift($parameters);
		}

		//	Create new search string after removing the options overrides
		$search = implode(" ", $parameters);
		$options = array('results' => $results, 'safe' => $safe);

		//	Perform google search. May throw a GoogleException, if no results are found
		$result = Google::search($search, $options);

		$this->respond($msg, $result);
	}

	public function dictionary(IRCMessage $msg) {
		$dictionaries = array(
			"UrbanDictionary" 	=> array("ud", "urban", "urbandictionary"),
			"Dictionary"		=> array("d", "def", "define", "dic", "dictionary")
		);
		$command = strtolower($msg->getCommand());
		$dictionary = null;
		foreach ($dictionaries as $name => $triggers) {
			if (in_array($command, $triggers)) {
				$dictionary = $name;
				break;
			}
		}

		if ($dictionary === null)
			throw new ModuleException("Invalid dictionary.");

		$this->_require($dictionary);

		if (!is_subclass_of($dictionary, "WebSearch"))
			throw new ModuleException("$dictionary does not implement WebSearch.");

		$parameters = $msg->getCommandParameters();
		if (!$parameters && $dictionary != "UrbanDictionary")
			throw new ModuleException("No search given.");

		$number = 1;
		$copy = $parameters;
		$last = array_pop($copy);
		if (!preg_match("/\\D+/", $last) && intval($last) > 0) {
			$number = intval($last);
			$parameters = $copy;
		}

		$options = array('number' => $number);
		/** @var $dictionary WebSearch */
		$result = $dictionary::search(implode(" ", $parameters), $options);

		$this->respond($msg, $result);
	}

	/**
	 * Lookup DNS for a hostname. Get A records (IPV4), AAAA records (IPV6), and CNAME records (alias)
	 *
	 * @param IRCMessage $msg
	 * @throws ModuleException If hostname is invalid, or no dns records are found
	 */
	public function dns(IRCMessage $msg) {
		//	Make sure the host isn't bogus before attempting dns
		if (!preg_match('/^([A-Z0-9\-]+\.)+[A-Z0-9\-]+\.?$/i', $msg->getCommandParameterString(), $match))
			throw new ModuleException("Invalid hostname format.");

		//	Append trailing . to speed up the return in some cases
		if (substr($match[0], -1) != ".")
			$match[0] .= ".";

		//	dns_get_record will throw an error if lookup fails, so @suppress it and throw an exception instead
		$records = @dns_get_record($match[0], DNS_A + DNS_AAAA + DNS_CNAME);
		if (!$records || count($records) == 0)
			throw new ModuleException("No DNS record found.");

		$result = array();

		//	Filter DNS record array based on record type
		foreach ($records as $entry) {
			if ($entry['type'] == "A")
				$result['A'][] = self::bold($entry['ip']);
			elseif ($entry['type'] == "AAAA")
				$result['AAAA'][] = self::bold($entry['ipv6']);
			elseif ($entry['type'] == "CNAME")
				$result['CNAME'][] = self::bold($entry['target']);
		}

		//	Join multiple entries of the same type with a comma, and join types with a semicolon
		if (count($result) > 0) {
			$response = array();
			foreach ($result as $type => $arr)
				$response[] = implode(", ", $arr). " [$type]";

			$response = self::bold($match[0]). " resolved to ". implode(self::$separator, $response);
			$this->respond($msg, $response);
		}

	}

	public function rdns(IRCMessage $msg) {
		$ip = $msg->getCommandParameterString();

		if (!preg_match('/^((25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/', $ip, $match))
			throw new ModuleException("Invalid IP address format.");

		$json = self::resourceBody("http://ip-api.com/json/$ip");
		$results = json_decode($json, true);

		if ($results['status'] != "success")
			throw new ModuleException("Lookup failed.");

		$output = array(
			sprintf("%s: %s [%s]", self::bold("Country"), $results['country'], $results['countryCode']),
			sprintf("%s: %s [%s]", self::bold("Region"), $results['regionName'], $results['region']),
			sprintf("%s: %s [%s]", self::bold("City"), $results['city'], $results['zip']),
			sprintf("%s: %s°%s, %s°%s", self::bold("Location"), round(abs($results['lat']), 2), (($results['lat'] < 0) ? "S" : "N"), round(abs($results['lon']), 2), (($results['lon'] < 0) ? "W" : "E")),
			sprintf("%s: %s", self::bold("Time Zone"), str_replace("_", " ", $results['timezone'])),
			sprintf("%s: %s", self::bold("ISP"), $results['isp'])
		);

		$this->respond($msg, implode(self::$separator, $output));
	}

	public function youtubeSearch(IRCMessage $msg) {
		$maxResults = 1;
		$parameterString = $msg->getCommandParameterString();
		$parameters = $msg->getCommandParameters();
		$first = array_shift($parameters);
		if (preg_match('/^rsz:([1-5])$/', $first, $match)) {
			$maxResults = $match[1];
			$parameterString = implode(" ", $parameters);
		}

		$json = self::resourceBody("https://www.googleapis.com/youtube/v3/search?part=snippet&type=video&key=". $this->getAPIKey("youtube"). "&q=". urlencode($parameterString));
		$items = json_decode($json, true)['items'];

		$count = count($items);
		if (!$count)
			throw new ModuleException("No results found.");


		$output = array();
		for ($i = 0; $i < $count; $i++) {
			if (!isset($items[$i]))
				break;

			if ($items[$i]['id']['kind'] == "youtube#video") {
				$output[] = sprintf(
					"http://www.youtube.com/watch?v=%s%s%s%s%s",
					$items[$i]['id']['videoId'], self::$separator, self::bold($items[$i]['snippet']['title']), self::$separator, $items[$i]['snippet']['description']
				);
			}

			if (count($output) >= $maxResults)
				break;
		}

		$this->respond($msg, implode("\n", $output));
	}
}
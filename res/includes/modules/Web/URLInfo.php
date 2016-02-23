<?php
/**
 * PHPBot - URLInfo.php
 * User: Benjamin
 * Date: 08/06/14
 */

require_once("WebSearch.php");

class URLInfoException extends Exception {}

class URLInfo implements WebSearch {
	const WIKIPEDIA_PREVIEW_LENGTH = 250;

	private static $youtubeAPIKey = "";
	private static $soundcloudClientID = "";

	private static $urlCache = array();
	private static $cacheDelay = 1800;

	/**
	 * Parse a URL and give standard or custom information about the contents, if relevant
	 *
	 * @param string $search The URL
	 * @param array $options Options array from interface. No effect.
	 * @return bool|string Relevant output, or false on failure
	 * @throws URLInfoException If content type is unable to be determined, or if the URL string can't be parsed properly
	 */
	public static function search($search, $options = array()) {
		if (!strlen(self::$youtubeAPIKey))
			self::$youtubeAPIKey = Module::getAPIKey("youtube");
		if (!strlen(self::$soundcloudClientID))
			self::$soundcloudClientID = Module::getAPIKey("soundcloud");

		$checkPermission = false;
		$permission = null;
		if (isset($options['permission']) && ($permission = $options['permission']) && $permission instanceof Permission && isset($options['ircmessage']) && $options['ircmessage'] instanceof IRCMessage)
			$checkPermission = true;

		//	Ignore recently parsed URLs
		$time = time();
		if (isset(self::$urlCache[$search]) && $time - self::$urlCache[$search] <= self::$cacheDelay)
			return false;
		self::$urlCache[$search] = $time;

		//	HTTP header only
		$headers = WebAccess::resourceHeader($search);

		//	Check content type
		if (!preg_match("/\sContent-Type: ?([^\s;]+)/i", $headers, $match))
			throw new URLInfoException("Content-Type header missing in '$search'.");
		$contentType = $match[1];

		//	text/html is a webpage, check for domain specific parsing or fall to default
		if ($contentType == "text/html") {
			if (!preg_match("/https?:\/\/([^\/]+)(?:\/(.*))?/i", $search, $match))
				throw new URLInfoException("Malformed url '$search'.");

			//	Grab http://domain/page
			@list(, $domain, $page) = $match;
			$mainDomain = $domain;
			if (substr_count($mainDomain, ".") > 1)
				//	Remove all subdomains
				$mainDomain = substr($mainDomain, 1 + strrpos($mainDomain, ".", strrpos($mainDomain, ".") - strlen($mainDomain) - 1));

			//	Check for domain-specific parsing (e.g., youtube video info)
			switch ($mainDomain) {
				case "youtube.com":
					if (preg_match("/^watch\?.*?v=([^#&?\/]+)/i", $page, $video) &&
						(!$checkPermission || $permission->hasPermission($options['ircmessage'], "youtube")))
						return self::youtube($video[1]);
				break;
				case "youtu.be":
					if (preg_match("/^([^#&?\/]+)/", $page, $video) &&
						(!$checkPermission || $permission->hasPermission($options['ircmessage'], "youtube")))
						return self::youtube($video[1]);
				break;

				case "wikipedia.org":
					if (preg_match("/^wiki\/(.+)/", $page, $article) &&
						(!$checkPermission || $permission->hasPermission($options['ircmessage'], "wikipedia")))
						return self::wikipedia($article[1]);
					return "";
				break;
				case "soundcloud.com":
					if (preg_match("/^[^\/]+\/.+/", $page) &&
						(!$checkPermission || $permission->hasPermission($options['ircmessage'], "soundcloud")))
						return self::soundcloud($search);
				break;

				//	Default to return html <title>
				default:
					if (!$checkPermission || $permission->hasPermission($options['ircmessage'], "urltitle"))
						return self::URLTitle($search);
				break;
			}
		}



		/*elseif 	($contentType == "image/jpeg" &&
				(!$checkPermission || $options['permission']->hasPermission($options['ircmessage'], "exif")) &&
			   	($image = WebAccess::resourceBody($search)) &&
			   	(file_put_contents("temp.jpg", $image)) &&
			   	($exif = exif_read_data("temp.jpg", "EXIF", true))) {

					print_r($exif['EXIF']);
					unlink("temp.jpg");
		}*/

		//	Not a page, but some other resource (image, song, etc)
		elseif (!$checkPermission || $permission->hasPermission($options['ircmessage'], "remotefile")) {
			//	Show only file size and type from header
			$contentLength = (preg_match("/\sContent-Length: ?(\d+)/", $headers, $match)) ? ", ". self::formatBytes($match[1]) : "";
			//	Format URl and eliminate query parameters
			$file = explode("?", rawurldecode(basename($search)))[0];

			return sprintf("\"%s\": %s%s",
						$file,
						$contentType,
						$contentLength);
		}

		return false;
	}

	/**
	 * Utility to format a byte count into human readable byte prefixes
	 *
	 * @param int $size Size in bytes
	 * @param int $precision Rounding digits after the decimal
	 * @return string Bytes divided to the most sensible units
	 */
	public static function formatBytes($size, $precision = 2) {
		//	$base determines the most sensible power of 1024 to divide by
		$base = log($size) / log(1024);
		$suffixes = array("B", "kB", "MB", "GB", "TB");

		return round(pow(1024, $base - floor($base)), $precision). $suffixes[(int)floor($base)];
	}

	/**
	 * Get YouTube information about a video
	 *
	 * @param string $video A video ID
	 * @return string A formatted string showing video information
	 * @throws URLInfoException If video is not found
	 */
	public static function youtube($video) {
		//	Access API and validate existence
		$json = WebAccess::resourceBody("https://www.googleapis.com/youtube/v3/videos?part=snippet%2CcontentDetails%2Cstatistics&id=$video&key=". self::$youtubeAPIKey);
		$data = json_decode($json, true);

		//	Invalid video
		if ($data['pageInfo']['totalResults'] < 1)
			throw new URLInfoException("Video '$video' not found.");

		//	Assign and format relevant information
		$info = $data['items'][0];
		$title = IRCUtility::italic(str_replace("''", "\"", $info['snippet']['title']));
		$uploader = IRCUtility::italic($info['snippet']['channelTitle']);
		$date = IRCUtility::italic(date("F j, Y", strtotime($info['snippet']['publishedAt'])));
		$duration = $info['contentDetails']['duration'];
		$views = IRCUtility::italic(number_format($info['statistics']['viewCount']));
		$likes = IRCUtility::italic(number_format($info['statistics']['likeCount']));
		$dislikes = IRCUtility::italic(number_format($info['statistics']['dislikeCount']));
		$comments = IRCUtility::italic(number_format($info['statistics']['commentCount']));
		$duration = self::duration($duration);

		//	Add in individual information sections to be joined
		$output = array(
			$title,
			$duration,
			"$views views",
			"$likes likes, $dislikes dislikes",
			"$comments comments",
			"Uploaded by $uploader on $date",
		);

		//	Special info for live broadcasts
		if ($info['snippet']['liveBroadcastContent'] == "live") {
			$json = WebAccess::resourceBody("https://www.googleapis.com/youtube/v3/videos?part=liveStreamingDetails&id=$video&key=". self::$youtubeAPIKey);
			$data = json_decode($json, true);
			if ($data['pageInfo']['totalResults'] < 1)
				throw new URLInfoException("Error getting live streaming info for '$video'.");
			$info = $data['items'][0];

			//	Format information and determine broadcast length
			$currentTime = new DateTime();
			$startTime = $info['liveStreamingDetails']['actualStartTime'];
			$currentBroadcastLength = $currentTime->diff(new DateTime($startTime));
			$duration = self::duration($currentBroadcastLength->format("P%dDT%hH%iM%sS"));
			$viewers = IRCUtility::italic($info['liveStreamingDetails']['concurrentViewers']);
			$startDate = IRCUtility::italic(date("H:i:s", strtotime($startTime))). " on ". IRCUtility::italic(date("m/d/y", strtotime($startTime)));

			//	Information sections for broadcast
			$output = array(
				$title,
				"Broadcasting for $duration",
				"Started at $startDate by $uploader",
				$viewers. " currently watching"
			);
		}

		return implode(Web::$separator, $output);
	}

	/**
	 * Convert an ISO8601 formatted time to a human readable duration
	 *
	 * @param string $iso8601
	 * @return string
	 */
	private static function duration($iso8601) {
		$duration = IRCUtility::italic("0sec");
		if (preg_match("/^P(?:(\d+)D)?T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)$/", $iso8601, $match)) {
			$units = array("d", "hr", "min", "sec");
			$durationComponents = array();
			for ($i = 0, $count = count($units); $i < $count; $i++) {
				if ($match[$i+1] || ($i == $count - 1 && count($durationComponents) == 0))
					$durationComponents[] = IRCUtility::italic($match[$i+1]). $units[$i];
			}

			$duration = implode(" ", $durationComponents);
		}

		return $duration;
	}

	/**
	 * Get a preview for a Wikipedia article
	 *
	 * @param string $article Name of article as it appears in the URL
	 * @return string The beginning of the article with formatting removed
	 * @throws URLInfoException If article is not found or lookup fails
	 */
	public static function wikipedia($article) {
		$content = WebAccess::resourceBody("http://en.wikipedia.org/w/api.php?action=query&prop=revisions&rvlimit=1&rvprop=content&format=json&rawcontinue&titles=$article");

		$data = json_decode($content, true);

		//	Page listing will be in here
		if (!isset($data['query']['pages']) || !count($data['query']['pages']))
			throw new URLInfoException("URLInfo::wikipedia: Invalid article '$article'.");

		//	Determine the index of the first result
		$pageID = array_keys($data['query']['pages'])[0];
		$pageArr = $data['query']['pages'][$pageID];

		//	The article in its current state will be under this index
		if (!isset($pageArr['revisions']) || !isset($pageArr['revisions'][0]['*']))
			throw new URLInfoException("URLInfo::wikipedia: Unable to get contents for '$article'.");

		$pageContents = $data['query']['pages'][$pageID]['revisions'][0]['*'];
		/*	Trim the beginning to lower processing time. We only need a few hundred content characters.
			A string that is too long will also cause the recursive regex to silently crash PHP	*/
		$pageContents = mb_substr($pageContents, 0, 5000);

		//	Delete {{stuff{{in}}braces}} recursively
		$pageContents = preg_replace("/\{\{(([^\{\}])*|(?R))*\}\}/", "", $pageContents);
		//	Remove infoboxes
		$pageContents = preg_replace("/\{\|[^\}]+\|\}/", "", $pageContents);
		//	Clear any file insert metadata
		$pageContents = preg_replace("/\[\[File:.+/i", "", $pageContents);
		//	Replace [[stuff|in|brackets]] with the last piece ("brackets" here)
		$pageContents = preg_replace("/\[\[(?:[^|\]]*\|)*([^\]]*?)\]\]/", "\$1", $pageContents);
		//	Replace ''words'' '''with quotes''' with the original word
		$pageContents = preg_replace("/'''?([^']*)'''?/", "\$1", $pageContents);
		//	Fix hanging parentheses openings if first item was cleared
		$pageContents = str_replace("(, ", "(", $pageContents);
		//	Remove references
		$pageContents = preg_replace("/<ref>.*?<\/ref>/", "", $pageContents);
		//	Get rid of html and surrounding whitespace
		$pageContents = trim(WebAccess::stripHTML($pageContents));

		//	Follow redirects
		if (preg_match("/^#REDIRECT (.+)/i", $pageContents, $match))
			return self::wikipedia($match[1]);

		//	Return remaining content with hard cutoff
		return sprintf("%s (%s): %s...",
					IRCUtility::bold("Wikipedia"),
					$data['query']['pages'][$pageID]['title'],
					mb_substr($pageContents, 0, self::WIKIPEDIA_PREVIEW_LENGTH));
	}

	public static function soundcloud($url) {
		$data = WebAccess::resourceBody("http://api.soundcloud.com/resolve?url=$url&client_id=". self::$soundcloudClientID);
		$json = json_decode($data, true);

		if (!isset($json['title']) || !$json['title'])
			throw new URLInfoException("Invalid song URL.");

		$title = IRCUtility::italic($json['title']);
		$artist = IRCUtility::italic($json['user']['username']);
		$plays = IRCUtility::italic(number_format($json['playback_count']));
		$timestamp = strtotime($json['created_at']);
		$date = IRCUtility::italic(date("M j, Y", $timestamp));
		$time = IRCUtility::italic(date("H:i:s", $timestamp));

		$currentTime = new DateTime();
		$offsetTime = $currentTime->diff(new DateTime(date("r", time() + floor($json['duration'] / 1000))));
		$duration = self::duration($offsetTime->format("P%dDT%hH%iM%sS"));

		$output = array(
			"$artist - $title",
			"$plays plays",
			$duration,
			"Created on $date at $time"
		);

		return implode(Web::$separator, $output);
	}

	/**
	 * Get the title or a webpage as defined in the HTML <title> tag
	 *
	 * @param string $url
	 * @param bool $skipSimilar True to abort if the URL and title are deemed "too similar" to provide any new information
	 * @param float $similarPercent The minimum percentage of words in the title that must also be found in the URL for it to be considered "too similar" (default 70%)
	 * @return string The connection delay and title tag formatted into output
	 * @throws URLInfoException If <title> tag can't be located
	 */
	public static function URLTitle($url, $skipSimilar = true, $similarPercent = 0.7) {
		$time = microtime(true);
		$page = WebAccess::resourceFull($url);

		if (preg_match("/<title>([^<]+)<\/title>/mi", $page, $match)) {
			$title = $match[1];

			//	Attempt to find encoding of page, assume UTF-8 if none found
			$encoding = "UTF-8";
			//	Get from HTTP headers
			if (preg_match('/Content-Type: [^;]*; ?charset=([0-9a-z\-_]+)/is', $page, $match))
				$encoding = strtoupper($match[1]);
			//	Or, get from HTML tags
			elseif (preg_match('/<meta http-equiv="Content-Type" content="[^;]*; ?charset=([^"]+)"/is', $page, $match))
				$encoding = strtoupper($match[1]);

			//	Convert encoding if necessary
			$encoding = str_replace("SHIFT_JIS", "SJIS", $encoding);
			if ($encoding != "UTF-8" && $encoding != "ISO-8859-1")
				$title = iconv($encoding, "UTF-8", $title);

			//	Convert entities
			$title = WebAccess::stripHTML($title);

			//	Compare words in title to part of the URL. If a certain percentage are present, abort returning the title
			if ($skipSimilar) {
				$words = explode(" ", $title);
				$common = 0;

				//	A word counts as "in" the title if it passes a regex with word boundaries, not including underscores
				foreach ($words as $key => $word) {
					//	Remove special characters for comparison
					$word = str_replace(array("(", ")", ":", "[", "]", "/", "\\", "{", "}", "'", "\"", "_"), "", $word);

					//	If "word" was only special characters, factor it out of the calculations
					if (!trim($word)) {
						unset($words[$key]);
						continue;
					}

					//	Word appears in URL
					if (preg_match("/(\b|_)$word(\b|_)/i", $url))
						$common++;
				}

				//	Too similar, not enough new information provided to output
				if ($common >= count($words) * $similarPercent)
					return "";
			}

			if (mb_strlen($title) > 300)
				$title = mb_substr($title, 0, 300). "...";
			return sprintf("(%ss): %s", round(microtime(true) - $time, 3), $title);
		}

		throw new URLInfoException("No title found for '$url'.");
	}
}
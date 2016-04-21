<?php
/**
 * PHPBot - URLParser.php
 * User: Benjamin
 * Date: 08/06/14
 */

namespace Utsubot\Web;
use Utsubot\{IRCMessage, ModuleException};
use function Utsubot\{bold, italic};


class URLParserException extends ModuleException {}

class URLParser {

	const WIKIPEDIA_PREVIEW_LENGTH = 350;
    const URL_CACHE_DELAY = 1800;

    private $web;
    private $URLRegexes = array();
	private $URLCache = array();

    public function __construct(Web $web) {
        $this->web = $web;

        $this->registerRegex("youtube.com", "/^watch\?.*?v=([^#&?\/]+)/i", array($this, "youtube"), "youtube");
        $this->registerRegex("youtu.be", "/^([^#&?\/]+)/", array($this, "youtube"), "youtube");
        $this->registerRegex("wikipedia.org", "/^wiki\/([^#&?]+)/", array($this, "wikipedia"), "wikipedia");
        $this->registerRegex("soundcloud.com", "/^[^\/]+\/.+/", array($this, "soundcloud"), "soundcloud");
    }

	/**
	 * Parse a URL and give standard or custom information about the contents, if relevant
	 *
	 * @param string $search The URL
	 * @param IRCMessage $msg
	 * @return bool|string Relevant output, or false on failure
	 * @throws URLParserException If content type is unable to be determined, or if the URL string can't be parsed properly
	 */
	public function search($search, IRCMessage $msg) {
        //  Don't look up recent links
        if ($this->isCached($search, "url", $msg->getResponseTarget()))
			return false;

		//	HTTP header only, if necessary individual parsers can download content later
		$headers = resourceHeader($search);

		//	Check content type
		if (!preg_match("/\sContent-Type: ?([^\s;]+)/i", $headers, $match))
			throw new URLParserException("Content-Type header missing in '$search'.");
		$contentType = $match[1];


		//	text/html is a webpage, check for domain specific parsing or fall to default
		if ($contentType == "text/html") {
			//	Abort if unable to regex domain
			if (!preg_match("/https?:\/\/([^\/]+)(?:\/(.*))?/i", $search, $match))
				throw new URLParserException("Malformed url '$search'.");

			//	Grab http://domain/page
			@list(, $domain, $page) = $match;
			$mainDomain = $domain;
			if (substr_count($mainDomain, ".") > 1)
				//	Remove all subdomains
				$mainDomain = substr($mainDomain, 1 + strrpos($mainDomain, ".", strrpos($mainDomain, ".") - strlen($mainDomain) - 1));

			//	Check for domain-specific parsing
            if (isset($this->URLRegexes[$mainDomain])) {
                foreach ($this->URLRegexes[$mainDomain] as $entry) {

                    //  Page file name regex match and permission ok
                    if (preg_match($entry['regex'], $page, $match) && $this->web->hasPermission($msg, $entry['permission']))
                        return call_user_func_array($entry['method'], array($search, $match, $msg->getResponseTarget()));
                }
            }

            //  Default, return URL title
            elseif ($this->web->hasPermission($msg, "urltitle"))
                return $this->URLTitle($search, $msg->getResponseTarget());
		}


		//	Not a page, but some other resource (image, song, etc)
		elseif ($this->web->hasPermission($msg, "remotefile")) {
			//	Show only file size and type from header
			$contentLength = (preg_match("/\sContent-Length: ?(\d+)/", $headers, $match)) ? ", ". self::formatBytes($match[1]) : "";

            $filteredURL = preg_split("/[#?]/", $search)[0];
            if ($this->isCached($filteredURL, "resource", $msg->getResponseTarget()))
                return false;

			return sprintf("\"%s\": %s%s",
						basename(rawurldecode($filteredURL)),
						$contentType,
						$contentLength);
		}

		return false;
	}

    /**
     * Assign a regular expression to match pages from a given domain
     *
     * @param string $domain
     * @param string $regex
     * @param callable $method The method to call to parse the webpage if a match occurs
     * @param string $permission The subcategory of the permission system for this event
     */
    private function registerRegex($domain, $regex, callable $method, $permission) {
        $this->URLRegexes[$domain][] = array(
            'regex'         => $regex,
            'method'        => $method,
            'permission'    => $permission
        );
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
     * Convert an ISO8601 formatted time to a human readable duration
     *
     * @param string $iso8601
     * @return string
     */
    private static function duration($iso8601) {
        $duration = italic("0sec");
        if (preg_match("/^P(?:(\d+)D)?T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)$/", $iso8601, $match)) {
            $units = array("d", "hr", "min", "sec");
            $durationComponents = array();
            for ($i = 0, $count = count($units); $i < $count; $i++) {
                if ($match[$i+1] || ($i == $count - 1 && count($durationComponents) == 0))
                    $durationComponents[] = italic($match[$i+1]). $units[$i];
            }

            $duration = implode(" ", $durationComponents);
        }

        return $duration;
    }

	/**
	 * Check if the name of a resource is cached, to prevent duplicate fetching and/or display
	 * Additionally, cache timer will be set if needed
	 *
	 * @param string $item Search term
	 * @param string $cache Subdivision of the cache (e.g., url, resource, youtube)
     * @param string $target Location of the action
	 * @return bool True/false if item is still within cache timer
	 */
	private function isCached($item, $cache, $target) {
		$time = time();
		if (isset($this->URLCache[$cache][$target][$item]) && $time - $this->URLCache[$cache][$target][$item] <= self::URL_CACHE_DELAY)
			return true;

		$this->URLCache[$cache][$target][$item] = $time;
		return false;
	}

	/**
	 * Get YouTube information about a video
	 *
	 * @param string $search Base URL
     * @param array $match Regex match, index 1 will be video id
     * @param string $channel Output channel name (for caching)
	 * @return string A formatted string showing video information
	 * @throws URLParserException If video is not found
	 */
	public function youtube($search, $match, $channel) {
        $video = $match[1];
		//	Don't fetch recent videos
		if ($this->isCached($video, "youtube", $channel))
			return "";

		//	Access API and validate existence
		$json = resourceBody("https://www.googleapis.com/youtube/v3/videos?part=snippet%2CcontentDetails%2Cstatistics&id=$video&key={$this->web->getAPIKey('youtube')}");
		$data = json_decode($json, true);

		//	Invalid video
		if ($data['pageInfo']['totalResults'] < 1)
			throw new URLParserException("Video '$video' not found.");

		//	Assign and format relevant information
		$info = $data['items'][0];
		$title = italic(str_replace("''", "\"", $info['snippet']['title']));
		$uploader = italic($info['snippet']['channelTitle']);
		$date = italic(date("F j, Y", strtotime($info['snippet']['publishedAt'])));
		$duration = $info['contentDetails']['duration'];
		$views = italic(number_format($info['statistics']['viewCount']));
		$likes = italic(number_format($info['statistics']['likeCount']));
		$dislikes = italic(number_format($info['statistics']['dislikeCount']));
		$comments = italic(number_format($info['statistics']['commentCount']));
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
			$json = resourceBody("https://www.googleapis.com/youtube/v3/videos?part=liveStreamingDetails&id=$video&key={$this->web->getAPIKey('youtube')}");
			$data = json_decode($json, true);
			if ($data['pageInfo']['totalResults'] < 1)
				throw new URLParserException("Error getting live streaming info for '$video'.");
			$info = $data['items'][0];

			//	Format information and determine broadcast length
			$currentTime = new \DateTime();
			$startTime = $info['liveStreamingDetails']['actualStartTime'];
			$currentBroadcastLength = $currentTime->diff(new \DateTime($startTime));
			$duration = self::duration($currentBroadcastLength->format("P%dDT%hH%iM%sS"));
			$viewers = italic($info['liveStreamingDetails']['concurrentViewers']);
			$startDate = italic(date("H:i:s", strtotime($startTime))). " on ". italic(date("m/d/y", strtotime($startTime)));

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
     * Get a preview for a Wikipedia article
     *
     * @param string $search Base URL
     * @param array $match Regex match, index 1 will be article name
     * @param string $channel Output channel name (for caching)
     * @return string The beginning of the article with formatting removed
     * @throws URLParserException If article is not found or lookup fails
     */
    public function wikipedia($search, $match, $channel) {
        $article = $match[1];
        //	Don't parse recent articles
        if ($this->isCached($article, "wikipedia", $channel))
            return "";

        $content = resourceBody("https://en.wikipedia.org/w/api.php?action=query&prop=text&action=parse&format=json&page=$article");
        $data = json_decode($content, true);

        //  Most likely article not found error
        if (isset($data['error']))
            throw new URLParserException("(Wikipedia) {$data['error']['info']}.");

        //  Unknown error
        elseif (!isset($data['parse']['text']['*']))
            throw new URLParserException("Unable to grab Wikipedia text.");

        $pageContents = $data['parse']['text']['*'];

        //  Form preview out of paragraphs
        if (preg_match_all("/<p>(.*?)<\/p>/is", $pageContents, $match)) {
            $return = array();
            $output = "";

            foreach($match[1] as $paragraph) {

                $paragraph = stripHTML($paragraph);
                //  Remove citation note links
                $paragraph = preg_replace("/\[\d+\]/", "", $paragraph);

                $return[] = trim($paragraph);
                $output = implode(" ", $return);

                //  Preview length exceeded, crop and break for output
                if (mb_strlen($output) > self::WIKIPEDIA_PREVIEW_LENGTH) {
                    $output = mb_substr($output, 0, self::WIKIPEDIA_PREVIEW_LENGTH). "...";
                    break;
                }
            }

            return sprintf("%s (%s): %s",
               bold("Wikipedia"),
               $data['parse']['title'],
               $output);
        }

        return "";
    }

    /**
     * Get metadata for a soundcloud song
     *
     * @param string $search Base URL
     * @param array $match Regex match
     * @param string $channel Output channel name (for caching)
     * @return string Song metadata
     * @throws ModuleException
     * @throws URLParserException
     */
	public function soundcloud($search, $match, $channel) {

		$data = resourceBody("http://api.soundcloud.com/resolve?url=$search&client_id={$this->web->getAPIKey('soundcloud')}");
		$json = json_decode($data, true);

		if (!isset($json['title']) || !$json['title'])
			throw new URLParserException("Invalid song URL.");

        if ($this->isCached($json['id'], "soundcloud", $channel))
            return "";

		$title = italic($json['title']);
		$artist = italic($json['user']['username']);
		$plays = italic(number_format($json['playback_count']));
		$timestamp = strtotime($json['created_at']);
		$date = italic(date("M j, Y", $timestamp));
		$time = italic(date("H:i:s", $timestamp));

		$currentTime = new \DateTime();
		$offsetTime = $currentTime->diff(new \DateTime(date("r", time() + floor($json['duration'] / 1000))));
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
     * @param string $channel Output channel name (for caching)
	 * @param bool $skipSimilar True to abort if the URL and title are deemed "too similar" to provide any new information
	 * @param float $similarPercent The minimum percentage of words in the title that must also be found in the URL for it to be considered "too similar" (default 70%)
	 * @return string The connection delay and title tag formatted into output
	 * @throws URLParserException If <title> tag can't be located
	 */
	public function URLTitle($url, $channel, $skipSimilar = true, $similarPercent = 0.7) {
		$time = microtime(true);
		$page = resourceFull($url);
        $fetchTime = round(microtime(true) - $time, 3);

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
			$title = stripHTML($title);

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

			//	Trim long titles
			if (mb_strlen($title) > 300)
				$title = mb_substr($title, 0, 300). "...";

			//	Don't show recent titles
			if ($this->isCached($title, "title", $channel))
				return "";

			return sprintf("(%ss): %s", $fetchTime, $title);
		}

		throw new URLParserException("No title found for '$url'.");
	}
}
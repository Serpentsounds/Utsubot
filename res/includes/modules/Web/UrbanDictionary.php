<?php
/**
 * Utsubot - UrbanDictionary.php
 * User: Benjamin
 * Date: 24/01/2015
 */

class UrbanDictionaryException extends WebSearchException {}

class UrbanDictionary implements WebSearch {

	/**
	 * Look up a word on Urban Dictionary
	 *
	 * @param string $search
	 * @param array $options Unavailable
	 * @return string Definition and examples
	 */
	public static function search($search, $options = array()) {
		$number = 1;
		if (isset($options['number']) && is_int($options['number']) && $options['number'] > 0)
			$number = $options['number'];

		return self::urbanDictionarySearch($search, $number);
	}

	/**
	 * Get a definition from Urban Dictionary
	 *
	 * @param string $term URL encoded search term
	 * @return string Definition and examples
	 * @throws UrbanDictionaryException If term is not found
	 */
	public static function urbanDictionarySearch($term, $number = 1) {
		if (!$term)
			$content = WebAccess::resourceBody("http://www.urbandictionary.com/random.php");
		else
			$content = WebAccess::resourceBody("http://www.urbandictionary.com/define.php?term=". urlencode($term));

		$regex = "/<div class='def-header'>\s*<a[^>]+>([^<]+)<\/a>\s*(?:<a class='play-sound'[^>]+>\s*<i[^>]+><\/i>\s*<\/a>\s*)?<\/div>\s*<div class='meaning'>\s*(.+?)<\/div>\s*<div class='example'>\s*(.+?)<\/div>/s";
		$number -= 1;

		if (!preg_match_all($regex, $content, $match, PREG_SET_ORDER))
			throw new UrbanDictionaryException("No definition found for '$term'.");

		elseif (!isset($match[$number]))
			throw new UrbanDictionaryException("Definition number $number not found for '$term'.");

		$result = sprintf("%s: %s\n%s",
					   IRCUtility::bold(WebAccess::stripHTML($match[$number][1])),
					   WebAccess::stripHTML($match[$number][2]),
					   WebAccess::stripHTML($match[$number][3]));

		if (mb_strlen($result) > 750)
			$result = mb_substr($result, 0, 750). " ...More at http://www.urbandictionary.com/define.php?term=". urlencode($match[$number][1]);

		return $result;
	}
} 
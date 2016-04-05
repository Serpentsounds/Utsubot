<?php
/**
 * PHPBot - Google.php
 * User: Benjamin
 * Date: 08/06/14
 */

namespace Utsubot\Web;
use function Utsubot\bold;


class GoogleException extends WebSearchException {}

class Google implements WebSearch {

	const DEFAULT_RESULTS = 1;
	const MAX_RESULTS = 5;
	const SAFE_SEARCH = false;

	/**
	 * Perform a Google search
	 *
	 * @param string $search
	 * @param array $options Options include result count and safe search
	 * @return string All results separated by linebreaks
	 */
	public static function search($search, $options = array()) {
		$results = self::DEFAULT_RESULTS;
		if (isset($options['results']) && is_int($options['results']) && $options['results'] <= self::MAX_RESULTS && $options['results'] >= 1)
			$results = $options['results'];

		$safeSearch = "off";
		if (self::SAFE_SEARCH || (isset($options['safe']) && $options['safe']))
			$safeSearch = "on";

		return self::googleSearch($search, $results, $safeSearch);
	}

	/**
	 * This function does the dirty work for search
	 *
	 * @param string $search Google search terms
	 * @param int $results Numer of results to return
	 * @param bool|string $safeSearch Safe search, true or false, or "on" or "off"
	 * @return string All results separated by linebreaks
	 * @throws GoogleException If no results are found
	 */
	public static function googleSearch($search, $results = self::DEFAULT_RESULTS, $safeSearch = self::SAFE_SEARCH) {
		//	Convert boolean safe search values to on or off, for use in api
		if ($safeSearch == self::SAFE_SEARCH)
			$safeSearch = (self::SAFE_SEARCH) ? "on" : "off";

		$string = resourceBody("http://ajax.googleapis.com/ajax/services/search/web?v=1.0&q=". urlencode($search). "&safe=$safeSearch&rsz=$results");

		$data = json_decode($string, TRUE);
		$out = array();

		//	There are some results
		if (count($data['responseData']['results']) > 0) {
			//	If we're returning more than 1 result, add a small header to the top
			if ($results > 1) {
				$out[] = sprintf("Top $results Google result(s) for %s (of %s total):",
							bold($search),
							number_format($data['responseData']['cursor']['estimatedResultCount']));

				//	Loop through all results and add them
				$currentResult = 0;
				while (isset($data['responseData']['results'][$currentResult]))
					$out[] = sprintf("%d. %s (%s) - %s",
								$currentResult + 1,
								stripHTML(rawurldecode($data['responseData']['results'][$currentResult]['url'])),
								stripHTML($data['responseData']['results'][$currentResult]['titleNoFormatting']),
								stripHTML($data['responseData']['results'][$currentResult++]['content']));
			}

			//	Add only the single result
			else
				$out[] = sprintf("%s (%s) - %s",
							 stripHTML(rawurldecode($data['responseData']['results'][0]['url'])),
							 stripHTML($data['responseData']['results'][0]['titleNoFormatting']),
							 stripHTML($data['responseData']['results'][0]['content']));
		}
		//	No results
		else
			throw new GoogleException("No results found for search '$search'.");

		return implode("\n", $out);
	}

}
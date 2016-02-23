<?php
/**
 * Utsubot - Calculator.php
 * User: Benjamin
 * Date: 17/12/2014
 */

class CalculatorException extends WebSearchException {}

class Calculator implements WebSearch {
	/**
	 * Perform a calculation
	 *
	 * @param string $search Expression
	 * @param array $options
	 * @return string Result
	 */
	public static function search($search, $options = array()) {
		$string = WebAccess::resourceBody("http://www.wolframalpha.com/input/?i=". urlencode($search));

		if (preg_match('/context\.jsonArray\.popups\.pod_0200\.push\( \{"stringified": "([^"]*)","mInput": "[^"]*","mOutput": "([^"]*)",/i', $string, $match) &&
			@$match[1] &&
		 	preg_match('/context\.jsonArray\.popups\.pod_0100\.push\( \{"stringified": "([^"]*)","mInput": "([^"]*)",/i', $string, $match2) && @$match2[1]) {
				$a = $match[1];
				if (@$match[2] && $match[2] != $a)
					$q = $a. '('. $match[2]. ')';

				return preg_replace('/\s{2,}|\\\\n/', ' ', str_replace("\\/", "/", WebAccess::stripHTML($match2[1]))). ' = '. preg_replace('/\s{2,}|\\\\n/', ' ', WebAccess::stripHTML($a));

		}

		return "";
	}
}

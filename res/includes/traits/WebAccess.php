<?php
/**
 * PHPBot - WebAccess.php
 * User: Benjamin
 * Date: 08/06/14
 */

class WebAccessException extends Exception {}

trait WebAccess {

	use IRCFormatting;

	/**
	 * Get the body of a web resource
	 *
	 * @param $url
	 * @return string|bool The body or false on failure
	 */
	public static function resourceBody($url) {
		$curl = self::cURLResource($url);

		$result = curl_exec($curl);
		curl_close($curl);

		return $result;
	}

	/**
	 * Get the HTTP headers of a web resource
	 *
	 * @param $url
	 * @return string|bool The headers or false on failure
	 */
	public static function resourceHeader($url) {
		$curl = self::cURLResource($url);

		//	NOBODY omits body for a more efficient transaction
		curl_setopt($curl, CURLOPT_HEADER, 1);
		curl_setopt($curl, CURLOPT_NOBODY, 1);

		$result = curl_exec($curl);
		curl_close($curl);

		return $result;
	}

	/**
	 * Get both the headers and body of a resource
	 *
	 * @param $url
	 * @return string|bool The full resource text or false on failure
	 */
	public static function resourceFull($url) {
		$curl = self::cURLResource($url);

		curl_setopt($curl, CURLOPT_HEADER, 1);

		$result = curl_exec($curl);
		curl_close($curl);

		return $result;
	}

	/**
	 * Common function used by web resource functions to initialize cURL
	 *
	 * @param $url
	 * @return resource The cURL resource
	 * @throws WebAccessException If cURL extension is not loaded
	 */
	protected static function cURLResource($url) {
		if (!extension_loaded("cURL"))
			throw new WebAccessException(get_class(). "::resourceBody: Extension cURL must be loaded.");

		$curl = curl_init($url);
		//	These are universal cURL options applied to all resources obtained through this class
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($curl, CURLOPT_MAXREDIRS, 5);
		curl_setopt($curl, CURLOPT_TIMEOUT, 10);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

		return $curl;
	}

	/**
	 * Takes HTML text and puts it through a series of parsings for normal display.
	 * Entities including unicode entities, bold, italic, underline, and extra spacing are all normalized, and stray html tags are removed
	 *
	 * @param $html
	 * @return string $html without html
	 */
	public static function stripHTML($html) {
		//	Convert unicode entities
		$html = preg_replace_callback("/\\\\u([0-9a-f]{4})|&#u([0-9a-f]{4});/i", function ($match) { return chr(hexdec(($match[2]?:$match[1]))); }, $html);
		$html = preg_replace_callback("/\\\\x([0-9a-f]{2})|&#x([0-9a-f]{2});/i", function ($match) { return chr(hexdec(($match[2]?:$match[1]))); }, $html);
		//	Convert standard entities
		$html = preg_replace_callback("/&#([0-9]{2,3});/i", function ($match) { return chr($match[1]); }, $html);
		$html = html_entity_decode($html, ENT_QUOTES, "UTF-8");

		//	Convert basic text formatting (bold, italic, underline)
		$html = preg_replace_callback("/<b>(.*?)<\/b>/i", function ($match) { return self::bold($match[1]); }, $html);
		$html = preg_replace_callback("/<i>(.*?)<\/i>/i", function ($match) { return self::italic($match[1]); }, $html);
		$html = preg_replace_callback("/<u>(.*?)<\/u>/i", function ($match) { return self::underline($match[1]); }, $html);

		//	Convert superscript (exponents?)
		//$html = preg_replace("/<sup>([^<]+)<\/sup>/i", " ^ $1", $html);

		//	Convert line breaks to space
		$html = mb_eregi_replace("<br( ?\/)?>|(\x0D)?\x0A", " ", $html);

		//	Remove remaining html
		$html = preg_replace("/<[^>]*>/s", "", $html);

		//	Condense extra space
		$html = mb_ereg_replace("\s+", " ", $html);
		$html = mb_ereg_replace("^\s+", "", $html);

		return $html;
	}
}
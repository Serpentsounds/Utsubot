<?php
/**
 * PHPBot - WebAccess.php
 * User: Benjamin
 * Date: 08/06/14
 */

declare(strict_types = 1);

class WebAccessException extends Exception {}

trait WebAccess {

	use IRCFormatting;

	/**
	 * Get the body of a web resource
	 *
	 * @param string $url
	 * @return string
     * @throws WebAccessException If cURL extension is not loaded, cURL resource is invalid, or cURL return fails
	 */
	public static function resourceBody(string $url): string {
		$curl = self::cURLResource($url);

		return self::cURLExec($curl);
	}

	/**
	 * Get the HTTP headers of a web resource
	 *
	 * @param string $url
	 * @return string
     * @throws WebAccessException If cURL extension is not loaded, cURL resource is invalid, or cURL return fails
	 */
	public static function resourceHeader(string $url): string {
		$cURL = self::cURLResource($url);

		//	NOBODY omits body for a more efficient transaction
		curl_setopt($cURL, CURLOPT_HEADER, 1);
		curl_setopt($cURL, CURLOPT_NOBODY, 1);

		return self::cURLExec($cURL);
	}

	/**
	 * Get both the headers and body of a resource
	 *
	 * @param string $url
	 * @return string
     * @throws WebAccessException If cURL extension is not loaded, cURL resource is invalid, or cURL return fails
	 */
	public static function resourceFull(string $url): string {
		$cURL = self::cURLResource($url);

		curl_setopt($cURL, CURLOPT_HEADER, 1);

		return self::cURLExec($cURL);
	}

    /**
     * Internal function to perform and validate cURL operations
     *
     * @param resource $cURL
     * @return string cURL transfer
     * @throws WebAccessException Invalid cURL resource or failed return
     */
    protected static function cURLExec($cURL): string {
        if (get_resource_type($cURL) != "curl")
            throw new WebAccessException("Invalid cURL resource passed to cURLExec.");

        $result = curl_exec($cURL);
        curl_close($cURL);

        if ($result === false)
            throw new WebAccessException("Invalid cURL return.");

        return $result;
    }

	/**
	 * Internal function used by web resource functions to initialize cURL
	 *
	 * @param $url
	 * @return resource The cURL resource
	 * @throws WebAccessException If cURL extension is not loaded
	 */
	protected static function cURLResource(string $url) {
		if (!extension_loaded("cURL"))
			throw new WebAccessException(get_class(). ": Extension cURL must be loaded.");

		$cURL = curl_init($url);
		//	These are universal cURL options applied to all resources obtained through this class
		curl_setopt($cURL, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($cURL, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($cURL, CURLOPT_MAXREDIRS, 5);
		curl_setopt($cURL, CURLOPT_TIMEOUT, 10);
		curl_setopt($cURL, CURLOPT_SSL_VERIFYPEER, false);

		return $cURL;
	}

	/**
	 * Takes HTML text and puts it through a series of parsings for normal display.
	 * Entities including unicode entities, bold, italic, underline, and extra spacing are all normalized, and stray html tags are removed
	 *
	 * @param string $html
	 * @return string $html without html
	 */
	public static function stripHTML(string $html): string {
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

        //	Convert unicode entities
        $html = preg_replace_callback("/\\\\u([0-9a-f]{4})|&#u([0-9a-f]{4});/i", function ($match) { return chr(hexdec(($match[2]?:$match[1]))); }, $html);
        $html = preg_replace_callback("/\\\\x([0-9a-f]{2})|&#x([0-9a-f]{2});/i", function ($match) { return chr(hexdec(($match[2]?:$match[1]))); }, $html);
        //	Convert standard entities
        $html = preg_replace_callback("/&#([0-9]{2,3});/i", function ($match) { return chr($match[1]); }, $html);
        $html = html_entity_decode($html, ENT_QUOTES, "UTF-8");

		//	Condense extra space
		$html = mb_ereg_replace("\s+", " ", $html);
		$html = mb_ereg_replace("^\s+|\s+$", "", $html);

		return $html;
	}
}
<?php
/**
 * Utsubot - URLParserRegex.php
 * Date: 01/05/2016
 */

declare(strict_types = 1);

namespace Utsubot\Web;


use Exception;


/**
 * Class URLParserRegexException
 *
 * @package Utsubot\Web
 */
class URLParserRegexException extends Exception {

}


/**
 * Class URLParserRegex
 *
 * @package Utsubot\Web
 */
class URLParserRegex {

    private $domain;
    private $regex;
    private $method;
    private $permission;


    /**
     * URLParserRegex constructor.
     *
     * @param string   $domain
     * @param string   $regex
     * @param callable $method
     * @param string   $permission
     */
    public function __construct(string $domain, string $regex, callable $method, string $permission) {
        $this->domain     = strtolower($domain);
        $this->regex      = $regex;
        $this->method     = $method;
        $this->permission = $permission;
    }


    /**
     * Grab regex capture groups and plug them into the saved callback to fetch web data
     *
     * @param string $url
     * @param string $channel
     * @return string
     * @throws URLParserRegexException
     */
    public function call(string $url, string $channel): string {
        $regexMatch = $this->match($url);

        return call_user_func($this->method, $regexMatch, $channel);
    }


    /**
     * Match a url against the internal regex and return capture groups if it matches
     *
     * @param string $url
     * @return array
     * @throws URLParserRegexException
     */
    private function match(string $url): array {
        if (!preg_match("/https?:\/\/([^\/]+)(?:\/(.*))?/i", $url, $urlParts))
            throw new URLParserRegexException("Malformed url '$url'.");

        //  Grab http://domain/page
        array_shift($urlParts);
        list($domain, $page) = $urlParts;

        //  Remove all subdomains
        $mainDomain = $domain;
        if (substr_count($mainDomain, ".") > 1)
            $mainDomain = substr($mainDomain, 1 + strrpos($mainDomain, ".", strrpos($mainDomain, ".") - strlen($mainDomain) - 1));

        //  Verify domain is intended for this object
        if (strtolower($mainDomain) != $this->domain)
            throw new URLParserRegexException("Domain '$mainDomain' doesn't match '{$this->domain}'.");

        //  Verify page information can be captured in this object's regex
        if (!preg_match($this->regex, $page, $match))
            throw new URLParserRegexException("No regex match on page '$page'.");

        return $match;
    }


    /**
     * @return string
     */
    public function getDomain(): string {
        return $this->domain;
    }


    /**
     * @return string
     */
    public function getRegex(): string {
        return $this->regex;
    }


    /**
     * @return callable
     */
    public function getMethod(): callable {
        return $this->method;
    }


    /**
     * @return string
     */
    public function getPermission(): string {
        return $this->permission;
    }
}
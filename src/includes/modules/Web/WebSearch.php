<?php
/**
 * PHPBot - WebSearch.php
 * User: Benjamin
 * Date: 08/06/14
 */

namespace Utsubot\Web;


class WebSearchException extends \Exception {}

interface WebSearch {

	public static function search(string $search, array $options = array()): string;

}
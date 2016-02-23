<?php
/**
 * PHPBot - WebSearch.php
 * User: Benjamin
 * Date: 08/06/14
 */

class WebSearchException extends Exception {}

interface WebSearch {

	public static function search($search, $options = array());

}
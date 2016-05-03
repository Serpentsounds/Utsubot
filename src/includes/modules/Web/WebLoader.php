<?php
/**
 * Utsubot - Web.php
 * Date: 02/05/2016
 */

declare(strict_types = 1);

namespace Utsubot\Web;
use Utsubot\{
    IRCBot,
    Module
};


/**
 * Class WebLoader
 *
 * @package Utsubot\Web
 */
class WebLoader extends Module {

    const Modules = array(
        "APIKeys",
        "Google",
        "URLParser",
        "Weather",
        "Dictionary",
        "DNS",
        "UrbanDictionary",
        "YouTube"
    );

    public function __construct(IRCBot $IRCBot) {
        parent::__construct($IRCBot);

        foreach (self::Modules as $module) {
            $this->IRCBot->loadModule(__NAMESPACE__. "\\$module");
        }
    }
}
<?php
/**
 * Utsubot - RelayMode.php
 * Date: 22/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Relay;


use Utsubot\FlagsEnum;


/**
 * Class RelayMode
 *
 * @package Utsubot\Relay
 */
class RelayMode extends FlagsEnum {

    const PRIVMSG    = 1 << 0;
    const NOTICE     = 1 << 1;
    const CTCP       = 1 << 2;
    const CTCP_REPLY = 1 << 3;
    const JOIN       = 1 << 4;
    const PART       = 1 << 5;
    const QUIT       = 1 << 6;
    const MODE       = 1 << 7;
    const TOPIC      = 1 << 8;
    const NICK       = 1 << 9;
    const KICK       = 1 << 10;

    const MESSAGE = self::PRIVMSG
                    | self::NOTICE
                    | self::CTCP
                    | self::CTCP_REPLY;

    const ALL = self::PRIVMSG
                | self::NOTICE
                | self::CTCP
                | self::CTCP_REPLY
                | self::JOIN
                | self::PART
                | self::QUIT
                | self::MODE
                | self::TOPIC
                | self::NICK
                | self::KICK;

}
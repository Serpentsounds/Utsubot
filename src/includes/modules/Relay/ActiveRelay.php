<?php
/**
 * Utsubot - ActiveRelay.php
 * Date: 24/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Relay;
use Utsubot\{
    Manageable,
    User,
    Channel
};


/**
 * Class ActiveRelayException
 *
 * @package Utsubot\Relay
 */
class ActiveRelayException extends RelayException {}

/**
 * Class ActiveRelay
 *
 * @package Utsubot\Relay
 */
class ActiveRelay {

    private $mode;
    private $from;
    private $to;

    /**
     * ActiveRelay constructor.
     *
     * @param Manageable $from
     * @param Manageable $to
     * @param RelayMode  $mode
     * @throws ActiveRelayException If one of the Manageable items is not a User or Channel
     */
    public function __construct(Manageable $from, Manageable $to, RelayMode $mode) {
        if (!($from instanceof User) && !($from instanceof Channel))
            throw new ActiveRelayException("ActiveRelay source must be a User or Channel.");
        $this->from = $from;

        if (!($to instanceof User) && !($to instanceof Channel))
            throw new ActiveRelayException("ActiveRelay destination must be a User or Channel.");
        $this->to = $to;

        $this->mode = $mode;
    }

    /**
     * @return RelayMode
     */
    public function getMode(): RelayMode {
        return $this->mode;
    }

    /**
     * @return Manageable|Channel|User
     */
    public function getFrom(): Manageable {
        return $this->from;
    }

    /**
     * @return Manageable|Channel|User
     */
    public function getTo(): Manageable {
        return $this->to;
    }
}
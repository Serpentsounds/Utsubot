<?php
/**
 * Utsubot - Trigger.php
 * Date: 02/05/2016
 */

declare(strict_types = 1);

namespace Utsubot;


/**
 * Class TriggerException
 *
 * @package Utsubot
 */
class TriggerException extends \Exception {}

/**
 * Class Trigger
 *
 * @package Utsubot
 */
class Trigger {

    private $trigger;
    private $method;

    /**
     * Trigger constructor.
     *
     * @param string   $trigger
     * @param callable $method
     * @throws TriggerException
     */
    public function __construct(string $trigger, callable $method) {
        if (!strlen($trigger))
            throw new TriggerException("Trigger must not be empty.");
        $this->trigger = strtolower($trigger);

        $this->method = $method;
    }

    /**
     * Attempt to trigger this command if a given IRCMessage matches
     *
     * @param IRCMessage $msg
     */
    public function trigger(IRCMessage $msg) {
        if ($msg->getCommand() === $this->trigger) {
            call_user_func($this->method, $msg);
            $msg->respond($this);
        }        
    }

    /**
     * @return string
     */
    public function getTrigger(): string {
        return $this->trigger;
    }
    
}
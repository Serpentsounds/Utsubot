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
    private $aliases = array();
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
        if ($this->willTrigger($msg->getCommand())) {
            call_user_func($this->method, $msg);
            $msg->respond($this);
        }        
    }

    /**
     * Check if a trigger will cause this command to activate
     * 
     * @param string $trigger
     * @return bool
     */
    public function willTrigger(string $trigger) {
        $trigger = strtolower($trigger);
        $return = false;

        //  Match command exactly
        if ($trigger === $this->trigger)
            $return = true;

        //  Match any aliases
        foreach ($this->aliases as $alias)
            if ($trigger === $alias)
                $return = true;

        return $return;
    }

    /**
     * Add a new alias that this command will also trigger on
     * 
     * @param string $alias
     */
    public function addAlias(string $alias) {
        if (!in_array($alias, $this->aliases, true))
            $this->aliases[] = strtolower($alias);
    }

    /**
     * @return string
     */
    public function getTrigger(): string {
        return $this->trigger;
    }

    /**
     * @return array
     */
    public function getAliases(): array {
        return $this->aliases;
    }
    
}
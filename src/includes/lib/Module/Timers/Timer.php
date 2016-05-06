<?php
/**
 * Utsubot - Timer.php
 * Date: 05/05/2016
 */

declare(strict_types = 1);

namespace Utsubot;


/**
 * Class TimerException
 *
 * @package Utsubot
 */
class TimerException extends \Exception {}

/**
 * Class Timer
 *
 * @package Utsubot
 */
class Timer {

    const TooEarly = 0;
    const TimerActivated = 1;
    
    protected $time;
    protected $function;
    protected $parameters;
    protected $activated = false;

    /**
     * Timer constructor.
     *
     * @param float    $time
     * @param callable $function
     * @param array    $parameters
     * @throws TimerException Invalid time parameter
     */
    public function __construct(float $time, callable $function, array $parameters) {
        if ($time < 0)
            throw new TimerException("Timer delay can not be negative.");

        $this->time = microtime(true) + $time;
        $this->function = $function;
        $this->parameters = $parameters;
    }

    /**
     * Attempt to activate this timer object
     *
     * @return mixed Result of internal function
     * @throws TimerException
     */
    public function activate() {
        if ($this->activated == true)
            throw new TimerException("This Timer has already activated.", self::TimerActivated);
        
        if (microtime(true) < $this->time)
            throw new TimerException("It is too early for this Timer to activate.", self::TooEarly);

        $return = call_user_func_array($this->function, $this->parameters);
        $this->activated = true;
        return $return;
    }
    
}
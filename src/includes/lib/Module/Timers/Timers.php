<?php
/**
 * Utsubot - Timers.php
 * Date: 05/05/2016
 */

declare(strict_types = 1);

namespace Utsubot;


/**
 * Class Timers
 *
 * @package Utsubot
 */
trait Timers {

    /** @var Timer[] */
    private $timers = array();

    /**
     * Add a timer to the queue
     * 
     * @param Timer $timer
     */
    protected function addTimer(Timer $timer) {
        $this->timers[] = $timer;
    }

    /**
     * @param float $time
     */
    public function time(float $time){
        foreach ($this->timers as $key => $timer) {
            try {
                $timer->activate();
                unset($this->timers[$key]);
            }
            catch(TimerException $e) {
                if ($e->getCode() == Timer::TimerActivated)
                    unset($this->timers[$key]);
            }
        }
    }
    
}
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
    private $timers = [ ];


    /**
     * Add a timer to the queue
     *
     * @param Timer $timer
     */
    protected function addTimer(Timer $timer) {
        $this->timers[] = $timer;
    }


    /**
     * Overwrite Module time tick function to call timer functions
     *
     * @param float $time Passed by Utsubot routine but not needed here
     */
    public function time(float $time) {

        foreach ($this->timers as $key => $timer) {
            //  Attempt to activate timer and remove it from queue
            try {
                $timer->activate();
                unset($this->timers[ $key ]);
            }
                //  Error activating timer
            catch (TimerException $e) {
                //  Timer was already activated by another routine, remove it from queue
                if ($e->getCode() === Timer::TimerActivated)
                    unset($this->timers[ $key ]);
                //  Otherwise, the error is because it is too early for the timer to activate, so no action is taken
            }
        }
    }

}
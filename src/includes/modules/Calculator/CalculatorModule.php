<?php
/**
 * Calculator Module
 *
 * Provides a !calc command that will safely evaluate user input as a mathematical expression
 */

declare(strict_types = 1);

namespace Utsubot\Calculator;
use Utsubot\{
    Module, IRCBot, IRCMessage
};


/**
 * Class CalculatorModule
 *
 * @package Utsubot\Calculator
 */
class CalculatorModule extends Module {

    /**
     * CalculatorModule constructor.
     *
     * @param IRCBot $irc
     */
    public function __construct(IRCBot $irc) {
        parent::__construct($irc);

        $this->triggers = array(
            'calculate'		=> "calculate",
            'calc'			=> "calculate",
            'c'				=> "calculate"
        );
    }

    /**
     * Pass user input to a Calculator object for evaluating
     *
     * @param IRCMessage $msg
     */
    public function calculate(IRCMessage $msg) {
        $this->respond($msg, (string)(new Calculator($msg->getCommandParameterString()))->calculate());
    }
}
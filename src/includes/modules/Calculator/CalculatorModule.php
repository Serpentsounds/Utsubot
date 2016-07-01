<?php
/**
 * Calculator Module
 *
 * Provides a !calc command that will safely evaluate user input as a mathematical expression
 */

declare(strict_types = 1);

namespace Utsubot\Calculator;
use Utsubot\{
    Module,
    IRCBot,
    IRCMessage,
    Trigger
};
use Utsubot\Help\{
    HelpEntry,
    IHelp,
    THelp
};

/**
 * Class CalculatorModule
 *
 * @package Utsubot\Calculator
 */
class CalculatorModule extends Module implements IHelp {

    use THelp;
    
    /**
     * CalculatorModule constructor.
     *
     * @param IRCBot $irc
     */
    public function __construct(IRCBot $irc) {
        parent::__construct($irc);

        $calculate = new Trigger("calculate", [$this, "calculate"]);
        $calculate->addAlias("calc");
        $calculate->addAlias("c");
        $this->addTrigger($calculate);
        
        $help = new HelpEntry("Calculator", $calculate);
        $help->addParameterTextPair("EXPRESSION", "Attempts to calculate the given mathematical EXPRESSION.");
        $this->addHelp($help);
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
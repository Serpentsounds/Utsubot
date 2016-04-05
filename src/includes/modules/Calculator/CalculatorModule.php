<?php
/**
 * Utsubot - CalculatorModule.php
 * Date: 04/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Calculator;
use Utsubot\{Module, IRCBot, IRCMessage};


class CalculatorModule extends Module {
    public function __construct(IRCBot $irc) {
        parent::__construct($irc);

        $this->triggers = array(
            'calculate'		=> "calculate",
            'calc'			=> "calculate",
            'c'				=> "calculate"
        );
    }

    public function calculate(IRCMessage $msg) {
        $this->respond($msg, (new Calculator($msg->getCommandParameterString()))->calculate());
    }
}
<?php
/**
 * Utsubot - Admin.php
 * Date: 05/04/2016
 */

declare(strict_types = 1);

namespace Utsubot;
use Utsubot\Accounts\AccountsException;
use Utsubot\Permission\ModuleWithPermission;


class Admin extends ModuleWithPermission {
    public function __construct(IRCBot $irc) {
        parent::__construct($irc);

        $this->triggers = array(
            'eval'			=> "_eval",
            'return'		=> "_return",
            'restart'		=> "restart",
        );
    }

    /**
     * Evaluate text as PHP. Much danger
     *
     * @param IRCMessage $msg
     * @throws AccountsException If User does not have permission
     */
    public function _eval(IRCMessage $msg) {
        $this->requireLevel($msg, 100);
        $this->respond($msg, eval($msg->getCommandParameterString().";"));
    }

    /**
     * Evaluate text as PHP and return the result. Still much danger
     *
     * @param IRCMessage $msg
     * @throws AccountsException If User does not have permission
     */
    public function _return(IRCMessage $msg) {
        $this->requireLevel($msg, 100);
        $this->respond($msg, eval("return ". $msg->getCommandParameterString(). ";"));
    }


    /**
     * Restart the IRC bot with an optional quit message
     *
     * @param IRCMessage $msg
     * @throws AccountsException If User does not have permission
     */
    public function restart(IRCMessage $msg) {
        $this->requireLevel($msg, 100);
        $this->IRCBot->restart($msg->getCommandParameterString());
    }
}
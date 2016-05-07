<?php
/**
 * Utsubot - Admin.php
 * Date: 05/04/2016
 */

declare(strict_types = 1);

namespace Utsubot;
use Utsubot\Accounts\ModuleWithAccountsException;
use Utsubot\Permission\ModuleWithPermission;


/**
 * Class Admin
 *
 * @package Utsubot
 */
class Admin extends ModuleWithPermission {

    /**
     * Admin constructor.
     *
     * @param IRCBot $irc
     */
    public function __construct(IRCBot $irc) {
        parent::__construct($irc);

        $this->addTrigger(new Trigger("eval",       array($this, "_eval"    )));
        $this->addTrigger(new Trigger("return",     array($this, "_return"  )));
        $this->addTrigger(new Trigger("restart",    array($this, "restart"  )));        
    }

    /**
     * Evaluate text as PHP. Much danger
     *
     * @param IRCMessage $msg
     * @throws ModuleWithAccountsException If User does not have permission
     */
    public function _eval(IRCMessage $msg) {
        $this->requireLevel($msg, 100);
        $this->respond($msg, (string)eval("{$msg->getCommandParameterString()};"));
    }

    /**
     * Evaluate text as PHP and return the result. Still much danger
     *
     * @param IRCMessage $msg
     * @throws ModuleWithAccountsException If User does not have permission
     */
    public function _return(IRCMessage $msg) {
        $this->requireLevel($msg, 100);
        $this->respond($msg, (string)eval("return {$msg->getCommandParameterString()};"));
    }


    /**
     * Restart the IRC bot with an optional quit message
     *
     * @param IRCMessage $msg
     * @throws ModuleWithAccountsException If User does not have permission
     */
    public function restart(IRCMessage $msg) {
        $this->requireLevel($msg, 100);
        $this->IRCBot->restart($msg->getCommandParameterString());
    }
}
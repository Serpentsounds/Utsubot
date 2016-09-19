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

        $this->addTrigger(new Trigger("eval", [ $this, "_eval" ]));
        $this->addTrigger(new Trigger("return", [ $this, "_return" ]));

        $this->addTrigger(new Trigger("restart", [ $this, "restart" ]));
        $this->addTrigger(new Trigger("quit", [ $this, "_quit" ]));
        $this->addTrigger(new Trigger("part", [ $this, "_part" ]));

        $this->addTrigger(new Trigger("say", [ $this, "say" ]));
        $this->addTrigger(new Trigger("act", [ $this, "say" ]));
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


    /**
     * Quit IRC with an optional quit message, and terminate the program
     *
     * @param IRCMessage $msg
     * @throws ModuleWithAccountsException
     */
    public function _quit(IRCMessage $msg) {
        $this->requireLevel($msg, 100);
        $this->IRCBot->quit($msg->getCommandParameterString());
    }


    /**
     * Request for the bot to join a channel
     *
     * @param IRCMessage $msg
     * @throws ModuleException
     * @throws ModuleWithAccountsException
     */
    public function _join(IRCMessage $msg) {
        $this->requireLevel($msg, 75);
        $this->requireParameters($msg, 1);

        $this->IRCBot->join($msg->getCommandParameterString());
    }


    /**
     * Request for the bot to leave a channel
     *
     * @param IRCMessage $msg
     * @throws ModuleException
     * @throws ModuleWithAccountsException
     */
    public function _part(IRCMessage $msg) {
        $this->requireLevel($msg, 75);
        $this->requireParameters($msg, 1);

        $this->IRCBot->part($msg->getCommandParameterString());
    }


    /**
     * Send a message on IRC to the given target
     *
     * @param IRCMessage $msg
     * @throws ModuleException
     * @throws ModuleWithAccountsException
     */
    public function say(IRCMessage $msg) {
        $this->requireLevel($msg, 75);
        $this->requireParameters($msg, 2);

        $parameters = $msg->getCommandParameters();
        $target     = array_shift($parameters);
        $message    = implode(" ", $parameters);

        switch ($msg->getCommand()) {
            case "say":
                $this->IRCBot->message($target, $message);
                break;
            case "act":
                $this->IRCBot->action($target, $message);
                break;
        }

    }

}
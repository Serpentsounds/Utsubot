<?php
/**
 * MEGASSBOT - Module.php
 * User: Benjamin
 * Date: 10/11/14
 */

namespace Utsubot;

use function Utsubot\Util\getClassOnly;


/**
 * Class ModuleException
 *
 * @package Utsubot
 */
class ModuleException extends \Exception {

}

/**
 * Class Module
 *
 * @package Utsubot
 */
abstract class Module {

    /**
     * @var IRCBot $IRCbot
     */
    protected $IRCBot;

    /** @var Trigger[] $triggers */
    private $triggers = [ ];


    /**
     * Module constructor.
     *
     * @param IRCBot $IRCBot
     */
    public function __construct(IRCBot $IRCBot) {
        $this->IRCBot = $IRCBot;
        $this->status("Loading Module ". getClassOnly($this). "...");
    }


    /**
     * Log a message from this module to the console
     *
     * @param string $msg
     */
    protected function status(string $msg) {
        $this->IRCBot->console(getClassOnly($this).": $msg");
    }


    /**
     * Shorthand function to reply to channel/user in commands
     *
     * @param IRCMessage $msg
     * @param            $text
     */
    protected function respond(IRCMessage $msg, string $text) {
        $this->IRCBot->message($msg->getResponseTarget(), $text);
    }


    /**
     * Shorthand function to require the existence of classes before executing a command
     *
     * @param string $class
     * @throws ModuleException
     */
    protected function _require(string $class) {
        if (!class_exists($class))
            throw new ModuleException("This action requires $class to be loaded.");
    }


    /**
     * Shorthand function to require a certain number of parameters before proceeding with command processing
     *
     * @param IRCMessage $msg
     * @param int        $count
     * @param string     $errorMessage
     * @throws ModuleException
     */
    protected function requireParameters(IRCMessage $msg, int $count = 1, string $errorMessage = "") {
        if ($errorMessage === "")
            $errorMessage = "This command requires at least $count parameter(s).";

        if (count($msg->getCommandParameters()) < $count)
            throw new ModuleException($errorMessage);
    }


    /**
     * Return a reference to another Module object also loaded into the bot
     *
     * @param string $module Name of module class
     * @return Module
     * @throws ModuleException If the specified module is not loaded
     */
    protected function getModule(string $module): Module {
        if (class_exists($module) && ($instance = $this->IRCBot->getModule($module)) instanceof $module)
            return $instance;

        throw new ModuleException("Module $module is not loaded.");
    }


    /**
     * Parse the format of module exception to output the error to the user
     *
     * @param \Exception $e
     * @param IRCMessage $msg Needed to determine the nature of the command, and if a nickname needs to be addressed
     * @return string The formatted response
     */
    protected function parseException(\Exception $e, IRCMessage $msg): string {
        $response = $e->getMessage();

        //  Prepend Exception class name to error output
        if (preg_match("/(.*?)Exception$/", get_class($e), $match)) {
            $nameParts = explode("\\", $match[ 1 ]);
            $response  = underline(italic(end($nameParts))." error:")." $response";
        }

        //  If the error occured in a public channel, address the user directly for clarity
        if (!$msg->inQuery())
            $response = "{$msg->getNick()}: $response";

        return $response;
    }


    /**
     * Given an IRCMessage and command triggers, call the necessary methods and process errors
     *
     * @param IRCMessage $msg
     */
    protected function parseTriggers(IRCMessage $msg) {
        if (!$msg->isCommand())
            return;

        foreach ($this->triggers as $trigger) {
            //  Attempt to output command
            try {
                $trigger->trigger($msg);
            }
                //  Error in triggered command, output to user
            catch (\Exception $e) {
                $this->respond($msg, $this->parseException($e, $msg));
            }
        }
    }


    /**
     * Add a new command trigger
     *
     * @param Trigger $trigger
     * @throws ModuleException
     */
    protected function addTrigger(Trigger $trigger) {
        $this->triggers[] = $trigger;
    }


    /**
     * @return array
     */
    public function getTriggers(): array {
        return $this->triggers;
    }


    /**
     * Triggered when module is loaded, but before connecting
     */
    public function startup() {
    }


    /**
     *
     */
    public function shutdown() {
    }


    /**
     *
     */
    public function connect() {
    }


    /**
     *
     */
    public function disconnect() {
    }


    /**
     * Execute timed commands
     * Triggered every time the bot polls for data
     *
     * @param $time
     */
    public function time(float $time) {
    }


    /**
     * @param IRCMessage $msg
     */
    public function ping(IRCMessage $msg) {
    }


    /**
     * @param IRCMessage $msg
     */
    public function error(IRCMessage $msg) {
    }


    /**
     * Parse commands
     *
     * @param IRCMessage $msg
     */
    public function privmsg(IRCMessage $msg) {
        $this->parseTriggers($msg);
    }


    /**
     * @param IRCMessage $msg
     */
    public function notice(IRCMessage $msg) {
    }


    /**
     * @param IRCMessage $msg
     */
    public function ctcp(IRCMessage $msg) {
    }


    /**
     * @param IRCMessage $msg
     */
    public function ctcpResponse(IRCMessage $msg) {
    }


    /**
     * @param IRCMessage $msg
     */
    public function mode(IRCMessage $msg) {
    }


    /**
     * @param IRCMessage $msg
     */
    public function topic(IRCMessage $msg) {
    }


    /**
     * @param IRCMessage $msg
     */
    public function join(IRCMessage $msg) {
    }


    /**
     * @param IRCMessage $msg
     */
    public function part(IRCMessage $msg) {
    }


    /**
     * @param IRCMessage $msg
     */
    public function quit(IRCMessage $msg) {
    }


    /**
     * @param IRCMessage $msg
     */
    public function nick(IRCMessage $msg) {
    }


    /**
     * @param IRCMessage $msg
     */
    public function kick(IRCMessage $msg) {
    }


    /**
     * @param IRCMessage $msg
     */
    public function raw(IRCMessage $msg) {
    }


    /**
     * @param User $user
     */
    public function user(User $user) {
    }


    /**
     * @param Channel $channel
     */
    public function channel(Channel $channel) {
    }

}
<?php
/**
 * MEGASSBOT - IRCBot.php
 * User: Benjamin
 * Date: 28/06/14
 */

namespace Utsubot;

class IRCBotException extends \Exception {}

class IRCBot {

    const SOCKET_POLL_TIME = 100000;
    const RECONNECT_TIMEOUT = 7;
    const RECONNECT_DELAY = 10;

    const PING_FREQUENCY = 90;
    const ACTIVITY_TIMEOUT = 150;

    /** @var IRCNetwork $IRCNetwork */
    private $IRCNetwork;
    /** @var Users $users */
    private $users;
    /** @var Channels $channels */
    private $channels;

    private $socket = null;

    private $host = "";
    private $nickname = "";
    private $address = "";

    private $modules = array();

    /**
     * Load up the config for this IRCBot
     *
     * @param IRCNetwork $network
     * @throws IRCBotException If invalid config is supplied
     */
    public function __construct(IRCNetwork $network) {
        $this->IRCNetwork = $network;

        $this->users = new Users($this);
        $this->channels = new Channels($this);
    }

    /**
     * Reset the server connection and register with the server
     */
    public function connect() {
        //	Reset socket
        if ($this->socket)
            $this->socket = null;

        //	Suppress error on fsockopen, and handle it later
        $server = $this->IRCNetwork->getServerCycle()->get();
        $port = $this->IRCNetwork->getPort();
        $this->console("Attempting to connect to $server:$port...");
        $this->socket = @fsockopen($server, $port, $errno, $errstr, self::RECONNECT_TIMEOUT);

        //	Connection was unsuccessful
        if (!$this->socket) {
            $this->IRCNetwork->getServerCycle()->cycle();
            $this->reconnectCountdown();
            return false;
        }

        //	Full speed ahead
        else {
            $this->console("Connection successful.");
            $this->IRCNetwork->getNicknameCycle()->reset();
            $nickname = $this->IRCNetwork->getNicknameCycle()->get();
            $this->raw("USER Utsubot 0 * :$nickname");
            $this->raw("NICK :$nickname");
            return true;
        }
    }

    /**
     * Kill the server connection
     */
    public function disconnect() {
        $this->socket = null;
    }

    /**
     * Play an update to the console while delaying reconnection
     */
    public function reconnectCountdown() {
        fwrite(STDOUT, "Error connecting, retrying in");

        for ($seconds = self::RECONNECT_DELAY; $seconds > 0; $seconds--) {
            fwrite(STDOUT, " $seconds...");
            sleep(1);
        }
        fwrite(STDOUT, "\n\n");
    }

    /**
     * @return bool True or false if the socket is active or not
     */
    public function connected() {
        if (is_resource($this->socket) && get_resource_type($this->socket) == "stream" && !feof($this->socket))
            return true;

        return false;
    }

    /**
     * Poll the socket for changes for a time, then returns a line if there is data to read
     *
     * @return string The line or an empty string
     */
    public function read() {
        $arr = array($this->socket);
        $write = $except = null;
        if (($changed = stream_select($arr, $write, $except, 0, self::SOCKET_POLL_TIME)) > 0)
            return trim(fgets($this->socket, 512));

        return "";
    }

    /**
     * Send raw data to the server and log it to the console
     *
     * @param string $msg Message(s) to log. If line breaks are found, the message will be split and each message will be processed separately
     */
    public function raw($msg) {
        $lines = explode("\n", $msg);
        foreach ($lines as $line) {
            $send = fputs($this->socket, "$line\n");
            $this->console(" -> $line");

            if (!$send)
                $this->connect();
        }
    }

    /**
     * Joins the irc channel $channel
     *
     * @param $channel
     */
    public function join($channel) {
        $this->raw("JOIN :$channel");
    }

    public function nick($nickname) {
        $this->setNickname($nickname);
        $this->IRCNetwork->getNicknameCycle()->setPrimary($nickname);
        $this->raw("NICK $nickname");
    }

    /**
     * @param string $nickname
     */
    public function setNickname(string $nickname) {
        $this->nickname = $nickname;
    }

    /**
     * Save this bot's address on the irc server
     *
     * @param string $address
     */
    public function setAddress(string $address) {
        $this->address = $address;
    }

    /**
     * @param string $host
     */
    public function setHost(string $host) {
        $this->host = $host;
    }

    /**	 *
     * @return IRCNetwork The network name set for this bot
     */
    public function getIRCNetwork(): IRCNetwork {
        return $this->IRCNetwork;
    }

    /**
     * @return string The server address the bot connected to
     */
    public function getHost() {
        return $this->host;
    }

    /**
     * @return string The bot's main nickname
     */
    public function getNickname() {
        return $this->nickname;
    }

    /**
     * @return string This bot's address on the irc server
     */
    public function getAddress() {
        return $this->address;
    }

    /**
     * @return Users
     */
    public function getUsers() {
        return $this->users;
    }

    /**
     * @return Channels
     */
    public function getChannels() {
        return $this->channels;
    }

    /**
     * Gets one of this bot's modules for external use
     *
     * @param string $module The class name of the module to get
     * @return Module|bool The module class matching $module, or false on failure
     */
    public function getModule($module) {
        if (isset($this->modules[$module]) && $this->modules[$module] instanceof Module)
            return $this->modules[$module];

        return false;
    }

    /**
     * @return array
     */
    public function getModules(): array {
        return $this->modules;
    }

    /**
     * Instantiate a new instance of a module and save it
     *
     * @param string $class The class name of the module
     * @throws IRCBotException If the class doesn't exist or is not a subclass of Module
     */
    public function loadModule($class) {
        if (!is_subclass_of("$class", "Utsubot\\Module"))
            throw new IRCBotException("$class does not exist or does not extend Utsubot\\Module.");
        
        $module = new $class($this, $class);
        $this->modules[$class] = $module;
    }

    /**
     * This method is called on any IRC event to send the info to modules and give them the proper chance to respond
     *
     * @param string $function The name of the function relevant to the IRC event, e.g. "privmsg"
     * @param Object|float|null $msg If available, send the IRCMessage object that was created from this event, or other relevant information
     */
    public function sendToModules($function, $msg = null) {
        //	These modules will receive the information first, if any relevant pre-processing needs to be done
        $priority = array("Core");

        try {
            //	Send event to priority modules
            foreach ($priority as $module) {
                if (isset($this->modules[$module]))
                    $this->sendToModule($this->modules[$module], $function, $msg);
            }

            //	Send event to all other modules
            foreach ($this->modules as $name => $module) {
                //	Skip the priority modules we already called
                if (!in_array($name, $priority))
                    $this->sendToModule($module, $function, $msg);
            }
        }
        catch (IRCBotException $e) {
            $this->console("Processing force halted: {$e->getMessage()}");
        }

    }

    /**
     * A helper for sendToModules that handles an individual module
     *
     * @param Module $module
     * @param string $function The name of the function relevant to the IRC event, e.g. "privmsg"
     * @param Object|float|null $msg If available, send the IRCMessage object that was created from this event, or other relevant information
     * @throws IRCBotException Bubble up IRCBotExceptions to force halt processing
     */
    private function sendToModule($module, $function, $msg = null) {
        //	These events don't require an IRCMessage
        $noParameters = array("connect", "shutdown");

        //	Attempt to call the method while handling errors
        if (method_exists($module, $function)) {
            try {
                if (in_array($function, $noParameters))
                    $module->{$function}();
                elseif ($msg)
                    $module->{$function}($msg);
            }
            catch (IRCBotException $e) {
                throw $e;
            }
            catch (\Exception $e) {
                $this->console(sprintf("%s: %s", get_class($e), $e->getMessage()));
            }
        }

    }

    /**
     * Output a string to the console for display
     *
     * @param string $string
     */
    public function console($string) {
        fwrite(STDOUT, "$string\n\n");
    }

    /**
     * Restart the bot program
     *
     * @param string $message Optional quit message
     */
    public function restart($message = "") {
        $this->raw("QUIT :$message");
        sleep(1);
        pclose(popen("start php -f Utsubot.php {$this->IRCNetwork->getName()}", "r"));
        exit;
    }

    /**
     * Messages the target on IRC with text. Splits text up into multiple messages if total data length exceeds 512 bytes (maximum transferable).
     * Also splits line breaks into multiple messages, and will carry over incomplete control codes.
     *
     * @param string $target User or channel to send message to
     * @param string|array $text Lines or collection of lines to send
     * @param bool $action Pass true to send message as an IRC Action (/me)
     */
    public function message($target, $text, $action = false) {
        //	Split array into multiple messages
        if (is_array($text)) {
            foreach ($text as $newText)
                $this->message($target, $newText, $action);
            return;
        }

        $text = (string) $text;
        //	Empty line
        if (strlen(trim(stripControlCodes($text))) == 0)
            return;

        /*	Maximum size of irc command is 512 bytes
         * 	Subtract 1 for leading ":", nicknam length, 1 for "!", address length, 9 for " PRIVMSG ", target length, 2 for " :", 2 for "\r\n"	*/
        $maxlen = 512 - 1 - strlen($this->nickname) - 1 - strlen($this->address) - 9 - strlen($target) - 2 - 2;
        //	9 extra characters for \x01ACTION \x01
        if ($action)
            $maxlen -= 9;

        //	Split line breaks into multiple messages
        if (strpos($text, "\n") !== false) {
            $textArray = explode("\n", $text);
            $this->message($target, $textArray, $action);
            return;
        }

        $words = explode(" ", $text);
        $builtString = "";

        //	Loop through words
        for ($i = 0, $wordCount = count($words); $i < $wordCount; $i++) {
            //	Build string
            $builtString .= $words[$i]. " ";

            //	If the next word would break the 512 byte limit, or we're out of words, output the string and clear it
            if ((isset($words[$i+1]) && strlen($builtString. $words[$i+1]) > $maxlen) || !isset($words[$i+1])) {
                //	Cut off trailing space
                $sendString = substr($builtString, 0, -1);

                //	Send data for action or regular message, and log to console
                if ($action) {
                    fputs($this->socket, "PRIVMSG $target :\x01ACTION $sendString\x01\n");
                    $this->console(" *-> $target: * $sendString");
                }
                else {
                    fputs($this->socket, "PRIVMSG $target :$sendString\n");
                    $this->console(" -> $target: $sendString");
                }

                //	Start next line with control codes continued
                $builtString = self::getNextLinePrefix($sendString);
            }
        }

    }

    /**
     * Calls $this->message, but sends the command as a CTCP ACTION (/me)
     *
     * @param string $target User or channel to send message to
     * @param string|array $text Lines or collection of lines to send
     */
    public function action($target, $text) {
        $this->message($target, $text, true);
    }

    public function notice($target, $text) {
        $this->raw("NOTICE $target :$text");
    }

    public function ctcp($target, $text) {
        $this->message($target, "\x01$text\x01");
    }

    public function ctcpReply($target, $type, $response) {
        $this->notice($target, "\x01$type $response\x01");
    }

    /**
     * When forced to split an IRC message up across multiple lines, this function will determine which control codes need to be continued
     *
     * @param string $message The IRC message
     * @return string The set of control codes that represent the state of the message at the end of the string
     */
    private static function getNextLinePrefix($message) {
        //	Bold, reverse, italic, underline respectively
        $controlCodes = array(2 => false, 22 => false, 29 => false, 31 => false);
        //	Denotes colored text
        $colorCode = chr(3);
        //	Clears all formatting
        $clearCode = chr(15);

        //	Initialize vars
        $colorPrefix = false;
        $nextLinePrefix = $background = $foreground = "";

        //	Loop through every character
        for ($j = 0, $length = strlen($message); $j < $length; $j++) {
            $character = mb_substr($message, $j, 1);

            //	Clear all formatting
            if ($character == $clearCode) {
                $clear = true;
                $colorPrefix = false;
                $foreground = $background = "";
            }
            //	No clearing for this iteration
            else
                $clear = false;

            //	Loop through control codes to activate or deactivate
            foreach ($controlCodes as $code => $toggle) {
                if ($clear)
                    $controlCodes[$code] = false;

                elseif ($character == chr($code))
                    $controlCodes[$code] = !($toggle);
            }

            //	Begin to parse colors
            if ($character == $colorCode) {
                //	Default to the code signifying the end of color
                if ($colorPrefix)
                    $colorPrefix = false;

                $foreground = $background = "";
                //	Check the next character for a number to represent a color index for the foreground
                if ($j + 1 < $length && is_numeric($next = mb_substr($message, $j + 1, 1))) {
                    $foreground = $next;
                    //	Advance character pointer
                    $j++;

                    //	Same for next potential foreground digit
                    if ($j + 1 < $length && is_numeric($next = mb_substr($message, $j + 1, 1))) {
                        $foreground .= $next;
                        $j++;

                        //	Check for background, if present, separated from foreground by comma
                        if ($j + 2 < $length && mb_substr($message, $j + 1, 1) == "," && is_numeric($next = mb_substr($message, $j + 2, 1))) {
                            $background = $next;
                            $j += 2;

                            //	Next potential background digit
                            if ($j + 1 < $length && is_numeric($next = mb_substr($message, $j + 1, 1))) {
                                $background .= $next;
                                $j++;
                            }

                        }
                    }

                    //	Matched at least one digit, so enable colors
                    $colorPrefix = true;
                }
            }

        }

        //	Whichever codes ended up as true will continue to the next line
        foreach ($controlCodes as $code => $toggle) {
            if ($toggle)
                $nextLinePrefix .= chr($code);
        }

        //	Include color if necessary
        if ($colorPrefix && is_numeric($foreground)) {
            $nextLinePrefix .= $colorCode. sprintf("%02d", $foreground);

            //	Optionally include background if necessary
            if (is_numeric($background))
                $nextLinePrefix .= sprintf(",%02d", $background);
        }

        return $nextLinePrefix;
    }

    /**
     * Print a variable's contents to a file (for debug purposes)
     *
     * @param string $filename
     * @param mixed $data
     */
    public function saveToFile($filename, $data) {
        ob_start();
        print_r($data);

        if (file_put_contents($filename, str_replace("\n", "\r\n", ob_get_contents())))
            $this->console("Contents successfully written to $filename.");
        else
            $this->console("Error writing contents to $filename.");

        ob_end_clean();
    }
}
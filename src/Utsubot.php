<?php
/**
 * Utsubot - Utsubot.php
 * User: Benjamin
 * Date: 14/11/14
 */

//  Normalize directory
chdir(dirname($_SERVER['PHP_SELF']));

//  Override settings to make sure we're in UTF-8 mode
mb_internal_encoding('UTF-8');
mb_regex_encoding("UTF-8");
mb_detect_order("UTF-8,ISO-8859-1,ASCII");

date_default_timezone_set("UTC");

error_reporting(E_ALL ^ E_STRICT);

//  We need to be running indefinitely
set_time_limit(0);


/**
 * Recursive function for searching directories
 *
 * @param string $dir Relative directory
 * @return array
 */
function getDirs($dir) {
    $files = scandir($dir);
    $dirs = [ ];
    foreach ($files as $entry) {
        if ($entry == "." || $entry == "..")
            continue;

        elseif (is_dir(getcwd(). "\\$dir\\$entry")) {
            $dirs[] = "$dir\\$entry";
            $dirs = array_merge($dirs, getDirs("$dir\\$entry"));
        }
    }
    return $dirs;
}

$dirs = getDirs("includes");
array_unshift($dirs, "includes");

//  Register autoload function so class dependency errors don't occur in includes
spl_autoload_register(function($class) use ($dirs) {
    $nameParts = explode("\\", $class);
    $shortName = end($nameParts);
    if (preg_match("/^(.+?)Exception$/", $shortName, $match))
        $shortName = $match[1];

    foreach ($dirs as $dir) {
        if (file_exists("$dir\\$shortName.php")) {
            include_once("$dir\\$shortName.php");
            return;
        }
    }

    #echo "$shortName not found in any of: ". implode(", ", $dirs). "\n";

});

//  Include all PHP resources
foreach ($dirs as $dir) {
    $files = scandir($dir);
    foreach ($files as $entry) {
        if ($entry == "." || $entry == "..")
            continue;

        $fullPath = getcwd(). "\\$dir\\$entry";
        if (is_file($fullPath) && pathinfo($fullPath)['extension'] == "php")
            include_once("$dir\\$entry");
    }
}

//  Need config file
if (!file_exists("../conf/config.ini"))
    throw new Exception("Configuration file is missing!");
$configFile = parse_ini_file("../conf/config.ini", true);

//  Need entry in config file
if (!isset($argv[1]))
    throw new Exception("A preconfigured network must be passed via CLI to continue.");
if (!isset($configFile[$argv[1]]))
    throw new Exception("No config entry found for network '{$argv[1]}'.");

//  Start with global settings, then override as necessary
$config = array_merge($configFile['global'], $configFile[$argv[1]]);
$config['network'] = $argv[1];

$network = new Utsubot\IRCNetwork($config['network'], $config['host'], $config['port'], $config['nickname'], (array)@$config['channel'], (array)@$config['onConnect'], (array)@$config['commandPrefix']);
$ircBot = new Utsubot\IRCBot($network);

//  Default modules
$config['modules'] = array_unique(array_merge(
    [ "Core" ],
    $config['modules']
                                  ));
//  Load all the good stuff
if (isset($config['modules'])) {
    foreach ($config['modules'] as $key => $module) {
        $ircBot->loadModule("Utsubot\\$module");
    }
}
//  First basic module event
$ircBot->sendToModules("startup");

$connected = false;
$lastData = $lastReconnect = $lastPing = time();

while (true) {
    //  Maintain connection
    if (!$ircBot->connected())
        $connected = $ircBot->connect();

    //  Parse data
    if ($connected && ($data = $ircBot->read())) {
        $lastData = time();
        $msg = new Utsubot\IRCMessage($data);
        $msg->parseCommand($ircBot->getIRCNetwork()->getCommandPrefixes());
        $type = $msg->getType();

        //  See the world through bot eyes
        $ircBot->console($data);

        //  We first know for sure our connection and registration was accepted when we get this raw
        if ($type == "raw" && $msg->getRaw() == "001")
            $ircBot->sendToModules("connect");

        //  We got the boot, deal with it
        elseif ($type == "error" && strpos($msg->getParameterString(), "Closing Link:") === 0) {
            $ircBot->sendToModules("disconnect");
            $lastReconnect = time();
            $ircBot->connect();
        }

        //  Send the message to all our modules. Commands will happen there.
        $ircBot->sendToModules($type, $msg);
    }

    //  Reevaluate time, don't know how long module commands may have taken to execute
    $time = time();

    //  Manual ping after no activity for a while
    if ($time - $lastData >= Utsubot\IRCBot::Ping_Frequency && $time - $lastPing >= Utsubot\IRCBot::Ping_Frequency) {
        $ircBot->raw("PING " . $ircBot->getHost());
        $lastPing = $time;
    }

    //  Manual reconnect after no activity for a longer while and no response to ping
    if ($time - $lastData >= Utsubot\IRCBot::Activity_Timeout && $time - $lastReconnect >= Utsubot\IRCBot::Reconnect_Delay) {
        $ircBot->sendToModules("disconnect");
        $lastReconnect = $time;
        $ircBot->connect();
    }

    //  Send timestamp for timer events
    $ircBot->sendToModules("time", microtime(true));

    usleep(10000);
}
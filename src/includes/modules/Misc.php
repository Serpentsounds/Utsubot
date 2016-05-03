<?php
/**
 * Utsubot - Main.php
 * User: Benjamin
 * Date: 16/11/14
 */

namespace Utsubot;

use Utsubot\Permission\ModuleWithPermission;
use function Utsubot\colorText;


class MiscException extends ModuleException {
}

class Misc extends ModuleWithPermission {

    const NOW_PLAYING_INTERVAL = 60;
    const NOW_PLAYING_FILE = '\\\\GENSOU\drop\nowplaying.txt';
    protected $inCountdown = array();
    private $lastNowPlaying = 0;

    public function __construct(IRCBot $irc) {
        parent::__construct($irc);

        $this->addTrigger(new Trigger("hug",            array($this, "hug"          )));

        $this->addTrigger(new Trigger("nowplaying",     array($this, "nowPlaying"   )));
        $this->addTrigger(new Trigger("np",             array($this, "nowPlaying"   )));

        $this->addTrigger(new Trigger("countdown",      array($this, "countdown"    )));
    }

    /**
     * Give the user a hug.
     *
     * @param IRCMessage $msg
     */
    public function hug(IRCMessage $msg) {
        $this->IRCBot->action($msg->getResponseTarget(), "gives {$msg->getNick()} a hug!");
    }

    /**
     * Show currently playing song information
     *
     * @param IRCMessage $msg
     * @throws ModuleException
     */
    public function nowPlaying(IRCMessage $msg) {

        if (time() - $this->lastNowPlaying < self::NOW_PLAYING_INTERVAL) {
            try {
                $this->requireLevel($msg, 75);
            }
            catch (\Exception $e) {
                throw new ModuleException("It is too soon to use that command again.");
            }
        }

        if (!file_exists(self::NOW_PLAYING_FILE))
            throw new ModuleException("Unable to locate song information.");

        $song = explode("|", file(self::NOW_PLAYING_FILE)[0]);

        if ($song[0] == "not running" || $song[0] == "?" || !$song[0])
            throw new ModuleException("There is currently no music playing.");

        list($codec, $artist, $title, $album, $date, $length, $bitrate, $composer, $performer, $time, $genre, $albumArtist, $path) = $song;

        $white   = new Color(Color::White);
        $black   = new Color(Color::Black);
        $yellow  = new Color(Color::Yellow);
        $lime    = new Color(Color::Lime);
        $fuchsia = new Color(Color::Fuchsia);

        $albumString = "";
        if ($album != "?")
            $albumString = colorText(" - ", $white, $black, false).colorText($album, $lime, $black, false);

        $nowPlaying =
            colorText("Playing ", $white, $black, false).
            colorText($codec, $yellow, $black, false).
            colorText("@", $white, $black, false).
            colorText($bitrate, $yellow, $black, false).
            colorText(": ", $white, $black, false).
            colorText($artist, $lime, $black, false).
            colorText(" - ", $white, $black, false).
            colorText($title, $lime, $black, false).
            $albumString.
            colorText(" [$time/$length]", $fuchsia, $black, true);

        $this->respond($msg, $nowPlaying);
        $this->respond($msg, "Tune in at http://radio.soredemo.net/ to listen along!");
        $this->lastNowPlaying = time();
    }

    public function countdown(IRCMessage $msg) {
        $target = $msg->getResponseTarget();
        if (isset($this->inCountdown[$target]))
            throw new ModuleException("A countdown is already in progress.");

        $this->inCountdown[$target] = true;
        $time                       = microtime(true);

        $countdownFrom     = 3;
        $countdownDelay    = 5;
        $countdownInterval = 1;
        $readyMessage      = "Get ready!";
        $endingMessage     = "GO!";

        preg_match_all("/(\S+?):(?:\"([^\"]*)\"|(\S+))/", $msg->getCommandParameterString(), $match, PREG_SET_ORDER);
        foreach ($match as $entry) {
            list(, $option, $value) = $entry;
            if (isset($entry[3]))
                $value = $entry[3];

            switch ($option) {
                case "from":
                    if (preg_match("/\D/", $value) || $value == 0)
                        throw new ModuleException("'from' must be a positive integer.");
                    $countdownFrom = (int)$value;
                    break;

                case "delay":
                    if (!is_numeric($value) || $value < 0)
                        throw new ModuleException("'delay' must be a non-negative number.");
                    $countdownDelay = (float)$value;
                    break;

                case "interval":
                    if (preg_match("/\D/", $value) || $value == 0)
                        throw new ModuleException("'interval' must be a positive integer.");
                    $countdownInterval = (int)$value;
                    break;

                case "ready":
                    $readyMessage = $value;
                    break;

                case "end":
                    $endingMessage = $value;
                    break;
            }
        }

        $baseTime = $time + $countdownDelay;
        $counted  = 0;
        while ($countdownFrom > 0) {
            $this->timerQueue[] = array(
                'time'    => $baseTime + $counted++ * $countdownInterval,
                'command' => "\$this->IRCBot->message('$target', '$countdownFrom');"
            );

            $countdownFrom -= $countdownInterval;
        }
        /*	$baseTime + $counted * $countdownInterval = time last number was sent
         * 	$countdownFrom + $countdownInterval = remaining time	*/
        $finalTime = $baseTime + --$counted * $countdownInterval + $countdownFrom + $countdownInterval;

        $this->timerQueue[] = array(
            'time'    => $finalTime,
            'command' => "\$this->IRCBot->message('$target', '$endingMessage');"
        );

        $this->timerQueue[] = array(
            'time'    => $finalTime,
            'command' => "unset(\$this->inCountdown['$target']);"
        );

        $this->IRCBot->message($target, $readyMessage);

    }
}


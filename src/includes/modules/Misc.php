<?php
/**
 * Utsubot - Main.php
 * User: Benjamin
 * Date: 16/11/14
 */

namespace Utsubot;

use Utsubot\Permission\ModuleWithPermission;
use Utsubot\Help\{
    HelpEntry,
    IHelp,
    THelp
};
use function Utsubot\colorText;


/**
 * Class MiscException
 *
 * @package Utsubot
 */
class MiscException extends ModuleException {

}

/**
 * Class Misc
 *
 * @package Utsubot
 */
class Misc extends ModuleWithPermission implements IHelp {

    use THelp;
    use Timers;

    const NOW_PLAYING_INTERVAL = 60;
    const NOW_PLAYING_FILE     = '\\\\GENSOU\\drop\\nowplaying.txt';

    protected $inCountdown    = [ ];
    private   $lastNowPlaying = 0;


    /**
     * Misc constructor.
     *
     * @param IRCBot $irc
     */
    public function __construct(IRCBot $irc) {
        parent::__construct($irc);

        //  Command triggers
        $triggers = [ ];

        $triggers[ 'hug' ] = new Trigger("hug", [ $this, "hug" ]);

        $triggers[ 'nowplaying' ] = new Trigger("nowplaying", [ $this, "nowPlaying" ]);
        $triggers[ 'nowplaying' ]->addAlias("np");

        $triggers[ 'countdown' ] = new Trigger("countdown", [ $this, "countdown" ]);

        foreach ($triggers as $trigger)
            $this->addTrigger($trigger);

        //  Help entries
        $help = [ ];

        $help[ 'hug' ] = new HelpEntry("Misc", $triggers[ 'hug' ]);
        $help[ 'hug' ]->addParameterTextPair("", "Get a free hug!");

        $help[ 'nowplayiying' ] = new HelpEntry("Misc", $triggers[ 'nowplaying' ]);
        $help[ 'nowplayiying' ]->addParameterTextPair("", "See what song I'm currently listening to. You were just dying to know, right?");

        $help[ 'countdown' ] = new HelpEntry("Misc", $triggers[ 'countdown' ]);
        $help[ 'countdown' ]->addParameterTextPair("", "Play a countdown to the channel. By default, counts down from 3 to 1 after a 5 second delay.");
        $help[ 'countdown' ]->addParameterTextPair(
            "OPTIONS",
            "Manually alter countdown options. Multiple options can be specified, in the form field:value for numbers or field:\"value\" for strings."
        );
        $help[ 'countdown' ]->addNotes("Valid options are from, delay, interval (numbers) and ready, end (strings).");

        foreach ($help as $entry)
            $this->addHelp($entry);

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

        $db = new DatabaseInterface(MySQLDatabaseCredentials::createFromConfig("soredemo"));
        $song = $db->query("SELECT * FROM `radio`");

        if (!$song)
            throw new ModuleException("There is currently no music playing.");
        $song = $song[0];

        /*
        if (!file_exists(self::NOW_PLAYING_FILE))
            throw new ModuleException("Unable to locate song information.");

        $song = explode("|", file(self::NOW_PLAYING_FILE)[ 0 ]);

        if ($song[ 0 ] == "not running" || $song[ 0 ] == "?" || !$song[ 0 ])
            throw new ModuleException("There is currently no music playing.");

        list($codec, $artist, $title, $album, $date, $length, $bitrate, $composer, $performer, $time, $genre, $albumArtist, $path) = $song;*/

        $white   = new Color(Color::White);
        $black   = new Color(Color::Black);
        $yellow  = new Color(Color::Yellow);
        $lime    = new Color(Color::Lime);
        $fuchsia = new Color(Color::Fuchsia);

        $albumString = "";
        if ($song[ 'album' ] != "?")
            $albumString = colorText(" - ", $white, $black, false).colorText($song[ 'album' ], $lime, $black, false);

        $nowPlaying =
            colorText("Playing ", $white, $black, false).
            colorText($song[ 'codec' ], $yellow, $black, false).
            colorText("@", $white, $black, false).
            colorText($song[ 'bitrate' ], $yellow, $black, false).
            colorText(": ", $white, $black, false).
            colorText($song[ 'artist' ], $lime, $black, false).
            colorText(" - ", $white, $black, false).
            colorText($song[ 'title' ], $lime, $black, false).
            $albumString.
            colorText(" [{$song[ 'playback_time' ]}/{$song[ 'length' ]}]", $fuchsia, $black, true);

        $this->respond($msg, $nowPlaying);
        $this->respond($msg, "Tune in at http://radio.soredemo.net/ to listen along!");
        $this->lastNowPlaying = time();
        $db = null;
    }


    /**
     * @param IRCMessage $msg
     * @throws ModuleException
     */
    public function countdown(IRCMessage $msg) {
        $target = $msg->getResponseTarget();
        if (isset($this->inCountdown[ $target ]))
            throw new ModuleException("A countdown is already in progress.");

        $this->inCountdown[ $target ] = true;

        //  Default settings
        $countdownFrom     = 3;
        $countdownDelay    = 5;
        $countdownInterval = 1;
        $readyMessage      = "Get ready!";
        $endingMessage     = "GO!";

        preg_match_all("/(\S+?):(?:\"([^\"]*)\"|(\S+))/", $msg->getCommandParameterString(), $match, PREG_SET_ORDER);

        foreach ($match as $entry) {
            list(, $option, $value) = $entry;
            //  Prefer latter capture group
            if (isset($entry[ 3 ]))
                $value = $entry[ 3 ];

            switch ($option) {
                //  Number to start counting down from
                case "from":
                    if (preg_match("/\D/", $value) || $value == 0)
                        throw new ModuleException("'from' must be a positive integer.");
                    $countdownFrom = (int)$value;
                    break;

                //  How long to wait to start countdown
                case "delay":
                    if (!is_numeric($value) || $value < 0)
                        throw new ModuleException("'delay' must be a non-negative number.");
                    $countdownDelay = (float)$value;
                    break;

                //  Time between intermediate numbers in countdown
                case "interval":
                    if (preg_match("/\D/", $value) || $value == 0)
                        throw new ModuleException("'interval' must be a positive integer.");
                    $countdownInterval = (int)$value;
                    break;

                //  Ready message displayed to channel
                case "ready":
                    $readyMessage = $value;
                    break;

                //  Finish message displayed to channel
                case "end":
                    $endingMessage = $value;
                    break;
            }
        }

        //  Timers for intermediate numbers
        $counted = 0;
        while ($countdownFrom > 0) {

            $this->addTimer(
                new Timer(
                    $countdownDelay + $counted++ * $countdownInterval,
                    [ $this->IRCBot, "message" ],
                    [ $target, $countdownFrom ]
                )
            );

            $countdownFrom -= $countdownInterval;
        }

        /*	$countdownDelay + $counted * $countdownInterval = time last number was sent
         * 	$countdownFrom + $countdownInterval = remaining time	*/
        $finalTime = $countdownDelay + --$counted * $countdownInterval + $countdownFrom + $countdownInterval;

        //  Timer for finish message
        $this->addTimer(
            new Timer(
                $finalTime,
                [ $this->IRCBot, "message" ],
                [ $target, $endingMessage ]
            )
        );

        //  Timer to clear out the countdown entry when finished
        $this->addTimer(
            new Timer(
                $finalTime,
                function ($target) {
                    unset($this->inCountdown[ $target ]);
                },
                [ $target ]
            )
        );

        $this->IRCBot->message($target, $readyMessage);

    }

}


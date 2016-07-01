<?php

/**
 * Utsubot - TriviaCheat.php
 * User: Benjamin
 * Date: 20/12/2015
 */

namespace Utsubot;

class TriviaCheat extends Module {

    use Timers;

    private static $logfile       = 'trivia.log';
    private static $botnick       = "Rapidash-Trivia";
    private static $questionRegex = '/^\[\d{2}:\d{2}:\d{2}\] <[~&@%+]?BOTNICK> \d+\. (.+)/i';
    private static $answerRegex   = '/^\[\d{2}:\d{2}:\d{2}\] <[~&@%+]?BOTNICK> Winner: \S+ Answer: (.+?) Time:/i';
    private static $triggerRegex  = '/^\d+\. (.+)/i';

    private $questions = [ ];


    public function __construct(IRCBot $irc) {
        parent::__construct($irc);

        self::$questionRegex = str_replace("BOTNICK", self::$botnick, self::$questionRegex);
        self::$answerRegex   = str_replace("BOTNICK", self::$botnick, self::$answerRegex);

        $this->cacheLog();
    }


    public function privmsg(IRCMessage $msg) {

        if ($msg->getNick() == self::$botnick && preg_match(self::$triggerRegex, stripControlCodes($msg->getParameterString()), $match)) {
            foreach ($this->questions as $arr) {
                if (!strcmp(strtolower(trim($match[ 1 ])), strtolower(trim($arr[ 0 ])))) {

                    $this->addTimer(
                        new Timer(
                            time() + 1,
                            [ $this->IRCBot, "message" ],
                            [ $msg->getResponseTarget(), $arr[ 1 ] ]
                        )
                    );

                    break;
                }
            }
        }

    }


    public function cacheLog() {
        $this->questions = [ ];
        $lines           = file(self::$logfile);

        $question = "";
        foreach ($lines as $line) {
            if ($question && preg_match(self::$answerRegex, $line, $match)) {
                $this->questions[] = [ strtolower($question), $match[ 1 ] ];
                $question          = "";
            }

            elseif (!$question && preg_match(self::$questionRegex, $line, $match))
                $question = $match[ 1 ];
        }

        $this->IRCBot->saveToFile("triv.txt", $this->questions);
    }

}
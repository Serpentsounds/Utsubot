<?php
/**
 * Utsubot - Main.php
 * User: Benjamin
 * Date: 16/11/14
 */

class MiscException extends ModuleException {}

class Misc extends ModuleWithPermission {

    const INCLUDES_TYPE_CLASS = 1;
    const INCLUDES_TYPE_INTERFACE = 2;
    const INCLUDES_TYPE_TRAIT = 3;
    const INCLUDES_DISPLAY_MAX = 15;

	private $lastNowPlaying = 0;
	private static $nowPlayingInterval = 60;
	private static $nowPlayingFile = '\\\\GENSOU\drop\nowplaying.txt';

	protected static $versionResponse = "Utsubot by Serpentsounds: https://github.com/Serpentsounds/Utsubot";

	protected $inCountdown = array();

	public function __construct(IRCBot $irc) {
		parent::__construct($irc);

		$this->triggers = array(
			'hug'			=> "hug",

			'eval'			=> "_eval",
			'return'		=> "_return",
			'restart'		=> "restart",

			'nowplaying'	=> "nowPlaying",
			'np'			=> "nowPlaying",

			'includes'		=> "showIncludes",

			'countdown'		=> "countdown",

			'calculate'		=> "calculate",
			'calc'			=> "calculate",
			'c'				=> "calculate"
		);
	}

	public function calculate(IRCMessage $msg) {
		$this->respond($msg, (new Calculator($msg->getCommandParameterString()))->calculate());
	}

	public function ctcp(IRCMessage $msg) {
		switch (strtolower($msg->getCTCP())) {
			case "version":
				$this->IRCBot->ctcpReply($msg->getResponseTarget(), $msg->getCTCP(), self::$versionResponse);
			break;
			case "time":
				$this->IRCBot->ctcpReply($msg->getResponseTarget(), $msg->getCTCP(), date("D M j H:i:s Y"));
			break;
			case "ping":
				$this->IRCBot->ctcpReply($msg->getResponseTarget(), $msg->getCTCP(), $msg->getParameterString());
			break;
		}
	}

	/**
	 * Give the user a hug.
	 *
	 * @param IRCMessage $msg
	 */
	public function hug(IRCMessage $msg) {
		$this->IRCBot->action($msg->getResponseTarget(), "gives ". $msg->getNick(). " a hug!");
	}

	/**
	 * Evaluate text as PHP. Much danger
	 *
	 * @param IRCMessage $msg
	 */
	public function _eval(IRCMessage $msg) {
		$this->requireLevel($msg, 100);
		$this->respond($msg, eval($msg->getCommandParameterString().";"));
	}

	/**
	 * Evaluate text as PHP and return the result. Still much danger
	 *
	 * @param IRCMessage $msg
	 */
	public function _return(IRCMessage $msg) {
		$this->requireLevel($msg, 100);
		$this->respond($msg, eval("return ". $msg->getCommandParameterString(). ";"));
	}


	/**
	 * Restart the IRC bot with an optional quit message
	 *
	 * @param IRCMessage $msg
	 */
	public function restart(IRCMessage $msg) {
		$this->requireLevel($msg, 100);
		$this->IRCBot->restart($msg->getCommandParameterString());
	}

	/**
	 * Show currently playing song information
	 *
	 * @param IRCMessage $msg
	 * @throws ModuleException
	 */
	public function nowPlaying(IRCMessage $msg) {

		if (time() - $this->lastNowPlaying < self::$nowPlayingInterval) {
			try {
				$this->requireLevel($msg, 75);
			}
			catch (Exception $e) {
				throw new ModuleException("It is too soon to use that command again.");
			}
		}

		if (!file_exists(self::$nowPlayingFile))
			throw new ModuleException("Unable to locate song information.");

		$song = explode("|", file(self::$nowPlayingFile)[0]);

		if ($song[0] == "not running" || $song[0] == "?" || !$song[0])
			throw new ModuleException("There is currently no music playing.");

		list($codec, $artist, $title, $album, $date, $length, $bitrate, $composer, $performer, $time, $genre, $albumArtist, $path) = $song;

		$albumString = "";
		if ($album != "?")
			$albumString = self::color(" - ", "white", "black", false). self::color($album, "lime", "black", false);

		$nowPlaying =
			self::color("Playing ", 			"white",	"black", false).
			self::color($codec, 				"yellow",	"black", false).
			self::color("@", 					"white",	"black", false).
			self::color($bitrate, 			"yellow",	"black", false).
			self::color(": ",					"white",	"black", false).
			self::color($artist,				"lime",		"black", false).
			self::color(" - ",				"white",	"black", false).
			self::color($title,				"lime",		"black", false).
			$albumString.
			self::color(" [$time/$length]",	"fuchsia",	"black", true);

		$this->respond($msg, $nowPlaying);
		$this->respond($msg, "Tune in at http://radio.soredemo.net/ to listen along!");
		$this->lastNowPlaying = time();
	}

	/**
	 * Give information about included files
	 *
	 * @param IRCMessage $msg
     * @throws MiscException
	 */
	public function showIncludes(IRCMessage $msg) {
        $parameters = $msg->getCommandParameters();
        $mode = $parameters[0] ?? "";

        $output = array();
        switch ($mode) {
            case "class":
            case "classes":
                $output = self::classList(self::INCLUDES_TYPE_CLASS);
            break;

            case "interface":
            case "interfaces":
                $output = self::classList(self::INCLUDES_TYPE_INTERFACE);
            break;

            case "trait":
            case "traits":
                $output = self::classList(self::INCLUDES_TYPE_TRAIT);
            break;

            case "file":
            case "files":
                $files = self::includeInfo();
                $output = array_column($files, "details");
            break;

            case "":
                $files = self::includeInfo();

                $totalLines = array_sum(array_column($files, "lines"));
                $totalSize = array_sum(array_column($files, "sizes")) / 1024;
                $totalFiles = count($files);

                $this->respond($msg, sprintf(
                    "There are a total of %d lines (%.2fKiB) over %d files, for an average of %.2f lines (%.2fKiB) per file.",
                    $totalLines, $totalSize, $totalFiles, $totalLines / $totalFiles, $totalSize / $totalFiles
                ));

                $classCount = count(self::classList(self::INCLUDES_TYPE_CLASS));
                $interfaceCount = count(self::classList(self::INCLUDES_TYPE_INTERFACE));
                $traitCount = count(self::classList(self::INCLUDES_TYPE_TRAIT));

                $this->respond($msg, sprintf(
                    "There are %d custom classes, %d custom interfaces, and %d custom traits defined.",
                    $classCount, $interfaceCount, $traitCount
                ));
            break;

            default:
                throw new MiscException("Invalid includes category '$mode'.");
        }

        if (count($output)) {
            $numClasses = count($output);
            if ($numClasses > self::INCLUDES_DISPLAY_MAX) {
                $output   = array_slice($output, 0, self::INCLUDES_DISPLAY_MAX);
                $output[] = "and " . ($numClasses - self::INCLUDES_DISPLAY_MAX) . " more.";
            }

            $this->respond($msg, sprintf(
                "There are %d entries: %s",
                $numClasses, implode(", ", $output)
            ));

        }
	}

    private static function includeInfo(): array {
        $files = get_included_files();
        $details = array();

        foreach ($files as $file) {
            $lineCount = count(file($file));
            $fileSize = fileSize($file);

            $details[$file]['lines'] = $lineCount;
            $details[$file]['sizes'] = $fileSize;
            $details[$file]['details'] = sprintf("%s (%d li.)", basename($file), $lineCount);
        }

        usort($details, function($a, $b) {
            return $b['lines'] - $a['lines'];
        });

        return $details;
    }

    private static function classList(int $type): array {
        $classes = array();

        switch ($type) {
            case self::INCLUDES_TYPE_CLASS:
                $classes = get_declared_classes();
            break;
            case self::INCLUDES_TYPE_INTERFACE:
                $classes = get_declared_interfaces();
            break;
            case self::INCLUDES_TYPE_TRAIT:
                $classes = get_declared_traits();
            break;
        }

        $classes = array_filter(
            $classes,
            function ($class) {
                $reflection = new ReflectionClass($class);
                return !($reflection->isInternal());
            }
        );

        sort($classes);

        return $classes;
    }

	public function countdown(IRCMessage $msg) {
		$target = $msg->getResponseTarget();
		if (isset($this->inCountdown[$target]))
			throw new ModuleException("A countdown is already in progress.");

		$this->inCountdown[$target] = true;
		$time = microtime(true);

		$countdownFrom = 3;
		$countdownDelay = 5;
		$countdownInterval = 1;
		$readyMessage = "Get ready!";
		$endingMessage = "GO!";

		preg_match_all("/(\S+?):(?:\"([^\"]*)\"|(\S+))/", $msg->getCommandParameterString(), $match, PREG_SET_ORDER);
		foreach ($match as $entry) {
			list(, $option, $value) = $entry;
			if (isset($entry[3]))
				$value = $entry[3];

			switch ($option) {
				case "from":
					if (preg_match("/\D/", $value) || $value == 0)
						throw new ModuleException("'from' must be a positive integer.");
					$countdownFrom = $value;
				break;

				case "delay":
					if (!is_numeric($value) || $value < 0)
						throw new ModuleException("'delay' must be a non-negative number.");
					$countdownDelay = $value;
				break;

				case "interval":
					if (preg_match("/\D/", $value) || $value == 0)
						throw new ModuleException("'interval' must be a positive integer.");
					$countdownInterval = $value;
				break;

				case "ready":
					$readyMessage = $value;
				break;

				case "message":
					$endingMessage = $value;
				break;
			}
		}

		$baseTime = $time + $countdownDelay;
		$counted = 0;
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


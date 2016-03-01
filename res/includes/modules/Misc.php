<?php
/**
 * Utsubot - Main.php
 * User: Benjamin
 * Date: 16/11/14
 */

class Misc extends Module {

	use AccountAccess;

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
		$this->IRCBot->message($msg->getResponseTarget(), (new Calculator($msg->getCommandParameterString()))->calculate());
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
		$this->IRCBot->message($msg->getResponseTarget(), eval($msg->getCommandParameterString().";"));
	}

	/**
	 * Evaluate text as PHP and return the result. Still much danger
	 *
	 * @param IRCMessage $msg
	 */
	public function _return(IRCMessage $msg) {
		$this->requireLevel($msg, 100);
		$this->IRCBot->message($msg->getResponseTarget(), eval("return ". $msg->getCommandParameterString(). ";"));
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

		list($codec, $artist, $title, $album, $date, $length, $bitrate, $composer, $performer, $time) = $song;

		//	Optionally show album and composer/performer information as well
		$parameters = $msg->getCommandParameters();
		$showAlbum = ((boolean)@$parameters[0]) ?: false;
		$showComposer = ((boolean)@$parameters[1]) ?: false;

		//	Never mind, just show them
		$showAlbum = $showComposer = true;

		$composerString = "";
		if ($showComposer) {
			if ($performer != "?") {
				if ($composer != "?")
					$composerString = IRCUtility::color(" [$composer feat. $performer]", "navy", "black", false);
				else
					$composerString = IRCUtility::color(" [$performer]", "navy", "black", false);
			}
			elseif ($composer != "?")
				$composerString = IRCUtility::color(" [$composer]", "navy", "black", false);
		}

		$albumString = "";
		if ($showAlbum && $album != "?")
			$albumString = IRCUtility::color(" - ", "white", "black", false). IRCUtility::color($album, "lime", "black", false);

		$nowPlaying =
			IRCUtility::color("Playing ", 			"white",	"black", false).
			IRCUtility::color($codec, 				"yellow",	"black", false).
			IRCUtility::color("@", 					"white",	"black", false).
			IRCUtility::color($bitrate, 			"yellow",	"black", false).
			IRCUtility::color(": ",					"white",	"black", false).
			IRCUtility::color($artist,				"lime",		"black", false).
			$composerString.
			IRCUtility::color(" - ",				"white",	"black", false).
			IRCUtility::color($title,				"lime",		"black", false).
			$albumString.
			IRCUtility::color(" [$time/$length]",	"fuchsia",	"black", true);

		$this->IRCBot->message($msg->getResponseTarget(), $nowPlaying);
		$this->IRCBot->message($msg->getResponseTarget(), "Tune in at http://radio.soredemo.net/ to listen along!");
		$this->lastNowPlaying = time();
	}

	/**
	 * Give information about included files
	 *
	 * @param IRCMessage $msg
	 */
	public function showIncludes(IRCMessage $msg) {
		$files = get_included_files();
		$arr = array();

		foreach ($files as $file) {
			$lineCount = count(file($file));
			$fileSize = fileSize($file);
			$arr[$file]['lines'] = $lineCount;
			$arr[$file]['sizes'] = $fileSize;
			$arr[$file]['details'] = sprintf("%s: %s (%.2fKiB)", basename($file), $lineCount, $fileSize / 1024);
		}

		usort($arr, function($a, $b) {
			return $a['lines'] - $b['lines'];
		});

		$parameters = $msg->getCommandParameters();
		if (isset($parameters[0]) && strtolower($parameters[0]) == "all")
			$this->IRCBot->message($msg->getResponseTarget(), implode("; ", array_column($arr, "details")));

		else {
			$totalLines = array_sum(array_column($arr, "lines"));
			$totalSize = array_sum(array_column($arr, "sizes")) / 1024;
			$totalFiles = count($arr);
			$this->IRCBot->message($msg->getResponseTarget(), sprintf("There are a total of %d lines (%.2fKiB) over %d files, for an average of %.2f lines (%.2fKiB) per file.",
																	  $totalLines, $totalSize, $totalFiles, $totalLines / $totalFiles, $totalSize / $totalFiles));
		}
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


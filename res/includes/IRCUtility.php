<?php
/**
 * IRCUtility
 *
 * A collection of functions for dealing with IRC text formatting
 * This class throws exceptions on error, so catch them!
 */

class IRCUtilityException extends Exception {}

class IRCUtility {

	/**
	 * Check whether the variable can be represented as a string, whether or not it IS a string. Utility for input validation leniency
	 *
	 * @param mixed $var The variable to test
	 * @return bool True if it has a representation, false otherwise
	 */
	public static function isString($var) {
		return ($var === null || is_scalar($var) || (is_object($var) && method_exists($var, "__toString")));
	}

	/**
	 * Require all parameters to pass isString, or an exception is thrown. Utility for input validation
	 *
	 * @param mixed $args You can pass an arbitrary number of arguments to this function, and all will be validated
	 * @throws IRCUtilityException If any parameter fails to validate
	 */
	public static function requireString($args = null) {
		$args = func_get_args();
		foreach ($args as $arg) {
			if (!self::isString($arg))
				throw new IRCUtilityException("IRCUtility::requireString: passed variable can not be converted to a string.");
		}
	}

	/**
	 * Returns text bolded
	 *
	 * @param string $text Text to be bolded
	 * @return string Text bolded
	 */
	public static function bold($text) {
		self::requireString($text);
		return "\x02$text\x02";
	}

	/**
	 * Returns text italicized
	 *
	 * @param string $text Text to be italicized
	 * @return string Text italicized
	 */
	public static function italic($text) {
		self::requireString($text);
		return "\x1D$text\x1D";
	}

	/**
	 * Returns text underlined
	 *
	 * @param string $text Text to be underlined
	 * @return string Text underlined
	 */
	public static function underline($text) {
		self::requireString($text);
		return "\x1F$text\x1F";
	}

	/**
	 * Returns text "reversed", which reverses the default background and foreground colors
	 *
	 * @param string $text Text to be reversed
	 * @return string Text reversed
	 */
	public static function reverse($text) {
		self::requireString($text);
		return "\x16$text\x16";
	}

	/**
	 * Returns text with formatting intact, but with the clear formatting control code (\x0F) so subsequent text will have no formatting
	 *
	 * @param string $text The text to be terminated
	 * @return string Text terminated
	 */
	public static function terminate($text) {
		self::requireString($text);
		return "$text\x0F";
	}

	/**
	 * Strip bold, underline, italic, reverse, and color from text
	 *
	 * @param string $text The text to be stripped
	 * @return string Text with no control codes
	 */
	public static function stripControlCodes($text) {
		//	Must be a string
		self::requireString($text);

		//	Strip colors with a regex
		$text = preg_replace('/\x03\d{1,2}(,\d{1,2})?/', "", $text);

		//	Strip all other formatting
		$text = str_replace(array(chr(2), chr(3), chr(15), chr(22), chr(29), chr(31)), "", $text);

		return $text;
	}

	/**
	 * Returns text with given IRC color
	 *
	 * @param string $text The text to be colored
	 * @param int|string $color The foreground color, as an index or color name
	 * @param int|string $background (Optional) The background color, as an index or color name. Default none
	 * @param bool $close True to close the color, so subsequent text isn't colored. Default true
	 * @return string The colored text
	 * @throws IRCUtilityException If mistyped parameters are passed, or if an invalid color is passed
	 */
	public static function color($text, $color, $background = null, $close = true) {
		//	Make sure the values can be represented as a string
		self::requireString($text, $color, $background);

		//	Set appended text to close the color
		$close = ($close) ? "\x03" : "";

		//	Valid colors
		$colorNames = array("white", "black", "navy", "green", "red", "maroon", "purple", "orange", "yellow", "lime", "teal", "aqua", "blue", "fuchsia", "light gray", "gray");

		$color = strtolower($color);
		//	Invalid color passed
		if (!isset($colorNames[$color])) {
			if (($color = array_search($color, $colorNames)) === false)
				throw new IRCUtilityException("IRCUtility::color: $color is not a valid color.");
		}
		//	Repeat for background, if applicable
		if ($background !== null && !isset($colorNames[$background])) {
			$background = strtolower($background);
			if (($background = array_search($background, $colorNames)) === false)
				throw new IRCUtilityException("IRCUtility::color: $background is not a valid background color.");
		}

		//	Use background color
		if (is_numeric($background))
			$background = sprintf(",%02d", $background);

		return sprintf("\x03%02d$background$text$close", $color);
	}

	/**
	 * Utility function used in calculating the Jaro distance between two strings
	 *
	 * @param string $base The first string
	 * @param string $comparison The second string
	 * @return string The common characters between them
	 */
	private static function getMatchingCharacters($base, $comparison) {
		$lengths = array(strlen($base), strlen($comparison));
		$maxDistance = floor(max($lengths) / 2) - 1;
		$result = "";

		for ($i = 0; $i < $lengths[0]; $i++) {
			$min = max(0, $i - $maxDistance);
			$max = min($i + $maxDistance, $lengths[1]);

			for ($j = intval($min); $j < $max; $j++) {
				if ($comparison[$j] == $base[$i]) {

					$result .= $base[$i];
					$comparison[$j] = "";
					break;
				}
			}

		}

		return $result;
	}

	/**
	 * Calculate the Jaro distance between 2 strings. 1 = exact match, 0 = no match, similarities are somewhere in between
	 *
	 * @param string $base The first string
	 * @param string $comparison The second string
	 * @return float The Jaro distance
	 */
	public function jaroDistance($base, $comparison){
		$lengths = array(strlen($base), strlen($comparison));

		$matchingCharacters = array(
			self::getMatchingCharacters($base, $comparison),
			self::getMatchingCharacters($comparison, $base)
		);
		$matchingLengths = array(strlen($matchingCharacters[0]), strlen($matchingCharacters[1]));

		$matchingLengthsMinimum = min($matchingLengths);
		if ($matchingLengthsMinimum == 0)
			return 0;

		$swaps = 0;
		for ($i = 0; $i < $matchingLengthsMinimum; $i++){
			if ($matchingCharacters[0][$i] != $matchingCharacters[1][$i])
				$swaps++;
		}
		$swaps /= 2;

		//	Jaro Calculation
		return ($matchingLengths[0] / $lengths[0] + $matchingLengths[0] / $lengths[1] + ($matchingLengths[0] - $swaps) / $matchingLengths[0]) / 3;
	}

	/**
	 * Use the Winkler prefix weight in conjunction with the Jaro distance to calculate the Jaro-Winkler distance
	 *
	 * @param string $base The first string
	 * @param string $comparison The second string
	 * @param int $prefixLength How many exactly matching characters to check for at the beginning of the strings. These characters have more weight in the metric. Max of 4, default 4
	 * @param float $prefixScale The weight to give to the matching prefix characters. Max of 0.25, default 0.1
	 * @return float The combined Jaro-Winkler distance
	 */
	public static function jaroWinklerDistance($base, $comparison, $prefixLength = 4, $prefixScale = 0.1) {
		$base = strtolower($base);
		$comparison = strtolower($comparison);

		//	Prepare to calculate length of common prefix
		$check = min($prefixLength, strlen($base), strlen($comparison));

		$commonPrefix = 0;
		for ($i = 0; $i < $check; $i++) {
			//	Characters must be the same
			if ($base[$i] != $comparison[$i])
				break;

			$commonPrefix++;
		}

		$commonSuffix = 0;
		if ($check >= 6) {
			for ($i = $check - 1; $i >= 0; $i--) {
				//	Characters must be the same
				if ($base[$i] != $comparison[$i])
					break;

				$commonSuffix++;
			}
		}

		$jaroDistance = self::jaroDistance($base, $comparison);
		//	Jaro-Winkler Calculation
		$jaroWinklerDistance = $jaroDistance + $commonPrefix * $prefixScale * (1.0 - $jaroDistance);
		#return $jaroWinklerDistance;
		return $jaroWinklerDistance + $commonSuffix * $prefixScale * (1.0 - $jaroWinklerDistance);
	}

	public static function romanizeKana($kana) {

		$katakana = array(
			'キャ', 'キュ', 'キョ', 'シャ', 'シュ', 'ショ', 'チャ', 'チュ', 'チョ', 'ニャ', 'ニュ', 'ニョ', 'ヒャ', 'ヒュ', 'ヒョ', 'ミャ', 'ミュ', 'ミョ', 'リャ', 'リュ', 'リョ',
			'ギャ', 'ギュ', 'ギョ', 'ジャ', 'ジュ', 'ジョ', 'ヂャ', 'ヂュ', 'ヂョ', 'ビャ', 'ビュ', 'ビョ', 'ピャ', 'ピュ', 'ピョ',
			'ア', 'イ', 'ウ', 'エ', 'オ', 'カ', 'キ', 'ク', 'ケ', 'コ', 'サ', 'シ', 'ス', 'セ', 'ソ', 'タ', 'チ', 'ツ', 'テ', 'ト', 'ナ', 'ニ', 'ヌ', 'ネ', 'ノ',
			'ハ', 'ヒ', 'フ', 'ヘ', 'ホ', 'マ', 'ミ', 'ム', 'メ', 'モ', 'ヤ', 'ユ', 'ヨ', 'ラ', 'リ', 'ル', 'レ', 'ロ', 'ワ', 'ヲ',
			'ガ', 'ギ', 'グ', 'ゲ', 'ゴ', 'ザ', 'ジ', 'ズ', 'ゼ', 'ゾ', 'ダ', 'ヂ', 'ヅ', 'デ', 'ド', 'バ', 'ビ', 'ブ', 'ベ', 'ボ', 'パ', 'ピ', 'プ', 'ペ', 'ポ', 'ン',
			'ァ', 'ィ', 'ゥ', 'ェ', 'ォ');

		$hiragana = array(
			'きゃ', 'きゅ', 'きょ', 'しゃ', 'しゅ', 'しょ', 'ちゃ', 'ちゅ', 'ちょ', 'にゃ', 'にゅ', 'にょ', 'ひゃ', 'ひゅ', 'ひょ', 'みゃ', 'みゅ', 'みょ', 'りゃ', 'りゅ', 'りょ',
			'ぎゃ', 'ぎゅ', 'ぎょ', 'じゃ', 'じゅ', 'じょ', 'ぢゃ', 'ぢゅ', 'ぢょ', 'びゃ', 'びゅ', 'びょ', 'ぴゃ', 'ぴゅ', 'ぴょ',
			'あ', 'い', 'う', 'え', 'お', 'か', 'き', 'く', 'け', 'こ', 'さ', 'し', 'す', 'せ', 'そ', 'た', 'ち', 'つ', 'て', 'と', 'な', 'に', 'ぬ', 'ね', 'の',
			'は', 'ひ', 'ふ', 'へ', 'ほ', 'ま', 'み', 'む', 'め', 'も', 'や', 'ゆ', 'よ', 'ら', 'り', 'る', 'れ', 'ろ', 'わ', 'を',
			'が', 'ぎ', 'ぐ', 'げ', 'ご', 'ざ', 'じ', 'ず', 'ぜ', 'ぞ', 'だ', 'ぢ', 'づ', 'で', 'ど', 'ば', 'び', 'ぶ', 'べ', 'ぼ', 'ぱ', 'ぴ', 'ぷ', 'ぺ', 'ぽ', 'ん',
			'ぁ', 'ぃ', 'ぅ', 'ぇ', 'ぉ');

		$extraKatakana = array(
			'ヴァ', 'ヴィ', 'ヴ', 'ヴェ', 'ヴォ', 'ウィ', 'ウェ', 'ウォ', 'ファ', 'フィ', 'フェ', 'フォ', 'チェ', 'ディ', 'ドゥ', 'ティ', 'トゥ', 'シェ');

		$romanization = array(
			'kya', 'kyu', 'kyo', 'sha', 'shu', 'sho', 'cha', 'chu', 'cho', 'nya', 'nyu', 'nyo', 'hya', 'hyu', 'hyo', 'mya', 'myu', 'myo', 'rya', 'ryu', 'ryo',
			'gya', 'gyu', 'gyo', 'ja', 'ju', 'jo', 'ja', 'ju', 'jo', 'bya', 'byu', 'byo', 'pya', 'pyu', 'pyo',
			'a', 'i', 'u', 'e', 'o', 'ka', 'ki', 'ku', 'ke', 'ko', 'sa', 'shi', 'su', 'se', 'so', 'ta', 'chi', 'tsu', 'te', 'to', 'na', 'ni', 'nu', 'ne', 'no',
			'ha', 'hi', 'fu', 'he', 'ho', 'ma', 'mi', 'mu', 'me', 'mo', 'ya', 'yu', 'yo', 'ra', 'ri', 'ru', 're', 'ro', 'wa', 'wo',
			'ga', 'gi', 'gu', 'ge', 'go', 'za', 'ji', 'zu', 'ze', 'zo', 'da', 'ji', 'zu', 'de', 'do', 'ba', 'bi', 'bu', 'be', 'bo', 'pa', 'pi', 'pu', 'pe', 'po', 'n',
			'a', 'i', 'u', 'e', 'o');

		$extraKatakanaRomanization = array(
			'va', 'vi', 'vu', 've', 'vo', 'wi', 'we', 'wo', 'fa', 'fi', 'fe', 'fo', 'che', 'di', 'dou', 'ti', 'tou', 'she');

		//	Adds ' for n followed by vowel (んあ vs. な)
		$kana = preg_replace('/([\\x{30F3}\\x{3093}])([\\x{30A1}-\\x{30AA}\\x{3041}-\\x{304A}])/u', '$1\'$2', $kana);
		//	Replace extra katakana sounds with romaji
		$kana = str_replace($extraKatakana, $extraKatakanaRomanization, $kana);
		//	Replace katakana with romaji
		$kana = str_replace($katakana, $romanization, $kana);
		//	Replace hiragana with romaji
		$kana = str_replace($hiragana, $romanization, $kana);
		//	Double up long katakana vowels (aー to aa, etc)
		$kana = preg_replace('/([aeiou])\\x{30FC}/u', '$1$1', $kana);
		//	Fill in sokuons (っk to kk, etc)
		$kana = preg_replace('/(?:\\x{30C3}|\\x{3063})([a-z])/u', '$1$1', $kana);

		return $kana;
	}

}
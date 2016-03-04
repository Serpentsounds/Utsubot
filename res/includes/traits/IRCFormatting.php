<?php

declare(strict_types = 1);

class IRCFormattingException extends Exception {}

/**
 * Trait IRCFormatting
 *
 * A collection of functions for dealing with IRC text formatting
 */
trait IRCFormatting {

	/**
	 * Returns text bolded
	 *
	 * @param string $text Text to be bolded
	 * @return string Text bolded
	 */
	public static function bold(string $text): string {
		return "\x02$text\x02";
	}

	/**
	 * Returns text italicized
	 *
	 * @param string $text Text to be italicized
	 * @return string Text italicized
	 */
	public static function italic(string $text): string {
		return "\x1D$text\x1D";
	}

	/**
	 * Returns text underlined
	 *
	 * @param string $text
	 * @return string
	 */
	public static function underline(string $text): string {
		return "\x1F$text\x1F";
	}

	/**
	 * Returns text "reversed", which reverses the default background and foreground colors
	 *
	 * @param string $text
	 * @return string
	 */
	public static function reverse(string $text): string {
		return "\x16$text\x16";
	}

	/**
	 * Returns text with formatting intact, but with the clear formatting control code (\x0F) so subsequent text will have no formatting
	 *
	 * @param string $text
	 * @return string
	 */
	public static function terminate(string $text): string {
		return "$text\x0F";
	}

	/**
	 * Strip bold, underline, italic, reverse, and color from text
	 *
	 * @param string $text
	 * @return string
	 */
	public static function stripControlCodes(string $text): string {
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
	 * @return string
	 * @throws IRCFormattingException If mistyped parameters are passed, or if an invalid color is passed
	 */
	public static function color(string $text, $color, $background = null, bool $close = true): string {
		//	Set appended text to close the color
		$close = ($close) ? "\x03" : "";

		//	Valid colors
		$colorNames = array("white", "black", "navy", "green", "red", "maroon", "purple", "orange", "yellow", "lime", "teal", "aqua", "blue", "fuchsia", "light gray", "gray");

		$color = strtolower($color);
		//	Invalid color passed
		if (!isset($colorNames[$color])) {
			if (($color = array_search($color, $colorNames)) === false)
				throw new IRCFormattingException("'$color' is not a valid IRC color.");
		}
		//	Repeat for background, if applicable
		if ($background !== null && !isset($colorNames[$background])) {
			$background = strtolower($background);
			if (($background = array_search($background, $colorNames)) === false)
				throw new IRCFormattingException("'$background' is not a valid IRC color.");
		}

		//	Use background color
		if (is_numeric($background))
			$background = sprintf(",%02d", $background);

		return sprintf("\x03%02d$background$text$close", $color);
	}

}
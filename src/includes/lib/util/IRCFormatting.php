<?php
/**
 * IRCFormatting
 * A collection of functions for dealing with text formatting over IRC
 */
declare(strict_types = 1);

namespace Utsubot;


/**
 * Returns text bolded
 *
 * @param string $text
 * @return string
 */
function bold(string $text): string {
    return "\x02$text\x02";
}


/**
 * Returns text italicized
 *
 * @param string $text
 * @return string
 */
function italic(string $text): string {
    return "\x1D$text\x1D";
}


/**
 * Returns text underlined
 *
 * @param string $text
 * @return string
 */
function underline(string $text): string {
    return "\x1F$text\x1F";
}


/**
 * Returns text "reversed", which reverses the default background and foreground colors
 *
 * @param string $text
 * @return string
 */
function reverse(string $text): string {
    return "\x16$text\x16";
}


/**
 * Returns text with formatting intact, but with the clear formatting control code (\x0F) so subsequent text will have
 * no formatting
 *
 * @param string $text
 * @return string
 */
function terminate(string $text): string {
    return "$text\x0F";
}


/**
 * Strip bold, underline, italic, reverse, and color from text
 *
 * @param string $text
 * @return string
 */
function stripControlCodes(string $text): string {
    //	Strip colors with a regex
    $text = preg_replace('/\x03\d{1,2}(,\d{1,2})?/', "", $text);

    //	Strip all other formatting
    $text = str_replace([ chr(2), chr(3), chr(15), chr(22), chr(29), chr(31) ], "", $text);

    return $text;
}


/**
 * Returns text with given IRC color
 *
 * @param string $text
 * @param Color  $color
 * @param Color  $background
 * @param bool   $close True to close the color code, so subsequent text isn't colored
 * @return string
 */
function colorText(string $text, Color $color, Color $background = null, bool $close = true): string {
    return sprintf(
        "\x03%02d%s%s%s",
        $color->getValue(),
        ($background instanceof Color && $background->getValue() != Color::Clear) ?
            sprintf(",%02d", $background->getValue()) :
            "",
        $text,
        ($close) ?
            "\x03" :
            ""
    );
}

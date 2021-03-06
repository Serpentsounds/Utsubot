<?php
/**
 * Utsubot - TypeColors.php
 * Date: 12/05/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon\Types;
use Utsubot\{
    Enum,
    Color,
    EnumException
};
use function Utsubot\colorText;


/**
 * Class TypeColorsException
 *
 * @package Utsubot\Pokemon\Types\lib
 */
class TypeColorException extends EnumException {}

/**
 * Class TypeColors
 *
 * @package Utsubot\Pokemon\Types\lib
 */
class TypeColor extends Enum {

    const Bug       = [ Color::Green,      Color::Clear ];
    const Dark      = [ Color::Black,      Color::White ];
    const Dragon    = [ Color::Teal,       Color::Clear ];
    const Electric  = [ Color::Yellow,     Color::Black ];
    const Fighting  = [ Color::Maroon,     Color::Clear ];
    const Fire      = [ Color::Red,        Color::Clear ];
    const Flying    = [ Color::Light_Gray, Color::Black ];
    const Ghost     = [ Color::Purple,     Color::Clear ];
    const Grass     = [ Color::Lime,       Color::Black ];
    const Ground    = [ Color::Orange,     Color::Clear ];
    const Ice       = [ Color::Aqua,       Color::Black ];
    const Normal    = [ Color::White,      Color::Black ];
    const Poison    = [ Color::Purple,     Color::Clear ];
    const Psychic   = [ Color::Fuchsia,    Color::Clear ];
    const Rock      = [ Color::Orange,     Color::Clear ];
    const Steel     = [ Color::Gray,       Color::Clear ];
    const Water     = [ Color::Blue,       Color::Clear ];
    const Fairy     = [ Color::Fuchsia,    Color::Clear ];

    const Bird          = self::Flying;
    const FlyingPress   = self::Fighting;
    const FreezeDry     = self::Ice;

    /**
     * @return string
     */
    public function format(): string {
        return colorText(
            $this->getName(),
            new Color($this->value[0]),
            new Color($this->value[1])
        );
    }

    /**
     * @param array $colors
     * @return string
     * @throws TypeColorException If an element is not a valid Color
     */
    public static function colorAll(array $colors): string {
        $return = [ ];

        foreach ($colors as $color) {
            if (!($color instanceof TypeColor))
                throw new TypeColorException("Invalid TypeColor object passed for coloring.");

            $return[] = $color->format();
        }

        return implode("/", $return);
    }
}
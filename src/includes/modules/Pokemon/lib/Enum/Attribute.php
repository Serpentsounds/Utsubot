<?php
/**
 * Utsubot - Attribute.php
 * Date: 16/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon;
use Utsubot\{
    Enum,
    Color
};


/**
 * Class Attribute
 *
 * @package Utsubot\Pokemon
 * @method static Attribute fromName(string $name)
 */
class Attribute extends Enum {
    
    const Cool     = 0;
    const Beauty   = 1;
    const Cute     = 2;
    const Smart    = 3;
    const Tough    = 4;

    protected static $colors = [
        self::Cool     => [ Color::Red,     Color::Clear ],
        self::Beauty   => [ Color::Blue,    Color::Clear ],
        self::Cute     => [ Color::Fuchsia, Color::Clear ],
        self::Smart    => [ Color::Green,   Color::Clear ],
        self::Tough    => [ Color::Yellow,  Color::Black ]
    ];

    /**
     * @return array
     */
    public function getColors(): array {
        $values = self::$colors[$this->getValue()];
        return [
            new Color($values[0]),
            new Color($values[1])
        ];
    }
}
<?php
/**
 * Utsubot - Requirement.php
 * Date: 15/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon\Pokemon;

use Utsubot\Enum;


/**
 * Class EvolutionRequirement
 *
 * @package Utsubot\Pokemon\Pokemon
 */
class EvolutionRequirement extends Enum {

    const Level           = 1;
    const Gender          = 1 << 1;
    const Time            = 1 << 2;
    const Happiness       = 1 << 3;
    const Beauty          = 1 << 4;
    const Affection       = 1 << 5;
    const Rain            = 1 << 6;
    const Upside_Down     = 1 << 7;
    const Relative_Stats  = 1 << 8;
    const Use_Item        = 1 << 9;
    const Hold_Item       = 1 << 10;
    const Location        = 1 << 11;
    const Generation      = 1 << 12;
    const Knows_Move      = 1 << 13;
    const Knows_Move_Type = 1 << 14;
    const Party_Pokemon   = 1 << 15;
    const Party_Type      = 1 << 16;
    const Trade_For       = 1 << 17;

    private static $display = [
        self::Level           => "(%d)",
        self::Gender          => "(%s)",
        self::Time            => "during %s",
        self::Happiness       => "(%d+ happiness)",
        self::Beauty          => "(%d+ beauty)",
        self::Affection       => "(%d+ affection)",
        self::Rain            => "(during overworld rain)",
        self::Upside_Down     => "(turn system upside-down)",
        self::Relative_Stats  => "with %s",
        self::Use_Item        => "%s",
        self::Hold_Item       => "holding %s",
        self::Location        => "at %s",
        self::Generation      => "(gen %d)",
        self::Knows_Move      => "(knows %s)",
        self::Knows_Move_Type => "(knows %s-type move)",
        self::Party_Pokemon   => "(%s in party)",
        self::Party_Type      => "(%s-type in party)",
        self::Trade_For       => "for %s"
    ];


    /**
     * @return string
     */
    public function __toString(): string {
        return self::$display[ $this->getValue() ];
    }

}
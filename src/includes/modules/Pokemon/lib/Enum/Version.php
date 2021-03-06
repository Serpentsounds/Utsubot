<?php
/**
 * Utsubot - Version.php
 * Date: 15/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon;
use Utsubot\Enum;


/**
 * Class Version
 *
 * @package Utsubot\Pokemon
 * @method static Version fromName(string $name)
 */
class Version extends Enum {

    const Red                       = 0;
    const Blue                      = 1;
    const Yellow                    = 2;
    const Gold                      = 3;
    const Silver                    = 4;
    const Crystal                   = 5;
    const Ruby                      = 6;
    const Sapphire                  = 7;
    const Emerald                   = 8;
    const FireRed                   = 9;
    const LeafGreen                 = 10;
    const Diamond                   = 11;
    const Pearl                     = 12;
    const Platinum                  = 13;
    const HeartGold                 = 14;
    const SoulSilver                = 15;
    const Black                     = 16;
    const White                     = 17;
    const Colosseum                 = 18;
    const XD                        = 19;
    const Black_2                   = 20;
    const White_2                   = 21;
    const X                         = 22;
    const Y                         = 23;
    const Omega_Ruby                = 24;
    const Alpha_Sapphire            = 25;    
    
    const Red_Blue                  = 100;
    const Gold_Silver               = 101;
    const Ruby_Sapphire             = 102;
    const FireRed_LeafGreen         = 103;
    const Diamond_Pearl             = 104;
    const HeartGold_SoulSilver      = 105;
    const Black_White               = 106;
    const Black_2_White_2           = 107;
    const X_Y                       = 108;
    const Omega_Ruby_Alpha_Sapphire = 109;

}
<?php
/**
 * Utsubot - Flavor.php
 * Date: 16/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon;
use Utsubot\Enum;


/**
 * Class Flavor
 *
 * @package Utsubot\Pokemon
 * @method static Flavor fromName(string $name)
 */
class Flavor extends Enum {

    const Spicy     = 0;
    const Dry       = 1;
    const Sweet     = 2;
    const Bitter    = 3;
    const Sour      = 4;
    
}
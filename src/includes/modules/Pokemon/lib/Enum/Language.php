<?php
/**
 * Utsubot - Language.php
 * Date: 15/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon;
use Utsubot\Enum;


/**
 * Class Language
 *
 * @package Utsubot\Pokemon
 * @method static Language fromName(string $name)
 */
class Language extends Enum {

    const English           = 0;
    const Japanese          = 1;
    const Official_roomaji  = 2;
    const Roumaji           = 2;
    const Korean            = 3;
    const Chinese           = 4;
    const French            = 5;
    const German            = 6;
    const Spanish           = 7;
    const Italian           = 8;
    const Czech             = 9;
    const All               = 10;
    
}
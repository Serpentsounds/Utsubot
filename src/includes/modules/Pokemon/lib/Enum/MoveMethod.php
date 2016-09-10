<?php
/**
 * Created by PhpStorm.
 * User: benny
 * Date: 9/10/2016
 * Time: 5:59 AM
 */

namespace Utsubot\Pokemon;

use Utsubot\Enum;


/**
 * Class Method
 *
 * @package Utsubot\Pokemon
 * @method static MoveMethod fromName(string $name)
 */
class MoveMethod extends Enum {

    const Level_Up                = 1;
    const Egg                     = 2;
    const Tutor                   = 3;
    const Machine                 = 4;
    const Stadium_Surfing_Pikachu = 5;
    const Light_Ball_Egg          = 6;
    const Form_Change             = 10;

}
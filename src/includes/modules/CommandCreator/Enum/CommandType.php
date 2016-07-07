<?php
/**
 * Created by PhpStorm.
 * User: benny
 * Date: 7/6/2016
 * Time: 6:49 PM
 */

namespace Utsubot\CommandCreator;

use Utsubot\Enum;


/**
 * Class CommandType
 *
 * @package Utsubot\CommandCreator
 * @method static CommandType fromName(string $name)
 */
class CommandType extends Enum {

    const Message = 0;
    const Action  = 1;
    
}
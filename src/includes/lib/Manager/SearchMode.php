<?php
/**
 * Utsubot - SearchMode.php
 * Date: 03/07/2016
 */

declare(strict_types = 1);

namespace Utsubot\Manager;


use Utsubot\Enum;


/**
 * Class SearchMode
 *
 * @package Utsubot\Manager
 * @method static SearchMode fromName(string $name)
 */
class SearchMode extends Enum {

    const Any = 0;
    const All = 1;

}
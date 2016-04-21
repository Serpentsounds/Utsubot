<?php
/**
 * MEGASSBOT - Ability.php
 * User: Benjamin
 * Date: 06/11/14
 */

namespace Utsubot\Pokemon\Ability;
use Utsubot\Manageable;
use Utsubot\Pokemon\{
    AbilityItemBase,
    PokemonBaseException
};


/**
 * Class AbilityException
 *
 * @package Utsubot\Pokemon\Ability
 */
class AbilityException extends PokemonBaseException {}

/**
 * Class Ability
 *
 * @package Utsubot\Pokemon\Ability
 */
class Ability extends AbilityItemBase implements Manageable {}

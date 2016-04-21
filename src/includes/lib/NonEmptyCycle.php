<?php
/**
 * Utsubot - NonEmptyCycle.php
 * Date: 21/04/2016
 */

declare(strict_types = 1);

namespace Utsubot;


/**
 * Class NonEmptyCycleException
 *
 * @package Utsubot
 */
class NonEmptyCycleException extends CycleException {}

/**
 * Class NonEmptyCycle
 * A cycle which must have at least one value
 *
 * @package Utsubot
 */
class NonEmptyCycle extends Cycle {

    /**
     * NonEmptyCycle constructor.
     *
     * @param array $items
     * @throws NonEmptyCycleException Empty $items
     */
    public function __construct(array $items) {
        if (empty($items))
            throw new NonEmptyCycleException("NonEmptyCycle must have starting items.");

        parent::__construct($items);
    }
}
<?php
/**
 * Utsubot - Manageable.php
 * Date: 24/03/2016
 */

declare(strict_types = 1);

namespace Utsubot;

/**
 * Interface Manageable
 *
 * @package Utsubot
 */
interface Manageable {

    /**
     * @param mixed $terms
     * @return bool
     */
    function search($terms): bool;
    
}
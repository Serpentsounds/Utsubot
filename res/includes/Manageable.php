<?php
/**
 * Utsubot - Manageable.php
 * Date: 24/03/2016
 */

declare(strict_types = 1);

interface Manageable {
    function search($terms): bool;
}
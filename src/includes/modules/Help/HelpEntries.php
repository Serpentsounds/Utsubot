<?php
/**
 * Utsubot - HelpEntries.php
 * Date: 04/05/2016
 */

declare(strict_types = 1);

namespace Utsubot\Help;


/**
 * Class HelpEntriesException
 *
 * @package Utsubot
 */
class HelpEntriesException extends \Exception {}

/**
 * Class HelpEntries
 *
 * @package Utsubot
 */
class HelpEntries extends \ArrayObject {

    /**
     * @param mixed $value
     * @throws HelpEntriesException
     */
    public function append($value) {
        if (!($value instanceof HelpEntry))
            throw new HelpEntriesException("Only a HelpEntry can be added to HelpEntries.");

        parent::append($value);
    }

}
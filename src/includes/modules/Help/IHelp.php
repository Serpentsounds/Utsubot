<?php
/**
 * Utsubot - IHelp.php
 * Date: 03/05/2016
 */

declare(strict_types = 1);

namespace Utsubot\Help;


/**
 * Interface Help
 *
 * @package Utsubot
 */
interface IHelp {

    /**
     * @param string $topic
     * @return HelpEntry
     */
    public function getHelp(string $topic): HelpEntry;

    /**
     * @return HelpEntries
     */
    public function getHelpEntries(): HelpEntries;
}
<?php
/**
 * Utsubot - THelp.php
 * Date: 04/05/2016
 */

declare(strict_types = 1);

namespace Utsubot\Help;


/**
 * Class THelpException/
 *
 * @package Utsubot
 */
class THelpException extends \Exception {}

/**
 * Trait THelp
 *
 * @package Utsubot
 */
trait THelp {

    /** @var HelpEntries $help */
    private $help;

    /**
     * Add a HelpEntry to the collection
     * 
     * @param HelpEntry $helpEntry
     */
    public function addHelp(HelpEntry $helpEntry) {
        $this->initializeHelpEntries();        
        $this->help->append($helpEntry);
    }

    /**
     * Get a HelpEntry for a given topic
     * 
     * @param string $topic
     * @return HelpEntry
     * @throws THelpException No matching entry
     */
    public function getHelp(string $topic): HelpEntry {
        $this->initializeHelpEntries();
        
        foreach ($this->help as $entry)
            /** @var HelpEntry $entry */
            if ($entry->getTrigger() === strtolower($topic))
                return clone $entry;

        throw new THelpException("No help could be found for '$topic'.");
    }

    /**
     * Get the whole collection of HelpEntry objects
     * @return HelpEntries
     */
    public function getHelpEntries(): HelpEntries {
        $this->initializeHelpEntries();
        return $this->help;
    }

    /**
     * Make sure Help storage is in valid format
     */
    private function initializeHelpEntries() {
        if (!($this->help instanceof HelpEntries))
            $this->help = new HelpEntries();
    }
}
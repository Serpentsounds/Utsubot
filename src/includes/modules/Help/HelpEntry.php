<?php
/**
 * Utsubot - HelpEntry.php
 * Date: 03/05/2016
 */

declare(strict_types = 1);

namespace Utsubot\Help;
use Utsubot\Trigger;


/**
 * Class HelpEntryException
 *
 * @package Utsubot\Help
 */
class HelpEntryException extends \Exception {}

/**
 * Class HelpEntry
 *
 * @package Utsubot
 */
class HelpEntry {

    private $trigger;
    private $category;

    private $parameterText = [ ];
    private $notes = [ ];

    /**
     * HelpEntry constructor.
     *
     * @param string $category
     * @param Trigger $trigger
     * @throws HelpEntryException
     */
    public function __construct(string $category, Trigger $trigger) {
        $this->trigger = $trigger;

        if (!strlen($category))
            throw new HelpEntryException("Category for HelpEntry can not be blank.");
        $this->category = $category;
    }

    /**
     * @return string
     */
    public function __toString(): string {
        return $this->trigger->getTrigger();
    }

    /**
     * Check if a command matches this entry
     * 
     * @param string $command
     * @return bool
     */
    public function matches(string $command): bool {
        return $this->trigger->willTrigger($command);
    }
    
    /**
     * @param string $parameters
     * @param string $text
     */
    public function addParameterTextPair(string $parameters, string $text) {
        $this->parameterText[$parameters] = $text;
    }

    /**
     * @param string $notes
     */
    public function addNotes(string $notes) {
        $this->notes[] = $notes;
    }

    /**
     * @return string
     */
    public function getTrigger(): string {
        return $this->trigger->getTrigger();
    }

    /**
     * @return string
     */
    public function getCategory(): string {
        return $this->category;
    }
    
    /**
     * @return array
     */
    public function getAliases(): array {
        return $this->trigger->getAliases();
    }

    /**
     * @return array
     */
    public function getParameterTextPairs(): array {
        return $this->parameterText;
    }

    /**
     * @return array
     */
    public function getNotes(): array {
        return $this->notes;
    }
    
}
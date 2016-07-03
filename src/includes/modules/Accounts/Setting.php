<?php
/**
 * Utsubot - Setting.php
 * Date: 28/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Accounts;

/**
 * Class SettingException
 *
 * @package Utsubot\Accounts
 */
class SettingException extends AccountsException {

}


/**
 * Class Setting
 * Data structure to validate a set of parameters describing an account setting field to put into the database
 *
 * @package Utsubot\Accounts
 */
class Setting {

    private $module;
    private $name;
    private $display;
    private $maxEntries;


    /**
     * Setting constructor.
     *
     * @param ModuleWithAccounts $module Parent module
     * @param string             $name
     * @param string             $display
     * @param int                $maxEntries
     * @throws SettingException Invalid parmaeters
     */
    public function __construct(ModuleWithAccounts $module, string $name, string $display, int $maxEntries) {
        $this->module = $module;

        if (!strlen($name))
            throw new SettingException("Name must not be empty.");
        $this->name = $name;

        if (!strlen($display))
            throw new SettingException("Display must not be empty.");
        $this->display = $display;

        if ($maxEntries < 1)
            throw new SettingException("Maximum number of entries must be at least 1.");
        $this->maxEntries = $maxEntries;
    }


    /**
     * @return string
     */
    public function __toString(): string {
        return $this->display;
    }


    /**
     * @param string $value
     */
    public function validate(string $value) {
        $this->module->validateSetting($this, $value);
    }


    /**
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }


    /**
     * @return string
     */
    public function getDisplay(): string {
        return $this->display;
    }


    /**
     * @return int
     */
    public function getMaxEntries(): int {
        return $this->maxEntries;
    }

}
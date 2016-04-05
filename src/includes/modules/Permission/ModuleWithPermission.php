<?php
/**
 * Utsubot - ModuleWithPermission.php
 * Date: 04/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Permission;

use Utsubot\{ModuleException, IRCMessage};
use Utsubot\Accounts\{ModuleWithAccounts};

/**
 * Class ModuleWithPermission
 *
 * Extends Module functionality further on top of accounts levels to allow individual command blocking based on any
 * combination of nickname (wildcard), channel, address (wildcard), account id, or specific parameter string (wildcard)
 */
abstract class ModuleWithPermission extends ModuleWithAccounts {

    /**
     * Check if a user has permission to use their issued command
     *
     * @param IRCMessage $msg
     * @param string $trigger
     * @return bool
     * @throws ModuleException If Permission module isn't loaded
     */
    public function hasPermission(IRCMessage $msg, string $trigger): bool {
        /** @var $permission Permission */
        $permission = $this->externalModule("Utsubot\\Permission\\Permission");
        return $permission->hasPermission($msg, $trigger);
    }

    /**
     * Throw an exception if a command is denied
     *
     * @param IRCMessage $msg
     * @param string $trigger
     * @throws PermissionException
     */
    protected function requirePermission(IRCMessage $msg, string $trigger) {
        if (!($this->hasPermission($msg, $trigger)))
            throw new PermissionException("You do not have permission to use $trigger.");
    }

    /**
     * Check permission before calling default parseTriggers
     *
     * @param IRCMessage $msg
     */
    protected function parseTriggers(IRCMessage $msg) {
        if (!$msg->isCommand())
            return;

        $triggers = $this->triggers;
        $cmd = strtolower($msg->getCommand());
        //	Triggered a command
        if (isset($triggers[$cmd]) && method_exists($this, $triggers[$cmd]) && $this->hasPermission($msg, $triggers[$cmd]))
            parent::parseTriggers($msg);
    }

}
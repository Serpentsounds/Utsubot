<?php
/**
 * Utsubot - ModuleWithAccounts.php
 * Date: 04/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Accounts;
use Utsubot\{Module, ModuleException, IRCMessage, User};

abstract class ModuleWithAccounts extends Module {

    /**
     * Internal function to verify and return the Accounts class and object
     *
     * @return Accounts
     * @throws \Exception
     */
    protected function getAccounts(): Accounts {
        return $this->externalModule("Utsubot\\Accounts\\Accounts");
    }

    /**
     * Get settings regarding another command
     * Custom settings are optional, so exceptions here are caught and ignored
     *
     * @param string $nick User nickname
     * @param string $setting The name of the single setting as a string
     * @return string|bool The setting value or false if it isn't set
     */
    protected function getSetting($nick, $setting) {
        $accounts = $this->getAccounts();

        $users = $this->IRCBot->getUsers();
        if (($user = $users->search($nick)) && $user instanceof User) {
            $accountID = $accounts->getAccountIDByUser($user);

            if ($accountID !== false && ($settings = $accounts->getInterface()->getSettings($accountID, $setting)))
                return $settings[0]['value'];
        }

        return false;
    }

    /**
     * Used to restrict access of a command to a particular level or above
     *
     * @param IRCMessage $msg
     * @param int $level
     * @throws ModuleException Accounts not loaded or user does not have permission
     */
    protected function requireLevel(IRCMessage $msg, $level) {
        $accounts = $this->getAccounts();

        $users = $this->IRCBot->getUsers();
        $user = $users->createIfAbsent($msg->getNick() . "!" . $msg->getIdent() . "@" . $msg->getFullHost());
        $accounts->requireLevel($user, $level);
    }

    /**
     * Get the account ID of a user if they are logged in
     *
     * @param User $user
     * @return bool|int ID or false if not logged in
     * @throws ModuleException Accounts not loaded
     */
    protected function getAccountIDByUser(User $user) {
        $accounts = $this->getAccounts();

        return $accounts->getAccountIDByUser($user);
    }
}
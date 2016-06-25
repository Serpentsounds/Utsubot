<?php
/**
 * Utsubot - ModuleWithAccounts.php
 * Date: 04/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Accounts;
use Utsubot\{
    Module,
    ModuleException,
    IRCBot,
    IRCMessage,
    User
};


/**
 * Class ModuleWithAccountsException
 *
 * @package Utsubot\Accounts
 */
class ModuleWithAccountsException extends ModuleException {}

/**
 * Class ModuleWithAccounts
 *
 * @package Utsubot\Accounts
 */
abstract class ModuleWithAccounts extends Module {
    
    /**
     * Register an account settings field to this module and insert it into the database, if necessary
     *
     * @param Setting $setting
     * @throws AccountsDatabaseInterfaceException
     */
    protected function registerSetting(Setting $setting) {
        $this->getAccounts()->registerSetting($setting);
    }

    /**
     * This function will be called when a user attempts to set a setting on his or her account
     * If the parameters are deemed invalid, an exception should be thrown
     * 
     * @param Setting $setting
     * @param string  $value
     */
    public function validateSetting(Setting $setting, string $value) {}

    /**
     * Internal function to verify and return the Accounts class and object
     *
     * @return Accounts
     * @throws \Utsubot\ModuleException
     */
    protected function getAccounts(): Accounts {
        return $this->externalModule("Utsubot\\Accounts\\Accounts");
    }

    /**
     * Get a registered setting object by matching the name field
     *
     * @param string $name
     * @return Setting
     * @throws ModuleWithAccountsException
     */
    public function getSettingObject(string $name): Setting {
        return $this->getAccounts()->getSettingObject($name);
    }

    /**
     * Get settings regarding another command
     *
     * @param string $nick
     * @param Setting $setting
     * @return string
     * @throws AccountsDatabaseInterfaceException
     */
    protected function getSetting(string $nick, Setting $setting) {
        $accountID = $this->getAccountIDByNickname($nick);

        $settings = $this->getAccounts()->getInterface()->getUserSetting($accountID, $setting);
        return $settings[0]['value'];
        
    }

    /**
     * Used to restrict access of a command to a particular level or above
     *
     * @param IRCMessage $msg
     * @param int $level
     * @throws ModuleException Accounts not loaded or user does not have permission
     */
    protected function requireLevel(IRCMessage $msg, int $level) {
        $accounts = $this->getAccounts();

        $users = $this->IRCBot->getUsers();
        $user = $users->createIfAbsent($msg->getNick() . "!" . $msg->getIdent() . "@" . $msg->getFullHost());

        //	Confirm login and get level. May throw exception
        $userLevel = $accounts->getAccessByUser($user);

        if ($userLevel < $level)
            throw new ModuleWithAccountsException("You need level $level to access that command. Your current access level is $userLevel.");
    }

    /**
     * Get the account ID of a user if they are logged in
     *
     * @param User $user
     * @return int
     * @throws ModuleException Accounts not loaded
     * @throws AccountsException User is not logged in
     */
    protected function getAccountIDByUser(User $user): int {
        $accounts = $this->getAccounts();

        return $accounts->getAccountIDByUser($user);
    }

    /**
     * Fetch the account ID of a nickname
     *
     * @param string $nickname
     * @return int
     * @throws AccountsException
     * @throws \Utsubot\ManagerException
     */
    protected function getAccountIDByNickname(string $nickname): int {
        $users = $this->IRCBot->getUsers();
        $user = $users->search($nickname);
        return $this->getAccountIDByUser($user);
    }
}
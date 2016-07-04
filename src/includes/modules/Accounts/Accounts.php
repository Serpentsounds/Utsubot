<?php
/**
 * Utsubot - Account.php
 * User: Benjamin
 * Date: 28/04/2015
 */

namespace Utsubot\Accounts;

use Utsubot\Help\{
    HelpEntry,
    IHelp,
    THelp
};
use Utsubot\{
    ModuleException,
    IRCBot,
    Timers,
    Timer,
    Trigger,
    IRCMessage,
    User
};
use function Utsubot\bold;


/**
 * Class AccountsException
 *
 * @package Utsubot\Accounts
 */
class AccountsException extends ModuleException {

}

/**
 * Class Accounts
 *
 * @package Utsubot\Accounts
 */
class Accounts extends ModuleWithAccounts implements IHelp {

    use THelp;
    use Timers;

    private $interface;

    /** @var Setting[] $settings */
    private $settings;

    private $autoLoginCache   = [ ];
    private $loggedIn         = [ ];
    private $defaultNickCheck = [ ];


    /**
     * Accounts constructor.
     *
     * @param IRCBot $IRCBot
     */
    public function __construct(IRCBot $IRCBot) {
        parent::__construct($IRCBot);
        $this->interface = new AccountsDatabaseInterface();

        //  Account settings
        $this->registerSetting(new Setting($this, "nick", "Default Nickname", 1));
        $this->registerSetting(new Setting($this, "disablenotify", "Disable Auto Login Notification", 1));
        $this->registerSetting(new Setting($this, "autologin", "Auto Login Host", 5));

        //  Command triggers
        $triggers               = [ ];
        $triggers[ 'login' ]    = new Trigger("login", [ $this, "login" ]);
        $triggers[ 'logout' ]   = new Trigger("logout", [ $this, "logout" ]);
        $triggers[ 'register' ] = new Trigger("register", [ $this, "register" ]);
        $triggers[ 'set' ]      = new Trigger("set", [ $this, "set" ]);
        $triggers[ 'unset' ]    = new Trigger("unset", [ $this, "_unset" ]);
        $triggers[ 'settings' ] = new Trigger("settings", [ $this, "settings" ]);
        $triggers[ 'access' ]   = new Trigger("access", [ $this, "access" ]);

        foreach ($triggers as $trigger)
            $this->addTrigger($trigger);

        //  Help entries
        /** @var HelpEntry[] $help */
        $help     = [ ];
        $category = "Account";

        $help[ 'login' ] = new HelpEntry($category, $triggers[ 'login' ]);
        $help[ 'login' ]->addParameterTextPair("USERNAME PASSWORD", "Logs you into your account USERNAME using PASSWORD.");

        $help[ 'logout' ] = new HelpEntry($category, $triggers[ 'logout' ]);
        $help[ 'logout' ]->addParameterTextPair("", "Logs you out of your account.");

        $help[ 'register' ] = new HelpEntry($category, $triggers[ 'register' ]);
        $help[ 'register' ]->addParameterTextPair("USERNAME PASSWORD", "Register a new account with the bot.");

        $help[ 'set' ] = new HelpEntry($category, $triggers[ 'set' ]);
        $help[ 'set' ]->addParameterTextPair("OPTION [VALUE]", "Enable OPTION or set OPTION to VALUE on your account.");

        $help[ 'unset' ] = new HelpEntry($category, $triggers[ 'unset' ]);
        $help[ 'unset' ]->addParameterTextPair("OPTION [VALUE]", "Remove the OPTION setting from your account. If OPTION accepts multiple simultaneous values, you must specify which to remove using VALUE.");

        $help[ 'settings' ] = new HelpEntry($category, $triggers[ 'settings' ]);
        $help[ 'settings' ]->addParameterTextPair("OPTION", "View your current settings for OPTION.");
        $help[ 'settings' ]->addParameterTextPair("", "View all of your account settings.");

        $help[ 'access' ] = new HelpEntry($category, $triggers[ 'access' ]);
        $help[ 'access' ]->addParameterTextPair("add USER LEVEL", "Give USER's account access level LEVEL.");
        $help[ 'access' ]->addParameterTextPair("remove USER", "Remove USER's account's access (set to level 0).");
        $help[ 'access' ]->addParameterTextPair("list [USER]", "Show the access level for USER's account. If USER is omitted, show your own access level.");
        $help[ 'access' ]->addParameterTextPair("", "Shortcut to list your own access level.");
        $help[ 'access' ]->addNotes("All USER parameters must be nicknames, as account names are private.");
        $help[ 'access' ]->addNotes("The add and remove commands require access level 90.");
        $help[ 'access' ]->addNotes("You can only modify access on someone whose level is lower than yours, and can only give a lower access level than you have.");

        //  Add common properties for help entries
        foreach ($help as $key => $entry) {
            $entry->addNotes("This command can only be used in a private message.");
            $this->addHelp($entry);
        }

        //  Initialization
        //  Create a timer to add notes to the 'set' help entry after all modules have registered
        $this->addTimer(new Timer(
                            0,  //  No delay necessary, all modules will finish loading before the next Module time() tick occurs
                            [ $this, "updateSettingsHelp" ],
                            [ $help[ 'set' ] ]
                        ));

        $this->updateAutoLoginCache();
    }


    /**
     * Register a setting upon module initialization for use with the 'set' command
     *
     * @param Setting $setting
     * @throws AccountsDatabaseInterfaceException
     */
    public function registerSetting(Setting $setting) {
        $this->settings[] = $setting;
        $this->interface->registerSetting($setting);
    }


    /**
     * Cache auto login information from the database
     *
     * @throws AccountsException
     */
    private function updateAutoLoginCache() {
        $autoLogin = $this->interface->getEntriesForSetting($this->getSettingObject("autologin"));

        foreach ($autoLogin as $row)
            $this->autoLoginCache[ intval($row[ 'id' ]) ][] = $row[ 'value' ];
    }


    /**
     * Get a registered setting object by matching the name field
     *
     * @param string $name
     * @return Setting
     * @throws AccountsException
     */
    public function getSettingObject(string $name): Setting {
        foreach ($this->settings as $setting)
            if ($setting->getName() == $name)
                return $setting;

        throw new AccountsException("No setting has been registered with name '$name'.");
    }


    /**
     * Update notes in the 'set' command to show all settings. Called on a timer to allow all modules to load their
     * settings first
     *
     * @param HelpEntry $entry
     * @throws AccountsException
     */
    public function updateSettingsHelp(HelpEntry $entry) {

        //  Only the 'set' command needs the settings list
        if (!$entry->matches("set"))
            throw new AccountsException("Help entry does not correspond to the 'set' command.");

        $info = [ ];
        foreach ($this->settings as $setting) {
            $info[] = sprintf(
                "%s (%s)%s",
                $setting->getName(),
                $setting->getDisplay(),
                ($setting->getMaxEntries() > 1) ? " [Up to {$setting->getMaxEntries()} entries]" : ""
            );
        }

        $entry->addNotes("Available settings: ".implode(", ", $info));
    }


    /**
     * @param Setting $setting
     * @param string  $value
     * @throws AccountsException
     */
    public function validateSetting(Setting $setting, string $value) {
        switch ($setting->getName()) {
            case "disablenotify":
                if (strlen($value))
                    throw new AccountsException("The setting 'disablenotify' does not take any parameters.");
                break;
            case "autologin":
                if (!fnmatch("*!*@*", $value))
                    throw new AccountsException("Invalid hostname format. Please use nickname!ident@host (wildcards are OK).");
                break;
        }
    }


    /**
     * @return AccountsDatabaseInterface
     */
    public function getInterface() {
        return $this->interface;
    }


    /**
     * Fetch the User object currently logged in to an account
     *
     * @param int $accountID
     * @return User
     * @throws AccountsException
     */
    public function getUserByAccountID(int $accountID): User {
        $index = array_search($accountID, $this->loggedIn);
        if ($index === false)
            throw new AccountsException("Account '$accountID' is not currently logged in.");

        $users = $this->IRCBot->getUsers();

        //  Users sets id on User object to match the internal key
        return $users->get($index);
    }


    /**
     * React to raw messages by verifying NickServ logins for user settings
     *
     * @param IRCMessage $msg
     */
    public function raw(IRCMessage $msg) {
        switch ($msg->getRaw()) {

            //  Logged into NickServ, used when verifying default nickname
            case 307:
            case 330:
                $parameters = $msg->getParameters();
                //  Adjust to format for different raws
                $nick = ($msg->getRaw() == 307) ? $parameters[ 0 ] : $parameters[ 1 ];

                //  Make sure user is in the verification process
                if (isset($this->defaultNickCheck[ $nick ])) {
                    //  Remove user from list of pending verifications
                    $info = $this->defaultNickCheck[ $nick ];
                    unset($this->defaultNickCheck[ $nick ]);

                    //  Make sure that this response corresponds to the recent request, but allow 5 seconds for server latency
                    if (time() - $info[ 'time' ] <= 5) {
                        try {
                            $this->interface->setUserSetting($info[ 'accountID' ], $this->getSettingObject("nick"), $nick);
                            $this->IRCBot->message($nick, "Your default nickname has been saved as ".bold($nick).".");
                        }
                        catch (\Exception $e) {
                            $this->IRCBot->message($nick, "Unable to save your default nickname in the database. Is it already set?");
                        }

                    }
                }
                break;

            /*
             *  End of /WHOIS, report default nickname verification failure
             */
            case 318:
                $nick = $msg->getParameters()[ 0 ];
                //  Make sure user is in the verification process
                if (isset($this->defaultNickCheck[ $nick ])) {
                    unset($this->defaultNickCheck[ $nick ]);
                    $this->IRCBot->message($nick, "Unable to save your default nickname because you are not identified with NickServ.");
                }
                break;
        }
    }


    /**
     * Search for auto-login entries that can be applied to a User when one is created, and attempt to log them in
     *
     * @param User $user
     * @throws AccountsException
     */
    public function user(User $user) {

        try {
            //  Account ID to be logged in to
            $accountID = $this->getAutoLogin("{$user->getNick()}!{$user->getAddress()}");
            $this->loginUser($user, $accountID);
            $this->status("{$user->getNick()} has automatically logged in to account ID $accountID.");

            $this->interface->getUserSetting($accountID, $this->getSettingObject("disablenotify"));
        }

            //  No matching auto login entries, do nothing
        catch (AccountsException $e) {
        }

            //  User doesn't not have disablenotify set, given them a notification
        catch (AccountsDatabaseInterfaceException $e) {
            /** @var int $accountID */
            $level    = $this->interface->getAccessByID($accountID);
            $username = $this->interface->getUsernameByID($accountID);

            $this->IRCBot->message(
                $user->getNick(),
                "You have automatically been logged into your account '$username'. Your access level is $level. To disable these notifications, use the command 'set disablenotify'."
            );
        }
    }


    /**
     * Given a hostname, return an account which permits autologins on that host
     *
     * @param string $host The hostname to match
     * @return int Account id
     * @throws AccountsException If no entries exist
     */
    private function getAutoLogin(string $host) {
        $results = [ ];

        //  Test wildcard match vs every host
        foreach ($this->autoLoginCache as $id => $wildcardHosts) {
            foreach ($wildcardHosts as $wildcardHost) {
                if (fnmatch($wildcardHost, $host)) {
                    /*  Associate the account with the number of non-wildcard characters in a host. In the event that more than 1 host matches, the account with the highest value is logged in to
                        This is to help prevent a user from accidentally being logged into someone else's account, if that person has an ambiguous auto-login mask  */
                    $significantCharacters = strlen(str_replace([ "*", "?", "[", "]" ], "", $wildcardHost));

                    //  Only overwrite the same account's entry with a higher value
                    if (!isset($results[ $id ]) || $results[ $id ] < $significantCharacters)
                        $results[ $id ] = $significantCharacters;
                }
            }
        }

        if (!$results)
            throw new AccountsException("No auto-login entries found matching '$host'.");

        //  Place the highest value of significant characters at the front of the array
        arsort($results);

        return (int)(array_keys($results)[ 0 ]);
    }


    /**
     * Mark a User as being logged in to an account
     *
     * @param User $user
     * @param int  $accountID
     */
    private function loginUser(User $user, $accountID) {
        $this->loggedIn[ $user->getId() ] = $accountID;
    }


    /**
     * Update account settings
     *
     * @param IRCMessage $msg
     * @throws AccountsException
     */
    public function set(IRCMessage $msg) {
        $this->requireParameters($msg, 1, "Syntax: SET <option> [<value>]");

        $accountID = $this->getAccountIDByNickname($msg->getNick());

        $parameters  = $msg->getCommandParameters();
        $settingName = array_shift($parameters);

        switch ($settingName) {
            //  Setting default nickname
            case "nick":
                $this->setDefaultNick($accountID, $msg->getNick());
                break;

            //  Changing account password
            case "password":
                $this->requireParameters($msg, 3, "Syntax: SET PASSWORD <old password> <new password>");
                list($password, $newPassword) = $parameters;

                if (preg_match('/\s/', $newPassword))
                    throw new AccountsException("Invalid password format. Pass a string with no whitespace.");

                $username = $this->interface->getUsernameByID($accountID);
                $this->interface->verifyPassword($username, $password);
                $this->interface->setPassword($username, $newPassword);
                $this->respond($msg, "Your password has been saved.");
                break;

            default:
                $settingObject = $this->getSettingObject($settingName);
                $value         = implode(" ", $parameters);
                $settingObject->validate($value);

                $this->interface->setUserSetting($accountID, $settingObject, $value);
                $value = (strlen($value)) ? $value : "enabled";
                $this->respond($msg, "'{$settingObject->getDisplay()}' has been set to '$value'.");
                break;
        }
    }


    /**
     * Helper function to begin the default nickname verification upon registration or manual setting
     *
     * @param $accountID
     * @param $nick
     */
    public function setDefaultNick(int $accountID, string $nick) {
        $this->defaultNickCheck[ $nick ] = [ 'time' => time(), 'accountID' => $accountID ];
        $this->IRCBot->raw("WHOIS $nick");
    }


    /**
     * Remove account settings
     *
     * @param IRCMessage $msg
     * @throws AccountsException
     */
    public function _unset(IRCMessage $msg) {
        $this->requireParameters($msg, 1, "Syntax: UNSET <option> [<value>]");

        $accountID = $this->getAccountIDByNickname($msg->getNick());

        $parameters    = $msg->getCommandParameters();
        $option        = array_shift($parameters);
        $settingObject = $this->getSettingObject($option);
        $value         = implode(" ", $parameters);

        //  Exception thrown if settings are invalid or unsuccessful
        $this->interface->removeUserSetting($accountID, $settingObject, $value);

        $this->respond($msg, "'{$settingObject->getDisplay()}' settings have been removed.");
    }


    /**
     * View current account settings
     *
     * @param IRCMessage $msg
     * @throws AccountsException
     */
    public function settings(IRCMessage $msg) {
        $users = $this->IRCBot->getUsers();
        $user  = $users->createIfAbsent($msg->getNick()."!".$msg->getIdent()."@".$msg->getFullHost());

        //  Must be logged in
        $accountID = $this->getAccountIDByUser($user);

        $parameters = $msg->getCommandParameters();

        if (count($parameters)) {
            $settingName = strtolower(array_shift($parameters));

            //  Exception thrown if all settings are invalid
            $settingObject = $this->getSettingObject($settingName);
            $settings      = $this->interface->getUserSetting($accountID, $settingObject);
        }
        else
            $settings = $this->interface->getSettingsForUser($accountID);

        //  Construct reply
        $response = $responseString = [ ];

        //  List each setting under name of setting
        foreach ($settings as $setting) {
            $key                = "{$setting['name']} ({$setting['display']})";
            $response[ $key ][] = (strlen($setting[ 'value' ])) ? $setting[ 'value' ] : "enabled";
        }

        //  Convert name => settings[] entries into readable format
        foreach ($response as $setting => $values)
            $responseString[] = "$setting: ".implode(", ", $values);

        $this->respond($msg, implode("\n", $responseString));
    }


    /**
     * Fetch the account ID of a User
     *
     * @param User $user
     * @return int Account ID
     * @throws AccountsException
     */
    public function getAccountIDByUser(User $user): int {
        $id = $user->getId();
        if (!isset($this->loggedIn[ $id ]))
            throw new AccountsException("User '$user' is not logged in.");

        return $this->loggedIn[ $id ];
    }


    /**
     * Attempt to log user in with given credentials
     *
     * @param IRCMessage $msg
     * @throws AccountsException
     */
    public function login(IRCMessage $msg) {
        $this->requireParameters($msg, 2, "Syntax: LOGIN <username> <password>");

        $users      = $this->IRCBot->getUsers();
        $user       = $users->createIfAbsent($msg->getNick()."!".$msg->getIdent()."@".$msg->getFullHost());
        $parameters = $msg->getCommandParameters();

        list($username, $password) = $parameters;

        //  Attempt login, exception thrown if unsuccessful
        $this->interface->verifyPassword($username, $password);
        $accountID = $this->interface->getAccountIDByUsername($username);
        $this->loginUser($user, $accountID);

        $level = $this->interface->getAccessByID($accountID);
        $this->respond($msg, "Login successful. Your access level is $level.");
    }


    /**
     * Log out of current account
     *
     * @param IRCMessage $msg
     * @throws AccountsException
     */
    public function logout(IRCMessage $msg) {
        $users = $this->IRCBot->getUsers();
        $user  = $users->createIfAbsent($msg->getNick()."!".$msg->getIdent()."@".$msg->getFullHost());

        $this->logoutUser($user);
        $this->respond($msg, "You have logged out of your account.");
    }


    /**
     * Logs a User out of their account
     *
     * @param User $user
     * @throws AccountsException
     */
    public function logoutUser(User $user) {
        $id = $user->getId();
        if (!isset($this->loggedIn[ $id ]))
            throw new AccountsException("User '$user' is not logged in to an account.");

        unset($this->loggedIn[ $id ]);
    }


    /**
     * Register a new account
     *
     * @param IRCMessage $msg
     * @throws AccountsException
     */
    public function register(IRCMessage $msg) {
        $users      = $this->IRCBot->getUsers();
        $user       = $users->createIfAbsent($msg->getNick()."!".$msg->getIdent()."@".$msg->getFullHost());
        $parameters = $msg->getCommandParameters();

        //  Cannot be logged in
        try {
            $this->getAccountIDByUser($user);
            throw new AccountsException("You are already registered!", 1);
        }
        catch (AccountsException $e) {
            if ($e->getCode() == 1)
                throw $e;
        }

        //  Not enough parameters
        if (count($parameters) < 2)
            throw new AccountsException("Syntax: REGISTER <username> <password>");
        list($username, $password) = $parameters;

        //  Validate credentials
        if (!is_string($username) || preg_match('/\s/', $username))
            throw new AccountsException("Invalid username format. Pass a string with no whitespace.");
        if (!is_string($password) || preg_match('/\s/', $password))
            throw new AccountsException("Invalid password format. Pass a string with no whitespace.");

        //  Attempt registration, will throw exception on existing username
        $this->interface->registerUser($username, $password);

        //  Success
        $this->respond($msg, "Registration successful. Please remember your username and password for later use: '$username', '$password'. You will now be automatically logged in.");

        //  Add autologin host
        $autoLogin = "*!*{$msg->getIdent()}@{$msg->getFullHost()}";
        $accountID = $this->interface->getAccountIDByUsername($username);
        $this->interface->setUserSetting($accountID, $this->getSettingObject("autologin"), $autoLogin);
        //  Automatically login upon registration
        $this->loginUser($user, $accountID);
        $this->respond($msg, "$autoLogin has been added as an autologin host for this account. You will automatically be logged in when connecting from this host. To remove this, please use 'unset autologin'.");

        //  Attempt to automatically set nickname, if it's not already set
        $settings = $this->interface->getUsersWithSetting($this->getSettingObject("nick"), $msg->getNick());
        if ($settings)
            $this->respond($msg, "Your nickname could not be linked to your account because it is already linked to another account.");
        else
            $this->setDefaultNick($accountID, $msg->getNick());
    }


    /**
     * Manage or view account access
     *
     * @param IRCMessage $msg
     * @throws AccountsException
     */
    public function access(IRCMessage $msg) {
        $users      = $this->IRCBot->getUsers();
        $user       = $users->createIfAbsent($msg->getNick()."!".$msg->getIdent()."@".$msg->getFullHost());
        $parameters = $msg->getCommandParameters();
        $userLevel  = $this->getAccessByUser($user);

        //  No parameters, return user's access level
        if (empty($parameters))
            $this->respond($msg, "Your current level is $userLevel.");

        else {

            $mode = strtolower(array_shift($parameters));
            switch ($mode) {

                //  Add or update access for a user
                case "add":
                    $this->requireLevel($msg, 90);

                    //  Require a 3rd parameter as level for add
                    $this->requireParameters($msg, 3, "Syntax: ACCESS ADD <user> <value>");
                    list($nickname, $level) = $parameters;

                    //  Users can only grant access below their level
                    if ($level >= $userLevel)
                        throw new AccountsException("You do not have permission to grant level $level.");

                    //  Make sure target user is online. Access can only be modified through nickname, not account name.
                    $targetUser = $users->findFirst($nickname);
                    $accountID  = $this->getAccountIDByUser($targetUser);

                    //  Prevent modifying somebody with higher access than you
                    $targetUserLevel = $this->interface->getAccessByID($accountID);
                    if ($targetUserLevel >= $userLevel)
                        throw new AccountsException("You do not have permission to modify settings for user '{$targetUser->getNick()}'.");

                    $intLevel = intval($level);
                    if ($intLevel != $level)
                        throw new AccountsException("Level must be an integer.");

                    //  Attempt to set level. A malformed $level will thrown an exception
                    $this->interface->setAccess($accountID, $intLevel);

                    $this->respond($msg, "Access has been updated for '{$targetUser->getNick()}'.");
                    break;

                //  Remove access for a user (set to level 0)
                case "remove":
                    $this->requireLevel($msg, 90);

                    $this->requireParameters($msg, 2, "Syntax: ACCESS REMOVE <user>");
                    $nickname = array_shift($parameters);

                    //  Make sure target user is online. Access can only be modified through nickname, not account name.
                    $targetUser = $users->findFirst($nickname);
                    $accountID  = $this->getAccountIDByUser($targetUser);

                    //  Prevent modifying somebody with higher access than you
                    $targetUserLevel = $this->interface->getAccessByID($accountID);
                    if ($targetUserLevel >= $userLevel)
                        throw new AccountsException("You do not have permission to modify settings for user '{$targetUser->getNick()}'.");

                    //  Attempt to set level
                    $this->interface->setAccess($accountID, 0);

                    $this->respond($msg, "Access has been updated for '{$targetUser->getNick()}'.");
                    break;

                // Get the access of an online user
                case "list":
                    //  Not enough parameters, default to user
                    if ($parameters)
                        $nickname = array_shift($parameters);
                    else
                        $nickname = $msg->getNick();

                    $targetUser      = $users->findFirst($nickname);
                    $targetUserLevel = $this->getAccessByUser($targetUser);

                    $this->respond($msg, "Access level for '{$targetUser->getNick()}' is $targetUserLevel.");
                    break;

                //  Invalid parameters
                default:
                    throw new AccountsException("Syntax: ACCESS [ADD|REMOVE|LIST] [<user>] [<value>]");
                    break;
            }

        }
    }


    /**
     * Get the access level for a User object. Default 0, unregistered has a level of -1
     *
     * @param User $user
     * @return int -1 if account ID doesn't exist, or their access level otherwise
     */
    public function getAccessByUser(User $user) {
        $accountID = $this->getAccountIDByUser($user);
        if ($accountID === false)
            return -1;

        return $this->interface->getAccessByID($accountID);
    }


    /**
     * Given an IRCMessage and command triggers, call the necessary methods and process errors
     *
     * @param IRCMessage $msg
     */
    protected function parseTriggers(IRCMessage $msg) {
        //  Account modification should only be done in private message
        if (!$msg->inQuery())
            return;

        parent::parseTriggers($msg);
    }

}
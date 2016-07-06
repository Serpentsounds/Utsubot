<?php
/**
 * Utsubot - AccountsDatabaseInterface.php
 * User: Benjamin
 * Date: 01/05/2015
 */

namespace Utsubot\Accounts;

use Utsubot\{
    DatabaseInterface, DatabaseInterfaceException, SQLiteDatbaseCredentials
};


/**
 * Class AccountsDatabaseInterfaceException
 *
 * @package Utsubot\Accounts
 */
class AccountsDatabaseInterfaceException extends DatabaseInterfaceException {

}

/**
 * Class AccountsDatabaseInterface
 *
 * @package Utsubot\Accounts
 */
class AccountsDatabaseInterface extends DatabaseInterface {

    /**
     * AccountsDatabaseInterface constructor.
     */
    public function __construct() {
        parent::__construct(SQLiteDatbaseCredentials::createFromConfig("utsubot"));

        $this->createTables();
        $this->checkForAdmin();
    }


    /**
     * Create tables for first run
     */
    private function createTables() {

        //  Create users table
        try {
            $this->query(
                'CREATE TABLE "users"
                (
                  "id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                  "username" TEXT UNIQUE NOT NULL,
                  "password" TEXT NOT NULL,
                  "level" INTEGER DEFAULT 0 NOT NULL
                )'
            );

            echo "Accounts 'users' table was successfully created.\n\n";
        }
            //  Table already exists, do nothing
        catch (\PDOException $e) {
        }

        //  Create settings table
        try {
            $this->query(
                'CREATE TABLE "account_settings"
                (
                  "id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                  "name" TEXT UNIQUE NOT NULL,
                  "display" TEXT NOT NULL,
                  "max_entries" INTEGER DEFAULT 0 NOT NULL
                )'
            );
            echo "Accounts 'account_settings' table was successfully created.\n\n";
        }
            //  Table already exists, do nothing
        catch (\PDOException $e) {
        }

        //  Create users<->settings relation table
        try {
            $this->query(
                'CREATE TABLE "users_account_settings"
                (
                  "user_id" INTEGER NOT NULL,
                  "account_setting_id" INTEGER NOT NULL,
                  "value" TEXT,
                  FOREIGN KEY ("user_id") REFERENCES "users" ("id"),
                  FOREIGN KEY ("account_setting_id") REFERENCES "account_settings" ("id")
                )'
            );
            echo "Accounts 'users_account_settings' table was successfully created.\n\n";
        }
            //  Table already exists, do nothing
        catch (\PDOException $e) {
        }

    }


    /**
     * Verify that an administrator user exists, or prompt to create one
     */
    private function checkForAdmin() {
        $admins = $this->query(
            'SELECT *
            FROM "users"
            WHERE "level"=100'
        );

        //  No users with level 100, likely first run
        if (!count($admins)) {
            echo "There are no administrator users in the Accounts database. Please create one now.\n";

            //  Prompt for username
            do {
                echo "Username: ";
            } while (!strlen($username = trim(fgets(STDIN, 64))));

            //  Prompt for password
            do {
                echo "Password: ";
            } while (!strlen($password = trim(fgets(STDIN, 64))));

            //  Create administrator
            try {
                $this->query(
                    'INSERT INTO "users" ("username", "password", "level")
                    VALUES (?, ?, ?)',
                    [ $username, md5($password), 100 ]
                );
                echo "Your account has successfully been registered as the administrator.\n\n";
            }
                //  Kill the program if we cannot create an administrator user.
            catch (\PDOException $e) {
                echo "Unable to create administrator user. The program will now exit.\n";
                exit;
            }
        }

    }


    /**
     * Register a new user
     *
     * @param string $username
     * @param string $password
     * @throws AccountsDatabaseInterfaceException
     */
    public function registerUser(string $username, string $password) {
        $rowCount = $this->query(
            'INSERT INTO "users" ("username", "password")
            VALUES (?, ?)',
            [ $username, md5($password) ]
        );

        if (!$rowCount)
            throw new AccountsDatabaseInterfaceException("Username '$username' already exists!");
    }


    /**
     * Changing a user's password
     *
     * @param string $username
     * @param string $newPassword
     * @throws AccountsDatabaseInterfaceException
     */
    public function setPassword(string $username, string $newPassword) {
        $rowCount = $this->query(
            'UPDATE "users"
            SET "password"=?
            WHERE "username"=?',
            [ md5($newPassword), $username ]
        );

        if (!$rowCount)
            throw new AccountsDatabaseInterfaceException("Password unchanged.");
    }


    /**
     * Verify a user/password combination, to perform password protection actions like logging in or changing your
     * password
     *
     * @param string $username
     * @param string $password
     * @throws DatabaseInterfaceException
     * @throws AccountsDatabaseInterfaceException If username doesn't exist, or password is invalid
     */
    public function verifyPassword(string $username, string $password) {
        $results = $this->query(
            'SELECT "password"
            FROM "users"
            WHERE "username"=?'
            [ $username ]
        );

        //  No row in database
        if (!$results)
            throw new AccountsDatabaseInterfaceException("Invalid username.");

        //  Password hashes don't match
        elseif ($results[ 0 ][ 'password' ] != md5($password))
            throw new AccountsDatabaseInterfaceException("Invalid password.");

    }


    /**
     * Change the bot access level for a username
     *
     * @param int $accountID
     * @param int $level Maximum of 99
     * @throws DatabaseInterfaceException
     * @throws AccountsDatabaseInterfaceException If $level isn't an integer or is above 99
     */
    public function setAccess(int $accountID, int $level) {
        //  Make sure level is an int, and don't give anyone else root access
        if ($level > 99)
            throw new AccountsDatabaseInterfaceException("Level must be an integer below 99.");

        $rowCount = $this->query(
            'UPDATE "users"
            SET "level"=?
            WHERE "id"=?',
            [ $level, $accountID ]
        );

        if (!$rowCount)
            throw new AccountsDatabaseInterfaceException("Access level unchanged.");
    }


    /**
     * Register a setting by creating it in the database, if necessary
     *
     * @param Setting $setting
     * @throws AccountsDatabaseInterfaceException
     */
    public function registerSetting(Setting $setting) {
        try {
            $this->query(
                'INSERT INTO "account_settings" ("name", "display", "max_entries")
                VALUES (?, ?, ?)',
                [
                    $setting->getName(),
                    $setting->getDisplay(),
                    $setting->getMaxEntries()
                ]
            );
            echo "Registered new settings field '{$setting->getName()}' (display: {$setting->getDisplay()}, maximum entries: {$setting->getMaxEntries()}).\n\n";
        }

            //  UNIQUE constraint triggered
        catch (\PDOException $e) {
            #echo "Settings field '{$setting->getName()}' already exists.\n\n";
        }

    }


    /**
     * Add a settings entry for a user
     *
     * @param int    $accountID
     * @param int    $settingID
     * @param string $value
     * @throws DatabaseInterfaceException
     * @throws AccountsDatabaseInterfaceException If the add failed (database error), or if an ID can't be retrieved
     */
    private function addUserSetting(int $accountID, int $settingID, string $value) {
        $rowCount = $this->query(
            'INSERT INTO "users_account_settings" ("user_id", "account_setting_id", "value")
            VALUES (?, ?, ?)',
            [ $accountID, $settingID, $value ]
        );

        //  Database error
        if (!$rowCount)
            throw new AccountsDatabaseInterfaceException("Failed to enter setting into database.");
    }


    /**
     * Change a user's settings. Absent settings will be added, and settings with only 1 entry will be overwritten.
     * Settings that allow an arbitrary number of entries will have one added. Settings that allow n entries will have
     * one added, but only if there is space.
     *
     * @param int     $accountID
     * @param Setting $setting
     * @param string  $value
     * @throws DatabaseInterfaceException
     * @throws AccountsDatabaseInterfaceException If the add fails (database error), or if the add fails because the
     *                                            maximum # of entries are already present
     */
    public function setUserSetting(int $accountID, Setting $setting, string $value) {
        $maxEntries = $setting->getMaxEntries();
        $settingID  = $this->getSettingID($setting);

        //  Max value of 0 means an indefinite number of entries are allowed, add a new one
        if ($maxEntries === 0)
            $this->addUserSetting($accountID, $settingID, $value);

        elseif ($maxEntries > 0) {

            //  If entries for $settings exist for this user
            try {
                $results = $this->getUserSetting($accountID, $setting);

                //  Not a single-entry setting, but a cap exists
                if ($maxEntries > 1) {

                    //  This user is already at or over the limit
                    if ($maxEntries <= count($results))
                        throw new AccountsDatabaseInterfaceException("Cannot add more entries for '$setting'.");

                    //  There is space, add a new one
                    else
                        $this->addUserSetting($accountID, $settingID, $value);
                }

                //  Only one entry permitted, overwrite it
                else {

                    $rowCount = $this->query(
                        'UPDATE "users_account_settings"
                        SET "value"=?
                        WHERE "user_id"=?
                        AND "account_setting_id"=?',
                        [ $value, $accountID, $settingID ]
                    );

                    //  No change in database, value was most likely the same as the record
                    if (!$rowCount)
                        throw new AccountsDatabaseInterfaceException("Setting '$setting' unchanged.");
                }
            }

                //  No existing entries, add a new one
            catch (AccountsDatabaseInterfaceException $e) {
                $this->addUserSetting($accountID, $settingID, $value);
            }
        }
    }


    /**
     * Delete a settings entry for a user
     *
     * @param int     $accountID
     * @param Setting $setting
     * @param string  $value Pass to delete only entries matching a certain value. If absent, all values will be
     *                       deleted.
     * @return int    Number of entries deleted
     * @throws AccountsDatabaseInterfaceException No entries found
     */
    public function removeUserSetting(int $accountID, Setting $setting, string $value = ""): int {

        $settingsID = $this->getSettingID($setting);

        //  Default to deleting all entries
        $constraint = "";
        $parameters = [ $accountID, $settingsID ];

        //  Looking to delete a specific value, update query and parameters
        if (strlen($value)) {
            $constraint   = ' AND "value"=?';
            $parameters[] = $value;
        }

        $query =
            'DELETE FROM "users_account_settings"
            WHERE "user_id"=?
            AND "account_setting_id"=?';
        
        $rowCount = $this->query(
            $query.$constraint,
            $parameters
        );

        if (!$rowCount)
            throw new AccountsDatabaseInterfaceException("Unable to remove entry for '$setting'.");

        return $rowCount;
    }


    /**
     * Retrieve account settings for this user
     *
     * @param int     $accountID
     * @param Setting $setting
     * @return array
     * @throws AccountsDatabaseInterfaceException Invalid setting name or no results
     */
    public function getUserSetting(int $accountID, Setting $setting): array {
        $settingID = $this->getSettingID($setting);

        $results = $this->query(
            'SELECT "uas"."value", "as"."name", "as"."display"
            FROM "users_account_settings" "uas"
            INNER JOIN "account_settings" "as"
            ON "as"."id"="uas"."account_setting_id"
            WHERE "uas"."user_id"=?
            AND "as"."id"=?',
            [ $accountID, $settingID ]
        );

        if (!$results)
            throw new AccountsDatabaseInterfaceException("There are no settings saved for '{$setting->getDisplay()}'.");

        return $results;
    }


    /**
     * Get entries for all Settings for a given user
     *
     * @param int $accountID
     * @return array
     */
    public function getSettingsForUser(int $accountID) {
        return $this->query(
            'SELECT "uas"."value", "as"."name", "as"."display"
            FROM "users" "u"
            INNER JOIN "users_account_settings" "uas"
            ON "u"."id"="uas"."user_id"
            INNER JOIN "account_settings" "as"
            ON "as"."id"="uas"."account_settings_id"
            WHERE "u"."id"=?',
            [ $accountID ]
        );
    }


    /**
     * Get entries from all users for a given Setting
     *
     * @param Setting $setting
     * @return array
     */
    public function getEntriesForSetting(Setting $setting): array {
        return $this->query(
            'SELECT "uas"."value", "u"."id"
            FROM "users" "u"
            INNER JOIN "users_account_settings" "uas"
            ON "u"."id"="uas"."user_id"
            INNER JOIN "account_settings" "as"
            ON "as"."id"="uas"."account_setting_id"
            WHERE "as"."name"=?',
            [ $setting->getName() ]
        );
    }


    /**
     * Return a list of users who have the given setting set to the given value
     *
     * @param $setting
     * @param $value
     * @return array
     * @throws AccountsDatabaseInterfaceException If settings fail validation
     */
    public function getUsersWithSetting(Setting $setting, string $value): array {
        return $this->query(
            'SELECT "uas"."user_id"
            FROM "users_account_settings" "uas"
            INNER JOIN "account_settings" "as"
            ON "uas"."account_setting_id"="as"."id"
            WHERE "uas"."value"=?
            AND "as"."name"=?
            ORDER BY "uas"."user_id" ASC',
            [ $value, $setting->getName() ]
        );
    }


    /**
     * Return a list of user and setting pairs where the setting value matches the given wildcard string
     *
     * @param Setting $setting
     * @param string  $pattern
     * @return array
     */
    public function getUsersWithWildcardSetting(Setting $setting, string $pattern): array {
        return $this->query(
            'SELECT "uas"."user_id", "uas"."value"
            FROM "users_account_settings" "uas"
            INNER JOIN "account_settings" "as"
            ON "uas"."account_setting_id"="as"."id"
            WHERE "uas"."value" LIKE ? AND "as"."name"=?
            ORDER BY "uas"."user_id" ASC',
            [ $pattern, $setting->getName() ]
        );
    }


    /**
     * Helper function to get an ID number for an account setting
     *
     * @param $setting
     * @return int ID, or false if it doesn't exist
     * @throws DatabaseInterfaceException If query fails
     */
    private function getSettingID(Setting $setting): int {
        $results = $this->query(
            'SELECT "id"
            FROM "account_settings"
            WHERE "name"=?',
            [ $setting->getName() ]
        );

        if (!$results)
            throw new AccountsDatabaseInterfaceException("Setting '{$setting->getName()} was not found in the database.");

        return intval($results[ 0 ][ 'id' ]);
    }


    /**
     * Get the bot access level for an account ID
     *
     * @param int $accountID
     * @return int -1 if account ID doesn't exist, or their access level otherwise
     */
    public function getAccessByID(int $accountID): int {
        $results = $this->query(
            'SELECT "level"
            FROM "users"
            WHERE "id"=?',
            [ $accountID ]
        );

        if ($results)
            return intval($results[ 0 ][ 'level' ]);

        //  Unregisterd users have a level of -1
        return -1;
    }


    /**
     * Get the username an account logs in with, given account ID
     *
     * @param int $accountID
     * @return string
     * @throws DatabaseInterfaceException
     * @throws AccountsDatabaseInterfaceException If account doesn't exist
     */
    public function getUsernameByID(int $accountID): string {
        $results = $this->query(
            'SELECT "user"
            FROM "users"
            WHERE "id"=?
            LIMIT 1',
            [ $accountID ]
        );

        if (!$results)
            throw new AccountsDatabaseInterfaceException("Unable to find account with ID '$accountID'.");

        return $results[ 0 ][ 'user' ];
    }


    /**
     * Get an username's account ID in the database
     *
     * @param string $username
     * @return int
     * @throws AccountsDatabaseInterfaceException
     */
    public function getAccountIDByUsername(string $username): int {
        $results = $this->query(
            'SELECT "id"
            FROM "users"
            WHERE "user"=?',
            [ $username ]
        );

        if (!$results)
            throw new AccountsDatabaseInterfaceException("Unable to find account for username '$username'.");

        return intval($results[ 0 ][ 'id' ]);
    }

}
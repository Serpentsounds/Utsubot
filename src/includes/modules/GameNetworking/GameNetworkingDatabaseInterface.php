<?php
/**
 * Utsubot - GameNetworkingDatabaseInterface.php
 * User: Benjamin
 * Date: 18/04/2015
 */

namespace Utsubot\GameNetworking;


use Utsubot\{
    HybridDatabaseInterface, DatabaseInterfaceException, ModuleException
};


/**
 * Class GameNetworkingDatabaseInterface
 *
 * @package Utsubot\GameNetworking
 */
class GameNetworkingDatabaseInterface extends HybridDatabaseInterface {

    protected static $table = "users_codes";

    protected static $userIDColumn   = "user_id";
    protected static $nicknameColumn = "nickname";

    protected static $itemIDColumn    = "code_id";
    protected static $itemValueColumn = "value";


    /**
     * Insert a networking code into the database
     *
     * @param string $nickname
     * @param int    $item     ID of code
     * @param string $value    Code itself
     * @return int|bool Number of affected rows, or false on failure
     * @throws DatabaseInterfaceException If a PDO error is encountered
     * @throws ModuleException If there is an error configuring the hybrid analysis, or if the nickname link failsafe
     *                         is triggered
     */
    public function insert($nickname, $item, $value) {
        $hybridAnalysis = $this->analyze($nickname);
        list($column, $user) = $this->parseMode($hybridAnalysis);

        //	Don't allow adding in nickname mode for linked nicknames (the user forgot to log in) to prevent stray database records
        $this->checkNicknameLink($hybridAnalysis);

        return $this->query(
            sprintf("INSERT INTO `%s` (`$column`, `%s`, `%s`) VALUES (?, ?, ?)", self::$table, self::$itemIDColumn, self::$itemValueColumn),
            [ $user, $item, $value ]
        );
    }


    /**
     * Delete a networking code or codes from the database
     *
     * @param string $nickname
     * @param int    $item     ID of code
     * @param string $value    Code itself, or null to remove all codes of given ID
     * @return int|bool Number of affected rows, or false on failure
     * @throws DatabaseInterfaceException If a PDO error is encountered
     * @throws ModuleException If there is an error configuring the hybrid analysis, or if the nickname link failsafe
     *                         is triggered
     */
    public function delete($nickname, $item, $value = null) {
        $hybridAnalysis = $this->analyze($nickname);
        list($column, $user) = $this->parseMode($hybridAnalysis);

        //	Don't allow deleting in nickname mode for linked nicknames, for security reasons
        $this->checkNicknameLink($hybridAnalysis);

        //	Delete all codes of a single type
        if ($value === null)
            return $this->query(
                sprintf("DELETE FROM `%s` WHERE `$column`=? AND `%s`=?", self::$table, self::$itemIDColumn),
                [ $user, $item ]
            );

        //	Delete a specific code of a single type
        else
            return $this->query(
                sprintf("DELETE FROM `%s` WHERE `$column`=? AND `%s`=? AND `%s`=? LIMIT 1", self::$table, self::$itemIDColumn, self::$itemValueColumn),
                [ $user, $item, $value ]
            );
    }


    /**
     * Retrieve a networking code from the database
     *
     * @param string $nickname
     * @param int    $item        ID of code, or null to retrieve all codes for given nickname
     * @param string $value       Code itself, or null to retrieve all codes of given ID
     * @param string $callingNick Nickname of user who is performing the search (needed to enforce private permissions)
     * @return array|bool Result set, or false on failure
     * @throws DatabaseInterfaceException If a PDO error is encountered
     * @throws ModuleException If there is an error configuring the hybrid analysis
     */
    public function select($nickname, $item = null, $value = null, $callingNick = "") {
        $hybridAnalysis = $this->analyze($nickname);
        //	Insecure mode, allow account mode retrieval through linked nickname even if not logged in
        list($column, $user) = $this->parseMode($hybridAnalysis, false);

        $x = 0;
        do {
            //	Select all codes of all types
            if ($item === null)
                $codes = $this->query(
                    sprintf("SELECT * FROM `%s` WHERE `$column`=?", self::$table),
                    [ $user ]
                );

            //	Select all codes of a single type
            elseif ($value === null)
                $codes = $this->query(
                    sprintf("SELECT * FROM `%s` WHERE `$column`=? AND `%s`=?", self::$table, self::$itemIDColumn),
                    [ $user, $item ]
                );

            //	Select a specific code of a single type
            else
                $codes = $this->query(
                    sprintf("SELECT * FROM `%s` WHERE `$column`=? AND `%s`=? AND `%s`=? LIMIT 1", self::$table, self::$itemIDColumn, self::$itemValueColumn),
                    [ $user, $item, $value ]
                );

            //	Retry in secure mode to prevent account linking, if user has an account but has codes still in nickname mode
            list($column, $user) = $this->parseMode($hybridAnalysis, true);
            $x++;
        } while (!$codes && $x < 2);

        //	Begin determining if the code entries belong to the calling nickname, to check public/private permissions against
        $sameUser = false;
        if ($nickname == $callingNick)
            $sameUser = true;

        $callingUserHybridAnalysis = $this->analyze($callingNick);

        //	Calling user is logged into the account of the target codes
        if ($callingUserHybridAnalysis->getMode() == "account" && ($callingUserHybridAnalysis->getAccountID() == $hybridAnalysis->getAccountID() ||
                                                                   $callingUserHybridAnalysis->getAccountID() == $hybridAnalysis->getLinkedAccountID())
        )
            $sameUser = true;
        //	Calling user is linked to the account of the target codes
        elseif ($callingUserHybridAnalysis->getMode() == "nickname" && is_int($callingUserHybridAnalysis->getLinkedAccountID()) &&
                $callingUserHybridAnalysis->getLinkedAccountID() == $hybridAnalysis->getAccountID()
        )
            $sameUser = true;

        //	If calling user does not own the codes, block locked codes from being displayed
        foreach ($codes as &$code) {
            if ($code[ 'locked' ] && !$sameUser)
                $code[ 'value' ] = "(Private)";
        }

        return $codes;

    }


    /**
     * Update a code entry with various setting
     *
     * @param string     $nickname
     * @param int        $item       Code ID
     * @param string     $value      Code itself
     * @param string     $field      Name of settings column
     * @param int|string $fieldValue New value
     * @return int|bool Number of affected rows, or false on failure
     * @throws DatabaseInterfaceException If an invalid setting is specified, or a PDO error is encountered
     * @throws ModuleException If there is an error configuring the hybrid analysis
     */
    public function update($nickname, $item, $value, $field, $fieldValue) {
        if (!in_array($field, [ "notes", "locked" ]))
            throw new DatabaseInterfaceException("Invalid field '$field' for updating GameNetworking entry.");

        $hybridAnalysis = $this->analyze($nickname);

        list($column, $user) = $this->parseMode($hybridAnalysis);

        //	Don't allow updating in nickname mode for linked nicknames, for security reasons
        $this->checkNicknameLink($hybridAnalysis);

        //	Clear value
        if ($fieldValue === null) {
            return $this->query(
                sprintf("UPDATE `%s` SET `$field`=NULL WHERE `$column`=? AND `%s`=? AND `%s`=?", self::$table, self::$itemIDColumn, self::$itemValueColumn),
                [ $user, $item, $value ]
            );
        }
        //	Set value
        else
            return $this->query(
                sprintf("UPDATE `%s` SET `$field`=? WHERE `$column`=? AND `%s`=? AND `%s`=?", self::$table, self::$itemIDColumn, self::$itemValueColumn),
                [ $fieldValue, $user, $item, $value ]
            );
    }


    /**
     * Search for the owner of a code entry
     *
     * @param int    $item  Code ID
     * @param string $value Code itself
     * @return array|bool Result set, or false on failure
     * @throws DatabaseInterfaceException If a PDO error is encountered
     */
    public function selectValue($item, $value) {
        return $this->query(
            sprintf("SELECT * FROM `%s` WHERE `%s`=? AND `%s`=? LIMIT 1", self::$table, self::$itemIDColumn, self::$itemValueColumn),
            [ $item, $value ]
        );
    }

}
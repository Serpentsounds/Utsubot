<?php
/**
 * Utsubot - PermissionDatabaseInterface.php
 * Date: 06/07/2016
 */

declare(strict_types = 1);

namespace Utsubot\Permission;


use Utsubot\{
    DatabaseInterface,
    DatabaseInterfaceException,
    SQLiteDatbaseCredentials
};


/**
 * Class PermissionDatabaseInterfaceException
 *
 * @package Utsubot\Permission
 */
class PermissionDatabaseInterfaceException extends DatabaseInterfaceException {

}


/**
 * Class PermissionDatabaseInterface
 *
 * @package Utsubot\Permission
 */
class PermissionDatabaseInterface extends DatabaseInterface {

    /**
     * PermissionDatabaseInterface constructor.
     */
    public function __construct() {
        parent::__construct(SQLiteDatbaseCredentials::createFromConfig("utsulite"));
    }


    /**
     * @param array $parameters
     * @param array $values
     * @return int
     * @throws PermissionDatabaseInterfaceException
     */
    public function addPermission(array $parameters, array $values): int {
        //  Replace null values with "null" to put into database
        array_walk($values, function (&$element) {
            if ($element === null)
                $element = "null";
        });
        $value = implode(", ", $values);

        try {
            $rowCount = $this->query(
                'INSERT INTO "command_permission" ("trigger", "type", "channel", "user_id", "nickname", "address", "parameters")
                 VALUES ('.$value.')',
                $parameters
            );
        }

            //  Duplicate permission
        catch (\PDOException $e) {
            throw new PermissionDatabaseInterfaceException("Permission already exists.");
        }

        return $rowCount;
    }


    /**
     * @param array $parameters
     * @param array $values
     * @return int
     * @throws PermissionDatabaseInterfaceException
     */
    public function removePermission(array $parameters, array $values): int {
        //  Form conditionals for each column
        $columns = [ '"trigger"', '"type"', '"channel"', '"user_id"', '"nickname"', '"address"', '"parameters"' ];
        array_walk($values, function (&$element, $key) use ($columns) {
            if ($element === null)
                $element = $columns[ $key ]." IS NULL";
            else
                $element = $columns[ $key ]."=?";
        });
        $value = implode(" AND ", $values);

        $rowCount = $this->query(
            'DELETE FROM "command_permission"
            WHERE '.$value,
            $parameters
        );

        //  No database matches
        if (!$rowCount)
            throw new PermissionDatabaseInterfaceException("Permission does not exist.");

        return $rowCount;
    }


    /**
     * @param string $trigger
     * @return array
     */
    public function getPermissionsByTrigger(string $trigger): array {
        return $this->query(
            'SELECT * FROM "command_permission"
            WHERE "trigger"=?',
            [ $trigger ]
        );
    }
}
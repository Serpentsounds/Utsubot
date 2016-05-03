<?php
/**
 * Utsubot - APIKeysDatabaseInterface.php
 * Date: 01/05/2016
 */

declare(strict_types = 1);

namespace Utsubot\Web;
use Utsubot\{
    DatabaseInterface,
    DatabaseInterfaceException,
    MySQLDatabaseCredentials
};


/**
 * Class APIKeysDatabaseInterfaceException
 *
 * @package Utsubot\Web
 */
class APIKeysDatabaseInterfaceException extends DatabaseInterfaceException {}

/**
 * Class APIKeysDatabaseInterface
 *
 * @package Utsubot\Web
 */
class APIKeysDatabaseInterface extends DatabaseInterface {

    /**
     * APIKeysDatabaseInterface constructor.
     */
    public function __construct() {
        parent::__construct(MySQLDatabaseCredentials::createFromConfig("utsubot"));
        
        $this->createAPIKeyTable();
    }

    /**
     * Create table in database if necessary
     */
    private function createAPIKeyTable() {
        try {
            $this->query(
                "CREATE TABLE `apikeys`
                (
	              `service` varchar(64) NOT NULL,
	              `key` varchar(256) NOT NULL,
	              PRIMARY KEY (`service`)
                )"
            );

            echo "APIKey database table successfully created.\n\n";
        }

        //  Table exists, do nothing
        catch (\PDOException $e) {}
    }

    /**
     * Add a new API key to the database
     * 
     * @param string $service
     * @param string $key
     * @throws APIKeysDatabaseInterfaceException
     */
    public function insertAPIKey(string $service, string $key) {
        try {
            $this->query(
                "INSERT INTO `apikeys` (`service`, `key`)
                VALUES (?, ?)",
                array($service, $key)
            );
        }
        
        //  Duplicate service, attempt update
        catch (\PDOException $e) {
            $rowCount = $this->query(
                "UPDATE `apikeys`
                SET `key`=?
                WHERE `service`=?
                LIMIT 1",
                array($key, $service)
            );

            //  Update failed, key matches existing database value
            if (!$rowCount)
                throw new APIKeysDatabaseInterfaceException("API key unchanged.");
        }
    }

    /**
     * Remove an API key from the database
     * 
     * @param string $service
     * @throws APIKeysDatabaseInterfaceException
     */
    public function deleteAPIKey(string $service) {
        $rowCount = $this->query(
            "DELETE FROM `apikeys`
            WHERE `service`=?
            LIMIT 1",
            array($service)
        );

        //  No rows deleted
        if (!$rowCount)
            throw new APIKeysDatabaseInterfaceException("No API keys for '$service' found.");
    }

    /**
     * Fetch all API keys in the database
     * 
     * @return array
     */
    public function getAPIKeys(): array {
        return $this->query(
            "SELECT *
             FROM `apikeys`
        ");
    }

}
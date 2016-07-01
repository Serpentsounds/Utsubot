<?php
/**
 * Utsubot - MySQLDatabaseCredentials.php
 * Date: 29/03/2016
 */

declare(strict_types = 1);

namespace Utsubot;

/**
 * Class MySQLDatabaseCredentialsException
 *
 * @package Utsubot
 */
class MySQLDatabaseCredentialsException extends \Exception {}

/**
 * Class MySQLDatabaseCredentials
 *
 * Implementation of DatabaseCredentials to work with the MySQL PDO driver
 */
class MySQLDatabaseCredentials extends DatabaseCredentials {

    protected static $requiredFields = [ "host", "username", "password" ];
    protected static $driver = "mysql";

    /**
     * Form MySQL DSN from config array
     *
     * @param array $config
     * @return string
     */
    protected static function getDSNFromConfig(array $config): string {
        //  These aren't used in the DSN for MySQL
        unset($config['username'], $config['password']);

        $dsnComponents = [ ];
        foreach ($config as $key => $value)
            $dsnComponents[] = "$key=$value";

        return implode(";", $dsnComponents);
    }

}
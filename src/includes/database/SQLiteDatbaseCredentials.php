<?php
/**
 * Utsubot - SQLiteDatbaseCredentials.php
 * Date: 07/05/2016
 */

declare(strict_types = 1);

namespace Utsubot;

/**
 * Class SQLiteDatbaseCredentials
 *
 * @package Utsubot
 */
class SQLiteDatbaseCredentials extends DatabaseCredentials {

    protected static $driver = "sqlite";
    protected static $requiredFields = [ "dbname" ];

    /**
     * @param array $config
     * @return string
     * @throws DatabaseCredentialsException
     */
    protected static function getDSNFromConfig(array $config): string {
        return $config['dbname'];
    }

}
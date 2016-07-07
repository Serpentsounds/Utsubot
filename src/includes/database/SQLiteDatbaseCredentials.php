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
     * Override construct to enable foreign keys
     *
     * @param string $dsn
     * @param string $username
     * @param string $password
     */
    public function __construct(string $dsn, string $username, string $password) {
        parent::__construct($dsn, $username, $password);

        $this->addConnectionCommand("PRAGMA foreign_keys = ON;");
    }

    /**
     * @param array $config
     * @return string
     * @throws DatabaseCredentialsException
     */
    protected static function getDSNFromConfig(array $config): string {
        return $config['dbname'];
    }

}
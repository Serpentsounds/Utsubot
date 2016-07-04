<?php
/**
 * Utsubot - DatabaseCredentials.php
 * Date: 29/03/2016
 */

declare(strict_types = 1);

namespace Utsubot;

/**
 * Class DatabaseCredentialsException
 *
 * @package Utsubot
 */
class DatabaseCredentialsException extends \Exception {

}


/**
 * Class DatabaseCredentials
 *
 * Implement and pass into a DatabaseInterface for construction
 */
abstract class DatabaseCredentials {

    /**
     * @var string $configFile
     * Override in implementation to point to a different config file, used for the factory method createFromConfig().
     * File should be in ini format, and the keyword passed in to the factory method should be one of the top level
     * sections. Valid fields are driver, host, username, and password. All additional fields should be used in a
     * getDSNFromConfig() implementation to construct the dsn.
     */
    protected static $configFile = "../conf/dbconfig.ini";

    /**
     * @var array $requiredFields
     * Optionally specify a list of required fields in config file to proceed with creation.
     */
    protected static $requiredFields = [ ];

    /**
     * @var string $driver
     * The driver an implementation uses. Should match the driver field in config entry.
     */
    protected static $driver = "";

    protected $dsn;
    protected $username;
    protected $password;


    /**
     * Manually construct credentials rather than using config file
     *
     * @param string $dsn
     * @param string $username
     * @param string $password
     */
    public function __construct(string $dsn, string $username, string $password) {
        $this->dsn      = $dsn;
        $this->username = $username;
        $this->password = $password;
    }


    /**
     * Read database credentials from config file
     *
     * @param string $database Name of ini section in config
     * @return DatabaseCredentials
     * @throws DatabaseCredentialsException
     */
    public static final function createFromConfig(string $database): DatabaseCredentials {
        //  Config required to proceed
        if (!file_exists(static::$configFile))
            throw new DatabaseCredentialsException("Configuration file '".static::$configFile."' is missing!");

        //  Read config into memory
        $configFile = parse_ini_file(static::$configFile, true);
        $config     = [ ];
        //  Start with global config, then override as necessary
        if (isset($configFile[ 'global' ]))
            $config = $configFile[ 'global' ];
        if (isset($configFile[ $database ]))
            $config = array_merge($config, $configFile[ $database ]);

        //  Class driver variable should match the driver value in config file section
        if (!isset($config[ 'driver' ]) || $config[ 'driver' ] != static::$driver)
            throw new DatabaseCredentialsException("Config option for 'driver' must be '".static::$driver."'.");
        unset($config[ 'driver' ]);

        //  Abort if any required fields are empty or not present
        foreach (static::$requiredFields as $field) {
            if (empty($config[ $field ]))
                throw new DatabaseCredentialsException("Config option '$field' is required.");
        }

        /* Optional discrete username and password fields.
           Some PDO drivers put them in the dsn instead. In those cases, it's ok to pass them as empty strings.
        */
        $username = $config[ 'username' ] ?? "";
        $password = $config[ 'password' ] ?? "";
        $dsn      = static::$driver.":".static::getDSNFromConfig($config);

        //  Empty DSN returned
        if (!strlen($dsn))
            throw new DatabaseCredentialsException("DSN string is empty.");

        return new static($dsn, $username, $password);
    }


    /**
     * Given an array of values from the config ini file, this method should construct a valid DSN for the driver of
     * this implentation.
     *
     * @param array $config
     * @return string
     * @throws DatabaseCredentialsException
     */
    protected static function getDSNFromConfig(array $config): string {
        throw new DatabaseCredentialsException("Please override getDSNFromConfig() in ".get_called_class().".");
    }


    /**
     * @return string
     */
    public function getDSN(): string {
        return $this->dsn;
    }


    /**
     * @return string
     */
    public function getUsername(): string {
        return $this->username;
    }


    /**
     * @return string
     */
    public function getPassword(): string {
        return $this->password;
    }
}
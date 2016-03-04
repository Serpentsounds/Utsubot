<?php
/**
 * MEGASSBOT - DatabaseInterface.php
 * User: Benjamin
 * Date: 03/11/14
 */

class DatabaseInterfaceException extends Exception {}

class DatabaseInterface {

	protected static $configFile = "dbconfig.ini";

	protected $pdo;
	protected $host;
	protected $database;
	protected $username;
	protected $password;

	/**
	 * @param string $database The name of a config entry in the database ini file
	 * @throws DatabaseInterfaceException If config is invalid
	 */
	public function __construct($database) {
		$config = self::getCredentials($database);

		if (!isset($config['host']) || !isset($config['username']) || !isset($config['password']))
			throw new DatabaseInterfaceException("Not enough information in configuration file.");

		$this->host = $config['host'];
		$this->database = $database;
		$this->username = $config['username'];
		$this->password = $config['password'];
	}

	/**
	 * Read database credentials from config file
	 *
	 * @param string $database Ini entry
	 * @return array option => value config pairs
	 * @throws DatabaseInterfaceException
	 */
	protected static function getCredentials($database) {
		if (!file_exists(self::$configFile))
			throw new DatabaseInterfaceException("Configuration file '". self::$configFile. "' is missing!");
		$configFile = parse_ini_file(self::$configFile, true);

		$config = array();
		//	Start with global config, then override as necessary
		if (isset($configFile['global']))
			$config = $configFile['global'];
		if (isset($configFile[$database]))
			$config = array_merge($config, $configFile[$database]);

		return $config;
	}

	/**
	 * Initiate connection to sql server to prepare for a query
	 *
	 * @param bool $hard Default true, forces recreation of PDO
	 * @throws DatabaseInterfaceException If PDO creation fails
	 */
	protected function connect($hard = true) {
		if (!($this->pdo instanceof PDO) || $hard)
			$this->pdo = new PDO("mysql:host={$this->host};dbname={$this->database};charset=utf8", $this->username, $this->password,
								array(	PDO::ATTR_EMULATE_PREPARES		=> false,
										PDO::ATTR_ERRMODE				=> PDO::ERRMODE_EXCEPTION,
										PDO::ATTR_DEFAULT_FETCH_MODE	=> PDO::FETCH_ASSOC)
			);

		if (!($this->pdo instanceof PDO))
			throw new DatabaseInterfaceException("Unable to create PDO object.");
	}

	/**
	 * Destroy resources when finished with query
	 *
	 * @param array $statements To be destroyed
	 */
	public function disconnect(&$statements = array()) {
		if (is_array($statements) && count($statements)) {
			foreach ($statements as &$statement)
				$statement = null;
		}

		$this->pdo = null;
	}

	/**
	 * Query wrapper for single-use statements. Automatically creates and destroys new PDO and statement
	 *
	 * @param string $query
	 * @param array $parameters
	 * @return array|bool|int Results set for SELECT, affected rows for INSERT/DELETE, false otherwise
	 * @throws DatabaseInterfaceException If PDO is invalid
	 * @throws PDOException If query fails
	 */
	public function query($query, $parameters = array()) {
		$this->connect();
		if (!($this->pdo instanceof \PDO))
			throw new DatabaseInterfaceException("PDO object is invalid.");

		$statement = $this->pdo->prepare($query);
		$statements = array($statement);
		try {
			$statement->execute($parameters);
		}
		//	If statement fails, clean up before bubbling exception
		catch (PDOException $e) {
			$this->disconnect($statements);
			throw $e;
		}

		//	Determine return type
		$return = false;
		$query = trim($query);
		if (stripos($query, "INSERT") === 0 || stripos($query, "DELETE") === 0)
			$return = $statement->rowCount();
		elseif (stripos($query, "SELECT") === 0)
			$return = $statement->fetchAll();


		$this->disconnect($statements);
		return $return;
	}

	/**
	 * Prepare a statement for repeated use. Disconnect() must be called manually for cleanup
	 *
	 * @param string $query
	 * @return PDOStatement
	 * @throws DatabaseInterfaceException If PDO is invalid
	 */
	public function prepare($query) {
		$this->connect(false);
		if (!($this->pdo instanceof \PDO))
			throw new DatabaseInterfaceException("PDO object is invalid.");

		return $this->pdo->prepare($query);
	}

	/**
	 * Begin a database transaction
	 *
	 * @return bool True on success, false on failure
	 * @throws DatabaseInterfaceException
	 */
	public function beginTransaction() {
		$this->connect(false);
		if (!($this->pdo instanceof \PDO))
			throw new DatabaseInterfaceException("PDO object is invalid.");

		return $this->pdo->beginTransaction();
	}

	/**
	 * Commit a database transaction
	 *
	 * @return bool True on success, false on failure
	 * @throws DatabaseInterfaceException
	 */
	public function commit() {
		if (!($this->pdo instanceof \PDO))
			throw new DatabaseInterfaceException("PDO object is invalid.");

		return $this->pdo->commit();
	}

	/**
	 * Roll back a database transaction
	 *
	 * @return bool True on success, false on failure
	 * @throws DatabaseInterfaceException
	 */
	public function rollBack() {
		if (!($this->pdo instanceof \PDO))
			throw new DatabaseInterfaceException("PDO object is invalid.");

		return $this->pdo->rollBack();
	}
}
<?php
/**
 * MEGASSBOT - DatabaseInterface.php
 * User: Benjamin
 * Date: 03/11/14
 */

namespace Utsubot;

/**
 * Class DatabaseInterfaceException
 *
 * @package Utsubot
 */
class DatabaseInterfaceException extends \Exception {

}

/**
 * Class DatabaseInterface
 *
 * @package Utsubot
 */
class DatabaseInterface {

    /** @var $pdo \PDO */
    protected $pdo;
    protected $dsn;
    protected $username;
    protected $password;


    /**
     * @param DatabaseCredentials $credentials
     * @throws DatabaseInterfaceException If config is invalid
     */
    public function __construct(DatabaseCredentials $credentials) {
        $this->dsn      = $credentials->getDSN();
        $this->username = $credentials->getUsername();
        $this->password = $credentials->getPassword();
    }


    /**
     * Query wrapper for single-use statements. Automatically creates and destroys new PDO and statement
     *
     * @param string $query
     * @param array  $parameters
     * @return array|bool|int Results set for SELECT, affected rows for INSERT/DELETE, false returns by the respective
     *                        PDO methods will bubble
     * @throws DatabaseInterfaceException If PDO is invalid
     * @throws \PDOException If query fails
     */
    public function query(string $query, array $parameters = [ ]) {
        $this->connect();

        $statement  = $this->pdo->prepare($query);
        $statements = [ $statement ];
        try {
            $statement->execute($parameters);
        }
            //  If statement fails, clean up before bubbling exception
        catch (\PDOException $e) {
            $this->disconnect($statements);
            throw $e;
        }

        //  Determine return type
        $return = false;
        $query  = trim($query);
        if (stripos($query, "INSERT") === 0 || stripos($query, "DELETE") === 0 || stripos($query, "UPDATE") === 0)
            $return = $statement->rowCount();
        elseif (stripos($query, "SELECT") === 0)
            $return = $statement->fetchAll();

        $this->disconnect($statements);

        return $return;
    }


    /**
     * Initiate connection to sql server to prepare for a query
     *
     * @param bool $hard Default true, forces recreation of PDO
     * @throws DatabaseInterfaceException If PDO creation fails
     */
    protected function connect(bool $hard = true) {
        if (!($this->pdo instanceof \PDO) || $hard)
            $this->pdo = new \PDO(
                $this->dsn,
                $this->username,
                $this->password,
                [
                    \PDO::ATTR_EMULATE_PREPARES   => false,
                    \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
                ]
            );

        $this->verifyPDO();
    }


    /**
     * Used in various methods to verify type of PDO before calling PDO class methods
     *
     * @throws DatabaseInterfaceException
     */
    protected function verifyPDO() {
        if (!($this->pdo instanceof \PDO))
            throw new DatabaseInterfaceException("PDO object is invalid.");
    }


    /**
     * Destroy resources when finished with query
     *
     * @param array $statements To be destroyed
     */
    public function disconnect(array &$statements = [ ]) {
        if (count($statements)) {
            foreach ($statements as &$statement)
                $statement = null;
        }

        $this->pdo = null;
    }


    /**
     * Prepare a statement for repeated use. Disconnect() must be called manually for cleanup
     *
     * @param string $query
     * @return \PDOStatement
     * @throws DatabaseInterfaceException If PDO is invalid
     */
    public function prepare(string $query): \PDOStatement {
        $this->connect(false);

        return $this->pdo->prepare($query);
    }


    /**
     * Begin a database transaction
     *
     * @throws DatabaseInterfaceException
     */
    public function beginTransaction() {
        $this->connect(false);

        if (!$this->pdo->beginTransaction())
            throw new DatabaseInterfaceException("Unable to begin database transaction.");
    }


    /**
     * Commit a database transaction
     *
     * @throws DatabaseInterfaceException
     */
    public function commit() {
        $this->verifyPDO();

        if (!$this->pdo->commit())
            throw new DatabaseInterfaceException("Unable to commit database transaction.");
    }


    /**
     * Roll back a database transaction
     *
     * @throws DatabaseInterfaceException
     */
    public function rollBack() {
        $this->verifyPDO();

        if (!$this->pdo->rollBack())
            throw new DatabaseInterfaceException("Unable to roll back database transaction.");
    }
}
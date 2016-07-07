<?php
/**
 * Created by PhpStorm.
 * User: benny
 * Date: 7/6/2016
 * Time: 4:41 PM
 */

namespace Utsubot\CommandCreator;

use Utsubot\DatabaseInterface;
use Utsubot\DatabaseInterfaceException;
use Utsubot\SQLiteDatbaseCredentials;


/**
 * Class CommandCreatorDatabaseInterfaceException
 *
 * @package Utsubot\CommandCreator
 */
class CommandCreatorDatabaseInterfaceException extends DatabaseInterfaceException {

}


/**
 * Class CommandCreatorDatabaseInterface
 *
 * @package Utsubot\CommandCreator
 */
class CommandCreatorDatabaseInterface extends DatabaseInterface {

    /**
     * CommandCreatorDatabaseInterface constructor.
     */
    public function __construct() {
        parent::__construct(SQLiteDatbaseCredentials::createFromConfig("utsulite"));

        $this->createTables();
    }


    /**
     * Perform first-run initialization to create database tables
     */
    public function createTables() {
        try {
            $this->query(
                'CREATE TABLE "custom_command_types"
                (
                  "id" INTEGER PRIMARY KEY NOT NULL,
                  "name" TEXT UNIQUE NOT NULL
                )'
            );

            //  Populate types with Enum values
            $types     = CommandType::listConstants();
            $statement = $this->prepare(
                'INSERT INTO "custom_command_types" ("id", "name")
                VALUES (?, ?)'
            );

            foreach ($types as $name => $id)
                $statement->execute([ $id, $name ]);

            //  Clean up resources
            $this->disconnect($statements = [ $statement ]);

            echo "Created and populated 'custom_command_types' table.\n";
        }

            //  Table exists, ignore
        catch (\PDOException $e) {
        }

        try {
            $this->query(
                'CREATE TABLE "custom_commands"
                (
                  "id" INTEGER PRIMARY KEY NOT NULL,
                  "name" TEXT UNIQUE NOT NULL,
                  "type" INTEGER NOT NULL
                  REFERENCES "custom_command_types" ("id") ON UPDATE CASCADE ON DELETE CASCADE,
                  "format" TEXT NOT NULL
                )'
            );

            echo "Created 'custom_commands' table.\n";
        }

            //  Table exists, ignore
        catch (\PDOException $e) {
        }

        try {
            $this->query(
                'CREATE TABLE "custom_command_triggers"
                (
                  "id" INTEGER PRIMARY KEY NOT NULL,
                  "custom_command_id" INTEGER NOT NULL
                  REFERENCES "custom_commands" ("id") ON UPDATE CASCADE ON DELETE CASCADE,
                  "trigger" TEXT UNIQUE NOT NULL
                )'
            );

            echo "Created 'custom_command_triggers' table.\n";
        }

            //  Table exists, ignore
        catch (\PDOException $e) {
        }

        try {
            $this->query(
                'CREATE TABLE "custom_command_parameters"
                (
                  "id" INTEGER PRIMARY KEY NOT NULL,
                  "custom_command_id" INTEGER NOT NULL 
                  REFERENCES "custom_commands" ("id") ON UPDATE CASCADE ON DELETE CASCADE,
                  "slot" INTEGER DEFAULT 1 NOT NULL,
                  "value" TEXT DEFAULT NULL
                )'
            );

            echo "Created 'custom_command_parameters' table.\n";
        }

            //  Table exists, ignore
        catch (\PDOException $e) {
        }

    }


    /**
     * Get the full list of triggers and the commands they are associated with
     * 
     * @return array
     */
    public function getTriggers(): array {
        return $this->query(
            'SELECT *
            FROM "custom_commands_triggers"',
            [ ]
        );
    }


    /**
     * @param int $ID
     * @return array
     * @throws CommandCreatorDatabaseInterfaceException
     */
    public function getCommandByID(int $ID): array {
        $commandInfo = $this->query(
            'SELECT *
            FROM "custom_commands"
            WHERE "id"=?',
            [ $ID ]
        );

        if (!$commandInfo)
            throw new CommandCreatorDatabaseInterfaceException("Unable to locate command ID '$ID'.");

        return $commandInfo[ 0 ];
    }

    /**
     * @param string $command
     * @return int
     * @throws CommandCreatorDatabaseInterfaceException
     */
    public function getCommandID(string $command): int {
        $results = $this->query(
            'SELECT "id"
            FROM "custom_commands"
            WHERE "name"=?',
            [ $command ]
        );

        if (!$results)
            throw new CommandCreatorDatabaseInterfaceException("ID lookup for command '$command' failed.");

        return (int)($results[ 0 ][ 'id' ]);
    }


    /**
     * @param int $commandID
     * @return array
     */
    public function getParametersByID(int $commandID): array {
        return $this->query(
            'SELECT *
            FROM "custom_command_parameters"
            WHERE "custom_command_id"=?',
            [ $commandID ]
        );
    }

    /**
     * @param string $command
     * @param CommandType $type
     * @param string $format
     * @throws CommandCreatorDatabaseInterfaceException
     */
    public function createCommand(string $command, CommandType $type, string $format) {
        try {
            $this->query(
                'INSERT INTO "custom_commands" ("name", "type", "format")
                VALUES (?, ?, ?)',
                [ $command, $type->getName(), $format ]
            );
        }
        
        catch (\PDOException $e) {
            throw new CommandCreatorDatabaseInterfaceException("Command '$command' already exists.");
        }
    }
}
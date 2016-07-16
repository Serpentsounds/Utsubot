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
            //  Custom command types refer to what type of message the bot will use upon triggering (e.g., say, action)
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
            $statements = [ $statement ];
            $this->disconnect($statements);

            echo "Created and populated 'custom_command_types' table.\n";
        }

            //  Table exists, ignore
        catch (\PDOException $e) {
        }

        try {
            //  Custom command table containers the main entries for a custom command
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
            //  Triggers table contains one or more rows that hold trigger words for a custom command
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
            //  The parameters table holds list items for a given list slot for a custom command
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
            FROM "custom_command_triggers"',
            [ ]
        );
    }


    /**
     * Look up the main command entry by ID
     *
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
     * Get a command's ID given its name
     *
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
     * Get the collection of list item parameters given a command ID
     *
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
     * @return string
     * @throws CommandCreatorDatabaseInterfaceException
     */
    public function getCommandFormat(string $command): string {
        $id = $this->getCommandID($command);

        $results = $this->query(
            'SELECT "format"
            FROM "custom_commands"
            WHERE "id"=?',
            [ $id ]
        );

        return $results[ 'format' ];
    }


    /**
     * @param string $command
     * @return CommandType
     * @throws CommandCreatorException
     */
    public function getCommandType(string $command): CommandType {
        $id = $this->getCommandByID($command);

        $results = $this->query(
            'SELECT "type"
            FROM "custom_commands"
            WHERE "id"=?',
            [ $id ]
        );

        return new CommandType((int)$results[ 'type' ]);
    }


    /**
     * @param string $command
     * @param int    $slot
     * @return array
     * @throws CommandCreatorDatabaseInterfaceException
     */
    public function getListItems(string $command, int $slot = 1): array {
        $id = $this->getCommandID($command);

        $results = $this->query(
            'SELECT "value"
            FROM "custom_command_parameters"
            WHERE "custom_command_id"=?
            AND "slot"=?',
            [ $id, $slot ]
        );

        if (!$results)
            throw new CommandCreatorDatabaseInterfaceException("No list items found for command '$command' in slot $slot.");

        return $results;
    }


    /**
     * @param string      $command
     * @param CommandType $type
     * @param string      $format
     * @throws CommandCreatorDatabaseInterfaceException
     */
    public function createCommand(string $command, CommandType $type, string $format) {
        try {
            $this->query(
                'INSERT INTO "custom_commands" ("name", "type", "format")
                VALUES (?, ?, ?)',
                [ $command, $type->getValue(), $format ]
            );
        }

        catch (\PDOException $e) {
            throw new CommandCreatorDatabaseInterfaceException("Command '$command' already exists.");
        }
    }


    /**
     * @param string $command
     * @throws CommandCreatorDatabaseInterfaceException
     */
    public function destroyCommand(string $command) {
        $rowCount = $this->query(
            'DELETE FROM "custom_commands"
            WHERE "name"=?',
            [ $command ]
        );

        if (!$rowCount)
            throw new CommandCreatorDatabaseInterfaceException("Command '$command' was not found.");
    }


    /**
     * @param string $command
     * @param string $format
     * @throws CommandCreatorDatabaseInterfaceException
     */
    public function setCommandFormat(string $command, string $format) {
        $id = $this->getCommandID($command);

        $rowCount = $this->query(
            'UPDATE "custom_commands"
            SET "format"=?
            WHERE "id"=?',
            [ $format, $id ]
        );

        if (!$rowCount)
            throw new CommandCreatorDatabaseInterfaceException("Format '$format' of command '$command' unchanged.");
    }


    /**
     * @param string      $command
     * @param CommandType $type
     * @throws CommandCreatorDatabaseInterfaceException
     */
    public function setCommandType(string $command, CommandType $type) {
        $id = $this->getCommandID($command);

        $rowCount = $this->query(
            'UPDATE "custom_commands"
            SET "type"=?
            WHERE "id"=?',
            [ $type->getValue(), $id ]
        );

        if (!$rowCount)
            throw new CommandCreatorDatabaseInterfaceException("Type '{$type->getName()}' for command '$command' unchanged.");
    }


    /**
     * @param string $command
     * @param string $trigger
     * @throws CommandCreatorDatabaseInterfaceException
     */
    public function addCommandTrigger(string $command, string $trigger) {
        $id = $this->getCommandID($command);

        try {
            $this->query(
                'INSERT INTO "custom_command_triggers" ("custom_command_id", "trigger")
                VALUES (?, ?)',
                [ $id, $trigger ]
            );
        }
        catch (\PDOException $e) {
            throw new CommandCreatorDatabaseInterfaceException("Trigger '$trigger' already exists.");
        }
    }


    /**
     * @param string $command
     * @param string $trigger
     * @throws CommandCreatorDatabaseInterfaceException
     */
    public function removeCommandTrigger(string $command, string $trigger) {
        $id = $this->getCommandID($command);

        $rowCount = $this->query(
            'DELETE FROM "custom_command_triggers"
            WHERE "custom_command_id"=?
            AND "value"=?',
            [ $id, $trigger ]
        );

        if (!$rowCount)
            throw new CommandCreatorDatabaseInterfaceException("Trigger '$trigger' for command '$command' was not found.");
    }


    /**
     * @param string $command
     * @return int
     * @throws CommandCreatorDatabaseInterfaceException
     */
    public function clearCommandTriggers(string $command): int {
        $id = $this->getCommandID($command);

        $rowCount = $this->query(
            'DELETE FROM "custom_command_triggers"
            WHERE "custom_command_id"=?',
            [ $id ]
        );

        return (int)$rowCount;
    }


    /**
     * @param string $command
     * @param string $item
     * @param int    $slot
     * @throws CommandCreatorDatabaseInterfaceException
     */
    public function addListItem(string $command, string $item, int $slot = 1) {
        $id = $this->getCommandID($command);

        $this->query(
            'INSERT INTO "custom_command_parameters" ("custom_command_id", "slot", "value")
            VALUES (?, ?, ?)',
            [ $id, $slot, $item ]
        );
    }


    /**
     * @param string $command
     * @param string $item
     * @param int    $slot
     * @throws CommandCreatorDatabaseInterfaceException
     */
    public function removeListItem(string $command, string $item, int $slot = 1) {
        $id = $this->getCommandID($command);

        $rowCount = $this->query(
            'DELETE FROM "custom_command_parameters"
            WHERE "custom_command_id"=?
            AND "slot"=?
            AND "value"=?',
            [ $id, $slot, $item ]
        );

        if (!$rowCount)
            throw new CommandCreatorDatabaseInterfaceException("Item '$item' for command '$command' in slot $slot was not found.");
    }


    /**
     * @param string $command
     * @param int    $slot
     * @return int
     * @throws CommandCreatorDatabaseInterfaceException
     */
    public function clearListItems(string $command, int $slot = 1): int {
        $id = $this->getCommandID($command);

        $rowCount = $this->query(
            'DELETE FROM "custom_command_parameters"
            WHERE "custom_command_id"=?
            AND "slot"=?',
            [ $id, $slot ]
        );

        return $rowCount;
    }
}
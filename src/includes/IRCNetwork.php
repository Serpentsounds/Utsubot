<?php
/**
 * Utsubot - IRCNetwork.php
 * Date: 05/03/2016
 */

declare(strict_types = 1);

namespace Utsubot;

class IRCNetwork {

    private $name;
    private $serverCycle;
    private $port;
    private $nicknameCycle;
    private $defaultChannels;
    private $onConnect;
    private $commandPrefixes;

    public function __construct(string $name, array $servers, int $port, array $nicknames, array $defaultChannels = array(), array $onConnect = array(), array $commandPrefixes = array()) {
        $this->serverCycle = new NonEmptyCycle($servers);
        $this->nicknameCycle = new NonEmptyCycle($nicknames);

        $this->name            = $name;
        $this->port            = $port;
        $this->defaultChannels = $defaultChannels;
        $this->onConnect       = $onConnect;
        $this->commandPrefixes = $commandPrefixes;
    }

    /**
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * @return int
     */
    public function getPort(): int {
        return $this->port;
    }

    /**
     * @return array
     */
    public function getDefaultChannels(): array {
        return $this->defaultChannels;
    }

    /**
     * @return array
     */
    public function getOnConnect(): array {
        return $this->onConnect;
    }

    /**
     * @return array
     */
    public function getCommandPrefixes(): array {
        return $this->commandPrefixes;
    }

    /**
     * @return NonEmptyCycle
     */
    public function getNicknameCycle(): NonEmptyCycle {
        return $this->nicknameCycle;
    }

    /**
     * @return NonEmptyCycle
     */
    public function getServerCycle(): NonEmptyCycle {
        return $this->serverCycle;
    }
}
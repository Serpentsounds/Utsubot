<?php
/**
 * Utsubot - IRCNetwork.php
 * Date: 05/03/2016
 */

declare(strict_types = 1);

class IRCNetwork {

    private $name;
    private $serverCycle;
    private $port;
    private $nicknameCycle;
    private $defaultChannels;
    private $onConnect;
    private $commandPrefixes;

    public function __construct(string $name, array $servers, int $port, array $nicknames, array $channels, array $onConnect = array(), array $commandPrefixes = array()) {
        $this->serverCycle = new NonEmptyCycle($servers);
        $this->nicknameCycle = new NonEmptyCycle($nicknames);

        $this->name            = $name;
        $this->port            = $port;
        $this->defaultChannels = $channels;
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

class Cycle {

    /** @var $InfiniteIterator InfiniteIterator */
    private $InfiniteIterator;
    /** @var $ArrayIterator ArrayIterator */
    private $ArrayIterator;

    /**
     * Cycle constructor.
     *
     * @param array $items
     */
    public function __construct(array $items) {
        $this->ArrayIterator = new ArrayIterator(array_values($items));
        $this->InfiniteIterator = new InfiniteIterator($this->ArrayIterator);
        $this->InfiniteIterator->rewind();
    }

    /**
     * Get the current item
     *
     * @param bool $cycle Automatically cycle to the next item
     * @return mixed
     */
    public function get(bool $cycle = false) {
        $value = $this->InfiniteIterator->current();

        if ($cycle)
            $this->cycle();

        return $value;
    }

    /**
     * Move to the next item
     */
    public function cycle() {
        $this->InfiniteIterator->next();
    }

    /**
     * Start over
     */
    public function reset() {
        $this->InfiniteIterator->rewind();
    }

    /**
     * Add an item to the end of the cycle
     *
     * @param mixed $item
     */
    public function add($item) {
        $this->ArrayIterator->append($item);
    }

    /**
     * Get the value at index 0
     *
     * @return mixed
     */
    public function getPrimary() {
        return $this->ArrayIterator->offSetGet(0);
    }

    /**
     * Move a value to index 0, and slide everything else down
     * @param $target
     * @throws CycleException
     */
    public function setPrimary($target) {
        $this->reset();
        $targetKey = null;
        foreach ($this->ArrayIterator as $key => $item) {
            if ($target === $item) {
                $targetKey = $key;
                break;
            }
        }

        if ($targetKey === null)
            throw new CycleException("Item '$target' not found in cycle.");

        $elements = $this->ArrayIterator->getArrayCopy();
        $this->ArrayIterator->offsetSet(0, $this->ArrayIterator->offsetGet($targetKey));
        $key = 1;
        foreach ($elements as $item) {
            if ($key != $targetKey)
                $this->ArrayIterator->offsetSet($key++, $item);
        }

    }


}

class CycleException extends Exception {}

class NonEmptyCycle extends Cycle {

    /**
     * NonEmptyCycle constructor.
     *
     * @param array $items
     * @throws NonEmptyCycleException Empty $items
     */
    public function __construct(array $items) {
        if (empty($items))
            throw new NonEmptyCycleException("NonEmptyCycle must have starting items.");

        parent::__construct($items);
    }
}

class NonEmptyCycleException extends CycleException {}
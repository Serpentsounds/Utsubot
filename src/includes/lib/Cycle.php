<?php
/**
 * Utsubot - Cycle.php
 * Date: 04/04/2016
 */

declare(strict_types = 1);

namespace Utsubot;


class Cycle {

    /** @var $InfiniteIterator \InfiniteIterator */
    private $InfiniteIterator;
    /** @var $ArrayIterator \ArrayIterator */
    private $ArrayIterator;

    /**
     * Cycle constructor.
     *
     * @param array $items
     */
    public function __construct(array $items) {
        $this->ArrayIterator = new \ArrayIterator(array_values($items));
        $this->InfiniteIterator = new \InfiniteIterator($this->ArrayIterator);
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
        return $this->ArrayIterator->offsetGet(0);
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

class CycleException extends \Exception {}

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
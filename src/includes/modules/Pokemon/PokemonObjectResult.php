<?php
/**
 * Utsubot - PokemonObjectResult.php
 * Date: 14/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon;
use Iterator;


/**
 * Class GetObjectResult
 *
 * @package Utsubot\Pokemon
 */
class PokemonObjectResult implements Iterator {
    private $items = [ ];
    private $index = 0;

    /**
     * @param PokemonBase $item
     */
    public function addItem(PokemonBase $item) {
        $this->items[] = $item;
    }

    /**
     * @param array $items
     */
    public function addItems(array $items) {
        foreach ($items as $item)
            $this->addItem($item);
    }

    /**
     * @return int
     */
    public function itemCount(): int {
        return count($this->items);
    }

    /**
     * If a Jaro search was used, this method will sort results from most similar to least similar
     */
    public function jaroSort() {
        usort($this->items,
            //  Sort results to put higher string similarities first
            function(PokemonBase $first, PokemonBase $second) {
                $jaroFirst = $first->getLastJaroResult()->getSimilarity();
                $jaroSecond = $second->getLastJaroResult()->getSimilarity();

                //  Values are floats, so manually specify cases to prevent int cast rounding errors
                if ($jaroFirst < $jaroSecond)
                    return 1;
                elseif ($jaroFirst > $jaroSecond)
                    return -1;
                return 0;
            }
        );
    }

    /**
     * Get the collection of "suggestions," or items that were matched via a Jaro search but are not the most similar (main result)
     *
     * @return array
     */
    public function getSuggestions(): array {
        if (count($this->items) <= 1)
            return [ ];

        //  Call __toString on all items
        return array_map(

            function($item) {
                return (string)$item;
            },

            array_slice($this->items, 1)

        );
    }

    /**
     * Return suggestions formatted for output to end user
     *
     * @return string
     */
    public function formatSuggestions(): string {
        $suggestions = $this->getSuggestions();
        if (!$suggestions)
            return "";

        return "You may also be looking for ". implode(", ", $suggestions). ".";
    }

    /**
     * Reset Iterator position
     */
    public function rewind() {
        $this->index = 0;
    }

    /**
     * Get current object from Iterator
     *
     * @return PokemonBase
     */
    public function current(): PokemonBase {
        return $this->items[$this->index];
    }

    /**
     * Get current position from Iterator
     *
     * @return int
     */
    public function key() {
        return $this->index;
    }

    /**
     * Advance Iterator to next position
     */
    public function next() {
        ++$this->index;
    }

    /**
     * Check if Iterator has a valid item to give
     *
     * @return bool
     */
    public function valid(): bool {
        return isset($this->items[$this->index]);
    }
}
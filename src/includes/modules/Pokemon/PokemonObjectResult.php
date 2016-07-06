<?php
/**
 * Utsubot - PokemonObjectResult.php
 * Date: 14/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon;
use Iterator;
use Utsubot\TypedArray;


/**
 * Class GetObjectResult
 *
 * @package Utsubot\Pokemon
 */
class PokemonObjectResult extends TypedArray {

    protected  static $contains = "Utsubot\\Pokemon\\PokemonBase";

    /**
     * @param array $items
     */
    public function addItems(array $items) {
        foreach ($items as $item)
            $this->append($item);
    }

    /**
     * If a Jaro search was used, this method will sort results from most similar to least similar
     */
    public function jaroSort() {
        $this->uasort(
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

        //  No usort method on ArrayObject, so we have to use this lousy workaround to reset key association...
        $this->exchangeArray(array_values($this->getArrayCopy()));
    }

    /**
     * Get the collection of "suggestions," or items that were matched via a Jaro search but are not the most similar (main result)
     *
     * @return array
     */
    public function getSuggestions(): array {
        if ($this->count() <= 1)
            return [ ];

        //  Call __toString on all items
        return array_map(

            function($item) {
                return (string)$item;
            },

            array_slice($this->getArrayCopy(), 1)

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


}
<?php
/**
 * Utsubot - PokemonGoDatabaseInterface.php
 * Date: 23/07/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon;

use Utsubot\DatabaseInterface;
use Utsubot\SQLiteDatbaseCredentials;


/**
 * Class PokemonGoDatabaseInterface
 *
 * @package Utsubot\Pokemon\database
 */
class PokemonGoDatabaseInterface extends DatabaseInterface implements PokemonObjectPopulator {

    /**
     * PokemonGoDatabaseInterface constructor.
     */
    public function __construct() {
        parent::__construct(SQLiteDatbaseCredentials::createFromConfig("pokemongo"));
    }


    /**
     * @param Pokemons $pokemons
     * @return Pokemons
     */
    public function getPokemon(Pokemons $pokemons): Pokemons {
        /** @var Pokemon\Pokemon[] $pokemons */

        $goPokemon = $this->query('SELECT * FROM "pokemon"');

        foreach ($goPokemon as $row) {
            if (isset($pokemons[ $row[ 'id' ]])) {
                $pokemons[ $row[ 'id' ] ]->setGoCatchRate((float)$row[ 'captureRate' ]);
                $pokemons[ $row[ 'id' ] ]->setGoFleeRate((float)$row[ 'fleeRate' ]);
                $pokemons[ $row[ 'id' ] ]->setCandyToEvolve(($row[ 'candyToEvolve' ]) ? (int)$row[ 'candyToEvolve' ] : 0);
            }
        }

        return $pokemons;
    }

    public function getMoves(Moves $moves): Moves {

    }


    /**
     * @param Abilities $abilities
     * @return Abilities
     */
    public function getAbilities(Abilities $abilities): Abilities {
        return $abilities;
    }


    /**
     * @param Items $items
     * @return Items
     */
    public function getItems(Items $items): Items {
        return $items;
    }


    /**
     * @param Natures $natures
     * @return Natures
     */
    public function getNatures(Natures $natures): Natures {
        return $natures;
    }
}
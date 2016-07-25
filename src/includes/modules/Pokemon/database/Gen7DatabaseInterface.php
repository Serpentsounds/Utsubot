<?php
/**
 * Utsubot - Gen7DatabaseInterface.php
 * Date: 01/07/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon;


use Utsubot\DatabaseInterface;
use Utsubot\Pokemon\Pokemon\{
    Evolution,
    Pokemon
};
use Utsubot\Pokemon\Ability\Ability;
use Utsubot\SQLiteDatbaseCredentials;


/**
 * Class Gen7DatabaseInterface
 *
 * @package Utsubot\Pokemon
 */
class Gen7DatabaseInterface extends DatabaseInterface implements PokemonObjectPopulator {

    /**
     * Gen7DatabaseInterface constructor.
     */
    public function __construct() {
        parent::__construct(SQLiteDatbaseCredentials::createFromConfig("gen7"));
    }


    /**
     * @param Pokemons $pokemons
     * @return Pokemons
     * @throws PokemonBaseException
     * @throws \Utsubot\Pokemon\Pokemon\PokemonException
     */
    public function getPokemon(Pokemons $pokemons): Pokemons {
        /** @var Pokemon[] $gen7Pokemons */
        $gen7Pokemons = new Pokemons();
        $offset  = 2000;

        //  Query names
        $names = $this->query(
            "SELECT p.id, pn.name, l.name as lang
            FROM pokemon p
            INNER JOIN pokemon_names pn
            ON p.id=pn.pokemon_id
            INNER JOIN languages l
            ON l.id=pn.language_id"
        );

        //  Set names
        foreach ($names as $row) {
            $id = $row[ 'id' ] + $offset;
            if (!isset($gen7Pokemons[ $id ])) {
                $gen7Pokemons[ $id ] = new Pokemon();
                $gen7Pokemons[ $id ]->setId($id);

                $gen7Pokemons[ $id ]->setGeneration(7);
            }

            $gen7Pokemons[ $id ]->setName($row[ 'name' ], Language::fromName($row[ 'lang' ]));
        }

        //  Query types
        $types = $this->query(
            "SELECT p.id, t.name, pt.slot
            FROM pokemon p
            INNER JOIN pokemon_types pt
            ON p.id=pt.pokemon_id
            INNER JOIN types t
            ON t.id=pt.type_id"
        );

        //  Set types
        foreach ($types as $row)
            $gen7Pokemons[ $row[ 'id' ] + $offset ]->setType($row[ 'slot' ] - 1, $row[ 'name' ]);

        //  Query abilities
        $abilities = $this->query(
            "SELECT p.id, pa.ability, pa.slot
            FROM pokemon p
            INNER JOIN pokemon_abilities pa 
            ON p.id=pa.pokemon_id"
        );

        //  Set abilities
        foreach ($abilities as $row)
            $gen7Pokemons[ $row[ 'id' ] + $offset ]->setAbility($row[ 'slot' ] - 1, $row[ 'ability' ]);

        //  Query evolutions
        $evolutions = $this->query(
            "SELECT *
            FROM pokemon_evolution"
        );

        //  Set evolutions
        foreach ($evolutions as $row) {
            $evolution = new Evolution();

            $fromId = $row[ 'from_id' ] + $offset;
            $toId   = $row[ 'to_id' ] + $offset;

            $fromName = $gen7Pokemons[ $fromId ]->getName(new Language(Language::English));
            $toName   = $gen7Pokemons[ $toId ]->getName(new Language(Language::English));

            if (!$fromName)
                $fromName = $gen7Pokemons[ $fromId ]->getName(new Language(Language::Roumaji));
            if (!$toName)
                $toName = $gen7Pokemons[ $toId ]->getName(new Language(Language::Roumaji));

            $evolution->setFrom($fromName);
            $evolution->setTo($toName);

            $gen7Pokemons[ $fromId ]->addEvolution(clone $evolution);
            $gen7Pokemons[ $toId ]->addPreEvolution(clone $evolution);
        }

        //  Query semantics
        $data = $this->query(
            "SELECT *
            FROM pokemon_semantics"
        );

        //  Set semantics
        foreach ($data as $row) {
            $id = $row[ 'pokemon_id' ] + $offset;
            $gen7Pokemons[ $id ]->setHeight((float)$row[ 'height' ]);
            $gen7Pokemons[ $id ]->setWeight((float)$row[ 'weight' ]);
        }

        /** @var Pokemons $gen7Pokemons */
        return new Pokemons($pokemons->getArrayCopy() + $gen7Pokemons->getArrayCopy());
    }


    /**
     * @param Abilities $abilities
     * @return Abilities
     */
    public function getAbilities(Abilities $abilities): Abilities {
        /** @var Ability[] $gen7Abilities */
        $gen7Abilities = new Abilities();
        $offset        = 2000;

        //  Query names
        $names = $this->query(
            "SELECT a.id, a.effect, an.name, l.name as lang
            FROM abilities a
            INNER JOIN ability_names an
            ON a.id=an.ability_id
            INNER JOIN languages l
            ON l.id=an.language_id"
        );

        //  Set names
        foreach ($names as $row) {
            $id = $row[ 'id' ] + $offset;
            if (!isset($gen7Abilities[ $id ])) {
                $gen7Abilities[ $id ] = new Ability();
                $gen7Abilities[ $id ]->setId($id);

                $gen7Abilities[ $id ]->setEffect($row[ 'effect' ]);
                $gen7Abilities[ $id ]->setShortEffect($row[ 'effect' ]);

                $gen7Abilities[ $id ]->setGeneration(7);
            }

            $gen7Abilities[ $id ]->setName($row[ 'name' ], Language::fromName($row[ 'lang' ]));
        }

        /** @var Abilities $gen7Abilities */
        return new Abilities($abilities->getArrayCopy() + $gen7Abilities->getArrayCopy());
    }


    /**
     * @param Items $items
     * @return Items
     */
    public function getItems(Items $items): Items {
        return $items;
    }


    /**
     * @param Moves $moves
     * @return Moves
     */
    public function getMoves(Moves $moves): Moves {
        return $moves;
    }


    /**
     * @param Natures $natures
     * @return Natures
     */
    public function getNatures(Natures $natures): Natures {
        return $natures;
    }
}
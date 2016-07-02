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
     * @return PokemonGroup
     * @throws PokemonBaseException
     * @throws \Utsubot\Pokemon\Pokemon\PokemonException
     */
    public function getPokemon(): PokemonGroup {
        /** @var Pokemon[] $pokemon */
        $pokemon = new PokemonGroup();
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
            if (!isset($pokemon[ $id ])) {
                $pokemon[ $id ] = new Pokemon();
                $pokemon[ $id ]->setId($id);
            }

            $pokemon[ $id ]->setName($row[ 'name' ], Language::fromName($row[ 'lang' ]));
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
            $pokemon[ $row[ 'id' ] + $offset ]->setType($row[ 'slot' ] - 1, $row[ 'name' ]);

        //  Query abilities
        $abilities = $this->query(
            "SELECT p.id, pa.ability, pa.slot
            FROM pokemon p
            INNER JOIN pokemon_abilities pa 
            ON p.id=pa.pokemon_id"
        );

        //  Set abilities
        foreach ($abilities as $row)
            $pokemon[ $row[ 'id' ] + $offset ]->setAbility($row[ 'slot' ] - 1, $row[ 'ability' ]);

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

            $fromName = $pokemon[ $fromId ]->getName(new Language(Language::English));
            $toName   = $pokemon[ $toId ]->getName(new Language(Language::English));

            if (!$fromName)
                $fromName = $pokemon[ $fromId ]->getName(new Language(Language::Roumaji));
            if (!$toName)
                $toName = $pokemon[ $toId ]->getName(new Language(Language::Roumaji));

            $evolution->setFrom($fromName);
            $evolution->setTo($toName);

            $pokemon[ $fromId ]->addEvolution(clone $evolution);
            $pokemon[ $toId ]->addPreEvolution(clone $evolution);
        }

        //  Query semantics
        $data = $this->query(
            "SELECT *
            FROM pokemon_semantics"
        );

        //  Set semantics
        foreach ($data as $row) {
            $id = $row[ 'pokemon_id' ] + $offset;
            $pokemon[ $id ]->setHeight((float)$row[ 'height' ]);
            $pokemon[ $id ]->setWeight((float)$row[ 'weight' ]);
        }

        return $pokemon;
    }


    /**
     * @return AbilityGroup
     */
    public function getAbilities(): AbilityGroup {
        /** @var Ability[] $abilities */
        $abilities = new AbilityGroup();
        $offset    = 2000;

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
            if (!isset($abilities[ $id ])) {
                $abilities[ $id ] = new Ability();
                $abilities[ $id ]->setId($id);

                $abilities[ $id ]->setEffect($row[ 'effect' ]);
                $abilities[ $id ]->setShortEffect($row[ 'effect' ]);
                
                $abilities[ $id ]->setGeneration(7);
            }

            $abilities[ $id ]->setName($row[ 'name' ], Language::fromName($row[ 'lang' ]));
        }

        return $abilities;
    }


    /**
     * @return ItemGroup
     */
    public function getItems(): ItemGroup {
        return new ItemGroup();
    }


    /**
     * @return MoveGroup
     */
    public function getMoves(): MoveGroup {
        return new MoveGroup();
    }


    /**
     * @return NatureGroup
     */
    public function getNatures(): NatureGroup {
        return new NatureGroup();
    }
}
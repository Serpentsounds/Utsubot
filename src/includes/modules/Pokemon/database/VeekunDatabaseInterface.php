<?php
/**
 * MEGASSBOT - VeekunDatabaseInterface.php
 * User: Benjamin
 * Date: 03/11/14
 */

namespace Utsubot\Pokemon;

use Utsubot\{
    DatabaseInterface,
    DatabaseInterfaceException,
    SQLiteDatbaseCredentials
};
use Utsubot\Pokemon\Pokemon\{
    Pokemon,
    Evolution,
    EvolutionMethod,
    EvolutionRequirement
};
use Utsubot\Pokemon\Ability\Ability;
use Utsubot\Pokemon\Item\Item;
use Utsubot\Pokemon\Nature\Nature;
use Utsubot\Pokemon\Move\Move;


/**
 * Class VeekunDatabaseInterfaceException
 *
 * @package Utsubot\Pokemon
 */
class VeekunDatabaseInterfaceException extends DatabaseInterfaceException {

}

/**
 * Class VeekunDatabaseInterface
 *
 * @package Utsubot\Pokemon
 */
class VeekunDatabaseInterface extends DatabaseInterface implements PokemonObjectPopulator {

    /**
     * VeekunDatabaseInterface constructor.
     */
    public function __construct() {
        parent::__construct(SQLiteDatbaseCredentials::createFromConfig("veekun"));
    }


    /**
     * @param Pokemons $pokemons
     * @return Pokemons
     * @throws PokemonBaseException
     * @throws \Utsubot\Pokemon\Pokemon\PokemonException
     * @throws \Utsubot\EnumException
     */
    public function getPokemon(Pokemons $pokemons): Pokemons  {
        /** @var Pokemon[] $pokemons */
        $pokemons = new Pokemons();

        $nationalDex = new Dex(Dex::National);

        /*  Main pokemon, no forms
            Includes generation, capture rate, color, base happiness, habitat, baby (y/n), steps to hatch, gender ratio  */
        $species = $this->getSpecies();
        foreach ($species as $row) {
            $newPokemon = new Pokemon();

            $newPokemon->setId($row[ 'id' ]);

            $newPokemon->setGeneration((int)$row[ 'generation_id' ]);

            $newPokemon->setCatchRate((int)$row[ 'capture_rate' ]);

            $newPokemon->setColor(ucwords($row[ 'color' ]));

            $newPokemon->setBaseHappiness((int)$row[ 'base_happiness' ]);

            $newPokemon->setHabitat(ucwords($row[ 'habitat' ]));

            $newPokemon->setBaby((bool)$row[ 'is_baby' ]);

            $newPokemon->setEggCycles((int)$row[ 'hatch_counter' ]);

            $newPokemon->setGenderRatio(
                ($row[ 'gender_rate' ] == -1) ?
                    -1 :
                    (8 - $row[ 'gender_rate' ]) / 8
            );

            $pokemons[ $row[ 'id' ] ] = $newPokemon;
        }

        //  Populate names in all languages, as well as dex species ("genus")
        $name = $this->getName();
        foreach ($name as $row) {
            $language = Language::fromName($row[ 'language' ]);
            $pokemons[ $row[ 'pokemon_species_id' ] ]
                ->setName((string)$row[ 'name' ], $language);

            $pokemons[ $row[ 'pokemon_species_id' ] ]
                ->setSpecies((string)$row[ 'genus' ], $language);
        }

        //  Populate egg breeding groups
        $eggGroup = $this->getEggGroup();
        foreach ($eggGroup as $row)
            $pokemons[ $row[ 'species_id' ] ]
                ->addEggGroup((string)$row[ 'name' ]);

        /*  Add entries for forms with stat/type changes
            Update these and base entries with height, weight, base experience, and dexnum  */
        $pokemonRow = $this->getPokemonRow();
        foreach ($pokemonRow as $row) {
            //  Copy base info from main species for alts
            if (!isset($pokemons[ $row[ 'id' ] ]))
                $pokemons[ $row[ 'id' ] ] = clone $pokemons[ $row[ 'species_id' ] ];

            //  Alt pokemon, update main name to reflect that
            if ($row[ 'id' ] > 10000) {
                #$pokemon[$row['id']]
                #    ->setName(ucwords($row['identifier']), new Language(Language::English));
                $pokemons[ $row[ 'id' ] ]
                    ->setId($row[ 'id' ]);
            }

            $pokemons[ $row[ 'id' ] ]
                ->setHeight($row[ 'height' ] / 10);
            $pokemons[ $row[ 'id' ] ]
                ->setWeight($row[ 'weight' ] / 10);
            $pokemons[ $row[ 'id' ] ]
                ->setBaseExp((int)$row[ 'base_experience' ]);
            $pokemons[ $row[ 'id' ] ]
                ->setDexNumber((int)$row[ 'species_id' ], $nationalDex);
        }

        //  Populate other alt forms (semantic changes), create references to all alts in main pokemon
        $alt = $this->getAlt();
        foreach ($alt as $row) {
            //  If form_identifier isn't blank, it is an actual alt form, not a row for base pokemon
            if ($row[ 'form_identifier' ]) {
                $info = [
                    'name' => $row[ 'identifier' ],
                    'form' => $row[ 'form_identifier' ],
                    'id'   => $row[ 'pokemon_id' ]
                ];

                //  References a form with its own 'pokemon' entry, meaning changed stats/type/etc, but we want a reference entry under the original pokemon
                if ($row[ 'pokemon_id' ] > 10000)
                    $pokemons[ $pokemons[ $row[ 'pokemon_id' ] ]->getDexNumber($nationalDex) ]
                        ->addToAlternateForm($row[ 'form_order' ] - 1, $info);

                //  Semantic form change, the pokemon_id will be the same as the original pokemon
                else
                    $pokemons[ $row[ 'pokemon_id' ] ]
                        ->addToAlternateForm($row[ 'form_order' ] - 1, $info);
            }
        }

        //  Populate names in all languages for alt forms
        $altName = $this->getAltName();
        foreach ($altName as $row) {
            $language = Language::fromName($row[ 'language' ]);

            //  Compound form name with original pokemon name
            if ($row[ 'pokemon_id' ] > 10000) {
                $name = $row[ 'pokemon_name' ] ?? $row[ 'form_name' ];
                $pokemons[ $row[ 'pokemon_id' ] ]
                    ->setName($name, $language);
            }

            //  Save form name in original pokemon
            else
                $pokemons[ $pokemons[ $row[ 'pokemon_id' ] ]->getDexNumber($nationalDex) ]
                    ->addToAlternateForm(
                        $row[ 'form_order' ] - 1,
                        [ 'names' => [ $language->getValue() => $row[ 'form_name' ] ] ]
                    );
        }

        //  Populate all dex numbers
        $dexnum = $this->getDexnum();
        foreach ($dexnum as $row)
            $pokemons[ $row[ 'species_id' ] ]
                ->setDexNumber((int)$row[ 'pokedex_number' ], new Dex(Dex::findValue($row[ 'name' ])));

        //  Populate evolution data
        $evoData       = $this->getEvolution();
        $genderIds     = [ 1 => "Female", 2 => "Male" ];
        $relativeStats = [ -1 => "Atk<Def", 0 => "Atk=Def", 1 => "Atk>Def" ];

        foreach ($evoData as $row) {

            $evoname = $row[ 'evo' ];
            if ($pokeRow = $this->getNameFromId("pokemon_species", intval($row[ 'evo' ])))
                $evoname = $pokeRow[ 'name' ];

            $preevoname = $row[ 'preevo' ];
            if ($pokeRow = $this->getNameFromId("pokemon_species", intval($row[ 'preevo' ])))
                $preevoname = $pokeRow[ 'name' ];

            $evolution = new Evolution();
            $evolution->setFrom($preevoname);
            $evolution->setTo($evoname);

            $trigger = -1;
            switch ($row[ 'method' ]) {
                case "level-up":
                    $trigger = new EvolutionMethod(EvolutionMethod::Level_Up);
                    break;
                case "trade":
                    $trigger = new EvolutionMethod(EvolutionMethod::Trade);
                    break;
                case "use-item":
                    $trigger = new EvolutionMethod(EvolutionMethod::Use);
                    break;
                case "shed":
                    $trigger = new EvolutionMethod(EvolutionMethod::Shed);
                    break;
            }
            $evolution->setMethod($trigger);

            foreach ($row as $key => $val) {
                if ($val) {
                    switch ($key) {
                        case "minimum_level":
                            $evolution->addRequirement(new EvolutionRequirement(EvolutionRequirement::Level), $val);
                            break;
                        case "gender_id":
                            $evolution->addRequirement(new EvolutionRequirement(EvolutionRequirement::Gender), $genderIds[ $val ]);
                            break;
                        case "time_of_day":
                            $evolution->addRequirement(new EvolutionRequirement(EvolutionRequirement::Time), $val);
                            break;
                        case "minimum_happiness":
                            $evolution->addRequirement(new EvolutionRequirement(EvolutionRequirement::Happiness), $val);
                            break;
                        case "minimum_beauty":
                            $evolution->addRequirement(new EvolutionRequirement(EvolutionRequirement::Beauty), $val);
                            break;
                        case "minimum_affection":
                            $evolution->addRequirement(new EvolutionRequirement(EvolutionRequirement::Affection), $val);
                            break;
                        case "needs_overworld_rain":
                            $evolution->addRequirement(new EvolutionRequirement(EvolutionRequirement::Rain), true);
                            break;
                        case "turn_upside_down":
                            $evolution->addRequirement(new EvolutionRequirement(EvolutionRequirement::Upside_Down), true);
                            break;
                        case "relative_physical_stats":
                            $evolution->addRequirement(new EvolutionRequirement(EvolutionRequirement::Relative_Stats), $relativeStats[ $val ]);
                            break;

                        case "trigger_item_id":
                            $useItem = $val;
                            if ($itemRow = $this->getNameFromId("item", intval($val)))
                                $useItem = $itemRow[ 'name' ];
                            $evolution->addRequirement(new EvolutionRequirement(EvolutionRequirement::Use_Item), $useItem);
                            break;

                        case "location_id":
                            $location = $val;
                            if ($locationRow = $this->getNameFromId("location", intval($val))) {
                                $location = $locationRow[ 'name' ];
                                $evolution->addRequirement(new EvolutionRequirement(EvolutionRequirement::Generation), $locationRow[ 'generation' ]);
                            }
                            $evolution->addRequirement(new EvolutionRequirement(EvolutionRequirement::Location), $location);
                            break;

                        case "held_item_id":
                            $holdItem = $val;
                            if ($itemRow = $this->getNameFromId("item", intval($val)))
                                $holdItem = $itemRow[ 'name' ];
                            $evolution->addRequirement(new EvolutionRequirement(EvolutionRequirement::Hold_Item), $holdItem);
                            break;

                        case "known_move_id":
                            $knowsMove = $val;
                            if ($moveRow = $this->getNameFromId("move", intval($val)))
                                $knowsMove = $moveRow[ 'name' ];
                            $evolution->addRequirement(new EvolutionRequirement(EvolutionRequirement::Knows_Move), $knowsMove);
                            break;

                        case "known_move_type_id":
                            $knowsMoveType = $val;
                            if ($typeRow = $this->getNameFromId("type", intval($val)))
                                $knowsMoveType = $typeRow[ 'name' ];
                            $evolution->addRequirement(new EvolutionRequirement(EvolutionRequirement::Knows_Move_Type), $knowsMoveType);
                            break;

                        case "party_species_id":
                            $partyPokemon = $val;
                            if ($pokeRow = $this->getNameFromId("pokemon_species", intval($val)))
                                $partyPokemon = $pokeRow[ 'name' ];
                            $evolution->addRequirement(new EvolutionRequirement(EvolutionRequirement::Party_Pokemon), $partyPokemon);
                            break;

                        case "party_type_id":
                            $partyType = $val;
                            if ($typeRow = $this->getNameFromId("type", intval($val)))
                                $partyType = $typeRow[ 'name' ];
                            $evolution->addRequirement(new EvolutionRequirement(EvolutionRequirement::Party_Type), $partyType);
                            break;

                        case "trade_species_id":
                            $tradeFor = $val;
                            if ($pokeRow = $this->getNameFromId("pokemon_species", intval($val)))
                                $tradeFor = $pokeRow[ 'name' ];
                            $evolution->addRequirement(new EvolutionRequirement(EvolutionRequirement::Trade_For), $tradeFor);
                            break;
                    }
                }
            }

            $pokemons[ $row[ 'preevo' ] ]->addEvolution(clone $evolution);
            if (isset($pokemons[ $row[ 'evo' ] ]))
                $pokemons[ $row[ 'evo' ] ]->addPreEvolution(clone $evolution);
        }

        //  Populate type(s)
        $type = $this->getPokemonType();
        foreach ($type as $row)
            $pokemons[ $row[ 'pokemon_id' ] ]
                ->setType($row[ 'slot' ] - 1, $row[ 'identifier' ]);

        //  Populate base stats and effort values rewarded
        $stats = $this->getPokemonStats();
        foreach ($stats as $row) {
            $stat = new Stat(Stat::findValue($row[ 'identifier' ]));

            $pokemons[ $row[ 'pokemon_id' ] ]
                ->setBaseStat($stat, $row[ 'base_stat' ]);
            $pokemons[ $row[ 'pokemon_id' ] ]
                ->setEVYield($stat, $row[ 'effort' ]);
        }

        //  Populate abilities
        $ability = $this->getPokemonAbility();
        foreach ($ability as $row)
            $pokemons[ $row[ 'pokemon_id' ] ]
                ->setAbility($row[ 'slot' ] - 1, $row[ 'name' ]);

        $dexEntries = $this->getPokemonDexEntries();
        foreach ($dexEntries as $row)
            $pokemons[ $row[ 'species_id' ] ]
                ->setDexEntry(
                    preg_replace("/\s+/", " ", $row[ 'flavor_text' ]),
                    Version::fromName($row[ 'version' ]),
                    Language::fromName($row[ 'language' ])
                );

        return $pokemons;
    }


    /**
     * @return array
     */
    public function getSpecies() {
        return $this->query(
            'SELECT ps.*, pc.identifier AS color, gr.identifier AS growth, phn.name AS habitat
            FROM pokemon_species AS ps
            INNER JOIN pokemon_colors AS pc
            ON ps.color_id=pc.id
            INNER JOIN growth_rates AS gr
            ON ps.growth_rate_id=gr.id
            LEFT JOIN pokemon_habitat_names AS phn
            ON ps.habitat_id=phn.pokemon_habitat_id            
            WHERE phn.local_language_id=9
            OR ps.habitat_id IS NULL
            ORDER BY ps.id ASC'
        );
    }


    /**
     * @return array
     */
    public function getName() {
        return $this->query(
            'SELECT ln.name AS "language", psn.*
            FROM language_names AS ln
            INNER JOIN pokemon_species_names AS psn
            ON ln.language_id=psn.local_language_id
            WHERE ln.local_language_id=9
            ORDER BY psn.pokemon_species_id ASC'
        );
    }


    /**
     * @return array
     */
    public function getEggGroup() {
        return $this->query(
            'SELECT egp.name, peg.species_id
            FROM egg_group_prose AS egp
            INNER JOIN pokemon_egg_groups AS peg
            ON peg.egg_group_id=egp.egg_group_id
            WHERE egp.local_language_id=9
            ORDER BY peg.species_id ASC'
        );
    }


    /**
     * @return array
     */
    public function getPokemonRow() {
        return $this->query(
            'SELECT *
            FROM pokemon
            ORDER BY id ASC'
        );
    }


    /**
     * @return array
     */
    public function getAlt() {
        return $this->query(
            'SELECT *
            FROM pokemon_forms
            ORDER BY pokemon_id ASC'
        );
    }


    /**
     * @return array
     */
    public function getAltName() {
        return $this->query(
            'SELECT ln.name AS "language", pfn.form_name, pfn.pokemon_name, pf.pokemon_id, pf.form_order
            FROM language_names AS ln
            INNER JOIN pokemon_form_names AS pfn
            ON ln.language_id=pfn.local_language_id
            INNER JOIN pokemon_forms AS pf
            ON pfn.pokemon_form_id=pf.id            
            WHERE ln.local_language_id=9 
            ORDER BY pfn.pokemon_form_id ASC'
        );
    }


    /**
     * @return array
     */
    public function getDexnum() {
        return $this->query(
            'SELECT pdn.species_id, pdn.pokedex_number, pp.name
            FROM pokemon_dex_numbers AS pdn
            INNER JOIN pokedex_prose AS pp
            ON pdn.pokedex_id=pp.pokedex_id
            WHERE pp.local_language_id=9
            ORDER BY pdn.species_id ASC'
        );
    }


    /**
     * @return array
     */
    public function getEvolution() {
        return $this->query(
            'SELECT ps.id AS evo, ps.evolves_from_species_id AS preevo, ps.evolution_chain_id,
              pe.*, pe.id AS entry, et.identifier AS method
            FROM pokemon_species AS ps
            INNER JOIN pokemon_evolution AS pe
            ON ps.id=pe.evolved_species_id
            INNER JOIN evolution_triggers AS et
            ON et.id=pe.evolution_trigger_id
            ORDER BY evo ASC, pe.location_id ASC'
        );
    }


    /**
     * @param string $table
     * @param int    $id
     * @return bool
     */
    public function getNameFromId(string $table, int $id) {
        if (!in_array($table, [ "item", "pokemon_species", "pokemon_form", "move", "ability", "location", "type", "language" ]))
            return false;

        if ($table == "location")
            $query = '  SELECT ln.name AS name, l.region_id AS generation
                        FROM location_names AS ln
                        INNER JOIN locations AS l
                        ON ln.location_id=l.id
                        WHERE l.id=? AND ln.local_language_id=9';
        else
            $query = 'SELECT "name"
                      FROM '.$table.'_names
                      WHERE '.$table.'_id=?
                      AND local_language_id=9';

        $res = $this->query($query, [ $id ]);

        if (!$res)
            return false;

        return $res[ 0 ];
    }


    /**
     * @return array
     */
    public function getPokemonType() {
        return $this->query(
            'SELECT pt.*, t.identifier
            FROM pokemon_types AS pt
            INNER JOIN types AS t
            ON t.id=pt.type_id
            ORDER BY pt.pokemon_id ASC'
        );
    }


    /**
     * @return array
     */
    public function getPokemonStats() {
        return $this->query(
            'SELECT ps.*, s.identifier
            FROM pokemon_stats AS ps
            INNER JOIN stats AS s
            ON s.id=ps.stat_id
            ORDER BY ps.pokemon_id ASC'
        );
    }


    /**
     * @return array
     */
    public function getPokemonAbility() {
        return $this->query(
            'SELECT pa.*, an.name
            FROM pokemon_abilities AS pa
            INNER JOIN ability_names AS an
            ON an.ability_id=pa.ability_id 
            WHERE an.local_language_id=9
            ORDER BY pa.pokemon_id ASC'
        );
    }


    /**
     * @return array
     */
    public function getPokemonDexEntries() {
        return $this->query(
            'SELECT ln.name AS "language", vn.name AS version, psft.*
            FROM pokemon_species_flavor_text AS psft
            INNER JOIN language_names AS ln
            ON ln.language_id=psft.language_id
            INNER JOIN version_names AS vn
            ON vn.version_id=psft.version_id
            WHERE ln.local_language_id=9 AND vn.local_language_id=9
            ORDER BY psft.species_id ASC'
        );
    }


    /**
     * @param Abilities $abilities
     * @return Abilities
     * @throws PokemonBaseException
     */
    public function getAbilities(Abilities $abilities): Abilities {
        /** @var Ability[] $abilities */
        $abilities = new Abilities();

        $names = $this->getAbilityNames();
        foreach ($names as $row) {
            if (!isset($abilities[ $row[ 'ability_id' ] ])) {
                $abilities[ $row[ 'ability_id' ] ] = new Ability();

                $abilities[ $row[ 'ability_id' ] ]
                    ->setId((int)$row[ 'ability_id' ]);

                $abilities[ $row[ 'ability_id' ] ]
                    ->setGeneration((int)$row[ 'generation_id' ]);
            }

            $abilities[ $row[ 'ability_id' ] ]
                ->setName((string)$row[ 'name' ], Language::fromName($row[ 'language' ]));
        }

        $text = $this->getAbilityText();
        foreach ($text as $row) {
            $abilities[ $row[ 'ability_id' ] ]
                ->setText(
                    self::stripCodes($row[ 'flavor_text' ]),
                    Version::fromName($row[ 'identifier' ]),
                    Language::fromName($row[ 'language' ])
                );
        }

        $effect = $this->getAbilityEffect();
        foreach ($effect as $row) {
            $abilities[ $row[ 'ability_id' ] ]
                ->setEffect(self::stripCodes($row[ 'effect' ]));
            $abilities[ $row[ 'ability_id' ] ]
                ->setShortEffect(self::stripCodes($row[ 'short_effect' ]));
        }

        return $abilities;
    }


    /**
     * @return array
     */
    public function getAbilityNames() {
        return $this->query(
            'SELECT ln.name AS `language`, an.*, a.generation_id
            FROM language_names ln, ability_names an, abilities a
            WHERE an.ability_id=a.id AND a.is_main_series=1 AND ln.local_language_id=9 AND ln.language_id=an.local_language_id
            ORDER BY an.ability_id ASC'
        );
    }


    /**
     * @return array
     */
    public function getAbilityText() {
        return $this->query(
            'SELECT ln.name AS "language", aft.*, vg.identifier
            FROM ability_flavor_text AS aft
            INNER JOIN version_groups AS vg
            ON aft.version_group_id=vg.id
            INNER JOIN language_names AS ln
            ON ln.language_id=aft.language_id
            WHERE ln.local_language_id=9 
            ORDER BY aft.ability_id ASC'
        );
    }


    /**
     * @param $string
     * @return mixed|string
     */
    private static function stripCodes($string) {
        $string = preg_replace_callback(
            '/\[([a-z0-9\- ]*)\]\{[a-z]+:([a-z0-9\-]+)\}/i',

            function ($match) {
                if (strlen($match[ 1 ]))
                    return $match[ 1 ];

                return ucwords(str_replace("-", " ", $match[ 2 ]));
            },

            $string);

        $string = mb_ereg_replace('(\s|\x0C)+', " ", $string);

        return $string;
    }


    /**
     * @return array
     */
    public function getAbilityEffect() {
        return $this->query(
            'SELECT *
            FROM ability_prose
            WHERE local_language_id=9
            ORDER BY ability_id ASC'
        );
    }


    /**
     * @param Items $items
     * @return Items
     * @throws \Utsubot\Pokemon\Item\ItemException
     * @throws PokemonBaseException
     */
    public function getItems(Items $items): Items {
        /** @var Item[] $items */
        $items = new Items();

        $itemRow = $this->getItemRow();
        foreach ($itemRow as $row) {
            $item = new Item();

            $item->setId((int)$row[ 'id' ]);

            $item->setCost((int)$row[ 'cost' ]);

            $item->setFlingPower((int)$row[ 'fling_power' ]);

            $item->setCategory($row[ 'category' ]);

            $item->setPocket($row[ 'pocket' ] - 1);

            $items[ $row[ 'id' ] ] = $item;
        }

        $fling = $this->getItemFling();
        foreach ($fling as $row)
            $items[ $row[ 'id' ] ]
                ->setFlingEffect($row[ 'fling_effect' ] - 1);

        $names = $this->getItemNames();
        foreach ($names as $row)
            $items[ $row[ 'item_id' ] ]
                ->setName($row[ 'name' ], Language::fromName($row[ 'language' ]));

        $text = $this->getItemText();
        foreach ($text as $row) {
            $items[ $row[ 'item_id' ] ]
                ->setText(
                    self::stripCodes($row[ 'flavor_text' ]),
                    Version::fromName($row[ 'identifier' ]),
                    Language::fromName($row[ 'language' ]));
        }

        $effect = $this->getItemEffect();
        foreach ($effect as $row) {
            $items[ $row[ 'item_id' ] ]
                ->setEffect(self::stripCodes($row[ 'effect' ]));
            $items[ $row[ 'item_id' ] ]
                ->setShortEffect(self::stripCodes($row[ 'short_effect' ]));
        }

        $flags = $this->getItemFlags();
        foreach ($flags as $row)
            $items[ $row[ 'item_id' ] ]
                ->addFlag(2 ** ($row[ 'item_flag_id' ] - 1));

        return $items;
    }


    /**
     * @return array
     */
    public function getItemRow() {
        return $this->query(
            'SELECT i.*, icp.name AS category, ipn.item_pocket_id AS pocket
            FROM items AS i
            INNER JOIN item_category_prose AS icp
            ON i.category_id=icp.item_category_id
            INNER JOIN item_categories AS ic
            ON i.category_id=ic.id
            INNER JOIN item_pocket_names AS ipn
            ON ic.pocket_id=ipn.item_pocket_id
            WHERE icp.local_language_id=9
            AND ipn.local_language_id=9
            ORDER BY i.id ASC'
        );
    }


    /**
     * @return array
     */
    public function getItemFling() {
        return $this->query(
            'SELECT i.*, ifep.item_fling_effect_id AS fling_effect
            FROM items AS i
            INNER JOIN item_fling_effect_prose AS ifep
            ON i.fling_effect_id=ifep.item_fling_effect_id 
            WHERE ifep.local_language_id=9
            ORDER BY i.id ASC'
        );
    }


    /**
     * @return array
     */
    public function getItemNames() {
        return $this->query(
            'SELECT ln.name AS "language", "in".*
            FROM item_names AS "in"
            INNER JOIN language_names AS ln
            ON ln.language_id="in".local_language_id
            WHERE ln.local_language_id=9
            ORDER BY "in".item_id ASC'
        );
    }


    /**
     * @return array
     */
    public function getItemText() {
        return $this->query(
            'SELECT ln.name AS "language", ift.*, vg.identifier
            FROM item_flavor_text AS ift
            INNER JOIN version_groups AS vg
            ON ift.version_group_id=vg.id
            INNER JOIN language_names AS ln
            ON ln.language_id=ift.language_id
            WHERE ln.local_language_id=9
            ORDER BY ift.item_id ASC'
        );
    }


    /**
     * @return array
     */
    public function getItemEffect() {
        return $this->query(
            'SELECT *
            FROM item_prose
            WHERE local_language_id=9
            ORDER BY item_id ASC'
        );
    }


    /**
     * @return array
     */
    public function getItemFlags() {
        return $this->query(
            'SELECT ifm.item_id, ifp.name, ifp.description, ifp.item_flag_id
            FROM item_flag_map AS ifm
            INNER JOIN item_flag_prose AS ifp
            ON ifm.item_flag_id=ifp.item_flag_id
            WHERE ifp.local_language_id=9
            ORDER BY ifm.item_flag_id ASC'
        );
    }


    /**
     * @param Natures $natures
     * @return Natures
     * @throws PokemonBaseException
     */
    public function getNatures(Natures $natures): Natures {
        /** @var Nature[] $natures */
        $natures = new Natures();

        $natureAttributes = $this->getNatureAttributes();
        foreach ($natureAttributes as $row) {
            $nature = new Nature();

            $nature->setId((int)$row[ 'id' ]);

            if ($row[ 'increases' ] != $row[ 'decreases' ]) {
                $nature->setIncreases(Stat::fromName($row[ 'increases' ]));
                $nature->setDecreases(Stat::fromName($row[ 'decreases' ]));

                $nature->setLikesAttr(Attribute::fromName($row[ 'likes' ]));
                $nature->setDislikesAttr(Attribute::fromName($row[ 'dislikes' ]));

                $nature->setLikesFlavor(Flavor::fromName($row[ 'likesFlavor' ]));
                $nature->setDislikesFlavor(Flavor::fromName($row[ 'dislikesFlavor' ]));
            }

            $natures[ $row[ 'id' ] ] = $nature;
        }

        $natureNames = $this->getNatureNames();
        foreach ($natureNames as $row)
            $natures[ $row[ 'nature_id' ] ]->setName($row[ 'name' ], Language::fromName($row[ 'language' ]));

        return $natures;
    }


    /**
     * @return array
     */
    public function getNatureAttributes() {
        return $this->query(
            'SELECT sn1.name AS increases, sn2.name AS decreases, ctn1.name AS likes, ctn2.name AS dislikes,
                    ctn1.flavor AS likesFlavor, ctn2.flavor AS dislikesFlavor, n.id                      
            FROM natures AS n            
            INNER JOIN stat_names AS sn1
            ON sn1.stat_id=n.increased_stat_id
            INNER JOIN stat_names AS sn2 
            ON sn2.stat_id=n.decreased_stat_id
            INNER JOIN contest_type_names AS ctn1
            ON ctn1.contest_type_id=n.likes_flavor_id
            INNER JOIN contest_type_names AS ctn2
            ON ctn2.contest_type_id=n.hates_flavor_id
            WHERE sn1.local_language_id=9
            AND sn2.local_language_id=9
            AND ctn1.local_language_id=9
            AND ctn2.local_language_id=9          
            ORDER BY n.id ASC'
        );
    }


    /**
     * @return array
     */
    public function getNatureNames() {
        return $this->query(
            'SELECT ln.name AS "language", nn.*
            FROM nature_names AS nn
            INNER JOIN language_names AS ln
            ON ln.language_id=nn.local_language_id            
            WHERE ln.local_language_id=9            
            ORDER BY nn.nature_id ASC'
        );
    }


    /**
     * @param $id
     */
    public function getLocation($id) {
    }


    /**
     * @param Moves $moves
     * @return Moves
     * @throws \Utsubot\Pokemon\Move\MoveException
     * @throws PokemonBaseException
     */
    public function getMoves(Moves $moves): Moves {
        /** @var Move[] $moves */
        $moves = new Moves();

        $moveRow = $this->getMoveRow();
        foreach ($moveRow as $row) {
            $move = new Move();

            $move->setId((int)$row[ 'id' ]);

            $move->setGeneration((int)$row[ 'generation_id' ]);

            $move->setPower((int)$row[ 'power' ]);

            $move->setPP((int)$row[ 'pp' ]);

            $move->setAccuracy((int)$row[ 'accuracy' ]);

            $move->setPriority((int)$row[ 'priority' ]);

            $move->setType(ucfirst($row[ 'type' ]));

            $move->setDamageType(ucwords($row[ 'damage' ]));

            $move->setTarget(ucwords($row[ 'target' ]));

            $move->setEffect(str_replace("\$effect_chance", $row[ 'effect_chance' ], self::stripCodes($row[ 'effect' ])));

            $move->setShortEffect(str_replace("\$effect_chance", $row[ 'effect_chance' ], self::stripCodes($row[ 'short_effect' ])));

            $moves[ $row[ 'id' ] ] = $move;
        }

        $moveContest = $this->getMoveContest();
        foreach ($moveContest as $row) {
            $moves[ $row[ 'id' ] ]
                ->setContestType(ucwords($row[ 'contestType' ]));

            $moves[ $row[ 'id' ] ]
                ->setContestAppeal((int)$row[ 'contestAppeal' ]);

            $moves[ $row[ 'id' ] ]
                ->setContestJam((int)$row[ 'jam' ]);

            $moves[ $row[ 'id' ] ]
                ->setContestFlavorText($row[ 'contestFlavor' ]);

            $moves[ $row[ 'id' ] ]
                ->setContestEffect($row[ 'contestEffect' ]);

            $moves[ $row[ 'id' ] ]
                ->setSuperContestAppeal((int)$row[ 'superContestAppeal' ]);

            $moves[ $row[ 'id' ] ]
                ->setSuperContestFlavorText($row[ 'superContestFlavor' ]);
        }

        $moveNames = $this->getMovesNames();
        foreach ($moveNames as $row)
            $moves[ $row[ 'move_id' ] ]
                ->setName($row[ 'name' ], Language::fromName($row[ 'language' ]));

        return $moves;
    }


    /**
     * @return array
     */
    public function getMoveRow() {
        return $this->query(
            'SELECT m.*, t.identifier AS "type", mdc.identifier AS damage, mt.identifier AS target, mep.effect, mep.short_effect
            FROM moves AS m
            INNER JOIN types AS t
            ON m.type_id=t.id
            INNER JOIN move_damage_classes AS mdc
            ON m.damage_class_id=mdc.id
            INNER JOIN move_targets AS mt
            ON m.target_id=mt.id
            INNER JOIN move_effect_prose AS mep
            ON m.effect_id = mep.move_effect_id
            WHERE mep.local_language_id=9            
            ORDER BY m.id ASC'
        );
    }


    /**
     * @return array
     */
    public function getMoveContest() {
        return $this->query(
            'SELECT m.*, ct.identifier AS contestType, ce.appeal AS contestAppeal, ce.jam, cep.flavor_text AS contestFlavor,
                    cep.effect AS contestEffect, sce.appeal AS superContestAppeal, scep.flavor_text AS superContestFlavor                      
            FROM moves AS m
            INNER JOIN contest_types AS ct
            ON m.contest_type_id=ct.id
            INNER JOIN contest_effects AS ce
            ON m.contest_effect_id=ce.id
            INNER JOIN contest_effect_prose AS cep
            ON ce.id=cep.contest_effect_id
            INNER JOIN super_contest_effects AS sce
            ON m.super_contest_effect_id=sce.id
            INNER JOIN super_contest_effect_prose scep
            ON sce.id=scep.super_contest_effect_id
            WHERE cep.local_language_id=9
            AND scep.local_language_id=9                      
            ORDER BY m.id ASC'
        );
    }


    /**
     * @return array
     */
    public function getMovesNames() {
        return $this->query(
            'SELECT ln.name AS "language", mn.*
            FROM move_names AS mn
            INNER JOIN language_names AS ln
            ON ln.language_id=mn.local_language_id
            WHERE ln.local_language_id=9            
            ORDER BY mn.move_id ASC'
        );
    }


    /**
     * @param array $LearnedMoves
     * @return array
     */
    public function getLearnedMoves(array $LearnedMoves): array {
        /** @var LearnedMove[] $LearnedMoves */
        $LearnedMoves = [ ];

        $learnedMoveRow = $this->getLearnedMoveRows();
        foreach ($learnedMoveRow as $row) {
            $versionGroup = Version::fromName($row['version']);
            $method = MoveMethod::fromName($row['method']);

            $LearnedMoves[ ] = new LearnedMove($row['pokemon_id'], $row['move_id'], $versionGroup, $method, $row['level']);
        }

        return $LearnedMoves;

    }

    /**
     * @return array
     */
    public function getLearnedMoveRows() {
        return $this->query(
            'SELECT pm.*, vg.identifier AS version, pmm.identifier AS method
            FROM pokemon_moves AS pm
            INNER JOIN version_groups AS vg
            ON vg.id=pm.version_group_id
            INNER JOIN pokemon_move_methods AS pmm
            ON pmm.id=pm.pokemon_move_method_id
            ORDER BY pm.pokemon_id ASC, pm.version_group_id ASC, move_id ASC, pokemon_move_method_id ASC'
        );
    }
}
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
    MySQLDatabaseCredentials
};
use Utsubot\Pokemon\Pokemon\{
    Pokemon,
    Evolution,
    Method,
    Requirement
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
        parent::__construct(MySQLDatabaseCredentials::createFromConfig("veekun"));
    }


    /**
     * @return PokemonGroup
     * @throws PokemonBaseException
     * @throws \Utsubot\Pokemon\Pokemon\PokemonException
     * @throws \Utsubot\EnumException
     */
    public function getPokemon(): PokemonGroup  {
        /** @var Pokemon[] $pokemon */
        $pokemon = new PokemonGroup();

        $nationalDex = new Dex(Dex::National);

        /*	Main pokemon, no forms
            Includes generation, capture rate, color, base happiness, habitat, baby (y/n), steps to hatch, gender ratio	*/
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

            $pokemon[ $row[ 'id' ] ] = $newPokemon;
        }

        //	Populate names in all languages, as well as dex species ("genus")
        $name = $this->getName();
        foreach ($name as $row) {
            $language = Language::fromName($row[ 'language' ]);
            $pokemon[ $row[ 'pokemon_species_id' ] ]
                ->setName((string)$row[ 'name' ], $language);

            $pokemon[ $row[ 'pokemon_species_id' ] ]
                ->setSpecies((string)$row[ 'genus' ], $language);
        }

        //	Populate egg breeding groups
        $eggGroup = $this->getEggGroup();
        foreach ($eggGroup as $row)
            $pokemon[ $row[ 'species_id' ] ]
                ->addEggGroup((string)$row[ 'name' ]);

        /*	Add entries for forms with stat/type changes
            Update these and base entries with height, weight, base experience, and dexnum	*/
        $pokemonRow = $this->getPokemonRow();
        foreach ($pokemonRow as $row) {
            //	Copy base info from main species for alts
            if (!isset($pokemon[ $row[ 'id' ] ]))
                $pokemon[ $row[ 'id' ] ] = clone $pokemon[ $row[ 'species_id' ] ];

            //	Alt pokemon, update main name to reflect that
            if ($row[ 'id' ] > 10000) {
                #$pokemon[$row['id']]
                #    ->setName(ucwords($row['identifier']), new Language(Language::English));
                $pokemon[ $row[ 'id' ] ]
                    ->setId($row[ 'id' ]);
            }

            $pokemon[ $row[ 'id' ] ]
                ->setHeight($row[ 'height' ] / 10);
            $pokemon[ $row[ 'id' ] ]
                ->setWeight($row[ 'weight' ] / 10);
            $pokemon[ $row[ 'id' ] ]
                ->setBaseExp((int)$row[ 'base_experience' ]);
            $pokemon[ $row[ 'id' ] ]
                ->setDexNumber((int)$row[ 'species_id' ], $nationalDex);
        }

        //	Populate other alt forms (semantic changes), create references to all alts in main pokemon
        $alt = $this->getAlt();
        foreach ($alt as $row) {
            //	If form_identifier isn't blank, it is an actual alt form, not a row for base pokemon
            if ($row[ 'form_identifier' ]) {
                $info = [
                    'name' => $row[ 'identifier' ],
                    'form' => $row[ 'form_identifier' ],
                    'id'   => $row[ 'pokemon_id' ]
                ];

                //	References a form with its own 'pokemon' entry, meaning changed stats/type/etc, but we want a reference entry under the original pokemon
                if ($row[ 'pokemon_id' ] > 10000)
                    $pokemon[ $pokemon[ $row[ 'pokemon_id' ] ]->getDexNumber($nationalDex) ]
                        ->addToAlternateForm($row[ 'form_order' ] - 1, $info);

                //	Semantic form change, the pokemon_id will be the same as the original pokemon
                else
                    $pokemon[ $row[ 'pokemon_id' ] ]
                        ->addToAlternateForm($row[ 'form_order' ] - 1, $info);
            }
        }

        //	Populate names in all languages for alt forms
        $altName = $this->getAltName();
        foreach ($altName as $row) {
            $language = Language::fromName($row[ 'language' ]);

            //	Compound form name with original pokemon name
            if ($row[ 'pokemon_id' ] > 10000) {
                $name = $row[ 'pokemon_name' ] ?? $row[ 'form_name' ];
                $pokemon[ $row[ 'pokemon_id' ] ]
                    ->setName($name, $language);
            }

            //	Save form name in original pokemon
            else
                $pokemon[ $pokemon[ $row[ 'pokemon_id' ] ]->getDexNumber($nationalDex) ]
                    ->addToAlternateForm(
                        $row[ 'form_order' ] - 1,
                        [ 'names' => [ $language->getValue() => $row[ 'form_name' ] ] ]
                    );
        }

        //	Populate all dex numbers
        $dexnum = $this->getDexnum();
        foreach ($dexnum as $row)
            $pokemon[ $row[ 'species_id' ] ]
                ->setDexNumber((int)$row[ 'pokedex_number' ], new Dex(Dex::findValue($row[ 'name' ])));

        //	Populate evolution data
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
                    $trigger = new Method(Method::Level_Up);
                    break;
                case "trade":
                    $trigger = new Method(Method::Trade);
                    break;
                case "use-item":
                    $trigger = new Method(Method::Use);
                    break;
                case "shed":
                    $trigger = new Method(Method::Shed);
                    break;
            }
            $evolution->setMethod($trigger);

            foreach ($row as $key => $val) {
                if ($val) {
                    switch ($key) {
                        case "minimum_level":
                            $evolution->addRequirement(new Requirement(Requirement::Level), $val);
                            break;
                        case "gender_id":
                            $evolution->addRequirement(new Requirement(Requirement::Gender), $genderIds[ $val ]);
                            break;
                        case "time_of_day":
                            $evolution->addRequirement(new Requirement(Requirement::Time), $val);
                            break;
                        case "minimum_happiness":
                            $evolution->addRequirement(new Requirement(Requirement::Happiness), $val);
                            break;
                        case "minimum_beauty":
                            $evolution->addRequirement(new Requirement(Requirement::Beauty), $val);
                            break;
                        case "minimum_affection":
                            $evolution->addRequirement(new Requirement(Requirement::Affection), $val);
                            break;
                        case "needs_overworld_rain":
                            $evolution->addRequirement(new Requirement(Requirement::Rain), true);
                            break;
                        case "turn_upside_down":
                            $evolution->addRequirement(new Requirement(Requirement::Upside_Down), true);
                            break;
                        case "relative_physical_stats":
                            $evolution->addRequirement(new Requirement(Requirement::Relative_Stats), $relativeStats[ $val ]);
                            break;

                        case "trigger_item_id":
                            $useItem = $val;
                            if ($itemRow = $this->getNameFromId("item", intval($val)))
                                $useItem = $itemRow[ 'name' ];
                            $evolution->addRequirement(new Requirement(Requirement::Use_Item), $useItem);
                            break;

                        case "location_id":
                            $location = $val;
                            if ($locationRow = $this->getNameFromId("location", intval($val))) {
                                $location = $locationRow[ 'name' ];
                                $evolution->addRequirement(new Requirement(Requirement::Generation), $locationRow[ 'generation' ]);
                            }
                            $evolution->addRequirement(new Requirement(Requirement::Location), $location);
                            break;

                        case "held_item_id":
                            $holdItem = $val;
                            if ($itemRow = $this->getNameFromId("item", intval($val)))
                                $holdItem = $itemRow[ 'name' ];
                            $evolution->addRequirement(new Requirement(Requirement::Hold_Item), $holdItem);
                            break;

                        case "known_move_id":
                            $knowsMove = $val;
                            if ($moveRow = $this->getNameFromId("move", intval($val)))
                                $knowsMove = $moveRow[ 'name' ];
                            $evolution->addRequirement(new Requirement(Requirement::Knows_Move), $knowsMove);
                            break;

                        case "known_move_type_id":
                            $knowsMoveType = $val;
                            if ($typeRow = $this->getNameFromId("type", intval($val)))
                                $knowsMoveType = $typeRow[ 'name' ];
                            $evolution->addRequirement(new Requirement(Requirement::Knows_Move_Type), $knowsMoveType);
                            break;

                        case "party_species_id":
                            $partyPokemon = $val;
                            if ($pokeRow = $this->getNameFromId("pokemon_species", intval($val)))
                                $partyPokemon = $pokeRow[ 'name' ];
                            $evolution->addRequirement(new Requirement(Requirement::Party_Pokemon), $partyPokemon);
                            break;

                        case "party_type_id":
                            $partyType = $val;
                            if ($typeRow = $this->getNameFromId("type", intval($val)))
                                $partyType = $typeRow[ 'name' ];
                            $evolution->addRequirement(new Requirement(Requirement::Party_Type), $partyType);
                            break;

                        case "trade_species_id":
                            $tradeFor = $val;
                            if ($pokeRow = $this->getNameFromId("pokemon_species", intval($val)))
                                $tradeFor = $pokeRow[ 'name' ];
                            $evolution->addRequirement(new Requirement(Requirement::Trade_For), $tradeFor);
                            break;
                    }
                }
            }

            $pokemon[ $row[ 'preevo' ] ]->addEvolution(clone $evolution);
            if (isset($pokemon[ $row[ 'evo' ] ]))
                $pokemon[ $row[ 'evo' ] ]->addPreEvolution(clone $evolution);
        }

        //	Populate type(s)
        $type = $this->getPokemonType();
        foreach ($type as $row)
            $pokemon[ $row[ 'pokemon_id' ] ]
                ->setType($row[ 'slot' ] - 1, $row[ 'identifier' ]);

        //	Populate base stats and effort values rewarded
        $stats = $this->getPokemonStats();
        foreach ($stats as $row) {
            $stat = new Stat(Stat::findValue($row[ 'identifier' ]));

            $pokemon[ $row[ 'pokemon_id' ] ]
                ->setBaseStat($stat, $row[ 'base_stat' ]);
            $pokemon[ $row[ 'pokemon_id' ] ]
                ->setEVYield($stat, $row[ 'effort' ]);
        }

        //	Populate abilities
        $ability = $this->getPokemonAbility();
        foreach ($ability as $row)
            $pokemon[ $row[ 'pokemon_id' ] ]
                ->setAbility($row[ 'slot' ] - 1, $row[ 'name' ]);

        $dexEntries = $this->getPokemonDexEntries();
        foreach ($dexEntries as $row)
            $pokemon[ $row[ 'species_id' ] ]
                ->setDexEntry(
                    preg_replace("/\s+/", " ", $row[ 'flavor_text' ]),
                    Version::fromName($row[ 'version' ]),
                    Language::fromName($row[ 'language' ])
                );

        return $pokemon;
    }


    /**
     * @return array|bool|int
     */
    public function getSpecies() {
        return $this->query(
            "SELECT ps.*, pc.identifier AS color, gr.identifier AS growth, phn.name AS habitat
            FROM pokemon_species ps, pokemon_colors pc, growth_rates gr, pokemon_habitat_names phn
            WHERE ((ps.habitat_id=phn.pokemon_habitat_id AND phn.local_language_id=9) OR ps.habitat_id IS NULL) AND ps.color_id=pc.id AND ps.growth_rate_id=gr.id
            ORDER BY ps.id ASC"
        );
    }


    /**
     * @return array|bool|int
     */
    public function getName() {
        return $this->query(
            "SELECT ln.name AS `language`, psn.*
            FROM language_names ln, pokemon_species_names psn
            WHERE ln.local_language_id=9 AND ln.language_id=psn.local_language_id
            ORDER BY psn.pokemon_species_id ASC"
        );
    }


    /**
     * @return array|bool|int
     */
    public function getEggGroup() {
        return $this->query(
            "SELECT `egp`.`name`, `peg`.`species_id`
            FROM `egg_group_prose` `egp`, `pokemon_egg_groups` `peg`
            WHERE `peg`.`egg_group_id`=`egp`.`egg_group_id` AND `egp`.`local_language_id`=9
            ORDER BY `peg`.`species_id` ASC"
        );
    }


    /**
     * @return array|bool|int
     */
    public function getPokemonRow() {
        return $this->query(
            "SELECT *
            FROM pokemon
            ORDER BY id ASC"
        );
    }


    /**
     * @return array|bool|int
     */
    public function getAlt() {
        return $this->query(
            "SELECT *
            FROM pokemon_forms
            ORDER BY pokemon_id ASC"
        );
    }


    /**
     * @return array|bool|int
     */
    public function getAltName() {
        return $this->query(
            "SELECT ln.name AS `language`, pfn.form_name, pfn.pokemon_name, pf.pokemon_id, pf.form_order
            FROM language_names ln, pokemon_form_names pfn, pokemon_forms pf
            WHERE ln.local_language_id=9 AND ln.language_id=pfn.local_language_id AND pfn.pokemon_form_id=pf.id
            ORDER BY pfn.pokemon_form_id ASC"
        );
    }


    /**
     * @return array|bool|int
     */
    public function getDexnum() {
        return $this->query(
            "SELECT pdn.species_id, pdn.pokedex_number, pp.name
            FROM pokemon_dex_numbers pdn, pokedex_prose pp
            WHERE pdn.pokedex_id=pp.pokedex_id AND pp.local_language_id=9
            ORDER BY pdn.species_id ASC"
        );
    }


    /**
     * @return array|bool|int
     */
    public function getEvolution() {
        return $this->query(
            "SELECT ps.id AS evo, ps.evolves_from_species_id AS preevo, ps.evolution_chain_id, pe.*, pe.id AS entry, et.identifier AS method
            FROM pokemon_species ps, pokemon_evolution pe, evolution_triggers et
            WHERE ps.id=pe.evolved_species_id AND et.id=pe.evolution_trigger_id
            ORDER BY evo ASC, pe.location_id ASC"
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
            $query = "  SELECT ln.name AS `name`, l.region_id AS generation
                        FROM location_names ln, locations l
                        WHERE ln.location_id=l.id AND l.id=? AND ln.local_language_id=9
                        LIMIT 1";
        else
            $query = "SELECT `name` FROM ${table}_names WHERE ${table}_id=? AND local_language_id=9 LIMIT 1";

        $res = $this->query($query, [ $id ]);

        if (!$res)
            return false;

        return $res[ 0 ];
    }


    /**
     * @return array|bool|int
     */
    public function getPokemonType() {
        return $this->query(
            "SELECT pt.*, t.identifier
            FROM pokemon_types pt, types t
            WHERE t.id=pt.type_id
            ORDER BY pt.pokemon_id ASC"
        );
    }


    /**
     * @return array|bool|int
     */
    public function getPokemonStats() {
        return $this->query(
            "SELECT ps.*, s.identifier
            FROM pokemon_stats ps, stats s
            WHERE s.id=ps.stat_id
            ORDER BY ps.pokemon_id ASC"
        );
    }


    /**
     * @return array|bool|int
     */
    public function getPokemonAbility() {
        return $this->query(
            "SELECT pa.*, an.name
            FROM pokemon_abilities pa, ability_names an
            WHERE an.ability_id=pa.ability_id AND an.local_language_id=9
            ORDER BY pa.pokemon_id ASC"
        );
    }


    /**
     * @return array|bool|int
     */
    public function getPokemonDexEntries() {
        return $this->query(
            "SELECT ln.name AS `language`, vn.name AS version, psft.*
            FROM language_names ln, pokemon_species_flavor_text psft, version_names vn
            WHERE ln.language_id=psft.language_id AND ln.local_language_id=9 AND vn.version_id=psft.version_id AND vn.local_language_id=9
            ORDER BY psft.species_id ASC"
        );
    }


    /**
     * @return AbilityGroup
     * @throws PokemonBaseException
     */
    public function getAbilities(): AbilityGroup {
        /** @var Ability[] $abilities */
        $abilities = new AbilityGroup();

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
     * @return array|bool|int
     */
    public function getAbilityNames() {
        return $this->query(
            "SELECT ln.name AS `language`, an.*, a.generation_id
            FROM language_names ln, ability_names an, abilities a
            WHERE an.ability_id=a.id AND a.is_main_series=1 AND ln.local_language_id=9 AND ln.language_id=an.local_language_id
            ORDER BY an.ability_id ASC"
        );
    }


    /**
     * @return array|bool|int
     */
    public function getAbilityText() {
        return $this->query(
            "SELECT ln.name AS `language`, aft.*, vg.identifier
            FROM language_names ln, ability_flavor_text aft, version_groups vg
            WHERE ln.local_language_id=9 AND ln.language_id=aft.language_id AND aft.version_group_id=vg.id
            ORDER BY aft.ability_id ASC"
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
     * @return array|bool|int
     */
    public function getAbilityEffect() {
        return $this->query(
            "SELECT *
            FROM ability_prose
            WHERE local_language_id=9
            ORDER BY ability_id ASC"
        );
    }


    /**
     * @return ItemGroup
     * @throws \Utsubot\Pokemon\Item\ItemException
     * @throws PokemonBaseException
     */
    public function getItems(): ItemGroup {
        /** @var Item[] $items */
        $items = new ItemGroup();

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
     * @return array|bool|int
     */
    public function getItemRow() {
        return $this->query(
            "SELECT i.*, icp.name as category, ipn.item_pocket_id as pocket
            FROM items i, item_category_prose icp, item_categories ic, item_pocket_names ipn
            WHERE i.category_id=icp.item_category_id AND icp.local_language_id=9
            AND i.category_id=ic.id AND ic.pocket_id=ipn.item_pocket_id AND ipn.local_language_id=9
            ORDER BY i.id ASC"
        );
    }


    /**
     * @return array|bool|int
     */
    public function getItemFling() {
        return $this->query(
            "SELECT i.*, ifep.item_fling_effect_id as fling_effect
            FROM items i, item_fling_effect_prose ifep
            WHERE i.fling_effect_id=ifep.item_fling_effect_id AND ifep.local_language_id=9
            ORDER BY i.id ASC"
        );
    }


    /**
     * @return array|bool|int
     */
    public function getItemNames() {
        return $this->query(
            "SELECT ln.name AS `language`, `in`.*
            FROM language_names ln, item_names `in`
            WHERE ln.local_language_id=9 AND ln.language_id=`in`.local_language_id
            ORDER BY `in`.item_id ASC"
        );
    }


    /**
     * @return array|bool|int
     */
    public function getItemText() {
        return $this->query(
            "SELECT ln.name AS `language`, ift.*, vg.identifier
            FROM language_names ln, item_flavor_text ift, version_groups vg
            WHERE ln.local_language_id=9 AND ln.language_id=ift.language_id AND ift.version_group_id=vg.id
            ORDER BY ift.item_id ASC"
        );
    }


    /**
     * @return array|bool|int
     */
    public function getItemEffect() {
        return $this->query(
            "SELECT *
            FROM item_prose
            WHERE local_language_id=9
            ORDER BY item_id ASC"
        );
    }


    /**
     * @return array|bool|int
     */
    public function getItemFlags() {
        return $this->query(
            "SELECT ifm.item_id, ifp.name, ifp.description, ifp.item_flag_id
            FROM item_flag_map ifm, item_flag_prose ifp
            WHERE ifm.item_flag_id=ifp.item_flag_id AND ifp.local_language_id=9
            ORDER BY ifm.item_flag_id ASC"
        );
    }


    /**
     * @return NatureGroup
     * @throws PokemonBaseException
     */
    public function getNatures(): NatureGroup {
        /** @var Nature[] $natures */
        $natures = new NatureGroup();

        $natureAttributes = $this->getNatureAttributes();
        foreach ($natureAttributes as $row) {
            $nature = new Nature();

            $nature->setId((int)$row[ 'id' ]);

            if ($row[ 'increases' ] != $row[ 'decreases' ]) {
                $nature->setIncreases(Stat::fromName($row[ 'increases' ]));
                $nature->setDecreases(Stat::fromName($row[ 'decreases' ]));

                $nature->setLikes(Attribute::fromName($row[ 'likes' ]));
                $nature->setDislikes(Attribute::fromName($row[ 'dislikes' ]));

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
     * @return array|bool|int
     */
    public function getNatureAttributes() {
        return $this->query(
            "SELECT   `sn1`.`name` AS `increases`, `sn2`.`name` AS `decreases`, `ctn1`.`name` AS `likes`, `ctn2`.`name` AS `dislikes`,
                      `ctn1`.`flavor` AS `likesFlavor`, `ctn2`.`flavor` AS `dislikesFlavor`, `n`.`id`
                      
            FROM      `stat_names` `sn1`, `stat_names` `sn2`, `contest_type_names` `ctn1`, `contest_type_names` `ctn2`, `natures` `n`
            
            WHERE     `sn1`.`local_language_id`=9 AND `sn2`.`local_language_id`=9 AND `ctn1`.`local_language_id`=9 AND `ctn2`.`local_language_id`=9 AND
                      `sn1`.`stat_id`=`n`.`increased_stat_id` AND `sn2`.`stat_id`=`n`.`decreased_stat_id` AND
                      `ctn1`.`contest_type_id`=`n`.`likes_flavor_id` AND `ctn2`.`contest_type_id`=`n`.`hates_flavor_id`
                      
            ORDER BY  `n`.`id` ASC"
        );
    }


    /**
     * @return array|bool|int
     */
    public function getNatureNames() {
        return $this->query(
            "SELECT   `ln`.`name` AS `language`, `nn`.*

            FROM      `language_names` `ln`, `nature_names` `nn`
            
            WHERE     `ln`.`local_language_id`=9 AND `ln`.`language_id`=`nn`.`local_language_id`
            
            ORDER BY  `nn`.`nature_id` ASC"
        );
    }


    /**
     * @param $id
     */
    public function getLocation($id) {
    }


    /**
     * @return MoveGroup
     * @throws \Utsubot\Pokemon\Move\MoveException
     * @throws PokemonBaseException
     */
    public function getMoves(): MoveGroup {
        /** @var Move[] $moves */
        $moves = new MoveGroup();

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
     * @return array|bool|int
     */
    public function getMoveRow() {
        return $this->query(
            "SELECT	  `m`.*, `t`.`identifier` AS `type`, `mdc`.`identifier` AS `damage`, `mt`.`identifier` AS `target`, `mep`.`effect`, `mep`.`short_effect`

            FROM      `moves` `m`, `types` `t`, `move_damage_classes` `mdc`, `move_targets` `mt`, `move_effect_prose` `mep`
            
            WHERE	  `m`.`type_id`=`t`.`id` AND `m`.`damage_class_id`=`mdc`.`id` AND `m`.`target_id`=`mt`.`id` AND `m`.`effect_id`=`mep`.`move_effect_id` AND `mep`.`local_language_id`=9
            
            ORDER BY  `m`.`id` ASC"
        );
    }


    /**
     * @return array|bool|int
     */
    public function getMoveContest() {
        return $this->query(
            "SELECT	  `m`.*, `ct`.`identifier` AS `contestType`, `ce`.`appeal` AS `contestAppeal`, `ce`.`jam`, `cep`.`flavor_text` AS `contestFlavor`, `cep`.`effect` AS `contestEffect`,
                      `sce`.`appeal` AS `superContestAppeal`, `scep`.`flavor_text` AS `superContestFlavor`
                      
            FROM	  `moves` `m`, `contest_types` `ct`, `contest_effects` `ce`, `contest_effect_prose` `cep`, `super_contest_effects` `sce`, `super_contest_effect_prose` `scep`
            
            WHERE	  `m`.`contest_type_id`=`ct`.`id` AND `m`.`contest_effect_id`=`ce`.`id` AND `ce`.`id`=`cep`.`contest_effect_id` AND `cep`.`local_language_id`=9 AND
                      `m`.`super_contest_effect_id`=`sce`.`id` AND `sce`.`id`=`scep`.`super_contest_effect_id` AND `scep`.`local_language_id`=9
                      
            ORDER BY  `m`.`id` ASC"
        );
    }


    /**
     * @return array|bool|int
     */
    public function getMovesNames() {
        return $this->query(
            "SELECT   `ln`.`name` AS `language`, `mn`.*

            FROM      `language_names` `ln`, `move_names` `mn`
            
            WHERE     `ln`.`local_language_id`=9 AND `ln`.`language_id`=`mn`.`local_language_id`
            
            ORDER BY  `mn`.`move_id` ASC"
        );
    }
}
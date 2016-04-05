<?php
/**
 * MEGASSBOT - VeekunDatabaseInterface.php
 * User: Benjamin
 * Date: 03/11/14
 */

namespace Utsubot\Pokemon;
use Utsubot\{DatabaseInterface, DatabaseInterfaceException, MySQLDatabaseCredentials};


class VeekunDatabaseInterfaceException extends DatabaseInterfaceException {}

class VeekunDatabaseInterface extends DatabaseInterface {

	public function __construct() {
		parent::__construct(MySQLDatabaseCredentials::createFromConfig("veekun"));
	}

	private static function stripCodes($string) {
		$string = preg_replace_callback('/\[([a-z0-9\- ]*)\]\{[a-z]+:([a-z0-9\-]+)\}/i',
			function ($match) {
				if (strlen($match[1]))
					return $match[1];
				return ucwords(str_replace("-", " ", $match[2]));
			},
										   $string);
		$string = mb_ereg_replace('(\s|\x0C)+', " ", $string);

		return $string;
	}

	private static function formatIdentifier($string) {
		return implode(" ", array_map(function($word) {
				if ($word == "and" || $word == "in")
					return $word;
				return ucfirst($word);
			},
			explode("-", $string)));
	}

	/**
	 * Use the $id passed in an SQL function to determine whether it needs to be matched against
	 * This makes the same routine reusable for fetching all rows or fetching a single row
	 *
	 * @param int $id The id to check against, or an invalid value for no constraint
	 * @param string $column The column name the id is to be checked against
	 * @param bool $first Whether or not this is the first constraint in the query, or false if you're tacking it on the end
	 * @return array array of $constraint being the SQL snippet, and $params being the parameter array
	 */
	protected static function addConstraint($id, $column, $first = false) {
		$constraint = "";
		$params = array();
		if (strlen($column) && is_int($id) && $id >= 0) {
			$constraint = " AND $column=?";
			if ($first)
				$constraint = "WHERE $column=?";
			$params = array($id);
		}
		return array($constraint, $params);
	}

	private static 	$versionGroups = array(
		'x-y' 					=> "XY",
		'black-2-white-2'		=> "BW2",
		'black-white'			=> "BW",
		'heartgold-soulsilver'	=> "HGSS",
		'platinum'				=> "P",
		'diamond-pearl'			=> "DP",
		'firered-leafgreen'		=> "FRLG",
		'emerald'				=> "E",
		'ruby-sapphire'			=> "RS"
	);

	public function getNameFromId($table, $id) {
		if (!in_array($table, array("item", "pokemon_species", "pokemon_form", "move", "ability", "location", "type", "language")))
			return false;
		if (!is_int($id))
			return false;

		if ($table == "location")
			$query = "	SELECT ln.name AS `name`, l.region_id AS generation
						FROM location_names ln, locations l
						WHERE ln.location_id=l.id AND l.id=? AND ln.local_language_id=9
						LIMIT 1";
		else
			$query = "SELECT `name` FROM ${table}_names WHERE ${table}_id=? AND local_language_id=9 LIMIT 1";

		$res = $this->query($query, array($id));

		if (!$res)
			return false;

		return $res[0];
	}

	public function getPokemon($id = false) {
		$pokemon = array();

		/*	Main pokemon, no forms
			Includes generation, capture rate, color, base happiness, habitat, baby (y/n), steps to hatch, gender ratio	*/
		$species = $this->getSpecies($id);
		foreach ($species as $row) {
			$pokemon[$row['id']] = array(
				'name' 			=> $row['identifier'],
				'generation'	=> $row['generation_id'],
				'captureRate'	=> $row['capture_rate'],
				'color' 		=> ucwords($row['color']),
				'happiness'		=> $row['base_happiness'],
				'habitat' 		=> ($row['habitat_id']) ? ucwords($row['habitat']) : "",
				'isBaby'		=> $row['is_baby'],
				'eggSteps'		=> (($row['hatch_counter'] + 1) * 255),
				'id'			=>	$row['id'],
				'genders'		=> array('male' => ((8 - $row['gender_rate']) / 8), 'female' => ($row['gender_rate'] / 8))
			);

			if ($row['gender_rate'] == -1)
				$pokemon[$row['id']]['genders'] = array('male' => -1, 'female' => -1);
		}

		//	Populate names in all languages, as well as dex species ("genus")
		$name = $this->getName($id);
		foreach ($name as $row) {
			$pokemon[$row['pokemon_species_id']]['names'][$row['language']] = $row['name'];

			//	Don't need the species in all languages
			if ($row['language'] == "English")
				$pokemon[$row['pokemon_species_id']]['species'] = $row['genus'];
		}

		//	Populate egg breeding groups
		$eggGroup = $this->getEggGroup($id);
		foreach ($eggGroup as $row)
			$pokemon[$row['species_id']]['eggGroup'][] = $row['name'];

		/*	Add entries for forms with stat/type changes
			Update these and base entries with height, weight, base experience, and dexnum	*/
		$pokemonRow = $this->getPokemonRow($id);
		foreach ($pokemonRow as $row) {
			//	Copy base info from main species
			if (!isset($pokemon[$row['id']]))
				$pokemon[$row['id']] = $pokemon[$row['species_id']];

			//	Alt pokemon, update main name to reflect that
			if ($row['id'] > 10000) {
				$pokemon[$row['id']]['name'] = $row['identifier'];
				$pokemon[$row['id']]['id'] = $row['id'];
			}

			$pokemon[$row['id']]['height'] = ($row['height'] / 10);
			$pokemon[$row['id']]['weight'] = ($row['weight'] / 10);
			$pokemon[$row['id']]['experience'] = $row['base_experience'];
			$pokemon[$row['id']]['dexnum'] = $row['species_id'];
		}

		//	Populate other alt forms (semantic changes), create references to all alts in main pokemon
		$alt = $this->getAlt($id);
		foreach ($alt as $row) {
			//	If form_identifier isn't blank, it is an actual alt form, not a row for base pokemon
			if ($row['form_identifier']) {
				$info = array('name' => $row['identifier'], 'form' => $row['form_identifier'], 'id' => $row['pokemon_id']);

				//	References a form with its own 'pokemon' entry, meaning changed stats/type/etc, but we want a reference entry under the original pokemon
				if ($row['pokemon_id'] > 10000)
					$pokemon[$pokemon[$row['pokemon_id']]['dexnum']]['alts'][($row['form_order'] - 1)] = $info;

				//	Semantic form change, the pokemon_id will be the same as the original pokemon
				else
					$pokemon[$row['pokemon_id']]['alts'][($row['form_order'] - 1)] = $info;
			}
		}

		//	Populate names in all languages for alt forms
		$altName = $this->getAltName($id);
		foreach ($altName as $row) {
			//	Compound form name with original pokemon name
			if ($row['pokemon_id'] > 10000) {
				$name = $row['form_name'];
				if (strpos($name, "Mega") !== 0 && strpos($name, "Méga") !== 0 && strpos($name, "メガ") !== 0 && strpos($name, "메가") !== 0)
					$name = $pokemon[$pokemon[$row['pokemon_id']]['dexnum']]['names'][$row['language']] . " " . $row['form_name'];

				$pokemon[$row['pokemon_id']]['names'][$row['language']] = $name;
			}

			//	Save form name in self
			$pokemon[$row['pokemon_id']]['alts'][($row['form_order'] - 1)]['names'][$row['language']] = $row['form_name'];
			#$pokemon[$row['pokemon_id']]['names'] = $pokemon[$pokemon[$row['pokemon_id']]['dexnum']]['names'];
			//	Save form name in original pokemon
			$pokemon[$pokemon[$row['pokemon_id']]['dexnum']]['alts'][($row['form_order'] - 1)]['names'][$row['language']] = $row['form_name'];
		}

		//	Populate all dex numbers
		$dexnum = $this->getDexnum($id);
		foreach ($dexnum as $row)
			$pokemon[$row['species_id']]['dexnums'][$row['name']] = $row['pokedex_number'];

		//	Populate evolution data
		$evolution = $this->getEvolution($id);
		$genderIds = array(1 => "Female", 2 => "Male");
		$relativeStats = array(-1 => "Atk<Def", 0 => "Atk=Def", 1 => "Atk>Def");

		foreach ($evolution as $row) {
			$info = array();

			foreach ($row as $key => $val) {
				if ($val) {
					switch ($key) {
						case "minimum_level":			$info['level'] = $val;							break;
						case "gender_id":				$info['gender'] = $genderIds[$val];				break;
						case "time_of_day":				$info['time'] = $val;							break;
						case "minimum_happiness":		$info['happiness'] = $val;						break;
						case "minimum_beauty":			$info['beauty'] = $val;							break;
						case "minimum_affection":		$info['affection'] = $val;						break;
						case "needs_overworld_rain":	$info['rain'] = true; 							break;
						case "turn_upside_down":		$info['upsideDown'] = true; 					break;
						case "relative_physical_stats":	$info['relativeStats'] = $relativeStats[$val];	break;

						case "trigger_item_id":
							$info['useItem'] = $val;
							if ($itemRow = $this->getNameFromId("item", intval($val)))
								$info['useItem'] = $itemRow['name'];
							break;

						case "location_id":
							$info['location'] = $val;
							if ($locationRow = $this->getNameFromId("location", intval($val))) {
								$info['location'] = $locationRow['name'];
								$info['generation'] = $locationRow['generation'];
							}
							break;

						case "held_item_id":
							$info['holdItem'] = $val;
							if ($itemRow = $this->getNameFromId("item", intval($val)))
								$info['holdItem'] = $itemRow['name'];
							break;

						case "known_move_id":
							$info['knowsMove'] = $val;
							if ($moveRow = $this->getNameFromId("move", intval($val)))
								$info['knowsMove'] = $moveRow['name'];
							break;

						case "known_move_type_id":
							$info['knowsMoveType'] = $val;
							if ($typeRow = $this->getNameFromId("type", intval($val)))
								$info['knowsMoveType'] = $typeRow['name'];
							break;

						case "party_species_id":
							$info['partyPokemon'] = $val;
							if ($pokeRow = $this->getNameFromId("pokemon_species", intval($val)))
								$info['partyPokemon'] = $pokeRow['name'];
							break;

						case "party_type_id":
							$info['partyType'] = $val;
							if ($typeRow = $this->getNameFromId("type", intval($val)))
								$info['partyType'] = $typeRow['name'];
							break;

						case "trade_species_id":
							$info['tradeFor'] = $val;
							if ($pokeRow = $this->getNameFromId("pokemon_species", intval($val)))
								$info['tradeFor'] = $pokeRow['name'];
							break;
					}
				}
			}

			$evoname = $row['evo'];
			if ($pokeRow = $this->getNameFromId("pokemon_species", intval($row['evo'])))
				$evoname = $pokeRow['name'];

			$preevoname = $row['preevo'];
			if ($pokeRow = $this->getNameFromId("pokemon_species", intval($row['preevo'])))
				$preevoname = $pokeRow['name'];

			$pokemon[$row['preevo']]['evolution'][$evoname][$row['method']][$row['entry']] = $info;
			if (isset($pokemon[$row['evo']]))
				$pokemon[$row['evo']]['preEvolution'][$preevoname][$row['method']][$row['entry']] = $info;
		}

		//	Populate type(s)
		$type = $this->getPokemonType($id);
		foreach ($type as $row)
			$pokemon[$row['pokemon_id']]['type'][($row['slot'] - 1)] = $row['identifier'];

		//	Populate base stats and effort values rewarded
		$stats = $this->getPokemonStats($id);
		foreach ($stats as $row) {
			$pokemon[$row['pokemon_id']]['stats'][$row['identifier']] = $row['base_stat'];
			$pokemon[$row['pokemon_id']]['evs'][$row['identifier']] = $row['effort'];
		}

		//	Populate abilities
		$ability = $this->getPokemonAbility($id);
		foreach ($ability as $row)
			$pokemon[$row['pokemon_id']]['abilities'][$row['slot']] = $row['name'];

		return $pokemon;
	}

	public function getPokemonRow($id) {
		list($constraint, $params) = self::addConstraint($id, "id", true);
		$query = "	SELECT *
					FROM pokemon
					{$constraint}
					ORDER BY id ASC";

		return $this->query($query, $params);
	}

	public function getSpecies($id) {
		list($constraint, $params) = self::addConstraint($id, "ps.id");
		$query = "	SELECT ps.*, pc.identifier AS color, gr.identifier AS growth, phn.name AS habitat
					FROM pokemon_species ps, pokemon_colors pc, growth_rates gr, pokemon_habitat_names phn
					WHERE ((ps.habitat_id=phn.pokemon_habitat_id AND phn.local_language_id=9) OR ps.habitat_id IS NULL) AND ps.color_id=pc.id AND ps.growth_rate_id=gr.id{$constraint}
					ORDER BY ps.id ASC";

		return $this->query($query, $params);
	}

	public function getEggGroup($id) {
		list($constraint, $params) = self::addConstraint($id, "`peg`.`species_id`");
		$query = "	SELECT `egp`.`name`, `peg`.`species_id`
					FROM `egg_group_prose` `egp`, `pokemon_egg_groups` `peg`
					WHERE `peg`.`egg_group_id`=`egp`.`egg_group_id` AND `egp`.`local_language_id`=9{$constraint}
					ORDER BY `peg`.`species_id` ASC";

		return $this->query($query, $params);
	}

	public function getAlt($id) {
		list($constraint, $params) = self::addConstraint($id, "pokemon_id", true);
		$query = "	SELECT *
					FROM pokemon_forms
					{$constraint}
					ORDER BY pokemon_id ASC";

		return $this->query($query, $params);
	}

	public function getName($id) {
		list($constraint, $params) = self::addConstraint($id, "psn.pokemon_species_id");
		$query = "	SELECT ln.name AS `language`, psn.*
					FROM language_names ln, pokemon_species_names psn
					WHERE ln.local_language_id=9 AND ln.language_id=psn.local_language_id{$constraint}
					ORDER BY psn.pokemon_species_id ASC";

		return $this->query($query, $params);
	}

	public function getAltName($id) {
		list($constraint, $params) = self::addConstraint($id, "pfn.pokemon_form_id");
		$query = "	SELECT ln.name AS `language`, pfn.form_name, pfn.pokemon_name, pf.pokemon_id, pf.form_order
					FROM language_names ln, pokemon_form_names pfn, pokemon_forms pf
					WHERE ln.local_language_id=9 AND ln.language_id=pfn.local_language_id AND pfn.pokemon_form_id=pf.id{$constraint}
					ORDER BY pfn.pokemon_form_id ASC";

		return $this->query($query, $params);
	}

	public function getDexnum($id) {
		list($constraint, $params) = self::addConstraint($id, "pdn.species_id");
		$query = "	SELECT pdn.species_id, pdn.pokedex_number, pp.name
					FROM pokemon_dex_numbers pdn, pokedex_prose pp
					WHERE pdn.pokedex_id=pp.pokedex_id AND pp.local_language_id=9{$constraint}
					ORDER BY pdn.species_id ASC";

		return $this->query($query, $params);
	}

	public function getEvolution($id) {
		list($constraint, $params) = self::addConstraint($id, "ps.evolves_from_species_id");
		$query = "	SELECT ps.id AS evo, ps.evolves_from_species_id AS preevo, ps.evolution_chain_id, pe.*, pe.id AS entry, et.identifier AS method
					FROM pokemon_species ps, pokemon_evolution pe, evolution_triggers et
					WHERE ps.id=pe.evolved_species_id AND et.id=pe.evolution_trigger_id{$constraint}
					ORDER BY evo ASC";

		return $this->query($query, $params);
	}

	public function getPokemonType($id) {
		list($constraint, $params) = self::addConstraint($id, "pt.pokemon_id");
		$query = "	SELECT pt.*, t.identifier
					FROM pokemon_types pt, types t
					WHERE t.id=pt.type_id{$constraint}
					ORDER BY pt.pokemon_id ASC";

		return $this->query($query, $params);
	}

	public function getPokemonAbility($id) {
		list($constraint, $params) = self::addConstraint($id, "pa.pokemon_id");
		$query = "	SELECT pa.*, an.name
					FROM pokemon_abilities pa, ability_names an
					WHERE an.ability_id=pa.ability_id AND an.local_language_id=9{$constraint}
					ORDER BY pa.pokemon_id ASC";

		return $this->query($query, $params);
	}

	public function getPokemonStats($id) {
		list($constraint, $params) = self::addConstraint($id, "ps.pokemon_id");
		$query = "	SELECT ps.*, s.identifier
					FROM pokemon_stats ps, stats s
					WHERE s.id=ps.stat_id{$constraint}
					ORDER BY ps.pokemon_id ASC";

		return $this->query($query, $params);
	}

	public function getAbility($id = false) {
		$abilities = array();

		$names = $this->getAbilityNames($id);
		foreach ($names as $row) {
			$abilities[$row['ability_id']]['names'][$row['language']] = $row['name'];
			$abilities[$row['ability_id']]['generation'] = $row['generation_id'];
			$abilities[$row['ability_id']]['id'] = $row['ability_id'];
		}

		$text = $this->getAbilityText($id);
		foreach ($text as $row) {
			$key = (isset(self::$versionGroups[$row['identifier']])) ? self::$versionGroups[$row['identifier']] : $row['identifier'];
			$abilities[$row['ability_id']]['text'][$key][$row['language']] = self::stripCodes($row['flavor_text']);
		}

		$effect = $this->getAbilityEffect($id);
		foreach ($effect as $row) {
			$abilities[$row['ability_id']]['effect'] = self::stripCodes($row['effect']);
			$abilities[$row['ability_id']]['short'] = self::stripCodes($row['short_effect']);
		}

		return $abilities;
	}

	public function getAbilityNames($id) {
		list($constraint, $params) = self::addConstraint($id, "an.ability_id");
		$query = "	SELECT ln.name AS `language`, an.*, a.generation_id
					FROM language_names ln, ability_names an, abilities a
					WHERE an.ability_id=a.id AND a.is_main_series=1 AND ln.local_language_id=9 AND ln.language_id=an.local_language_id{$constraint}
					ORDER BY an.ability_id ASC";

		return $this->query($query, $params);
	}

	public function getAbilityText($id) {
		list($constraint, $params) = self::addConstraint($id, "aft.ability_id");
		$query = "	SELECT ln.name AS `language`, aft.*, vg.identifier
					FROM language_names ln, ability_flavor_text aft, version_groups vg
					WHERE ln.local_language_id=9 AND ln.language_id=aft.language_id AND aft.version_group_id=vg.id{$constraint}
					ORDER BY aft.ability_id ASC";

		return $this->query($query, $params);
	}

	public function getAbilityEffect($id) {
		list($constraint, $params) = self::addConstraint($id, "ability_id");
		$query = "	SELECT *
					FROM ability_prose
					WHERE local_language_id=9{$constraint}
					ORDER BY ability_id ASC";

		return $this->query($query, $params);
	}

	public function getItem($id = false) {
		$items = array();

		$itemRow = $this->getItemRow($id);
		foreach ($itemRow as $row)
			$items[$row['id']] = array(	'cost'	=> $row['cost'],	'flingPower'	=> $row['fling_power'],	'category'	=> $row['category'],	'pocket'	=> $row['pocket'],	'flingEffect' => "");

		$fling = $this->getItemFling($id);
		foreach ($fling as $row)
			$items[$row['id']]['flingEffect'] = $row['fling_effect'];

		$names = $this->getItemNames($id);
		foreach ($names as $row) {
			$items[$row['item_id']]['names'][$row['language']] = $row['name'];
			#$items[$row['item_id']]['generation'] = $row['generation_id'];
			$items[$row['item_id']]['id'] = $row['item_id'];
		}

		$text = $this->getItemText($id);
		foreach ($text as $row) {
			$key = (isset(self::$versionGroups[$row['identifier']])) ? self::$versionGroups[$row['identifier']] : $row['identifier'];
			$items[$row['item_id']]['text'][$key][$row['language']] = self::stripCodes($row['flavor_text']);
		}

		$effect = $this->getItemEffect($id);
		foreach ($effect as $row) {
			$items[$row['item_id']]['effect'] = self::stripCodes($row['effect']);
			$items[$row['item_id']]['short'] = self::stripCodes($row['short_effect']);
		}

		$flags = $this->getItemFlags($id);
		foreach ($flags as $row)
			$items[$row['item_id']]['flags'][$row['name']] = $row['description'];

		return $items;
	}

	public function getItemNames($id) {
		list($constraint, $params) = self::addConstraint($id, "`in`.item_id");
		$query = "	SELECT ln.name AS `language`, `in`.*
					FROM language_names ln, item_names `in`
					WHERE ln.local_language_id=9 AND ln.language_id=`in`.local_language_id{$constraint}
					ORDER BY `in`.item_id ASC";

		return $this->query($query, $params);
	}

	public function getItemRow($id) {
		list($constraint, $params) = self::addConstraint($id, "i.id");
		$query = "	SELECT i.*, icp.name as category, ipn.name as pocket
					FROM items i, item_category_prose icp, item_categories ic, item_pocket_names ipn
					WHERE i.category_id=icp.item_category_id AND icp.local_language_id=9
					AND i.category_id=ic.id AND ic.pocket_id=ipn.item_pocket_id AND ipn.local_language_id=9{$constraint}
					ORDER BY i.id ASC";

		return $this->query($query, $params);
	}

	public function getItemFling($id) {
		list($constraint, $params) = self::addConstraint($id, "i.id");
		$query = "	SELECT i.*, ifep.effect as fling_effect
					FROM items i, item_fling_effect_prose ifep
					WHERE i.fling_effect_id=ifep.item_fling_effect_id AND ifep.local_language_id=9{$constraint}
					ORDER BY i.id ASC";

		return $this->query($query, $params);
	}

	public function getItemFlags($id) {
		list($constraint, $params) = self::addConstraint($id, "ifm.item_id");
		$query = "	SELECT ifm.item_id, ifp.name, ifp.description
					FROM item_flag_map ifm, item_flag_prose ifp
					WHERE ifm.item_flag_id=ifp.item_flag_id AND ifp.local_language_id=9{$constraint}
					ORDER BY ifm.item_flag_id ASC";

		return $this->query($query, $params);
	}

	public function getItemText($id) {
		list($constraint, $params) = self::addConstraint($id, "ift.item_id");
		$query = "	SELECT ln.name AS `language`, ift.*, vg.identifier
					FROM language_names ln, item_flavor_text ift, version_groups vg
					WHERE ln.local_language_id=9 AND ln.language_id=ift.language_id AND ift.version_group_id=vg.id{$constraint}
					ORDER BY ift.item_id ASC";

		return $this->query($query, $params);
	}

	public function getItemEffect($id) {
		list($constraint, $params) = self::addConstraint($id, "item_id");
		$query = "	SELECT *
					FROM item_prose
					WHERE local_language_id=9{$constraint}
					ORDER BY item_id ASC";

		return $this->query($query, $params);
	}


	public function getNature($id = false) {
		$natures = array();

		$natureAttributes = $this->getNatureAttributes($id);
		foreach ($natureAttributes as $row) {
			if ($row['increases'] == $row['decreases']) {
				$natures[$row['id']] = array_combine(array_keys($row), array_fill(0, count($row), ""));
				$natures[$row['id']]['id'] = $row['id'];
			}
			else
				$natures[$row['id']] = $row;
		}

		$natureNames = $this->getNatureNames($id);
		foreach ($natureNames as $row)
			$natures[$row['nature_id']]['names'][$row['language']] = $row['name'];

		return $natures;
	}

	public function getNatureNames($id) {
		list($constraint, $params) = self::addConstraint($id, "`nn`.`nature_id`");
		$query = "	SELECT `ln`.`name` AS `language`, `nn`.*
					FROM `language_names` `ln`, `nature_names` `nn`
					WHERE `ln`.`local_language_id`=9 AND `ln`.`language_id`=`nn`.`local_language_id`{$constraint}
					ORDER BY `nn`.`nature_id` ASC";

		return $this->query($query, $params);
	}

	public function getNatureAttributes($id) {
		list($constraint, $params) = self::addConstraint($id, "`n`.`id`");
		$query = "	SELECT	`sn1`.`name` AS `increases`, `sn2`.`name` AS `decreases`, `ctn1`.`name` AS `likes`, `ctn2`.`name` AS `dislikes`,
							`ctn1`.`flavor` AS `likesFlavor`, `ctn2`.`flavor` AS `dislikesFlavor`, `n`.`id`
					FROM `stat_names` `sn1`, `stat_names` `sn2`, `contest_type_names` `ctn1`, `contest_type_names` `ctn2`, `natures` `n`
					WHERE	`sn1`.`local_language_id`=9 AND `sn2`.`local_language_id`=9 AND `ctn1`.`local_language_id`=9 AND `ctn2`.`local_language_id`=9 AND
							`sn1`.`stat_id`=`n`.`increased_stat_id` AND `sn2`.`stat_id`=`n`.`decreased_stat_id` AND
							`ctn1`.`contest_type_id`=`n`.`likes_flavor_id` AND `ctn2`.`contest_type_id`=`n`.`hates_flavor_id`{$constraint}
					ORDER BY `n`.`id` ASC";

		return $this->query($query, $params);
	}

	public function getLocation($id){}

	public function getMove($id = null) {
		$moves = array();

		$moveRow = $this->getMoveRow($id);
		foreach ($moveRow as $row) {
			$moves[$row['id']] = array(
				'generation'				=> $row['generation_id'],
				'power'						=> (int)$row['power'],
				'pp'						=> (int)$row['pp'],
				'accuracy'					=> (int)$row['accuracy'],
				'priority'					=> (int)$row['priority'],
				'type'						=> self::formatIdentifier($row['type']),
				'damageType'				=> self::formatIdentifier($row['damage']),
				'target'					=> self::formatIdentifier($row['target']),
				'effect'					=> str_replace("\$effect_chance", $row['effect_chance'], self::stripCodes($row['effect'])),
				'shortEffect'				=> str_replace("\$effect_chance", $row['effect_chance'], self::stripCodes($row['short_effect'])),
			);
		}

		$moveContest = $this->getMoveContest($id);
		foreach ($moveContest as $row) {
			$moves[$row['id']] = array_merge($moves[$row['id']],
											 array(
												 'contestType'				=> self::formatIdentifier($row['contestType']),
												 'contestAppeal'				=> $row['contestAppeal'],
												 'contestJam'				=> $row['jam'],
												 'contestFlavorText'			=> $row['contestFlavor'],
												 'contestEffect'				=> $row['contestEffect'],
												 'superContestAppeal'		=> $row['superContestAppeal'],
												 'superContestFlavorText'	=> $row['superContestFlavor']
											 )
			);
		}


		$moveNames = $this->getMovesNames($id);
		foreach ($moveNames as $row)
			$moves[$row['move_id']]['names'][$row['language']] = $row['name'];

		return $moves;
	}

	public function getMoveRow($id) {
		list($constraint, $params) = self::addConstraint($id, "`m`.`id`");
		$query = "	SELECT	`m`.*, `t`.`identifier` AS `type`, `mdc`.`identifier` AS `damage`, `mt`.`identifier` AS `target`, `mep`.`effect`, `mep`.`short_effect`
					FROM	`moves` `m`, `types` `t`, `move_damage_classes` `mdc`, `move_targets` `mt`, `move_effect_prose` `mep`
					WHERE	`m`.`type_id`=`t`.`id` AND `m`.`damage_class_id`=`mdc`.`id` AND `m`.`target_id`=`mt`.`id` AND `m`.`effect_id`=`mep`.`move_effect_id` AND `mep`.`local_language_id`=9{$constraint}
					ORDER BY `m`.`id` ASC";

		return $this->query($query, $params);
	}

	public function getMoveContest($id) {
		list($constraint, $params) = self::addConstraint($id, "`m`.`id`");
		$query = "	SELECT	`m`.*, `ct`.`identifier` AS `contestType`, `ce`.`appeal` AS `contestAppeal`, `ce`.`jam`, `cep`.`flavor_text` AS `contestFlavor`, `cep`.`effect` AS `contestEffect`,
							`sce`.`appeal` AS `superContestAppeal`, `scep`.`flavor_text` AS `superContestFlavor`
					FROM	`moves` `m`, `contest_types` `ct`, `contest_effects` `ce`, `contest_effect_prose` `cep`, `super_contest_effects` `sce`, `super_contest_effect_prose` `scep`
					WHERE	`m`.`contest_type_id`=`ct`.`id` AND `m`.`contest_effect_id`=`ce`.`id` AND `ce`.`id`=`cep`.`contest_effect_id` AND `cep`.`local_language_id`=9 AND
							`m`.`super_contest_effect_id`=`sce`.`id` AND `sce`.`id`=`scep`.`super_contest_effect_id` AND `scep`.`local_language_id`=9{$constraint}
					ORDER BY `m`.`id` ASC";

		return $this->query($query, $params);
	}

	public function getMovesNames($id) {
		list($constraint, $params) = self::addConstraint($id, "`mn`.`move_id`");
		$query = "	SELECT `ln`.`name` AS `language`, `mn`.*
					FROM `language_names` `ln`, `move_names` `mn`
					WHERE `ln`.`local_language_id`=9 AND `ln`.`language_id`=`mn`.`local_language_id`{$constraint}
					ORDER BY `mn`.`move_id` ASC";

		return $this->query($query, $params);
	}
}
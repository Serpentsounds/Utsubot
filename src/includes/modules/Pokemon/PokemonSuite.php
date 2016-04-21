<?php
/**
 * MEGASSBOT - PokemonModule.php
 * User: Benjamin
 * Date: 10/11/14
 */

namespace Utsubot\Pokemon;
use Utsubot\{
    IRCBot,
    IRCMessage,
    ModuleException,
    DatabaseInterface,
    MySQLDatabaseCredentials,
    Manager,
    ManagerException,
    ManagerSearchCriterion
};
use function Utsubot\{
    bold,
    italic,
    colorText
, stripControlCodes
};
use function Utsubot\Web\resourceBody;
use function Utsubot\Pokemon\Types\{
    colorType,
    hasChart,
    typeChart,
    typeMatchup,
    pokemonMatchup
};


class PokemonSuiteException extends ModuleException {}

class PokemonSuite extends ModuleWithPokemon {

    public function __construct(IRCBot $IRCBot) {
        $this->_require("Utsubot\\Pokemon\\VeekunDatabaseInterface");
		$this->_require("Utsubot\\Pokemon\\MetaPokemonDatabaseInterface");

		parent::__construct($IRCBot);

        $modules = array("Pokemon", "Ability", "Item", "Nature", "Move", "Types");
        foreach ($modules as $module)
            $this->IRCBot->loadModule(__NAMESPACE__. "\\$module\\{$module}Module");

		$this->triggers = array(
			'psearch'		=> "search",

			'mgdb'			=> "updateMetagameDatabase"

		);
	}


    /**
     * Perform a custom Manager search using user defined criteria
     *
     * @param IRCMessage $msg
     * @throws ModuleWithPokemonException
     * @throws PokemonSuiteException
     * @throws ManagerException
     */
    public function search(IRCMessage $msg) {
        $parameters = $msg->getCommandParameters();

        //	Parse user-selected search category
        $categories = array("pokemon", "ability", "move", "nature", "item");
        $category = strtolower(array_shift($parameters));
        if (!in_array($category, $categories))
            throw new PokemonSuiteException("Invalid search category '$category'. Valid categories are: ".implode(", ", $categories).".");

        //	Compose collection of valid operators for input parsing
        $operators = array_merge(Manager::getNumericOperators(), Manager::getStringOperators(), Manager::getArrayOperators());

        //	Grab relevant Manager and include custom operators, if applicable
        $manager = $this->getOutsideManager(ucfirst($category));

        $customOperators = $manager::getCustomOperators();
        $customOperatorRegex = '/^('. preg_replace('/([\/\\*?+().{}])/', '\\\\$1', implode("|", $customOperators)). '):(.+)$/';

        //  Filter and merge operators to one collection
        $operators = array_unique(array_merge($operators, $customOperators));
        /*	Make longer operators appear earlier in the array, so the resulting regex will match them before shorter ones that might begin the same
            e.g., > will prevent >= from ever matching if it appears first in the capture group */
        usort($operators, function($a, $b) {
            return strlen($b) - strlen($a);
        });
        //	Regex to parse user input
        $regex = '/^(.*?)('. preg_replace('/([\/\\*?+().{}])/', '\\\\$1', implode("|", $operators)). ')(.+)$/';


        //  Default to return all results
        $return = 0;
        //  Default English
        $language = new Language(Language::English);
        $criteria = array();
        foreach ($parameters as $parameter) {

            //	Customize number of results returned
            if (preg_match('/^return:(\d+)$/', $parameter, $match))
                $return = $match[1];

            //	Customize language of result set
            elseif (preg_match('/^language:(.+)$/', $parameter, $match))
                $language = Language::fromName($match[1]);

            //  Apply a new criterion with a custom operator
            elseif (preg_match($customOperatorRegex, $parameter, $match)) {
                list(, $operator, $value) = $match;
                $criteria[] = new ManagerSearchCriterion($manager, "", $operator, $value);
            }

            //	Apply a new criterion with a default operator
            elseif (preg_match($regex, $parameter, $match)) {
                list(, $field, $operator, $value) = $match;
                $criteria[] = new ManagerSearchCriterion($manager, $field, $operator, $value);
            }

            //	Abort on unknown parameter
            else
                throw new PokemonSuiteException("Invalid parameter '$parameter'.");
        }

        /** @var PokemonBase[] $results */
        $results = $manager->fullSearch($criteria);
        //	Apply number of results limit
        if ($return > 0)
            $results = array_slice($results, 0, $return);

        //	Convert objects to strings in given language
        foreach ($results as $key => $result)
            $results[$key] = $result->getName($language);

        if (!count($results))
            throw new PokemonSuiteException("No results found.");

        $this->respond($msg, implode(", ", $results));
    }
    


	public function updateMetagameDatabase(IRCMessage $msg) {
		$this->requireLevel($msg, 100);

		$mode = @$msg->getCommandParameters()[0];
		switch ($mode) {
			case "download":
				$this->downloadMetagameDatabase($msg);
			break;

			case "insert":
				$this->insertMetagameDatabase($msg);
			break;
		}
	}

	private function downloadMetagameDatabase(IRCMessage $msg) {
		$base = "http://www.smogon.com/stats/";
		$index = resourceBody($base);
		if (!preg_match_all('/^<a href="(\d{4}-\d{2}\/)">/m', $index, $match, PREG_PATTERN_ORDER))
			throw new PokemonSuiteException("Unable to find latest metagame stats.");
		$latest = $match[1][count($match[1]) - 1];

		$files = array("ubers-0", "ou-0", "uu-0", "nu-0", "doublesubers-0", "doublesou-0", "doublesuu-0", "vgc2015-0");
		$jsonDir = $base. $latest. "chaos/";
		if (!is_dir("metagame"))
			mkdir("metagame");
		else {
			$this->respond($msg, "Clearing out old statistics files...");
			array_map("unlink", glob("metagame/*.json"));
		}
		$this->respond($msg, "Downloading newest metagame statistics...");
		foreach ($files as $file) {
			if (file_put_contents("metagame/$file.json", file_get_contents($jsonDir.$file.".json")))
				$this->respond($msg, "Successfully downloaded $file.");
			else
				$this->respond($msg, "Failed to download $file.");
		}
		$this->respond($msg, "Download complete.");
	}

	private function insertMetagameDatabase(IRCMessage $msg) {
		if (!is_dir("metagame"))
			throw new PokemonSuiteException("There are no metagame statistics to insert. Download them first.");

		$files = glob("metagame/*.json");
		if (!$files)
			throw new PokemonSuiteException("There are no metagame statistics to insert. Download them first.");

		$interface = new DatabaseInterface(MySQLDatabaseCredentials::createFromConfig("utsubot"));

		$tiers = $interface->query("SELECT * FROM `metagame_tiers` ORDER BY `id` ASC");
		$tierIds = array();
		foreach ($tiers as $id => $row)
			$tierIds[strtolower(str_replace(" ", "", $row['name']))] = $id;

		$fields = $interface->query("SELECT * FROM `metagame_fields` ORDER BY `id` ASC");
		$fieldIds = array();
		foreach ($fields as $id => $row)
			$fieldIds[$row['name']] = $id;

		$collections = array(
			'pokemon'		=> $this->PokemonManager->collection(),
			'items' 		=> $this->ItemManager->collection(),
			'abilities' 	=> $this->AbilityManager->collection(),
			'moves' 		=> $this->MoveManager->collection()
		);
		$cache = array();
		foreach ($collections as $key => $collection) {
			foreach ($collection as $currentObject) {
				/** @var $currentObject PokemonBase */
				$index = $name = $currentObject->getName();
				if (substr_count($index, " ") > 1)
					$index = implode(" ", array_slice(explode(" ", $index), 0, 2));
				$index = strtolower(str_replace(array(" ", "-"), "", $index));
				$cache[$key][$index] = array($currentObject->getId(), $currentObject->getName());
			}
		}

		$this->respond($msg, "Clearing out old data...");
		$interface->query("TRUNCATE TABLE `metagame_data`");

		$table = "`metagame_data`";
		$columns = array("`pokemon_id`", "`tier_id`", "`field_id`", "`entry`", "`value`");
		$columnCount = count($columns);
		$insertRows = 500;
		$maxData = $columnCount * $insertRows;
		$statement = $interface->prepare(
			"INSERT INTO $table (". implode(", ", $columns). ") VALUES ". implode(", ", array_fill(0, $insertRows, "(". implode(", ", array_fill(0, $columnCount, "?")). ")"))
		);

		$fieldNameTranslation = array("raw count" => "count", "checks and counters" => "counters");

		foreach ($files as $file) {
			$this->respond($msg, "Beginning to process $file...");

			$data = json_decode(file_get_contents($file), true);
			$tierId = $tierIds[$data['info']['metagame']];
			$battleCount = $data['info']['number of battles'];

			$queryData = array();
			$queryDataCount = 0;
			foreach ($data['data'] as $pokemon => $stats) {
				$pokemonIndex = strtolower(str_replace(array(" ", "-"), "", $pokemon));
				if (!isset($cache['pokemon'][$pokemonIndex]))
					continue;
				$pokemonId = $cache['pokemon'][$pokemonIndex][0];

				$total = $stats['Raw count'];
				foreach ($stats as $field => $entries) {

					$fieldName = strtolower($field);
					if (isset($fieldNameTranslation[$fieldName]))
						$fieldName = $fieldNameTranslation[$fieldName];

					if (!isset($fieldIds[$fieldName]))
						continue;

					$fieldId = $fieldIds[$fieldName];

					if ($fieldName == "count") {
						array_push($queryData, $pokemonId, $tierId, $fieldId, $battleCount, $entries);
						$queryDataCount += $columnCount;
					}

					if (!is_array($entries) || !$entries)
						continue;

					if ($fieldName == "teammates") {
						$lower = array_filter($entries, function($item) { return $item < 0; });
						$upper = array_filter($entries, function($item) { return $item > 0; });

						arsort($upper);
						$upper = array_slice($upper, 0, 10);
						asort($lower);
						$lower = array_slice($lower, 0, 10);

						$entries = array_merge($upper, $lower);
					}

					if ($fieldName == "counters") {
						$entries = array_combine(array_keys($entries), array_column($entries, 1));
						arsort($entries);
						$entries = array_slice($entries, 0, 10);
					}

					foreach ($entries as $entry => $frequency) {
						if (in_array($fieldName, array("items", "moves", "spreads")) && ($frequency / $total) < 0.05)
							continue;

						if (in_array($fieldName, array("abilities", "items", "moves", "teammates", "counters"))) {
							$cacheKey = null;
							switch ($fieldName) {
								case "abilities":
								case "items":
								case "moves":
									$cacheKey = $fieldName;
								break;
								case "teammates":
								case "counters":
									$cacheKey = "pokemon";
								break;
								default:
									continue;
								break;
							}
							$cacheKey2 = strtolower(str_replace(array(" ", "-"), "", $entry));

							if ($entry != "nothing" && !isset($cache[$cacheKey][$cacheKey2]))
								continue;
							elseif ($entry != "nothing")
								$entry = $cache[$cacheKey][$cacheKey2][1];
						}

						array_push($queryData, $pokemonId, $tierId, $fieldId, $entry, $frequency);
						$queryDataCount += $columnCount;

						if ($queryDataCount >= $maxData) {
							$statement->execute($queryData);
							$queryData = array();
							$queryDataCount = 0;
						}
					}

				}

				$this->IRCBot->console("Finished processing $pokemon data for {$data['info']['metagame']}.");
			}

			if ($queryDataCount) {
				$tempStatement = $interface->prepare(
					"INSERT INTO $table (" . implode(", ", $columns) . ") VALUES " . implode(", ", array_fill(0, floor($queryDataCount / $columnCount), "(" . implode(", ", array_fill(0, $columnCount, "?")) . ")"))
				);
				$tempStatement->execute($queryData);
				$tempStatement = null;
			}
			#$this->respond($msg, "Finished processing $file.");
		}

		$this->respond($msg, "All done.");
		$interface->disconnect($statements = array($statement));
	}

}
<?php
/**
 * MEGASSBOT - PokemonModule.php
 * User: Benjamin
 * Date: 10/11/14
 */

namespace Utsubot\Pokemon;

use Utsubot\Help\HelpEntry;
use Utsubot\{
    EnumException, IRCBot, IRCMessage, Trigger, ModuleException, DatabaseInterface, MySQLDatabaseCredentials, Color
};
use Utsubot\Manager\{
    SearchCriteria, SearchCriterion, ManagerException, Operator, SearchMode
};
use function Utsubot\{
    bold,
    italic,
    colorText,
    stripControlCodes
};
use function Utsubot\Web\resourceBody;
use function Utsubot\Pokemon\Types\{
    colorType,
    hasChart,
    typeChart,
    typeMatchup,
    pokemonMatchup
};


/**
 * Class PokemonSuiteException
 *
 * @package Utsubot\Pokemon
 */
class PokemonSuiteException extends ModuleException {

}

/**
 * Class PokemonSuite
 *
 * @package Utsubot\Pokemon
 */
class PokemonSuite extends ModuleWithPokemon {

    const Modules = [ "Pokemon", "Ability", "Item", "Nature", "Move", "Types" ];


    /**
     * PokemonSuite constructor.
     *
     * @param IRCBot $IRCBot
     */
    public function __construct(IRCBot $IRCBot) {
        $this->_require("Utsubot\\Pokemon\\VeekunDatabaseInterface");
        $this->_require("Utsubot\\Pokemon\\MetaPokemonDatabaseInterface");

        parent::__construct($IRCBot);

        foreach (self::Modules as $module)
            $this->IRCBot->loadModule(__NAMESPACE__."\\$module\\{$module}Module");

        //  Command triggers
        $psearch = new Trigger("psearch", [ $this, "search" ]);
        $this->addTrigger($psearch);

        $repopulate = new Trigger("repopulate", [ $this, "repopulate" ]);
        $this->addTrigger($repopulate);

        $mgdb = new Trigger("mgdb", [ $this, "updateMetagameDatabase" ]);
        $this->addTrigger($mgdb);

        //  Help entries
        $psearchHelp = new HelpEntry("Pokemon", $psearch);
        $psearchHelp->addParameterTextPair("CATEGORY PARAMETERS", "Search CATEGORY using any number of custom search PARAMETERS.");
        $psearchHelp->addNotes("CATEGORY is the name of a class of Pokemon objects (e.g., Pokemon, Item, etc.)");
        $psearchHelp->addNotes("Parameters are separated by spaces and have three parts each: a field, operator, and value.");
        $psearchHelp->addNotes("The operator must be approriate for the data type, e.g. atk>150 is O.K., English>Pikachu is not, but English=Pikachu is.");
        $this->addHelp($psearchHelp);
    }


    /**
     * Perform a custom Manager search using user defined criteria
     *
     * @param IRCMessage $msg
     * @throws ModuleWithPokemonException
     * @throws PokemonSuiteException
     * @throws ManagerException
     * @throws EnumException
     */
    public function search(IRCMessage $msg) {
        $parameters = $msg->getCommandParameters();

        //  Parse user-selected search category
        $categories = $this->listManagers();
        $category   = strtolower(array_shift($parameters));
        if (!in_array($category, $categories))
            throw new PokemonSuiteException("Invalid search category '$category'. Valid categories are: ".implode(", ", $categories).".");
        $manager = $this->getOutsideManager($category);

        //  Default to return all results
        $return = 0;
        //  Default English
        $language = new Language(Language::English);
        /** @var MethodInfo[] $show
         *  Default to show no extra fields */
        $show = [ ];
        //  Default to sort ascending by ID (results are returned that way)
        $sortMode = [ ];
        $sortBy = [ ];

        $switches = implode("|", [ "return", "language", "show", "sort" ]);
        $copy = $parameters;
        //  Check for parameter switch overrides at the beginning of command
        while (preg_match("/^($switches):(.*)/", array_shift($copy), $match)) {
            list(, $switch, $value) = $match;

            switch ($switch) {
                case "return":
                    if (preg_match("/[^0-9]/", $value))
                        throw new PokemonSuiteException("Invalid result count '$value'. Please specify a positive integer.");
                    $return = $value;
                    break;

                //  Will throw an EnumException if an invalid Language name is given
                case "language":
                    $language = Language::fromName($value);
                    break;

                case "show":
                    $show[ ] = $manager->getMethodFor($value);
                    break;

                case "sort":
                    if (preg_match("/^([+-])/", $value, $match)) {
                        $sortMode[ ] = $match[ 1 ];
                        $value = substr($value, 1);
                    }
                    else
                        $sortMode[ ] = "+";

                    $sortBy[ ] = $manager->getMethodFor($value);
                    break;
            }

            $parameters = $copy;
        }
        if (!$show)
            $show = [ $manager->getMethodFor($language->getValue()) ];

        //  Compose collection of valid operators for input parsing
        $operators = Operator::listConstants();
        /*  Make longer operators appear earlier in the array, so the resulting regex will match them before shorter ones that might begin the same
            e.g., > will prevent >= from ever matching if it appears first in the capture group */
        usort($operators, function ($a, $b) {
            return strlen($b) - strlen($a);
        });

        //  Regex to parse user input
        $operatorGroup = implode("|", preg_replace("/([*?^$-.])/", "\\\\$1", $operators));
        $regex = "/^([^<>*=!:]+)(?:($operatorGroup|:)(.+))?/";
        $inParameter = false;
        /** @var MethodInfo $methodInfo */
        $field = $operator = $value = $methodInfo = null;
        $criteria = new SearchCriteria();

        foreach ($parameters as $parameter) {

            //  Continue parsing a quoted parameter
            if ($inParameter) {

                //  Closing quote found, create Criterion
                if (($position = strpos($parameter, '"') !== FALSE)) {
                    $value .= " ". substr($parameter, 0, $position);

                    //  Use the : operator to pass parameters to a function
                    if ($operator == ":")
                        $criteria[ ] = new SearchCriterion($methodInfo->getMethod(), [ $value ], new Operator("=="), 1);
                    //  Default Criterion
                    else
                        $criteria[ ] = new SearchCriterion($methodInfo->getMethod(), $methodInfo->getParameters(), new Operator($operator), $value);

                    $inParameter = false;
                }

                //  No closing quote, continue parsing
                else
                    $value .= " ". $parameter;
            }

            //  Look for a new parameter
            else {
                if (preg_match($regex, $parameter, $match)) {
                    $field = $match[1];
                    $methodInfo = $manager->getMethodFor($field);

                    //  Comparison criterion
                    if (count($match) > 2) {
                        list(,, $operator, $value) = $match;

                        //  Quoted parameter, wait to grab the rest before creating Criterion
                        if (substr($value, 0, 1) == '"') {
                            $inParameter = true;
                            $value = substr($value, 1);
                            continue;
                        }

                        //  Single word parameter, create Criterion
                        else {
                            //  Use the : operator to pass parameters to a function
                            if ($operator == ":")
                                $criteria[ ] = new SearchCriterion($methodInfo->getMethod(), [ $value ], new Operator("=="), 1);
                            //  Default Criterion
                            else
                                $criteria[ ] = new SearchCriterion($methodInfo->getMethod(), $methodInfo->getParameters(), new Operator($operator), $value);
                        }
                    }

                    //  Boolean criterion, loose compare method result to 1 (true)
                    else
                        $criteria[ ] = new SearchCriterion($methodInfo->getMethod(), $methodInfo->getParameters(), new Operator("=="), 1);
                }

                //  Bad data
                else
                    throw new PokemonSuiteException("Invalid search term '$parameter'.");
            }
        }

        /** @var PokemonBase[] $results */
        $results = $manager->advancedSearch($criteria, new SearchMode(SearchMode::All), $return);

        if ($sortBy) {
            usort($results,
                /**
                 * @var PokemonBase  $a
                 * @var PokemonBase  $b
                 * @var MethodInfo[] $sortBy
                 * @var string[]     $sortMode
                 */
                function (PokemonBase $a, PokemonBase $b) use ($sortBy, $sortMode) {

                    /** @var MethodInfo[] $sortBy */
                    foreach ($sortBy as $key => $methodInfo) {
                        //  Can't compare if method is missing
                        if (!method_exists($a, $methodInfo->getMethod()) || !method_exists($b, $methodInfo->getMethod()))
                            continue;

                        //  Store values for each object
                        $values = [
                            call_user_func_array([ $a, $methodInfo->getMethod() ], $methodInfo->getParameters()),
                            call_user_func_array([ $b, $methodInfo->getMethod() ], $methodInfo->getParameters())
                        ];

                        //  Sorting for this method is equal, move on to the next method
                        if ($values[ 0 ] === $values[ 1 ])
                            continue;

                        //  Change return depending on ascending or descending sort
                        switch ($sortMode[$key]) {

                            //  Ascending sort, lower values first
                            case "+":
                                return ($values[ 0 ] > $values[ 1 ]) ? 1 : (($values[ 0 ] < $values[ 1 ]) ? -1 : 0);
                                break;

                            //  Descending sort, higher values first
                            case "-":
                                return ($values[ 0 ] > $values[ 1 ]) ? -1 : (($values[ 0 ] < $values[ 1 ]) ? 1 : 0);
                                break;

                            //  Unknown sort mode, how did we get here? Can't compare either way
                            default:
                                continue;
                                break;
                        }
                    }

                    //  All values were equal, order undefined
                    return 0;
                }
            );
        }

        $output = [ ];
        //  Convert objects to strings in given language
        foreach ($results as $key => $result) {
            $output[ $key ] = [ ];

            //  Add additional fields if specified
            foreach ($show as $methodInfo) {
                if (method_exists($result, $methodInfo->getMethod()))
                    $output[ $key ][ ] = call_user_func_array([ $result, $methodInfo->getMethod() ], $methodInfo->getParameters());
            }

            //  Format results into a group if multiple fields are displayed, otherwise just add the name
            $output[ $key ] = (count($output[ $key ]) > 1) ?

                colorText("[", new Color(Color::Teal)).
                    italic((string)$output[ $key ][ 0 ]). ": ".
                    implode(", ", array_slice($output[ $key ], 1)).
                    colorText("]", new Color(Color::Teal)) :

                $output[ $key ][ 0 ];
        }

        if (!$output)
            throw new PokemonSuiteException("No results found.");

        $this->respond($msg, sprintf("%s results: %s", bold(count($output)), implode(", ", $output)));
    }


    /**
     * @param IRCMessage $msg
     * @throws ModuleException
     * @throws ModuleWithPokemonException
     * @throws PokemonManagerBaseException
     * @throws PokemonSuiteException
     * @throws \Utsubot\Accounts\ModuleWithAccountsException
     */
    public function repopulate(IRCMessage $msg) {
        $this->requireLevel($msg, 100);
        $this->requireParameters($msg, 2, "Usage: !repopulate <manager> <index>");

        $parameters = $msg->getCommandParameters();
        $managerName = array_shift($parameters);
        $index = array_shift($parameters);

        $manager = $this->getOutsideManager($managerName);

        if (preg_match("/[^0-9]/", $index))
            throw new PokemonSuiteException("Index '$index' must be an integer.");

        $manager->populate((int)$index);
        $this->respond($msg, "Database has been reloaded.");
    }


    public function updateMetagameDatabase(IRCMessage $msg) {
        $this->requireLevel($msg, 100);

        $mode = @$msg->getCommandParameters()[ 0 ];
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
        $base  = "http://www.smogon.com/stats/";
        $index = resourceBody($base);
        if (!preg_match_all('/^<a href="(\d{4}-\d{2}\/)">/m', $index, $match, PREG_PATTERN_ORDER))
            throw new PokemonSuiteException("Unable to find latest metagame stats.");
        $latest = $match[ 1 ][ count($match[ 1 ]) - 1 ];

        $files   = [ "ubers-0", "ou-0", "uu-0", "nu-0", "doublesubers-0", "doublesou-0", "doublesuu-0", "vgc2015-0" ];
        $jsonDir = $base.$latest."chaos/";
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

        $tiers   = $interface->query("SELECT * FROM `metagame_tiers` ORDER BY `id` ASC");
        $tierIds = [ ];
        foreach ($tiers as $id => $row)
            $tierIds[ strtolower(str_replace(" ", "", $row[ 'name' ])) ] = $id;

        $fields   = $interface->query("SELECT * FROM `metagame_fields` ORDER BY `id` ASC");
        $fieldIds = [ ];
        foreach ($fields as $id => $row)
            $fieldIds[ $row[ 'name' ] ] = $id;

        $collections = [
            'pokemon'   => $this->PokemonManager->collection(),
            'items'     => $this->ItemManager->collection(),
            'abilities' => $this->AbilityManager->collection(),
            'moves'     => $this->MoveManager->collection()
        ];
        $cache       = [ ];
        foreach ($collections as $key => $collection) {
            foreach ($collection as $currentObject) {
                /** @var $currentObject PokemonBase */
                $index = $name = $currentObject->getName();
                if (substr_count($index, " ") > 1)
                    $index = implode(" ", array_slice(explode(" ", $index), 0, 2));
                $index                   = strtolower(str_replace([ " ", "-" ], "", $index));
                $cache[ $key ][ $index ] = [ $currentObject->getId(), $currentObject->getName() ];
            }
        }

        $this->respond($msg, "Clearing out old data...");
        $interface->query("TRUNCATE TABLE `metagame_data`");

        $table       = "`metagame_data`";
        $columns     = [ "`pokemon_id`", "`tier_id`", "`field_id`", "`entry`", "`value`" ];
        $columnCount = count($columns);
        $insertRows  = 500;
        $maxData     = $columnCount * $insertRows;
        $statement   = $interface->prepare(
            "INSERT INTO $table (".
            implode(", ", $columns).
            ") VALUES ".
            implode(", ", array_fill(0, $insertRows, "(".implode(", ", array_fill(0, $columnCount, "?")).")"))
        );

        $fieldNameTranslation = [ "raw count" => "count", "checks and counters" => "counters" ];

        foreach ($files as $file) {
            $this->respond($msg, "Beginning to process $file...");

            $data        = json_decode(file_get_contents($file), true);
            $tierId      = $tierIds[ $data[ 'info' ][ 'metagame' ] ];
            $battleCount = $data[ 'info' ][ 'number of battles' ];

            $queryData      = [ ];
            $queryDataCount = 0;
            foreach ($data[ 'data' ] as $pokemon => $stats) {
                $pokemonIndex = strtolower(str_replace([ " ", "-" ], "", $pokemon));
                if (!isset($cache[ 'pokemon' ][ $pokemonIndex ]))
                    continue;
                $pokemonId = $cache[ 'pokemon' ][ $pokemonIndex ][ 0 ];

                $total = $stats[ 'Raw count' ];
                foreach ($stats as $field => $entries) {

                    $fieldName = strtolower($field);
                    if (isset($fieldNameTranslation[ $fieldName ]))
                        $fieldName = $fieldNameTranslation[ $fieldName ];

                    if (!isset($fieldIds[ $fieldName ]))
                        continue;

                    $fieldId = $fieldIds[ $fieldName ];

                    if ($fieldName == "count") {
                        array_push($queryData, $pokemonId, $tierId, $fieldId, $battleCount, $entries);
                        $queryDataCount += $columnCount;
                    }

                    if (!is_array($entries) || !$entries)
                        continue;

                    if ($fieldName == "teammates") {
                        $lower = array_filter($entries, function ($item) {
                            return $item < 0;
                        });
                        $upper = array_filter($entries, function ($item) {
                            return $item > 0;
                        });

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
                        if (in_array($fieldName, [ "items", "moves", "spreads" ]) && ($frequency / $total) < 0.05)
                            continue;

                        if (in_array($fieldName, [ "abilities", "items", "moves", "teammates", "counters" ])) {
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
                            $cacheKey2 = strtolower(str_replace([ " ", "-" ], "", $entry));

                            if ($entry != "nothing" && !isset($cache[ $cacheKey ][ $cacheKey2 ]))
                                continue;
                            elseif ($entry != "nothing")
                                $entry = $cache[ $cacheKey ][ $cacheKey2 ][ 1 ];
                        }

                        array_push($queryData, $pokemonId, $tierId, $fieldId, $entry, $frequency);
                        $queryDataCount += $columnCount;

                        if ($queryDataCount >= $maxData) {
                            $statement->execute($queryData);
                            $queryData      = [ ];
                            $queryDataCount = 0;
                        }
                    }

                }

                $this->IRCBot->console("Finished processing $pokemon data for {$data['info']['metagame']}.");
            }

            if ($queryDataCount) {
                $tempStatement = $interface->prepare(
                    "INSERT INTO $table (".implode(", ", $columns).") VALUES ".implode(", ", array_fill(0, floor($queryDataCount / $columnCount), "(".implode(", ", array_fill(0, $columnCount, "?")).")"))
                );
                $tempStatement->execute($queryData);
                $tempStatement = null;
            }
            #$this->respond($msg, "Finished processing $file.");
        }

        $this->respond($msg, "All done.");
        $interface->disconnect($statements = [ $statement ]);
    }

}
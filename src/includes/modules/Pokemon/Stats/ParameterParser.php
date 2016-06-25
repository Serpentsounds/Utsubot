<?php
/**
 * Utsubot - ParameterParser.php
 * Date: 02/03/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon\Stats;

use Utsubot\Manager;
use Utsubot\Pokemon\Language;
use Utsubot\Pokemon\Pokemon\Pokemon;
use Utsubot\Pokemon\Nature\Nature;


class ParameterParserException extends \Exception {

}

class ParameterParser {

    /** @var $managers Manager[] */
    private $managers;

    private static $statList = [ "HP", "Attack", "Defense", "Special Attack", "Special Defense", "Speed" ];


    public function __construct() {
    }


    /**
     * Inject a manager into the object if a parsing subroutine needs to use it for a lookup
     *
     * @param string  $name Manager name, must match beginning of class name
     * @param Manager $manager
     * @throws ParameterParserException If the name and class don't match
     */
    public function injectManager(string $name, Manager $manager) {
        //  Normalize name
        $name = ucfirst(strtolower($name));

        $class = "Pokemon\\{$name}Manager";
        if (!($manager instanceof $class))
            throw new ParameterParserException("Manager '$name' must be of class '$class'.");

        $this->managers[ $name ] = $manager;
    }


    /**
     * Get one of the injected managers
     *
     * @param string $name Manager name
     * @return Manager
     * @throws ParameterParserException If specified manager hasn't been injected
     */
    private function getManager(string $name): Manager {
        //  Normalize name
        $name = ucfirst(strtolower($name));

        if (isset($this->managers[ $name ]))
            return $this->managers[ $name ];

        throw new ParameterParserException("Manager '$name' not found in array.");
    }


    /**
     * Given a string, searches for a valid pokemon name at the beginning
     *
     * @param string $type
     * @param array  $parameters
     * @param int    $maxWords Maximum number of consecutive words to search
     * @return Object An item of the respective manager
     * @throws ParameterParserException
     */
    public function getValid(string $type, array $parameters, int $maxWords = 3) {
        $object  = null;
        $manager = $this->getManager($type);
        $manages = $manager->getManages();

        for ($words = 1; $words <= $maxWords; $words++) {
            //	Add 1 word at a time
            $name   = implode(" ", array_slice($parameters, 0, $words));
            $object = $manager->search($name);

            //	Object found
            if ($object instanceof $manages)
                break;

            //	No object and we've no words left to check
            elseif ($words == $maxWords)
                throw new ParameterParserException("Unable to find a valid {$manager->getManages()}.");

        }

        return $object;
    }


    /**
     * Parse user input for !piv or !pstat into separate parameters
     *
     * @param array $parameters Input string array
     * @return IVStatParameterResult
     * @throws ParameterParserException Invalid parameter
     */
    public function parseIVStatParameters(array $parameters): IVStatParameterResult {
        //	Parse first words into pokemon
        /** @var $pokemon Pokemon */
        $pokemon = $this->getValid("Pokemon", $parameters);

        //	Shave pokemon name off front of parameters
        $parameters = array_slice($parameters, substr_count($pokemon->getName(new Language(Language::English)), " ") + 1);

        if (count($parameters) < 8)
            throw new ParameterParserException("Not enough parameters.");

        $level = array_shift($parameters);
        if (!($level >= 1 && $level <= 100))
            throw new ParameterParserException("Invalid level.");
        $level = intval($level);

        /** @var $nature Nature */
        $nature = $this->getValid("Nature", [ array_shift($parameters) ], 1);

        //	Initialize nature information
        $natureMultipliers = array_combine(self::$statList, array_fill(0, count(self::$statList), 1));
        $increases         = $nature->getIncreases();
        $decreases         = $nature->getDecreases();

        //	Update nature multipliers
        if (isset($natureMultipliers[ $increases ]))
            $natureMultipliers[ $increases ] = 1.1;
        if (isset($natureMultipliers[ $decreases ]))
            $natureMultipliers[ $decreases ] = 0.9;

        //	Normalize array
        $natureMultipliers = array_values($natureMultipliers);

        //	Check each parameter individually
        $statValues = $EVs = [ 0, 0, 0, 0, 0, 0 ];
        for ($i = 0; $i <= 5; $i++) {

            //	Effort values specified
            if (strpos($parameters[ $i ], ':') !== false) {
                list($stat, $EV) = explode(':', $parameters[ $i ]);
                //	Stat and EV minimum values
                if (!($stat >= 0 && $EV >= 0 && $EV <= 255))
                    throw new ParameterParserException("Invalid stat or EV parameter.");

                $statValues[ $i ] = intval($stat);
                $EVs[ $i ]        = intval($EV);
            }
            //	No effort value specified
            else {
                //	Stat minimum value
                if (!($parameters[ $i ] >= 0))
                    throw new ParameterParserException("Invalid stat or EV parameter.");

                $statValues[ $i ] = intval($parameters[ $i ]);
            }
        }

        return new IVStatParameterResult($pokemon, $level, $increases, $decreases, $natureMultipliers, $statValues, $EVs);
    }


    /**
     * Parse parameters for !basetomax and !maxtobase family of commands
     *
     * @param array  $parameters
     * @param string $command
     * @return baseMaxParameterResult
     * @throws ParameterParserException Invalid parameters
     */
    public function parseBaseMaxParameters($parameters, $command): baseMaxParameterResult {
        if (!$parameters)
            throw new ParameterParserException("No base given.");

        //	Stat must be a positive integer
        if (!is_numeric($parameters[ 0 ]) || ($stat = intval($parameters[ 0 ])) != $parameters[ 0 ] || $stat < 0)
            throw new ParameterParserException("Invalid stat value.");

        $HP     = false;
        $level  = 100;
        $match  = [ ];
        $switch = "";
        do {
            if ($switch) {
                switch (strtolower($match[ 1 ])) {
                    case "hp":
                        $HP = true;
                        break;
                    case "level":
                        if (!empty($match[ 2 ]) && is_numeric($match[ 2 ]) && ($value = intval($match[ 2 ])) == $match[ 2 ] && $value >= 1 && $value <= 100)
                            $level = $value;
                        break;
                    default:
                        throw new ParameterParserException("Invalid switch '{$match[1]}'.");
                        break;
                }
            }

            $switch = array_shift($parameters);
        } while (preg_match("/^-([^:]+)(?:\\:(.+))?/", $switch, $match));

        switch ($command) {
            case "b2m":
            case "btom":
            case "basetomax":
                $from = "base";
                $to   = "max";
                break;

            case "m2b":
            case "mtob":
            case "maxtobase":
                $from = "max";
                $to   = "base";
                break;

            default:
                throw new ParameterParserException("Invalid command.");
                break;
        }

        return new baseMaxParameterResult($stat, $level, $from, $to, $HP);
    }

}

/**
 * Organized result set from parsing user input of a !piv or !pstat command
 *
 * @package Pokemon
 */
class IVStatParameterResult {

    private $pokemon;
    private $level;
    private $natureIncreases;
    private $natureDecreases;
    private $natureMultipliers;
    private $statValues;
    private $EVs;

    const NUMBER_OF_STATS = 6;
    private static $statList = [ "HP", "Attack", "Defense", "Special Attack", "Special Defense", "Speed" ];


    /**
     * IVStatParameterResult constructor.
     *
     * @param Pokemon $pokemon
     * @param int     $level
     * @param string  $natureIncreases   Stat name
     * @param string  $natureDecreases   Stat name
     * @param array   $natureMultipliers One for each 6 stats
     * @param array   $statValues        One for each 6 stats
     * @param array   $EVs               One for each 6 stats
     * @throws ParameterParserException Validation failed
     */
    public function __construct(Pokemon $pokemon, int $level, string $natureIncreases, string $natureDecreases, array $natureMultipliers, array $statValues, array $EVs) {
        $this->pokemon = $pokemon;
        $this->level   = $level;

        //  Validate names of stats affected by nature
        if (array_search($natureIncreases, self::$statList) === false)
            throw new ParameterParserException("Invalid stat name '$natureIncreases'.'");
        $this->natureDecreases = $natureDecreases;
        if (array_search($natureDecreases, self::$statList) === false)
            throw new ParameterParserException("Invalid stat name '$natureDecreases'.'");
        $this->natureIncreases = $natureIncreases;

        //  Validate stat arrays
        if (count($natureMultipliers) != self::NUMBER_OF_STATS)
            throw new ParameterParserException("Invalid number of elements for nature multiplier array.");
        $this->natureMultipliers = $natureMultipliers;
        if (count($statValues) != self::NUMBER_OF_STATS)
            throw new ParameterParserException("Invalid keys for stat value array.");
        $this->statValues = $statValues;
        if (count($EVs) != self::NUMBER_OF_STATS)
            throw new ParameterParserException("Invalid keys for EV array.");
        $this->EVs = $EVs;
    }


    /**
     * @return Pokemon
     */
    public function getPokemon(): Pokemon {
        return $this->pokemon;
    }


    /**
     * @return int
     */
    public function getLevel(): int {
        return $this->level;
    }


    /**
     * @return string
     */
    public function getNatureIncreases(): string {
        return $this->natureIncreases;
    }


    /**
     * @return string
     */
    public function getNatureDecreases(): string {
        return $this->natureDecreases;
    }


    /**
     * @return array
     */
    public function getNatureMultipliers(): array {
        return $this->natureMultipliers;
    }


    /**
     * @return array
     */
    public function getStatValues(): array {
        return $this->statValues;
    }


    /**
     * @return array
     */
    public function getEVs(): array {
        return $this->EVs;
    }


    /**
     * Internal utility to normalize stat names to indexes
     *
     * @param mixed $stat Stat name or index
     * @return int $stat converted to index
     */
    private function getKey($stat): int {
        if (isset(self::$statList[ $stat ]))
            return $stat;
        elseif (($key = array_search($stat, self::$statList)) !== false)
            return $key;

        return -1;
    }


    /**
     * @param mixed $stat Stat name or index
     * @return int Nature multiplier value, or -1 on failure
     */
    public function getNatureMultiplier($stat): int {
        $key = $this->getKey($stat);

        return $this->natureMultipliers[ $key ] ?? $key;
    }


    /**
     * @param mixed $stat Stat name or index
     * @return int Stat value, or -1 on failure
     */
    public function getStatValue($stat): int {
        $key = $this->getKey($stat);

        return $this->statValues[ $key ] ?? $key;
    }


    /**
     * @param mixed $stat Stat name or index
     * @return int EV value, or -1 on failure
     */
    public function getEV($stat): int {
        $key = $this->getKey($stat);

        return $this->EVs[ $key ] ?? $key;
    }

}

/**
 * Organized result set from parsing user input of a !basetomax or !maxtobase command
 *
 * @package Pokemon
 */
class baseMaxParameterResult {

    private $stat;
    private $level;
    private $from;
    private $to;
    private $hp;


    public function __construct(int $stat, int $level, string $from, string $to, bool $hp) {
        $this->stat  = $stat;
        $this->level = $level;

        //  Whitelist conversion parameters
        $valid = [ "max", "base" ];
        if (!in_array($from, $valid))
            throw new ParameterParserException("Invalid 'from' category '$from'.");
        $this->from = $from;
        if (!in_array($to, $valid))
            throw new ParameterParserException("Invalid 'to' category '$to'.");
        $this->to = $to;

        $this->hp = $hp;
    }


    /**
     * @return int
     */
    public function getStat() {
        return $this->stat;
    }


    /**
     * @return int
     */
    public function getLevel() {
        return $this->level;
    }


    /**
     * @return string
     */
    public function getFrom() {
        return $this->from;
    }


    /**
     * @return string
     */
    public function getTo() {
        return $this->to;
    }


    /**
     * @return boolean
     */
    public function isHp() {
        return $this->hp;
    }
}
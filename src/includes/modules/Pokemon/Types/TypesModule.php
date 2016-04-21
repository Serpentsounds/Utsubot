<?php
/**
 * Utsubot - TypesModule.php
 * Date: 21/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon\Types;
use Utsubot\{
    IRCBot,
    IRCMessage,
    ManagerException,
    ManagerSearchCriterion
};
use Utsubot\Pokemon\{
    Language,
    ModuleWithPokemon,
    ModuleWithPokemonException,
    Pokemon\Pokemon,
    Move\Move
};
use function Utsubot\{
    bold,
    italic
};

/**
 * Class TypesModuleException
 *
 * @package Utsubot\Pokemon\Types
 */
class TypesModuleException extends ModuleWithPokemonException {}

/**
 * Class TypesModule
 *
 * @package Utsubot\Pokemon\Types
 */
class TypesModule extends ModuleWithPokemon {

    public function __construct(IRCBot $IRCBot) {
        parent::__construct($IRCBot);

        $this->triggers = array(
            'ptype'			=> "type",

            'pcoverage'		=> "coverage",
            'pcov'			=> "coverage",
        );
    }


    public function type(IRCMessage $msg) {
        $parameters = $msg->getCommandParameters();

        //  Check if switches were applied
        $copy = $parameters;
        $switch = null;
        $firstWord = strtolower(array_shift($copy));
        //  Switch detected, save it and remove from parameters
        if (substr($firstWord, 0, 1) == "-") {
            $switch = substr($firstWord, 1);
            $parameters = $copy;
        }

        $mode = null;
        switch ($switch) {
            case "d":
                $mode = "defensive";
                break;
            case "o":
                $mode = "offensive";
                break;
            case "p":
                $mode = "pokemon";
                break;
        }
        
        $next = 0;
        $types = array(null, null);
        $PokemonManager = $this->getOutsideManager("Pokemon");
        $MoveManager = $this->getOutsideManager("Move");
        //	Loop through words until we find 2 parameters
        for ($i = 0; $i <= 1; $i++) {
            //	No more words to check
            if (!isset($parameters[$next]))
                break;

            //	Save next two words to check for flying press
            $nextTwo = implode(" ", array_slice($parameters, $next, 2));

            //	Two word type (flying press)
            if (hasChart($nextTwo)) {
                $types[$i] = $nextTwo;
                $next += 2;
            }
            //	One word type (all others)
            elseif (hasChart($parameters[$next]))
                $types[$i] = $parameters[$next++];

            //	Type list delimited by "/"
            elseif (strpos($parameters[$next], "/") !== false) {
                $types = explode("/", $parameters[$next]);

                //	Check each type individually
                foreach ($types as $type) {
                    if (hasChart($type))
                        $types[$i][] = $type;
                    else
                        //	Invalid type given, abort
                        throw new TypesModuleException("Invalid type '$type'.");
                }
                $next++;
            }

            //	Type name not found, check for pokemon or move names
            else {
                //	Check for multi-word pokemon
                $maxWordsPerPokemon = 3;
                for ($words = $maxWordsPerPokemon; $words >= 1; $words--) {
                    //	Add 1 word at a time
                    $name = implode(" ", array_slice($parameters, $next, $words));
                    
                    try {
                        /** @var Move $move */
                        $move        = $MoveManager->search($name);
                        $types[ $i ] = $move->getType();
                        $next       += $words;
                        break;
                    }
                    catch (ManagerException $e) {
                        try {
                            $pokemon     = $PokemonManager->search($name);
                            $types[ $i ] = $pokemon;
                            $next       += $words;
                            break;
                        }
                        catch (ManagerException $e) {}
                    }

                    //	No pokemon and we've no words left to check
                    if ($words == 1)
                        throw new TypesModuleException("Invalid type '$name'.");

                }

            }
        }
        
        //  No user-overriden mode specified
        if (!$mode) {
            //	Default to a defensive matchup for a single pokemon, or for multi-type
            if (($types[0] instanceof Pokemon && !$types[1]) || (is_array($types[0]) && count($types[0]) > 2))
                $mode = "defensive";
            //  Otherwise default to offensive
            else
                $mode = "offensive";
        }

        $result = array();
        
        //	Pokemon mode, search for pokemon whose typing matches what was given
        if ($mode == "pokemon") {
            //	Replace pokemon with type list instead
            if ($types[0] instanceof Pokemon)
                $searchType = $types[0]->getType(0);

            //	Normalize type list to array with indices beginning at 1
            elseif (!is_array($types[0]))
                $searchType = array(1 => $types[0]);
            else
                $searchType = array(1 => $types[0][0], 2 => $types[0][1]);

            $searchType = array_map(function ($element) {
                return ucwords(strtolower($element));
            }, $searchType);

            //	Add criteria to allow reverse order for dual types
            $criteria = array(
                new ManagerSearchCriterion($PokemonManager, "types", "==", $searchType)
            );
            if (count($searchType) == 2)
                $criteria[] = new ManagerSearchCriterion($PokemonManager, "types", "==", array(1 => $searchType[2], 2 => $searchType[1]));

            //	Search matching ANY criteria
            /** @var $pokemon Pokemon[] */
            $pokemon = $PokemonManager->fullSearch($criteria, true, false);

            //	No results
            if (!$pokemon || !count($pokemon))
                throw new TypesModuleException("There are no ".colorType($searchType, true)."-type pokemon.");

            //	Save names of resulting pokemon
            $pokemonNames = array();
            foreach ($pokemon as $object)
                $pokemonNames[] = $object->getName(new Language(Language::English));

            $response = "There are ". bold(count($pokemonNames)). " ". colorType($searchType, true). "-type pokemon: ". implode(", ", $pokemonNames). ".";
            $this->respond($msg, $response);
            return;
        }

        //	Mode was not pokemon, output offensive or defense type info

        //	Pokemon vs. ...
        if ($types[0] instanceof Pokemon) {
            //	Vs. nothing, get type charts
            if (!$types[1]) {
                //	Type chart vs. this pokemon
                if ($mode == "defensive")
                    $result = pokemonMatchup(CHART_BASIC, $types[0]);
                //	This pokemon's compound types vs. type chart
                elseif ($mode == "offensive")
                    $result = typeChart($types[0]->getFormattedType(), "offensive");
            }

            //	Vs. another pokemon
            elseif ($types[1] instanceof Pokemon) {
                //	This pokemon's compound types vs. other pokemon
                if ($mode == "defensive")
                    $result = pokemonMatchup($types[0]->getFormattedType(), $types[1]);
                //	Other pokemon's compound types vs. this pokemon
                elseif ($mode == "offensive")
                    $result = pokemonMatchup($types[1]->getFormattedType(), $types[0]);
            }

            //	Vs. a type
            else {
                //	Type vs. this pokemon
                if ($mode == "defensive")
                    $result = pokemonMatchup($types[1], $types[0]);
                //	This pokemon's compound types vs. type
                elseif ($mode == "offensive")
                    $result = typeMatchup($types[0]->getFormattedType(), $types[1]);
            }
        }

        //	Type vs. ...
        else {
            //	Nothing, get type charts
            if (!$types[1])
                $result = typeChart($types[0], $mode);

            //	Vs. a pokemon
            elseif ($types[1] instanceof Pokemon) {
                //	Pokemon's compound types vs. this type
                if ($mode == "defensive")
                    $result = typeMatchup($types[1]->getFormattedType(), $types[0]);
                //	This type vs. pokemon
                elseif ($mode == "offensive")
                    $result = pokemonMatchup($types[0], $types[1]);
            }

            //	Vs. another type
            else {
                //	Other type vs. this type
                if ($mode == "defensive")
                    $result = typeMatchup($types[1], $types[0]);
                //	This type vs. other type
                elseif ($mode == "offensive")
                    $result = typeMatchup($types[0], $types[1]);
            }
        }

        //	Vs. nothing, output chart results
        if (!$types[1]) {
            $abilities = array();
            //	Save ability effects, if applicable
            if ($types[0] instanceof Pokemon && isset($result['abilities']))
                $abilities = $result['abilities'];

            //	Filter out 1x matchups
            $result = array_filter($result, function($element) {
                return (is_numeric($element) && $element != 1);
            });

            //	Function to format matchups. Save for additional use in ability charts if needed
            $parseChart = function($result) {
                //	Group types of the same effectiveness together
                $chart = array();
                foreach ($result as $type => $multiplier)
                    $chart[(string)$multiplier][] = colorType($type);
                ksort($chart);

                //	Append list of types to each multiplier for output
                $output = array();
                foreach ($chart as $multiplier => $entry)
                    $output[] = bold($multiplier."x") . ": " . implode(", ", $entry);

                //	Each element has Multipler: type1, type2, etc
                return $output;
            };
            $output = $parseChart($result);

            //	Add an extra line for abilities if needed, using the same $parseChart function
            $abilityOutput = array();
            foreach ($abilities as $ability => $abilityChart)
                $abilityOutput[] = sprintf("[%s]: %s", bold($ability), implode(" :: ", $parseChart($abilityChart)));

            //	Format intro with Pokemon name and type
            if ($types[0] instanceof Pokemon)
                $outputString = sprintf(
                    "%s (%s)",
                    bold($types[0]->getName(new Language(Language::English))),
                    colorType($types[0]->getFormattedType(), true)
                );

            //	Just a type
            else
                $outputString = colorType($types[0], true);

            //	Append formatted chart
            $outputString .= " $mode type chart: ". implode(" :: ", $output);

            //	Stick ability output on a new line if we have any
            if ($abilityOutput)
                $outputString .= "\n". implode("; ", $abilityOutput);

            $this->respond($msg, $outputString);
        }

        //	Vs. another type, output matchup
        else {
            $abilities = array();
            //	Save ability effects, if applicable
            if (isset($result['abilities'])) {
                $abilities = $result['abilities'];
                unset($result['abilities']);
            }

            //	Flatten array after removal of abilities
            if (is_array($result)) {
                $key = array_keys($result)[0];
                $result = $result[$key];
            }

            //	Add an extra line for abilities if needed
            $abilityOutput = array();
            foreach ($abilities as $ability => $abilityChart) {
                //	There should only be a single entry
                $key = array_keys($abilityChart)[0];
                $abilityOutput[] = sprintf("[%s]: %sx", bold($ability), $abilityChart[$key]);
            }

            //	Format intro with Pokemon name and type
            if ($types[0] instanceof Pokemon)
                $outputString = sprintf(
                    "%s (%s)",
                    bold($types[0]->getName(new Language(Language::English))),
                    colorType($types[0]->getFormattedType(), true)
                );

            //	Just a type
            else
                $outputString = colorType($types[0], true);

            $outputString .= " vs ";
            //	Format opponent
            if ($types[1] instanceof Pokemon)
                $outputString .= sprintf(
                    "%s (%s)",
                    bold($types[1]->getName(new Language(Language::English))),
                    colorType($types[1]->getFormattedType(), true));
            //	Just a type
            else
                $outputString .= colorType($types[1], true);

            $outputString .= ": ". bold((string)$result). "x";
            //	Stick ability output on a new line if we have any
            if ($abilityOutput)
                $outputString .= "\n". implode("; ", $abilityOutput);

            $this->respond($msg, $outputString);
        }

    }

    public function coverage(IRCMessage $msg) {
        $parameters = $msg->getCommandParameters();

        $typeDisplay = array();
        foreach ($parameters as $key => $type) {
            if (!hasChart($type))
                throw new TypesModuleException("Invalid type '$type'.");

            $typeDisplay[] = colorType($type, true);
            $parameters[$key] = strtolower($type);
        }

        $pokemonList = $this->getOutsideManager("Pokemon")->collection();
        $requiredResistances = count($parameters);
        $resistingPokemon = array();

        foreach ($pokemonList as $pokemon) {
            if ($pokemon instanceof Pokemon) {
                $abilityNames = array();
                $actualResistances = array('base' => 0);

                foreach ($parameters as $key => $type) {
                    $results = pokemonMatchup($type, $pokemon);

                    if (isset($results[$type]) && $results[$type] < 1)
                        $actualResistances['base']++;

                    elseif (isset($results['abilities'])) {
                        foreach ($results['abilities'] as $ability => $chart) {
                            if (!count($abilityNames))
                                $abilityNames[] = $ability;

                            if (isset($chart[$type]) && $chart[$type] < 1)
                                @$actualResistances[$ability]++;
                        }
                    }


                }

                if ($actualResistances['base'] == $requiredResistances)
                    $resistingPokemon[] = $pokemon->getName(new Language(Language::English));

                else {
                    foreach ($abilityNames as $ability) {
                        if (isset($actualResistances[$ability]) && ($actualResistances[$ability] + $actualResistances['base']) == $requiredResistances)
                            $resistingPokemon[] = $pokemon->getName(new Language(Language::English)). " [". italic(ucwords($ability)). "]";
                    }
                }

            }
        }

        $count = count($resistingPokemon);
        if ($count > 30) {
            $resistingPokemon = array_slice($resistingPokemon, 0, 30);
            $resistingPokemon[] = "and ". ($count-30). " more";
        }

        $output = "There are $count pokemon that resist ". implode(", ", $typeDisplay). ": ". implode(", ", $resistingPokemon);

        $this->respond($msg, $output);
    }
}
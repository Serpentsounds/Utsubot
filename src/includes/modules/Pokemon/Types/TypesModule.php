<?php
/**
 * Utsubot - TypesModule.php
 * Date: 21/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon\Types;


use Utsubot\{
    Help\HelpEntry,
    IRCBot,
    IRCMessage,
    Trigger
};
use Utsubot\Manager\{
    ManagerException,
    Operator,
    SearchCriteria,
    SearchCriterion,
    SearchMode
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
class TypesModuleException extends ModuleWithPokemonException {

}


/**
 * Class TypesModule
 *
 * @package Utsubot\Pokemon\Types
 */
class TypesModule extends ModuleWithPokemon {

    /**
     * TypesModule constructor.
     *
     * @param IRCBot $IRCBot
     */
    public function __construct(IRCBot $IRCBot) {
        parent::__construct($IRCBot);

        //  Command triggers
        $types = new Trigger("ptype", [ $this, "type" ]);
        $this->addTrigger($types);

        $coverage = new Trigger("pcoverage", [ $this, "coverage" ]);
        $coverage->addAlias("pcov");
        $this->addTrigger($coverage);

        //  Help entries
        $typeHelp = new HelpEntry("Pokemon", $types);
        $typeHelp->addParameterTextPair("[-d] TYPE1[/TYPE2] TYPE1[/TYPE2]", "View the type matchup of the first type or pair of types vs. the second. Specify -d to reverse the matchup.");
        $typeHelp->addParameterTextPair(
            "[-d|-o] TYPE1[/TYPE2]",
            "View an offensive type chart of the given type or pair of types. Specify -o to force an offensive chart, or -d to force a defensive chart."
        );
        $typeHelp->addParameterTextPair("[-p] TYPE1[/TYPE2]", "View a list of Pokemon whose typing matches the given type or pair of types.");
        $typeHelp->addNotes("A Pokemon or move name can be used in place of a type name to fill in their respective corresponding values.");
        $typeHelp->addNotes("Specifying a Pokemon in chart mode will imply a defensive type chart, and take into account the Pokemon's abilities.");
        $this->addHelp($typeHelp);

        $coverageHelp = new HelpEntry("Pokemon", $coverage);
        $coverageHelp->addParameterTextPair("TYPE1 [TYPE2] ... [TYPEN]", "View the combined coverage of the list of types. A list of Pokemon who resist all given types will be returned.");
        $coverageHelp->addNotes("Pokemon abilities will be taken into account for type effectiveness.");
        $this->addHelp($coverageHelp);

    }


    /**
     * @param IRCMessage $msg
     * @throws ManagerException
     * @throws ModuleWithPokemonException
     * @throws TypesException
     * @throws TypesModuleException
     */
    public function type(IRCMessage $msg) {
        $parameters = $msg->getCommandParameters();

        //  Check if switches were applied
        $copy      = $parameters;
        $switch    = null;
        $firstWord = strtolower(array_shift($copy));
        //  Switch detected, save it and remove from parameters
        if (substr($firstWord, 0, 1) == "-") {
            $switch     = substr($firstWord, 1);
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

        $next           = 0;
        $types          = [ null, null ];
        $PokemonManager = $this->getOutsideManager("Pokemon");
        $MoveManager    = $this->getOutsideManager("Move");
        //  Loop through words until we find 2 parameters
        for ($i = 0; $i <= 1; $i++) {
            //  No more words to check
            if (!isset($parameters[ $next ]))
                break;

            //  Save next two words to check for flying press
            $nextTwo = implode(" ", array_slice($parameters, $next, 2));

            //  Two word type (flying press)
            if (hasChart($nextTwo)) {
                $types[ $i ] = $nextTwo;
                $next += 2;
            }
            //  One word type (all others)
            elseif (hasChart($parameters[ $next ]))
                $types[ $i ] = $parameters[ $next++ ];

            //  Type list delimited by "/"
            elseif (strpos($parameters[ $next ], "/") !== false) {
                $userTypes = explode("/", $parameters[ $next ]);

                //  Check each type individually

                $types[ $i ] = [ ];
                foreach ($userTypes as $type) {
                    if (hasChart($type))
                        $types[ $i ][] = $type;
                    else
                        //  Invalid type given, abort
                        throw new TypesModuleException("Invalid type '$type'.");
                }
                $next++;
            }

            //  Type name not found, check for pokemon or move names
            else {
                //  Check for multi-word pokemon
                $maxWordsPerPokemon = 3;
                for ($words = $maxWordsPerPokemon; $words >= 1; $words--) {
                    //  Add 1 word at a time
                    $name = implode(" ", array_slice($parameters, $next, $words));

                    try {
                        /** @var Move $move */
                        $move        = $MoveManager->findFirst($name);
                        $types[ $i ] = $move->getType();
                        $next += $words;
                        break;
                    }
                    catch (ManagerException $e) {
                        try {
                            $pokemon     = $PokemonManager->findFirst($name);
                            $types[ $i ] = $pokemon;
                            $next += $words;
                            break;
                        }
                        catch (ManagerException $e) {
                        }
                    }

                    //  No Pokemon/move and we've no words left to check
                    if ($words == 1)
                        throw new TypesModuleException("Invalid type '$name'.");

                }

            }
        }

        //  No user-overriden mode specified
        if (!$mode) {
            //  Default to a defensive matchup for a single pokemon, or for multi-type
            if (($types[ 0 ] instanceof Pokemon && !$types[ 1 ]) || (is_array($types[ 0 ]) && count($types[ 0 ]) > 1))
                $mode = "defensive";
            //  Otherwise default to offensive
            else
                $mode = "offensive";
        }

        $result = [ ];

        //  Pokemon mode, search for pokemon whose typing matches what was given
        if ($mode == "pokemon") {
            //  Replace pokemon with type list instead
            if ($types[ 0 ] instanceof Pokemon)
                $searchType = $types[ 0 ]->getTypes();

            //  Normalize type list to array with indices beginning at 1
            elseif (!is_array($types[ 0 ]))
                $searchType = [ 0 => $types[ 0 ] ];
            else
                $searchType = [ 0 => $types[ 0 ][ 0 ], 1 => $types[ 0 ][ 1 ] ];

            $searchType = array_map(function ($element) {
                return strtolower($element);
            }, $searchType);

            //  Add criteria to allow reverse order for dual types
            $criteria   = new SearchCriteria();
            $criteria[] = new SearchCriterion("getTypes", [ ], new Operator("=="), $searchType);
            if (count($searchType) == 2)
                $criteria[] = new SearchCriterion("getTypes", [ ], new Operator("=="), [ 0 => $searchType[ 1 ], 1 => $searchType[ 0 ] ]);

            //  Search matching ANY criteria
            /** @var $pokemon Pokemon[] */
            $pokemon = $PokemonManager->advancedSearch($criteria, new SearchMode(SearchMode::Any));

            //  No results
            if (!$pokemon)
                throw new TypesModuleException("There are no ".colorType($searchType, true)."-type pokemon.");

            //  Save names of resulting pokemon
            $pokemonNames = [ ];
            foreach ($pokemon as $object)
                $pokemonNames[] = $object->getName(new Language(Language::English));

            $response = "There are ".bold((string)count($pokemonNames))." ".colorType($searchType, true)."-type pokemon: ".implode(", ", $pokemonNames).".";
            $this->respond($msg, $response);

            return;
        }

        //  Mode was not pokemon, output offensive or defense type info

        //  Pokemon vs. ...
        if ($types[ 0 ] instanceof Pokemon) {
            //  Vs. nothing, get type charts
            if (!$types[ 1 ]) {
                //  Type chart vs. this pokemon
                if ($mode == "defensive")
                    $result = pokemonMatchup(CHART_BASIC, $types[ 0 ]);
                //  This pokemon's compound types vs. type chart
                elseif ($mode == "offensive")
                    $result = typeChart($types[ 0 ]->getTypes(), "offensive");
            }

            //  Vs. another pokemon
            elseif ($types[ 1 ] instanceof Pokemon) {
                //  This pokemon's compound types vs. other pokemon
                if ($mode == "defensive")
                    $result = pokemonMatchup($types[ 0 ]->getTypes(), $types[ 1 ]);
                //  Other pokemon's compound types vs. this pokemon
                elseif ($mode == "offensive")
                    $result = pokemonMatchup($types[ 1 ]->getTypes(), $types[ 0 ]);
            }

            //  Vs. a type
            else {
                //  Type vs. this pokemon
                if ($mode == "defensive")
                    $result = pokemonMatchup($types[ 1 ], $types[ 0 ]);
                //  This pokemon's compound types vs. type
                elseif ($mode == "offensive")
                    $result = typeMatchup($types[ 0 ]->getTypes(), $types[ 1 ]);
            }
        }

        //  Type vs. ...
        else {
            //  Nothing, get type charts
            if (!$types[ 1 ])
                $result = typeChart($types[ 0 ], $mode);

            //  Vs. a pokemon
            elseif ($types[ 1 ] instanceof Pokemon) {
                //  Pokemon's compound types vs. this type
                if ($mode == "defensive")
                    $result = typeMatchup($types[ 1 ]->getTypes(), $types[ 0 ]);
                //  This type vs. pokemon
                elseif ($mode == "offensive")
                    $result = pokemonMatchup($types[ 0 ], $types[ 1 ]);
            }

            //  Vs. another type
            else {
                //  Other type vs. this type
                if ($mode == "defensive")
                    $result = typeMatchup($types[ 1 ], $types[ 0 ]);
                //  This type vs. other type
                elseif ($mode == "offensive")
                    $result = typeMatchup($types[ 0 ], $types[ 1 ]);
            }
        }

        //  Vs. nothing, output chart results
        if (!$types[ 1 ]) {
            $abilities = [ ];
            //  Save ability effects, if applicable
            if ($types[ 0 ] instanceof Pokemon && isset($result[ 'abilities' ]))
                $abilities = $result[ 'abilities' ];

            //  Filter out 1x matchups
            $result = array_filter($result, function ($element) {
                return (is_numeric($element) && $element != 1);
            });

            //  Function to format matchups. Save for additional use in ability charts if needed
            $parseChart = function ($result) {
                //  Group types of the same effectiveness together
                $chart = [ ];
                foreach ($result as $type => $multiplier)
                    $chart[ (string)$multiplier ][] = colorType($type);
                ksort($chart);

                //  Append list of types to each multiplier for output
                $output = [ ];
                foreach ($chart as $multiplier => $entry)
                    $output[] = bold($multiplier."x").": ".implode(", ", $entry);

                //  Each element has Multipler: type1, type2, etc
                return $output;
            };
            $output     = $parseChart($result);

            //  Add an extra line for abilities if needed, using the same $parseChart function
            $abilityOutput = [ ];
            foreach ($abilities as $ability => $abilityChart)
                $abilityOutput[] = sprintf("[%s]: %s", bold($ability), implode(" :: ", $parseChart($abilityChart)));

            //  Format intro with Pokemon name and type
            if ($types[ 0 ] instanceof Pokemon)
                $outputString = sprintf(
                    "%s (%s)",
                    bold($types[ 0 ]->getName(new Language(Language::English))),
                    colorType($types[ 0 ]->getFormattedType(), true)
                );

            //  Just a type
            else
                $outputString = colorType($types[ 0 ], true);

            //  Append formatted chart
            $outputString .= " $mode type chart: ".implode(" :: ", $output);

            //  Stick ability output on a new line if we have any
            if ($abilityOutput)
                $outputString .= "\n".implode("; ", $abilityOutput);

            $this->respond($msg, $outputString);
        }

        //  Vs. another type, output matchup
        else {
            $abilities = [ ];
            //  Save ability effects, if applicable
            if (isset($result[ 'abilities' ])) {
                $abilities = $result[ 'abilities' ];
                unset($result[ 'abilities' ]);
            }

            //  Flatten array after removal of abilities
            if (is_array($result)) {
                $key    = array_keys($result)[ 0 ];
                $result = $result[ $key ];
            }

            //  Add an extra line for abilities if needed
            $abilityOutput = [ ];
            foreach ($abilities as $ability => $abilityChart) {
                //  There should only be a single entry
                $key             = array_keys($abilityChart)[ 0 ];
                $abilityOutput[] = sprintf("[%s]: %sx", bold($ability), $abilityChart[ $key ]);
            }

            //  Format intro with Pokemon name and type
            if ($types[ 0 ] instanceof Pokemon)
                $outputString = sprintf(
                    "%s (%s)",
                    bold($types[ 0 ]->getName(new Language(Language::English))),
                    colorType($types[ 0 ]->getFormattedType(), true)
                );

            //  Just a type
            else
                $outputString = colorType($types[ 0 ], true);

            $outputString .= " vs ";
            //  Format opponent
            if ($types[ 1 ] instanceof Pokemon)
                $outputString .= sprintf(
                    "%s (%s)",
                    bold($types[ 1 ]->getName(new Language(Language::English))),
                    colorType($types[ 1 ]->getFormattedType(), true));
            //  Just a type
            else
                $outputString .= colorType($types[ 1 ], true);

            $outputString .= ": ".bold((string)$result)."x";
            //  Stick ability output on a new line if we have any
            if ($abilityOutput)
                $outputString .= "\n".implode("; ", $abilityOutput);

            $this->respond($msg, $outputString);
        }

    }


    /**
     * @param IRCMessage $msg
     * @throws ModuleWithPokemonException
     * @throws TypesModuleException
     */
    public function coverage(IRCMessage $msg) {
        $parameters = $msg->getCommandParameters();

        $typeDisplay = [ ];
        foreach ($parameters as $key => $type) {
            if (!hasChart($type))
                throw new TypesModuleException("Invalid type '$type'.");

            $typeDisplay[]      = colorType($type, true);
            $parameters[ $key ] = strtolower($type);
        }

        $pokemonList         = $this->getOutsideManager("Pokemon")->collection();
        $requiredResistances = count($parameters);
        $resistingPokemon    = [ ];

        foreach ($pokemonList as $pokemon) {
            if ($pokemon instanceof Pokemon) {
                $abilityNames      = [ ];
                $actualResistances = [ 'base' => 0 ];

                foreach ($parameters as $key => $type) {
                    $results = pokemonMatchup($type, $pokemon);

                    if (isset($results[ $type ]) && $results[ $type ] < 1)
                        $actualResistances[ 'base' ]++;

                    elseif (isset($results[ 'abilities' ])) {
                        foreach ($results[ 'abilities' ] as $ability => $chart) {
                            if (!$abilityNames)
                                $abilityNames[] = $ability;

                            if (isset($chart[ $type ]) && $chart[ $type ] < 1)
                                @$actualResistances[ $ability ]++;
                        }
                    }

                }

                if ($actualResistances[ 'base' ] == $requiredResistances)
                    $resistingPokemon[] = $pokemon->getName(new Language(Language::English));

                else {
                    foreach ($abilityNames as $ability) {
                        if (isset($actualResistances[ $ability ]) && ($actualResistances[ $ability ] + $actualResistances[ 'base' ]) == $requiredResistances)
                            $resistingPokemon[] = $pokemon->getName(new Language(Language::English))." [".italic(ucwords($ability))."]";
                    }
                }

            }
        }

        $count = count($resistingPokemon);
        if ($count > 30) {
            $resistingPokemon   = array_slice($resistingPokemon, 0, 30);
            $resistingPokemon[] = "and ".($count - 30)." more";
        }

        $output = "There are $count pokemon that resist ".implode(", ", $typeDisplay).": ".implode(", ", $resistingPokemon);

        $this->respond($msg, $output);
    }
}
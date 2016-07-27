<?php
/**
 * Utsubot - Stats.php
 * Date: 18/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon\Stats;


use Utsubot\Help\HelpEntry;
use Utsubot\Pokemon\{
    ModuleWithPokemon, ModuleWithPokemonException, Pokemon\Pokemon, Stat, Language
};
use Utsubot\{
    IRCBot,
    IRCMessage,
    Trigger,
    Color
};
use function Utsubot\{
    bold,
    colorText,
    Pokemon\Types\colorType,
    Util\checkInt
};


/**
 * Class Stats
 *
 * @package Utsubot\Pokemon\Stats
 */
class StatsModule extends ModuleWithPokemon {

    /**
     * Stats constructor.
     *
     * @param IRCBot $IRCBot
     */
    public function __construct(IRCBot $IRCBot) {
        parent::__construct($IRCBot);

        //  Command triggers
        $triggers[ 'phiddenpower' ] = new Trigger("phiddenpower", [ $this, "hiddenPower" ]);
        $triggers[ 'phiddenpower' ]->addAlias("php");

        $triggers[ 'piv' ] = new Trigger("piv", [ $this, "calculateIVs" ]);

        $triggers[ 'goiv' ] = new Trigger("goiv", [ $this, "goIV" ]);

        $triggers[ 'maxtobase' ] = new Trigger("maxtobase", [ $this, "baseMax" ]);
        $triggers[ 'maxtobase' ]->addAlias("mtob");
        $triggers[ 'maxtobase' ]->addAlias("m2b");

        $triggers[ 'basetomax' ] = new Trigger("basetomax", [ $this, "baseMax" ]);
        $triggers[ 'basetomax' ]->addAlias("btom");
        $triggers[ 'basetomax' ]->addAlias("b2m");

        $triggers[ 'maxtobase50' ] = new Trigger("maxtobase50", [ $this, "baseMax" ]);
        $triggers[ 'maxtobase50' ]->addAlias("mtob50");
        $triggers[ 'maxtobase50' ]->addAlias("m2b50");

        $triggers[ 'basetomax50' ] = new Trigger("basetomax50", [ $this, "baseMax" ]);
        $triggers[ 'basetomax50' ]->addAlias("btom50");
        $triggers[ 'basetomax50' ]->addAlias("b2m50");

        $triggers[ 'pstats' ] = new Trigger("pstat", [ $this, "baseStat" ]);
        $triggers[ 'pstats' ]->addAlias("pstat");

        foreach ($triggers as $trigger)
            $this->addTrigger($trigger);

        //  Help entries
        $help = [ ];

        $help[ 'phiddenpower' ] = new HelpEntry("Pokemon", $triggers[ 'phiddenpower' ]);
        $help[ 'phiddenpower' ]->addParameterTextPair("IVS", "Calculate the type and base power of a Pokemon's hidden power, given its IVs (in the form HP/ATK/DEF/SATK/SDEF/SPE).");

        $help[ 'piv' ] = new HelpEntry("Pokemon", $triggers[ 'piv' ]);
        $help[ 'piv' ]->addParameterTextPair(
            "POKEMON LEVEL NATURE HP[:EVS] ATK[:EVS] DEF[:EVS] SPA[:EVS] SPD[:EVS] SPE[:EVS]",
            "Perform an IV calculation for the Pokemon with the given stats. Fill in POKEMON, LEVEL, and NATURE with the respective values, followed by the stats."
        );
        $help[ 'piv' ]->addNotes("If there are EVs in a stat, append them with :EVS. Example: Stat - 300, Stat with max EVs - 300:252.");

        $help[ 'goiv' ] = new HelpEntry("Pokemon", $triggers[ 'goiv' ]);
        $help[ 'goiv' ]->addParameterTextPair(
            "POKEMON [powered] CP HP DUST_COST TRAINER_LEVEL",
            "Perform a Pokemon GO IV calculation for the given parameters. Add the word 'powered' where shown if you have used stardust on the Pokemon, and fill in the rest of the values."
        );
        #$help[ 'goiv' ]->addNotes("Results are given in sets, formatted as [Level, Stamina, Attack, Defense].");

        $help[ 'maxtobase' ] = new HelpEntry("Pokemon", $triggers[ 'maxtobase' ]);
        $help[ 'maxtobase' ]->addParameterTextPair(
            "[-hp] [-level:LEVEL] VALUE",
            "Return the base stat given a maximum stat as VALUE (assumes positive nature, 31 IVs, 252 EVs)."
        );
        $help[ 'maxtobase' ]->addNotes("Specify -hp to calculate it for the HP stat, and optionally change level from the default of 100 to LEVEL.");

        $help[ 'basetomax' ] = new HelpEntry("Pokemon", $triggers[ 'basetomax' ]);
        $help[ 'basetomax' ]->addParameterTextPair(
            "[-hp] [-level:LEVEL] VALUE",
            "Return the maximum possible stat (positive nature, 31 IVs, 252 EVs) given a base stat as VALUE."
        );
        $help[ 'basetomax' ]->addNotes("Specify -hp to calculate it for the HP stat, and optionally change level from the default of 100 to LEVEL.");

        $help[ 'pstats' ] = new HelpEntry("Pokemon", $triggers[ 'pstats' ]);
        $help[ 'pstats' ]->addParameterTextPair(
            "POKEMON LEVEL NATURE HP[:EVS] ATK[:EVS] DEF[:EVS] SPA[:EVS] SPD[:EVS] SPE[:EVS]",
            "Perform a stat calculation for the Pokemon with the given IVs. Fill in POKEMON, LEVEL, and NATURE with the respective values, followed by the IVs."
        );
        $help[ 'pstats' ]->addNotes("If there are EVs in a stat, append them with :EVS. Example: IV - 20, IV with max EVs - 20:252.");

        foreach ($help as $entry)
            $this->addHelp($entry);
    }


    /**
     * @param IRCMessage $msg
     * @throws ParameterParserException
     * @throws \Utsubot\ModuleException
     */
    public function baseMax(IRCMessage $msg) {
        $parser = new ParameterParser();
        $result = $parser->parseBaseMaxParameters($msg->getCommandParameters(), $msg->getCommand());

        if ($result->getFrom() == "base")
            $calculated = calculateStat($result->getStat(), 31, 252, $result->getLevel(), 1.1, $result->isHp());
        else
            $calculated = calculateBase($result->getStat(), 31, 252, $result->getLevel(), 1.1, $result->isHp());

        $this->respond($msg, sprintf(
            "%s %s = %s %s",
            $result->getStat(),
            $result->getFrom(),
            $calculated,
            $result->getTo()
        ));
    }


    /**
     * @param IRCMessage $msg
     * @throws ParameterParserException
     * @throws \Utsubot\ModuleException
     * @throws \Utsubot\Pokemon\ModuleWithPokemonException
     */
    public function baseStat(IRCMessage $msg) {
        $parser = new ParameterParser();
        $parser->injectManager("Pokemon", $this->getOutsideManager("Pokemon"));
        $parser->injectManager("Nature", $this->getOutsideManager("Nature"));
        $result = $parser->parseIVStatParameters($msg->getCommandParameters());

        $output = [ ];
        for ($i = 0; $i <= 5; $i++) {
            $stat     = new Stat($i);
            $statName = $stat->getName();

            $statValue = calculateStat(
                $result->getPokemon()->getBaseStat($stat),
                $result->getStatValue($i),
                $result->getEV($i),
                $result->getLevel(),
                $result->getNatureMultiplier($i),
                $i == 0
            );

            if ($statName == $result->getNatureIncreases())
                $statName = colorText($statName, new Color(Color::Red));
            elseif ($statName == $result->getNatureDecreases())
                $statName = colorText($statName, new Color(Color::Blue));

            $output[] = sprintf(
                "%s: %s",
                bold($statName),
                $statValue
            );
        }

        $this->respond($msg, sprintf(
            "Stats for %s: %s",
            bold($result->getPokemon()->getName(new Language(Language::English))),
            implode(" ", $output)
        ));

    }


    /**
     * @param IRCMessage $msg
     * @throws ParameterParserException
     * @throws StatCalculatorException
     * @throws \Utsubot\ModuleException
     * @throws \Utsubot\Pokemon\ModuleWithPokemonException
     */
    public function calculateIVs(IRCMessage $msg) {
        $parser = new ParameterParser();
        $parser->injectManager("Pokemon", $this->getOutsideManager("Pokemon"));
        $parser->injectManager("Nature", $this->getOutsideManager("Nature"));
        $result = $parser->parseIVStatParameters($msg->getCommandParameters());

        $output = [ ];
        for ($i = 0; $i <= 5; $i++) {
            $stat     = new Stat($i);
            $statName = $stat->getName();

            $IVRange = getIVRange(
                $result->getPokemon()->getBaseStat($stat),
                $result->getStatValue($i),
                $result->getEV($i),
                $result->getLevel(),
                $result->getNatureMultiplier($i),
                $i == 0
            );

            if ($statName == $result->getNatureIncreases())
                $statName = colorText($statName, new Color(Color::Red));
            elseif ($statName == $result->getNatureDecreases())
                $statName = colorText($statName, new Color(Color::Blue));

            $output[] = sprintf(
                "%s: %s",
                bold($statName),
                implode("-", $IVRange)
            );
        }

        $this->respond($msg, sprintf(
            "Possible IVs for %s: %s",
            bold($result->getPokemon()->getName(new Language(Language::English))),
            implode(" ", $output)
        ));
    }


    /**
     * @param IRCMessage $msg
     * @throws GOIVCalculatorException
     * @throws ModuleWithPokemonException
     * @throws ParameterParserException
     * @throws \Utsubot\ModuleException
     * @throws \Utsubot\Util\UtilException
     */
    public function goIV(IRCMessage $msg) {
        $parameters     = $msg->getCommandParameters();
        $pokemonManager = $this->getOutsideManager("pokemon");
        $maxWords       = 3;
        $object         = null;

        for ($words = 1; $words <= $maxWords; $words++) {
            try {
                //  Add 1 word at a time
                $name   = implode(" ", array_slice($parameters, 0, $words));
                $object = $this->getObject($name, true, $pokemonManager)->offsetGet(0);

                //  Object found
                break;
            }
            catch (ModuleWithPokemonException $e) {
                //  No object and we've no words left to check
                if ($words == $maxWords)
                    throw new ParameterParserException("Unable to find a valid Pokemon.");
            }
        }

        //  Shave pokemon name off of front
        $parameters = array_slice($parameters, $words);

        //  Check for "powered" switch
        $isPowered = false;
        $copy      = $parameters;
        $first     = array_shift($copy);
        if ($first && strtolower($first) == "powered") {
            $isPowered  = true;
            $parameters = $copy;
        }

        //  Make sure all parameters are here
        $this->requireParameters($msg, 4 + (int)$isPowered + $words);

        //  Verify integrity of parameters
        $CP           = checkInt($parameters[ 0 ]);
        $HP           = checkInt($parameters[ 1 ]);
        $dustCost     = checkInt($parameters[ 2 ]);
        $trainerLevel = checkInt($parameters[ 3 ]);

        /** @var Pokemon $object */
        $calculator = new GOIVCalculator($object, $CP, $HP, $dustCost, $trainerLevel, $isPowered);
        $calculator->calculate();

        $this->respond($msg, $calculator->formatResults());
    }


    /**
     * Output information about a pokemon's Hidden Power stats, given a list of their IVs
     *
     * @param IRCMessage $msg
     */
    public function hiddenPower(IRCMessage $msg) {
        $parameterString = $msg->getCommandParameterString();

        //  Match 6 numbers in a row
        if (preg_match('/^(\d{1,2}[\/ \\\\]){5}\d{1,2}$/', $parameterString)) {
            //  Split into individual numbers
            $ivs = array_map("intval", preg_split('/[\/ \\\\]/', $parameterString));

            $hiddenPower = (new HiddenPowerCalculator(...$ivs))->calculate();

            $this->respond($msg, sprintf(
                "Your hidden power is %s-type, with a base power of %s.",
                bold(colorType($hiddenPower->getType())),
                bold($hiddenPower->getPower())
            ));
        }
    }

}
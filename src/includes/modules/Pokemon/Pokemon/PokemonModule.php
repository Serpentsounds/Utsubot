<?php
/**
 * Utsubot - PokemonModule.php
 * Date: 09/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon\Pokemon;
use Utsubot\{
    IRCBot,
    IRCMessage,
    Trigger,
    Color
};
use Utsubot\Accounts\{
    Setting,
    AccountsDatabaseInterfaceException
};
use Utsubot\Pokemon\{
    ModuleWithPokemon,
    ModuleWithPokemonException,
    VeekunDatabaseInterface,
    Version,
    Language
};
use function Utsubot\{
    bold,
    stripControlCodes,
    colorText
};

/**
 * Class PokemonModuleException
 *
 * @package Utsubot\Pokemon\Pokemon
 */
class PokemonModuleException extends ModuleWithPokemonException {}

/**
 * Class PokemonModule
 *
 * @package Utsubot\Pokemon\Pokemon
 */
class PokemonModule extends ModuleWithPokemon {

    /**
     * PokemonModule constructor.
     *
     * @param IRCBot $IRCBot
     */
    public function __construct(IRCBot $IRCBot) {
        parent::__construct($IRCBot);

        $this->registerSetting(new Setting($this, "pinfo", "Pokemon Info Format", 1));
        
        $pokemonManager = new PokemonManager(new VeekunDatabaseInterface());
        $pokemonManager->load();
        $this->registerManager("Pokemon", $pokemonManager);

        $this->addTrigger(new Trigger("poke",       array($this, "pokemon"  )));
        $this->addTrigger(new Trigger("pinfo",      array($this, "pokemon"  )));
        $this->addTrigger(new Trigger("sinfo",      array($this, "pokemon"  )));
        $this->addTrigger(new Trigger("names",      array($this, "pokemon"  )));
        $this->addTrigger(new Trigger("dexes",      array($this, "pokemon"  )));

        $this->addTrigger(new Trigger("dex",        array($this, "dex"      )));

        $this->addTrigger(new Trigger("pcompare",   array($this, "compare"  )));
        $this->addTrigger(new Trigger("pcomp",      array($this, "compare"  )));
    }

    /**
     * Output various information about a pokemon
     *
     * @param IRCMessage $msg
     * @throws PokemonModuleException
     */
    public function pokemon(IRCMessage $msg) {
        $parameters = $msg->getCommandParameters();
        if (!$parameters)
            throw new PokemonModuleException("No Pokemon given.");
        
        $result = $this->getObject($msg->getCommandParameterString());        
        $info = new PokemonInfoFormat($result->current());

        $format = null;
        //	Try to replace default format with user-defined format, if possible
        switch ($msg->getCommand()) {
            
            case "pinfo":
            case "poke":
                try {
                    $format = $this->getSetting($msg->getNick(), $this->getSettingObject("pinfo"));
                }                
                catch (AccountsDatabaseInterfaceException $e) {
                    $format = PokemonInfoFormat::getDefaultFormat();
                }                
                break;
            
            case "sinfo":
                $format = PokemonInfoFormat::getSemanticFormat();
                break;
            
            case "names":
                $format = PokemonInfoFormat::getNamesFormat();
                break;
            
            case "dexes":
                $format = PokemonInfoFormat::getDexesFormat();
                break;
            
            default:
                throw new PokemonModuleException("Unsupported command '{$msg->getCommand()}'.");
                break;
            
        }

        //  Use user-saved units if available
        try {
            $units = $this->getSetting($msg->getNick(), $this->getSettingObject("units"));
            
            switch ($units) {
                
                case "imperial":
                    $info->setUnits(PokemonInfoFormat::UNITS_IMPERIAL);
                    break;
                
                case "metric":
                    $info->setUnits(PokemonInfoFormat::UNITS_METRIC);
                    break;
                
                case "both":
                    $info->setUnits(PokemonInfoFormat::UNITS_BOTH);
                    break;
                
            }
            
        }        
        //  Default to imperial
        catch (AccountsDatabaseInterfaceException $e) {
            $info->setUnits(PokemonInfoFormat::UNITS_IMPERIAL);
        }

        //	Pass format into info function for results
        $this->respond($msg, $info->parseFormat($format));
        
        //  Output spell check suggestions if they are available
        if ($suggestions = $result->formatSuggestions())
            $this->respond($msg, $suggestions);
    }


    /**
     * Output a synopsis mimicking Dexter from the pokemon anime
     *
     * @param IRCMessage $msg
     * @throws PokemonModuleException Invalid switch
     */
    public function dex(IRCMessage $msg) {
        $parameters = $msg->getCommandParameters();
        if (!$parameters)
            throw new PokemonModuleException("No Pokemon given.");

        $version = null;
        $language = new Language(Language::English);

        $match = array();
        $switch = "";
        do {
            if ($switch) {
                switch (strtolower($match[1])) {
                    case "language":
                        $language = Language::fromName($match[2]);
                        break;
                    case "version":
                        $version = Version::fromName($match[2]);
                        break;
                    default:
                        throw new PokemonModuleException("Invalid switch '{$match[1]}'.");
                        break;
                }
            }

            $switch = array_shift($parameters);
        }
        while (preg_match("/^-([^:]+):(.+)/", $switch, $match));

        //  Add last switch attempt back on
        array_unshift($parameters, $switch);

        /** @var Pokemon $result */
        $result = $this->getObject(implode(" ", $parameters))->current();

        if ($version === null)
            $output = $result->getLatestFormattedDexEntry($language);
        else
            $output = $result->getFormattedDexEntry($version, $language);

        $this->respond($msg, $output);

    }


    /**
     * Output a side by side comparison of two pokemon
     *
     * @param IRCMessage $msg
     * @throws PokemonModuleException
     */
    public function compare(IRCMessage $msg) {
        $parameters = $msg->getCommandParameters();

        $next = 0;
        $pokemonList = array(null, null);
        //  Iterate twice for two Pokemon
        for ($i = 0; $i <= 1; $i++) {
            //	No more words to check
            if (!isset($parameters[$next]))
                throw new PokemonModuleException("2 Pokemon must be provided.");

            //	Up to 3 words for Pokemon identifiers (e.g. Mega Charizard Y)
            $maxWordsPerPokemon = 3;

            for ($words = 1; $words <= 3; $words++) {
                //	Add 1 word at a time
                $name = implode(" ", array_slice($parameters, $next, $words));

                try {
                    //  Omit Jaro search to prevent false positives
                    $result            = $this->getObject($name, false);

                    //  Success, save and move to next section of input
                    $pokemonList[ $i ] = $result->current();
                    $next += $words;
                    break;
                }
                //  No results
                catch (ModuleWithPokemonException $e) {
                    //	No additional words left to add and check
                    if ($words == $maxWordsPerPokemon)
                        throw new PokemonModuleException("Invalid pokemon '$name'.");
                }

            }
        }

        //  Separate fields into data for comparison
        $data = array();
        for ($i = 0; $i <= 1; $i++) {
            $info = new PokemonInfoFormat($pokemonList[$i]);
            $data[$i] = array_map("trim", explode("%n", $info->parseFormat(PokemonInfoFormat::getCompareFormat())));
        }

        //  Corresponding indexes in data array
        $statNames = array(2 => " HP", 3 => "Atk", 4 => "Def", 5 => "SpA", 6 => "SpD", 7 => "Spe");

        $blue = new Color(Color::Blue);
        $lime = new Color(Color::Lime);
        $red = new Color(Color::Red);

        foreach ($statNames as $key => $statName) {
            $statValues = array(stripControlCodes($data[0][$i]), stripControlCodes($data[1][$key]));
            //  Default to blue for equal comparison
            $colors = array($blue, $blue);

            //  Dynamic whitespace padding for column alignment
            $paddingWidth = 3;
            $padding = array($paddingWidth - strlen($statValues[0]), $paddingWidth - strlen($statValues[1]));

            //  Not an equal comparison, overwrite colors with green for greater stat and red for lesser
            if ($statValues[0] > $statValues[1])
                $colors = array($lime, $red);
            elseif ($statValues[0] < $statValues[1])
                $colors = array($red, $lime);

            //  Overwrite data with formatted stat with color and padding
            $data[0][$key] = str_repeat(chr(160), $padding[0]). colorText($data[0][ $key ], $colors[0]). $statName;
            $data[1][$key] = str_repeat(chr(160), $padding[1]). colorText($data[1][ $key ], $colors[1]). $statName;
        }

        //  Overwrite data array with an element-per-line output pattern for both Pokemon
        for ($i = 0; $i <= 1; $i++) {
            $data[$i] = array_merge(
                array(
                    $data[$i][0],   //  Name
                    $data[$i][1],   //  Types
                    implode(" ", array_slice($data[$i], 2, 3)), //  First 3 stats
                    implode(" ", array_slice($data[$i], 5, 3))  //  Last 3 stats
                ),
                array_slice($data[$i], 8)   //  Abilities
            );
        }

        //  Calculate additional padding
        $lines = array();
        for ($i = 0; $i <= 1; $i++) {
            //  Find the longest single line
            $maxLength = max(
                array_map(
                    function ($item) {
                        return strlen(stripControlCodes($item));
                    },
                $data[$i]));

            //  Loop through lines and apply padding to match longest line
            foreach ($data[$i] as $key => $line) {
                //  Only need even padding on the left, to push separator to the same column
                if ($i == 0) {
                    $realLength = strlen(stripControlCodes($line));
                    $line = str_repeat(chr(160), $maxLength - $realLength). $line;
                }
                //  Right side will just be copied
                $lines[$key][$i] = $line;
            }

        }
        
        //  Form each line with each Pokemon's stats on either side of the separator
        foreach ($lines as $key => $compareValues)
            $lines[$key] = implode(" | ", $compareValues);

        //  Join line elements together with line break
        $this->respond($msg, implode("\n", $lines));
    }
    
}
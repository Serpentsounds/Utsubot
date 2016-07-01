<?php
/**
 * Utsubot - Dictionary.php
 * User: Benjamin
 * Date: 23/04/2015
 */

namespace Utsubot\Web;
use Utsubot\Help\HelpEntry;
use Utsubot\{
    IRCBot,
    IRCMessage,
    Trigger
};
use function Utsubot\{
    bold,
    italic
};


/**
 * Class DictionaryException
 *
 * @package Utsubot\Web
 */
class DictionaryException extends WebModuleException {}

/**
 * Class Dictionary
 *
 * @package Utsubot\Web
 */
class Dictionary extends WebModule {

    const NumberOfSuggestions = 5;

    /**
     * Dictionary constructor.
     *
     * @param IRCBot $IRCBot
     */
    public function __construct(IRCBot $IRCBot) {
        parent::__construct($IRCBot);
        
        //  Command triggers
        $dictionary = new Trigger("dictionary",     [$this, "dictionary"]);
        $dictionary->addAlias("dic");
        $dictionary->addAlias("define");
        $dictionary->addAlias("def");
        $dictionary->addAlias("d");
        $this->addTrigger($dictionary);
        
        //  Help entries
        $help = new HelpEntry("Web", $dictionary);
        $help->addParameterTextPair("WORD", "Look up the dictionary definition for WORD.");
        $this->addHelp($help);
        
    }

    /**
     * Output results of a dictionary search to the user
     *
     * @param IRCMessage $msg
     * @throws DictionaryException
     *
     * @usage !define <term>
     */
    public function dictionary(IRCMessage $msg) {
        $this->requireParameters($msg, 1);
        $parameters = $msg->getCommandParameters();

        $number = 1;
        $copy = $parameters;
        $last = array_pop($copy);
        //  Match a trailing integer to ordinally cycle through definitions
        if (!preg_match("/\\D+/", $last) && intval($last) > 0) {
            $number = intval($last);
            $parameters = $copy;
        }

        $this->respond($msg, $this->dictionarySearch(implode(" ", $parameters), $number));
    }

    /**
     * Perform a dictionary search
     *
     * @param string $term
     * @param int    $number
     * @return string
     * @throws APIKeysException
     * @throws DictionaryException
     */
    public function dictionarySearch(string $term, int $number = 1): string {
        $APIKey = $this->getAPIKeys()->getAPIKey("dictionary");

        $xml = resourceBody("http://www.dictionaryapi.com/api/v1/references/collegiate/xml/". urlencode($term). "?key=$APIKey");
        $parser = xml_parser_create("UTF-8");
        xml_parse_into_struct($parser, $xml, $values, $indices);

        //	The indexes of the items in the ENTRY indexes array, whose values give us the indexes of the start and close ENTRY tags
        $lowerIndexIndex = ($number - 1) * 2;
        $upperIndexIndex = $lowerIndexIndex + 1;

        //	Indexes not found, definition out of range or not found
        if (!isset($indices['ENTRY'][$lowerIndexIndex]) || !isset($indices['ENTRY'][$upperIndexIndex])) {

            //	Definition not found, but there are suggestions
            if (isset($indices['SUGGESTION'])) {

                $count = 0;
                $suggestions = [ ];
                //	Loop through and save suggestions
                foreach ($indices['SUGGESTION'] as $index) {
                    $suggestions[] = $values[$index]['value'];
                    $count++;

                    //	Too many suggestions, stop early
                    if ($count >= self::NumberOfSuggestions)
                        break;
                }

                //	Add to last suggestion for list formatting
                $lastIndex = count($suggestions) - 1;
                $suggestions[$lastIndex] = "or " . $suggestions[$lastIndex] . "?";

                throw new DictionaryException("No definition found for '$term'. Did you mean: " . implode(", ", $suggestions));
            }

            else
                throw new DictionaryException("No definition found for '$term'.");
        }

        //	Indexes of the start and close ENTRY tags, all definition info will be somewhere between these
        $lowerIndex = $indices['ENTRY'][$lowerIndexIndex];
        $upperIndex = $indices['ENTRY'][$upperIndexIndex];

        //	Grab part of speech index and value
        $partOfSpeechIndex = array_slice($indices['FL'], $lowerIndex, $upperIndex - $lowerIndex)[0];
        $partOfSpeech = $values[$partOfSpeechIndex]['value'];

        //	Search for definitions within range
        $definitionIndices = array_slice($indices['DT'], $lowerIndex, $upperIndex - $lowerIndex);
        $return = [ ];

        foreach ($definitionIndices as $key => $index) {
            $definitionInfo = $values[$index];

            //	Closing tag, no data here, skip
            if ($definitionInfo['type'] == "close")
                continue;

            $definition = $definitionInfo['value'];
            //	Match the relevant info from definition
            preg_match("/^\s*:?(.*?)(?:\s*:)?$/", $definition, $match);
            $definition = trim($match[1]);

            //	Opening tag, new definition with other tags in the middle
            if ($definitionInfo['type'] == "open") {

                //	Main definition is empty, check nested tags
                if (!strlen($definition) && !$return) {

                    //	Search through all tags between this and the definition closing tag
                    $nextDefinitionIndex = $definitionIndices[$key+1];
                    for ($i = $index + 1; $i < $nextDefinitionIndex; $i++) {
                        $itemInfo = $values[$i];

                        //	SX tag found, redirect to word
                        if ($itemInfo['tag'] == "SX")
                            return self::dictionarySearch($itemInfo['value'], $number);
                    }
                }

                //	Main definition has content, use that instead
                else
                    $return[] = $definition;
            }

            //	Complete definition tag, no extra tags in between
            elseif  ($definitionInfo['type'] == "complete")
                $return[] = $definition;

            //	Continuing from an "open" definition from interruption of some other tag
            elseif ($definitionInfo['type'] == "cdata") {
                $lastIndex = count($return) - 1;
                if (isset($return[$lastIndex]))
                    $return[$lastIndex] = trim($return[$lastIndex]). trim($definition);
            }
        }

        //  No data was extracted from definition, check the next one
        if (!$return)
            return self::dictionarySearch($term, $number + 1);

        return sprintf(
            "%s (%s): %s",
            bold($term),
            italic($partOfSpeech),
            implode("; ", array_filter($return))
        );

    }

}
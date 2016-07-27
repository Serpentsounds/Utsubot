<?php
/**
 * Utsubot - UrbanDictionary.php
 * User: Benjamin
 * Date: 24/01/2015
 */

namespace Utsubot\Web;


use Utsubot\Help\HelpEntry;
use Utsubot\Util\UtilException;
use Utsubot\{
    IRCBot,
    IRCMessage,
    Trigger
};
use function Utsubot\bold;
use function Utsubot\Util\checkInt;


/**
 * Class UrbanDictionaryException
 *
 * @package Utsubot\Web
 */
class UrbanDictionaryException extends WebModuleException {

}


/**
 * Class UrbanDictionary
 *
 * @package Utsubot\Web
 */
class UrbanDictionary extends WebModule {

    /**
     * UrbanDictionary constructor.
     *
     * @param IRCBot $IRCBot
     */
    public function __construct(IRCBot $IRCBot) {
        parent::__construct($IRCBot);

        //  Command triggers
        $urbanDictionary = new Trigger("urbandictionary", [ $this, "define" ]);
        $urbanDictionary->addAlias("urban");
        $urbanDictionary->addAlias("ud");
        $this->addTrigger($urbanDictionary);

        //  Help entries
        $help = new HelpEntry("Web", $urbanDictionary);
        $help->addParameterTextPair("WORD", "Look up the urban dictionary definition for WORD.");
        $this->addHelp($help);

    }


    /**
     * Output results of an  Urban Dictionary search to the user
     *
     * @param IRCMessage $msg
     * @throws DictionaryException
     *
     * @usage !ud <term> [<ordinal>]
     */
    public function define(IRCMessage $msg) {
        $parameters = $msg->getCommandParameters();

        $number = 1;
        $copy   = $parameters;
        $last   = array_pop($copy);

        //  Match a trailing integer to ordinally cycle through definitions
        try {
            $number = checkInt($last);
            $parameters = $copy;
        }
        catch (UtilException $e){}

        $this->respond($msg, $this->urbanDictionarySearch(implode(" ", $parameters), $number));
    }


    /**
     * Get a definition from Urban Dictionary
     *
     * @param string $term
     * @param        int number
     * @return string
     * @throws UrbanDictionaryException If term is not found
     */
    public function urbanDictionarySearch(string $term, int $number = 1): string {
        if (!$term)
            $content = resourceBody("http://www.urbandictionary.com/random.php");
        else
            $content = resourceBody("http://www.urbandictionary.com/define.php?term=".urlencode($term));

        $regex =
            "!<div class='def-header'>\s*".
            "<a[^>]+>([^<]+)</a>\s*".
            "(?:<a class='play-sound'[^>]+>\s*".
            "<i[^>]+><svg[^>]+><path[^>]+></svg></i>\s*".
            "</a>\s*)?".
            "</div>\s*".
            "<div class='meaning'>\s*(.+?)</div>\s*".
            "<div class='example'>\s*(.+?)</div>!s";
        $number -= 1;

        if (!preg_match_all($regex, $content, $match, PREG_SET_ORDER))
            throw new UrbanDictionaryException("No definition found for '$term'.");

        elseif (!isset($match[ $number ]))
            throw new UrbanDictionaryException("Definition number $number not found for '$term'.");

        $result = sprintf("%s: %s\n%s",
                          bold(stripHTML($match[ $number ][ 1 ])),
                          stripHTML($match[ $number ][ 2 ]),
                          stripHTML($match[ $number ][ 3 ]));

        if (mb_strlen($result) > 750)
            $result = mb_substr($result, 0, 750)." ...More at http://www.urbandictionary.com/define.php?term=".urlencode($match[ $number ][ 1 ]);

        return $result;
    }
}
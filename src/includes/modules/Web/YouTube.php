<?php
/**
 * Utsubot - YouTube.php
 * Date: 01/05/2016
 */

declare(strict_types = 1);

namespace Utsubot\Web;
use Utsubot\Accounts\Setting;
use Utsubot\Help\HelpEntry;
use Utsubot\{
    IRCBot,
    IRCMessage,
    Trigger
};
use function Utsubot\bold;


/**
 * Class YouTubeException
 *
 * @package Utsubot\Web
 */
class YouTubeException extends WebModuleException {}

/**
 * Class YouTube
 *
 * @package Utsubot\Web
 */
class YouTube extends WebModule {
    
    const DefaultResults    = 1;
    const MaxResults        = 3;

    /**
     * YouTube constructor.
     *
     * @param IRCBot $IRCBot
     */
    public function __construct(IRCBot $IRCBot) {
        parent::__construct($IRCBot);

        //  Account settings
        $this->registerSetting(new Setting($this, "youtuberesults",  "YouTube Result Count", 1));

        //  Command triggers
        $youtubeSearch = new Trigger("youtubesearch",  [$this, "youtube"]);
        $youtubeSearch->addAlias("youtube");
        $youtubeSearch->addAlias("yts");
        $youtubeSearch->addAlias("yt");
        $this->addTrigger($youtubeSearch);
        
        //  Help entries
        $help = new HelpEntry("Web", $youtubeSearch);
        $help->addParameterTextPair(
            "[-results:RESULTS]",
            "Search YouTube for videos matching SEARCH. Optionally specify a result count as RESULTS (default ". self::DefaultResults. ", maximum ". self::MaxResults. ")."
        );
        $this->addHelp($help);
        
    }


    /**
     * @param Setting $setting
     * @param string  $value
     * @throws YouTubeException
     */
    public function validateSetting(Setting $setting, string $value) {
        switch ($setting->getName()) {
            case "youtuberesults":
                if (preg_match("/\D/", $value) || intval($value) < 1 || intval($value) > self::MaxResults)
                    throw new YouTubeException("'{$setting->getDisplay()}' must be a positive integer between 1 and ". self::MaxResults. ".");
                break;

            default:
                throw new YouTubeException("Unrecognized setting name '{$setting->getName()}'.");
                break;
        }
    }
    

    /**
     * Output YouTube search results
     * 
     * @param IRCMessage $msg
     * @throws YouTubeException
     * 
     * @usage !yt <search>
     */
    public function youtube(IRCMessage $msg) {
        $this->requireParameters($msg, 1);
        
        //	Try and get user's # of results settings
        try {
            $results = (int)$this->getSetting($msg->getNick(), $this->getSettingObject("youtuberesults"));
        }
        //  Default value
        catch (\Exception $e) {
            $results = self::DefaultResults;
        }
        
        $parameters = $copy = $msg->getCommandParameters();
        $first = array_shift($copy);
        //	Optionally accept an override for result count
        if (preg_match('/^results:(\d+)$/i', $first, $match)) {
            $results = min(self::MaxResults, (int)$match[1]);
            $parameters = $copy;
        }
        
        //  Perform YouTube search. Exception will be thrown if no results are found
        $result = $this->youtubeSearch(implode(" ", $parameters), $results);

        $this->respond($msg, $result);
    }

    /**
     * Query YouTube and return search results
     * 
     * @param string $search
     * @param int    $results
     * @return string
     * @throws YouTubeException
     */
    public function youtubeSearch(string $search, int $results): string {
        $APIKey = $this->getAPIKey("youtube");
        $json = resourceBody("https://www.googleapis.com/youtube/v3/search?part=snippet&type=video&key=$APIKey&q=". urlencode($search));
        $items = json_decode($json, true)['items'];

        $count = count($items);
        if (!$count)
            throw new YouTubeException("No results found.");

        $output = [ ];
        for ($i = 0; $i < $count; $i++) {
            //  Error in result set, abort
            if (!isset($items[$i]))
                break;

            //  Add video to the output
            if ($items[$i]['id']['kind'] == "youtube#video") {
                $output[] = sprintf(
                    "http://www.youtube.com/watch?v=%s%s%s%s%s",
                    $items[$i]['id']['videoId'],
                    self::separator,
                    bold($items[$i]['snippet']['title']),
                    self::separator,
                    $items[$i]['snippet']['description']
                );
            }

            //  Too many results
            if (count($output) >= $results)
                break;
        }
        
        return implode("\n", $output);
    }
}
<?php
/**
 * PHPBot - Google.php
 * User: Benjamin
 * Date: 08/06/14
 */

namespace Utsubot\Web;
use Utsubot\{
    IRCBot,
    IRCMessage,
    ModuleException,
    Trigger
};
use Utsubot\Accounts\Setting;
use function Utsubot\bold;


class GoogleException extends WebModuleException {}

class Google extends WebModule {

	const DefaultResults    = 1;
    const SafeSearch        = false;
	const MaxResults        = 5;

    /**
     * Google constructor.
     *
     * @param IRCBot $IRCBot
     */
	public function __construct(IRCBot $IRCBot) {
        parent::__construct($IRCBot);

        $this->registerSetting(new Setting($this, "safesearch",     "Google Safe Search", 1));
        $this->registerSetting(new Setting($this, "googleresults",  "Google Result Count", 1));

        $this->addTrigger(new Trigger("google",     array($this, "google")));
        $this->addTrigger(new Trigger("g",          array($this, "google")));
    }


    /**
     * @param Setting $setting
     * @param string  $value
     * @throws GoogleException
     */
    public function validateSetting(Setting $setting, string $value) {
        switch ($setting->getName()) {
            case "safesearch":
                if (strlen($value))
                    throw new GoogleException("The setting 'safesearch' does not take any parameters.");
                break;

            case "googleresults":
                if (preg_match("/\D/", $value) || intval($value) < 1 || intval($value) > self::MaxResults)
                    throw new GoogleException("'{$setting->getDisplay()}' must be a positive integer between 1 and ". self::MaxResults. ".");
                break;

            default:
                throw new GoogleException("Unrecognized setting name '{$setting->getName()}'.");
                break;
        }
    }


    /**
     * Output Google search results
     *
     * @param IRCMessage $msg
     * @throws ModuleException If search is blank
     * @throws GoogleException If there are no results for the search
     *
     * @usage !google [results:RESULTS] <search>
     */
    public function google(IRCMessage $msg) {
        $this->requireParameters($msg, 1);

        //	Try and get user's safe-search settings
        try {
            $this->getSetting($msg->getNick(), $this->getSettingObject("safesearch"));
            $safe = true;
        }
        //  Default value
        catch (\Exception $e) {
            $safe = self::SafeSearch;
        }

        //	Try and get user's # of results settings
        try {
            $results = (int)$this->getSetting($msg->getNick(), $this->getSettingObject("googleresults"));
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

        //	Perform google search. May throw a GoogleException, if no results are found
        $result = $this->googleSearch(implode(" ", $parameters), $results, $safe);

        $this->respond($msg, $result);
    }


	/**
	 * Query Google and return search results
	 *
	 * @param string $search
	 * @param int $results
	 * @param bool $safeSearch
	 * @return string
	 * @throws GoogleException If no results are found
	 */
	public function googleSearch(string $search, int $results, bool $safeSearch) {
        $APIKey = $this->getAPIKey("google");
        $engine = ($safeSearch) ? $this->getAPIKey("googlecxsafe") : $this->getAPIKey("googlecx");

		$string = resourceBody(
            "https://www.googleapis.com/customsearch/v1?key=$APIKey&cx=$engine&fields=searchInformation/formattedTotalResults,items%28title,link,snippet%29&q=".
            urlencode($search)
        );

		$data = json_decode($string, TRUE);
		$out = array();

        //  Empty result set
        $resultCount = $data['searchInformation']['formattedTotalResults'];
        if (intval($resultCount) === 0)
            throw new GoogleException("No results found for search '$search'.");


        foreach ($data['items'] as $key => $entry) {

            $out[] = sprintf(
                "%d. %s (%s) - %s",
                $key + 1,
                $entry['link'],
                $entry['title'],
                str_replace("\n", "", $entry['snippet'])
            );

            //  End loop early if result limit is hit
            if ($key + 1 >= $results)
                break;
        }

        //  If returning more than 1 result, add a small header to the list
        if (count($out) > 1)
            array_unshift($out, sprintf(
                "Top $results Google result(s) for %s (of %s total):",
                bold($search),
                $resultCount
            ));


		return implode("\n", $out);
	}

}
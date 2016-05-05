<?php
/**
 * PHPBot - Search.php
 * User: Benjamin
 * Date: 08/06/14
 */

declare(strict_types = 1);

namespace Utsubot\Web;
use Utsubot\{
    IRCBot,
    IRCMessage,
    Trigger
};
use Utsubot\Accounts\{
    Setting,
    AccountsDatabaseInterfaceException
};
use function Utsubot\bold;


/**
 * Class WeatherException
 *
 * @package Utsubot\Web
 */
class WeatherException extends WebModuleException {}

/**
 * Class Weather
 *
 * @package Utsubot\Web
 */
class Weather extends WebModule {

	const ROUNDING_PRECISION = 1;
	const FORECAST_DAYS = 2;

    /**
     * Weather constructor.
     *
     * @param IRCBot $IRCBot
     */
    public function __construct(IRCBot $IRCBot) {
        parent::__construct($IRCBot);

        $this->registerSetting(new Setting($this,   "weather",    "Default Weather Location",    1));
        $this->registerSetting(new Setting($this,   "units",      "Weather Unit System",         1));

        $this->addTrigger(new Trigger("weather",    array($this, "weather")));
        $this->addTrigger(new Trigger("w",          array($this, "weather")));
        $this->addTrigger(new Trigger("forecast",   array($this, "weather")));
        $this->addTrigger(new Trigger("conditions", array($this, "weather")));
    }


    /**
     * @param Setting $setting
     * @param string  $value
     * @throws WeatherException
     */
    public function validateSetting(Setting $setting, string $value) {
        switch ($setting->getName()) {
            case "weather":
                //  Call but don't save location auto-complete to check if location is valid
                $this->weatherLocation($value);
                break;
            case "units":
                //  Call Units factory constructor to verify units name
                Units::fromName($value);
                break;
            default:
                throw new WeatherException("Unrecognized setting name '{$setting->getName()}'.");
                break;
        }
    }


    /**
     * Utility to format a set of metric and imperial values to have units and exclude undesired measurements
     *
     * @param Measurement $measurement
     * @param Units       $units
     * @param float       $imperial
     * @param float       $metric
     * @return string
     */
    public static function formatMeasurements(Measurement $measurement, Units $units, float $imperial, float $metric): string {

        $imperial = round($imperial, self::ROUNDING_PRECISION);
        $metric = round($metric, self::ROUNDING_PRECISION);

        switch ($measurement->getValue()) {
            case Measurement::Temperature:
                $imperial   .= chr(176). "F";
                $metric     .= chr(176). "C";
                break;
            case Measurement::Speed:
                $imperial   .= "MPH";
                $metric     .= "KPH";
                break;
        }

        $return = array();
        if ($units->hasFlag(Units::Imperial))
            $return[] = $imperial;
        if ($units->hasFlag(Units::Metric))
            $return[] = $metric;

        return implode("/", $return);
    }


    /**
     * Output weather information (current conditions, forecast, or both)
     *
     * @param IRCMessage $msg
     * @throws WeatherException
     * 
     * @usage !w <location>
     * @usage !conditions <location>
     * @usage !forecast <location>
     */
    public function weather(IRCMessage $msg) {
        $location = $msg->getCommandParameterString();
        if (!$location) {
            //	Try and get default location from user settings
            try {
                $location = $this->getSetting($msg->getNick(), $this->getSettingObject("weather"));
            }
                //  No default location and no given location
            catch (\Exception $e) {
                throw new WeatherException("Please specify a location or register a default location to your account.");
            }
        }

        //	Try and fetch units preferences from user settings
        try {
            $units = Units::fromName($this->getSetting($msg->getNick(), $this->getSettingObject("units")));
        }
        //  Default to both metric and imperial
        catch (\Exception $e) {
            $units = new Units(Units::Both);
        }

        //	If the user specified "forecast" or "conditions" rather than just "weather" for both, we exclude the other one
        $command = strtolower($msg->getCommand());
        $conditions = ($command == "forecast") ? false : true;
        $forecast = ($command == "conditions") ? false : true;

        //	Get weather info. WeatherException may be thrown if an error occurs
        $result = $this->weatherSearch($location, $units, $conditions, $forecast);

        $this->respond($msg, $result);
    }


    /**
	 * Public interface for searching
	 *
	 * @param string $search
	 * @param Units  $units
     * @param bool   $conditions
     * @param bool   $forecast
	 * @return string
	 */
	public function weatherSearch(string $search, Units $units, bool $conditions, bool $forecast): string {
		//	Convert location name into a string usable by the API
		$location = $this->weatherLocation($search);
		$output = array();

		//	Default to showing both conditions and forecast, but allow override
		if ($conditions)
			$output[] = $this->conditions($location, $units);
		if ($forecast)
			$output[] = $this->forecast($location, $units);

		return implode("\n", $output);
	}


	/**
	 * Utility to convert a location search into an API-usable string by using the API's autocomplete
	 *
	 * @param string $search
	 * @return string
	 * @throws WeatherException If the location isn't found
	 */
	public function weatherLocation(string $search): string {
		//	Convert from UTF-8 to UTF-8 to fix some special character errors
		$string = mb_convert_encoding(resourceBody("http://autocomplete.wunderground.com/aq?query=". urlencode($search)), "UTF-8", "UTF-8");

		$results = json_decode($string, TRUE);

		//	$query is set to an API-specific identifier returned by this search page
		$i = 0;
		$query = "";
		//	Save the identifier of the first result that matches the identifier format
		do {
			if (isset($results['RESULTS'][$i]['l']))
				$query = $results['RESULTS'][$i++]['l'];
			else
				break;
		} while (strpos($query, '/q/zmw:') === FALSE);

		//	List of results exhausted with no success
		if (!$query)
			throw new WeatherException("No location found for '$search'.");

		return $query;
	}


	/**
	 * Get the current conditions for an area
	 *
	 * @param string $location The API identifier for a location
	 * @param Units $units
	 * @return string
	 * @throws WeatherException If information isn't available
	 */
	public function conditions(string $location, Units $units): string {
        $APIKey = $this->getAPIKey("weather");
		$page = resourceBody("http://api.wunderground.com/api/$APIKey/conditions$location.json");
		$results = json_decode($page, TRUE);

		//	Check for this index to verify the search was successful
		if (!isset($results['current_observation']) || !($conditions = $results['current_observation']))
			throw new WeatherException("Weather::weather: Current observation not available for '$location'.");

		$output = array();

		//	Location, conditions, temperature
		$output[] = sprintf(
            "Current conditions for %s: %s (%s).",
            bold($conditions['display_location']['full']),
            $conditions['weather'],
            self::formatMeasurements(
                new Measurement(Measurement::Temperature),
                $units,
                (float)$conditions['temp_f'],
                (float)$conditions['temp_c']
            )
        );

		//	Only include heat index/wind chill if it is different
		if ($conditions['temp_f'] != $conditions['feelslike_f'])
			$output[] = sprintf(
                "Feels like %s.",
                self::formatMeasurements(
                    new Measurement(Measurement::Temperature),
                    $units,
                    (float)$conditions['feelslike_f'],
                    (float)$conditions['feelslike_c']
                )
            );

		//	Humidity, wind
		$output[] = sprintf(
            "Humidity: %s. Wind: From %s at %s.",
            $conditions['relative_humidity'],
            $conditions['wind_dir'],
            self::formatMeasurements(
                new Measurement(Measurement::Speed),
                $units,
                (float)$conditions['wind_mph'],
                (float)$conditions['wind_kph']
            )
        );

		//	Only include wind gusts if they are different
		if ($conditions['wind_mph'] < $conditions['wind_gust_mph'])
			$output[] = sprintf(
                "Gusting to %s.",
                self::formatMeasurements(
                    new Measurement(Measurement::Speed),
                    $units,
                    (float)$conditions['wind_gust_mph'],
                    $conditions['wind_gust_kph']
                )
            );

		return implode(" ", $output);
	}


	/**
	 * Get the n-day forecast for an area
	 *
	 * @param string $location The API identifier for a location
	 * @param Units $units
	 * @return string
	 * @throws WeatherException If information isn't available
	 */
	public function forecast(string $location, Units $units): string {
        $APIKey = $this->getAPIKey("weather");
		$page = resourceBody("http://api.wunderground.com/api/$APIKey/forecast$location.json");
		$results = json_decode($page, TRUE);

		//	Check for this index to verify the search was successful
		if (empty($results['forecast']['simpleforecast']['forecastday']))
			throw new WeatherException("Forecast is not available for '$location'.");
        $forecast = $results['forecast']['simpleforecast']['forecastday'];

		$output = array();
		//	Add a configurable number of days
		for ($day = 0; $day < self::FORECAST_DAYS; $day++) {
			$conditions = $forecast[$day];

			$output[] = sprintf(
                "%s: %s. High of %s. Low of %s.",
                bold($conditions['date']['weekday']),
                $conditions['conditions'],
                self::formatMeasurements(
                    new Measurement(Measurement::Temperature),
                    $units,
                    (float)$conditions['high']['fahrenheit'],
                    (float)$conditions['high']['celsius']
                ),
                self::formatMeasurements(
                    new Measurement(Measurement::Temperature),
                    $units,
                    (float)$conditions['low']['fahrenheit'],
                    (float)$conditions['low']['celsius']
                )
            );
		}

		return implode(" ", $output);
	}
}
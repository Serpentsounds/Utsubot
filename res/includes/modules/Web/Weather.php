<?php
/**
 * PHPBot - Search.php
 * User: Benjamin
 * Date: 08/06/14
 */

class WeatherException extends WebSearchException {}

class Weather implements WebSearch {
	const IMPERIAL = 0;
	const METRIC = 1;
	const BOTH = 2;

	const ROUNDING_PRECISION = 1;
	const FORECAST_DAYS = 2;

	private static $APIKey = "";

	/**
	 * Public interface for searching
	 *
	 * @param string $search The location to get weather for
	 * @param array $options An array of options, including measurementSystem, conditions, and forecast
	 * @return string
	 */
	public static function search($search, $options = array()) {
		//	Make sure API Key is set
		if (!strlen(self::$APIKey))
			self::$APIKey = Module::getAPIKey("weather");

		//	Convert location name into a string usable by the API
		$location = self::weatherLocation($search);
		$output = array();

		//	Default to showing both imperial and metric measurements, but allow override
		$measurementSystem = self::BOTH;
		if (isset($options['measurementSystem']) && ($options['measurementSystem'] == self::IMPERIAL || $options['measurementSystem'] == self::METRIC))
			$measurementSystem = $options['measurementSystem'];

		//	Default to showing both conditions and forecast, but allow override
		if (!isset($options['conditions']) || $options['conditions'])
			$output[] = self::conditions($location, $measurementSystem);
		if (!isset($options['forecast']) || $options['forecast'])
			$output[] = self::forecast($location, $measurementSystem);

		return implode("\n", $output);
	}


	/**
	 * Utility to convert a location search into an API-usable string by using the API's autocomplete
	 *
	 * @param string $search Location to find
	 * @return string Location code
	 * @throws WeatherException If the location isn't found
	 */
	public static function weatherLocation($search) {
		//	Convert from UTF-8 to UTF-8 to fix some special character errors
		$string = mb_convert_encoding(WebAccess::resourceBody('http://autocomplete.wunderground.com/aq?query='. urlencode($search)), 'UTF-8', 'UTF-8');

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
			throw new WeatherException("Weather::weatherLocation: No location found for '$search'.");

		return $query;
	}

	/**
	 * Utility to format a set of metric and imperial values to have units and exclude undesired measurements
	 *
	 * @param string $measurement Aspect to be measured, e.g., temperature or speed
	 * @param array $values array(imperial, metric)
	 * @param int $measurementSystem 0, 1, or 2 corresponding to the class constants represents imperial, metric or both. This decides which measurements to output
	 * @return string The relevant measurements with units appended
	 * @throws WeatherException If $values array isn't set up correctly, if $measurementSystem is invalid, or if $measurement isn't supported
	 */
	public static function formatMeasurements($measurement, $values, $measurementSystem) {
		if (!is_array($values) || !isset($values[0]) || !isset($values[1]) || !is_numeric($values[0]) || !is_numeric($values[1]))
			throw new WeatherException("Weather::formatMeasurements: Invalid array of values passed.");

		if (!is_int($measurementSystem) || $measurementSystem < 0 || $measurementSystem > 2)
			throw new WeatherException("Weather::formatMeasurements: '$measurementSystem' does not correspond to a valid measurement system.");

		switch ($measurement) {
			case "temperature":
				$suffixes = array(chr(176). "F", chr(176). "C");
			break;
			case "speed":
				$suffixes = array("MPH", "KPH");
			break;
			default:
				throw new WeatherException("Weather::formatMeasurements: unknown measurement type '$measurement'");
			break;
		}

		$values[0] = round($values[0], self::ROUNDING_PRECISION);
		$values[1] = round($values[1], self::ROUNDING_PRECISION);

		$return = array();
		if ($measurementSystem == self::IMPERIAL || $measurementSystem == self::BOTH)
			$return[] = $values[0]. $suffixes[0];
		if ($measurementSystem == self::METRIC || $measurementSystem == self::BOTH)
			$return[] = $values[1]. $suffixes[1];

		return implode("/", $return);

	}

	/**
	 * Get the current conditions for an area
	 *
	 * @param string $location The API identifier for a location
	 * @param int $measurementSystem Measurement systems to include
	 * @return string A formatted string describing the weather conditions at $location
	 * @throws WeatherException If information isn't available
	 */
	public static function conditions($location, $measurementSystem = self::BOTH) {
		$page = WebAccess::resourceBody("http://api.wunderground.com/api/". self::$APIKey. "/conditions$location.json");
		$results = json_decode($page, TRUE);

		//	Check for this index to verify the search was successful
		if (!isset($results['current_observation']) || !($conditions = $results['current_observation']))
			throw new WeatherException("Weather::weather: Current observation not available for '$location'.");

		$output = array();

		//	Location, conditions, temperature
		$output[] = sprintf("Current conditions for %s: %s (%s).",
						IRCUtility::bold($conditions['display_location']['full']),
						$conditions['weather'],
						self::formatMeasurements("temperature", array($conditions['temp_f'], $conditions['temp_c']), $measurementSystem));

		//	Only include heat index/wind chill if it is different
		if ($conditions['temp_f'] != $conditions['feelslike_f'])
			$output[] = sprintf("Feels like %s.", self::formatMeasurements("temperature", array($conditions['feelslike_f'], $conditions['feelslike_c']), $measurementSystem));

		//	Humidity, wind
		$output[] = sprintf("Humidity: %s. Wind: From %s at %s.",
						$conditions['relative_humidity'],
						$conditions['wind_dir'],
						self::formatMeasurements("speed", array($conditions['wind_mph'], $conditions['wind_kph']), $measurementSystem));

		//	Only include wind gusts if they are different
		if ($conditions['wind_mph'] < $conditions['wind_gust_mph'])
			$output[] = sprintf("Gusting to %s.", self::formatMeasurements("speed", array($conditions['wind_gust_mph'], $conditions['wind_gust_kph']), $measurementSystem));

		return implode(" ", $output);
	}

	/**
	 * Get the n-day forecast for an area
	 *
	 * @param string $location The API identifier for a location
	 * @param int $measurementSystem Measurement systems to include
	 * @return string A formatted string briefly describing the weather forecast for $location
	 * @throws WeatherException If information isn't available
	 */
	public static function forecast($location, $measurementSystem = self::BOTH) {
		$page = WebAccess::resourceBody("http://api.wunderground.com/api/". self::$APIKey. "/forecast$location.json");
		$results = json_decode($page, TRUE);

		//	Check for this index to verify the search was successful
		if (!isset($results['forecast']['simpleforecast']['forecastday']) || !($forecast = $results['forecast']['simpleforecast']['forecastday']))
			throw new WeatherException("Weather::weather: Forecast is not available for '$location'.");

		$output = array();

		//	Add a configurable number of days
		for ($day = 0; $day < self::FORECAST_DAYS; $day++) {
			$conditions = $forecast[$day];
			$output[] = sprintf('%s: %s. High of %s. Low of %s.',
							IRCUtility::bold($conditions['date']['weekday']),
							$conditions['conditions'],
							self::formatMeasurements("temperature", array($conditions['high']['fahrenheit'], $conditions['high']['celsius']), $measurementSystem),
							self::formatMeasurements("temperature", array($conditions['low']['fahrenheit'], $conditions['low']['celsius']), $measurementSystem));
		}

		return implode(" ", $output);
	}
}
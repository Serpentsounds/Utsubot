<?php
/**
 * Utsubot - Dictionary.php
 * User: Benjamin
 * Date: 23/04/2015
 */

namespace Utsubot\Web;
use Utsubot\Module;
use function Utsubot\{bold, italic};

class DictionaryException extends WebSearchException {}

class Dictionary implements WebSearch {

	const NUMBER_OF_SUGGESTIONS = 5;

	private static $APIKey = "";

	/**
	 * Look up a word via Merriam-Webster API
	 *
	 * @param string $search
	 * @param array $options Unavailable
	 * @return string Definition and examples
	 */
	public static function search($search, $options = array()) {
		//	Make sure API Key is set
		if (!strlen(self::$APIKey))
			self::$APIKey = Module::loadAPIKey("dictionary");

		$number = 1;
		if (isset($options['number']) && is_int($options['number']) && $options['number'] > 0)
			$number = $options['number'];

		return self::dictionarySearch($search, $number);
	}

	public static function dictionarySearch($term, $number = 1) {
		$xml = resourceBody("http://www.dictionaryapi.com/api/v1/references/collegiate/xml/". urlencode($term). "?key=". self::$APIKey);
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
				$suggestions = array();
				//	Loop through and save suggestions
				foreach ($indices['SUGGESTION'] as $index) {
					$suggestions[] = $values[$index]['value'];
					$count++;

					//	Too many suggestions, stop early
					if ($count >= self::NUMBER_OF_SUGGESTIONS)
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
		$partOfSpeechIndex = self::valuesBetween($indices['FL'], $lowerIndex, $upperIndex)[0];
		$partOfSpeech = $values[$partOfSpeechIndex]['value'];

		//	Search for definitions within range
		$definitionIndices = self::valuesBetween($indices['DT'], $lowerIndex, $upperIndex);
		$return = array();

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
				if (!strlen($definition) && !count($return)) {

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
				$return[$lastIndex] = trim($return[$lastIndex]). trim($definition);
			}
		}

		if (!$return)
			return self::dictionarySearch($term, $number + 1);

		return sprintf("%s (%s): %s", bold($term), italic($partOfSpeech), implode("; ", $return));

	}

	private static function valuesBetween($array, $lowerIndex, $upperIndex) {
		$return = array();
		foreach ($array as $val) {
			if ($val < $lowerIndex)
				continue;
			elseif ($val > $upperIndex)
				break;

			$return[] = $val;
		}

		return $return;
	}
}
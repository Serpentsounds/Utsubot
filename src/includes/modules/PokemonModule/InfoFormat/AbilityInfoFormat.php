<?php
/**
 * Utsubot - AbilityInfoFormat.php
 * User: Benjamin
 * Date: 05/12/2014
 */

namespace Utsubot\Pokemon;
use function Utsubot\bold;

class AbilityInfoFormat extends InfoFormat {
	protected static $class = "Ability";

	protected static $defaultFormat = "[^Ability^: {english}/{japanese}] [^Generation^: {generation}] [^Effect^: {shortEffect}]";
	protected static $verboseFormat = "[^Ability^: {english}/{japanese}] [^Generation^: {generation}] [^Effect^: {effect}]";

	protected static $validFields = array(	"english", "japanese", "roumaji", "german", "french", "spanish", "korean", "italian", "czech",
											 "xy", "bw", "bw2", "dp", "p", "hgss", "rs", "e", "frlg", "effect", "shortEffect", "generation");

	public static function getVerboseFormat() {
		return self::$verboseFormat;
	}

	protected function formatField($field, $fieldValue) {
		if ($field != "effect" && $field != "shortEffect")
			$fieldValue = bold($fieldValue);

		return $fieldValue;
	}

	protected function getField($field)	{
		switch ($field) {
			case "english":
			case "japanese":
			case "roumaji":
			case "german":
			case "french":
			case "spanish":
			case "korean":
			case "italian":
			case "czech":
			 	if (method_exists($this->object, "getName"))
					return $this->object->getName($field);
				return "";
			break;

			case "generation":
			case "effect":
			case "shortEffect":
				$method = "get" . ucfirst($field);
				if (method_exists($this->object, $method))
					return $this->object->{$method}();
				return "";
			break;

			case "xy":
			case "bw":
			case "bw2":
			case "dp":
			case "p":
			case "hgss":
			case "rs":
			case "e":
			case "frlg":
				if (method_exists($this->object, "getText")) {
					$text = $this->object->getText($field);
					if (isset($text['english']))
						return $text['english'];
				}
				return "";
			break;
		}

		return "";
	}

}
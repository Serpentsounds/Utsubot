<?php
/**
 * Utsubot - ItemInfoFormat.php
 * User: Benjamin
 * Date: 05/12/2014
 */

namespace Pokemon;

class ItemInfoFormat extends InfoFormat {
	protected static $class = "Item";

	protected static $defaultFormat =
"[^Item^: {english}/{japanese}] [^Cost^: {cost}] [^Category^: {category}{ (pocket Pocket)}] {[^Fling^: flingPower Power.flingEffect]} {[^Attributes^: flags]} [^Effect^: {shortEffect}]";

	protected static $verboseFormat =
"[^Item^: {english}/{japanese}] [^Cost^: {cost}] [^Category^: {category}{ (pocket Pocket)}] {[^Fling^: flingPower Power.flingEffect]} {[^Attributes^: flags]} [^Effect^: {effect}]";

	protected static $validFields = array(	"english", "japanese", "roumaji", "german", "french", "spanish", "korean", "italian", "czech",
									  		"xy", "bw", "bw2", "dp", "p", "hgss", "rs", "e", "frlg", "effect", "shortEffect",
											"cost", "flingPower", "flingEffect", "flags", "pocket", "category");

	public static function getVerboseFormat() {
		return self::$verboseFormat;
	}

	protected function formatField($field, $fieldValue) {
		if ($field != "effect" && $field != "shortEffect" && $field != "flags" && $field != "flingEffect")
			$fieldValue = \IRCUtility::bold($fieldValue);

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

			case "cost":
			case "effect":
			case "shortEffect":
			case "flingPower":
			case "pocket":
			case "category":
				$method = "get" . ucfirst($field);
				if (method_exists($this->object, $method))
					return $this->object->{$method}();
				return "";
			break;

			case "flingEffect":
				if (method_exists($this->object, "getFlingEffect")) {
					$flingEffect = $this->object->getFlingEffect();
					if ($flingEffect)
						$flingEffect = " $flingEffect";

					return $flingEffect;
				}
				return "";
			break;

			case "flags":
				if (method_exists($this->object, "getFlag")) {
					$flags   = $this->object->getFlag("all");
					$display = array();

					foreach ($flags as $flag => $description) {
						if ($flag == "Usable_overworld" || $flag == "Usable_in_battle")
							$display[] = $description;

						elseif ($flag == "Underground")
							$display[] = "Sinnoh Underground";

						elseif ($flag == "Holdable_active")
							$display[] = "Activates while held";

						elseif ($flag == "Holdable_passive")
							$display[] = "Passive while held";

						else
							$display[] = $flag;
					}

					$display = array_map(array("IRCUtility", "bold"), $display);
					return implode(", ", $display);
				}
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
					break;
				}
				return "";
			break;
		}

		return "";
	}
}

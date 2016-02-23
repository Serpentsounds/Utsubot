<?php
/**
 * Utsubot - NatureInfoFormat.php
 * User: Benjamin
 * Date: 04/12/2014
 */

namespace Pokemon;

class NatureInfoFormat extends InfoFormat {
	protected static $class = "Nature";

	protected static $defaultFormat = "[^Nature^: {english}/{japanese}] [^Increases^: {increases}] [^Decreases^: {decreases}] [^Likes^: {likesFlavor}{ (likes)}] [^Dislikes^: {dislikesFlavor}{ (dislikes)}]";

	protected static $validFields = array(	"english", "japanese", "roumaji", "german", "french", "spanish", "korean", "italian", "czech",
										  	"likes", "dislikes", "likesFlavor", "dislikesFlavor", "increases", "decreases");

	protected function formatField($field, $fieldValue) {
		if ($field == "likes" || $field == "dislikes")
			$fieldValue = \IRCUtility::bold(Nature::colorAttribute($fieldValue));

		//	Default case, just bold
		else
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

			case "likes":
			case "dislikes":
			case "likesFlavor":
			case "dislikesFlavor":
			case "increases":
			case "decreases":
				$method = "get" . ucfirst($field);
				if (method_exists($this->object, $method))
					return $this->object->{$method}();
				return "";
			break;
		}

		return "";
	}

} 
<?php
/**
 * Utsubot - InfoFormat.php
 * User: Benjamin
 * Date: 04/12/2014
 */

namespace Pokemon;

class InfoFormatException extends \Exception {}

abstract class InfoFormat {

	use \IRCFormatting;
    use \Japanese;

	protected $object;
	protected static $class = "";
	protected static $defaultFormat = "";
	protected static $validFields = array();

	protected static $headerColor = "teal";
	protected static $headerBackgroundColor = false;

	public function __construct($object) {
		$qualifiedName = "Pokemon\\". static::$class;
		if (!($object instanceof $qualifiedName))
			throw new InfoFormatException(get_class($this). "::__construct: Object is not an instance of '$qualifiedName'.");

		$this->object = $object;
	}

	public function parseFormat($format = null)	{
		//	Standard format
		if (!$format)
			$format = static::$defaultFormat;

		//	Replace ^text^ as teal colored headers
		$headerColors = array(static::$headerColor, static::$headerBackgroundColor);
		$format = preg_replace_callback('/\^([^\^]*)\^/',
			function ($match) use ($headerColors) {
				return ($headerColors[0] === false) ? $match[1] : self::color($match[1], $headerColors[0], $headerColors[1]);
			},
			$format);

		//	Parse {text} for fields
		$format = preg_replace_callback('/\{([^}]*)\}/',
			function ($match) {
				$userField = $match[1];
				$hasField = false;

				//	Check string for every valid field
				foreach (static::$validFields as $field) {

					//	Require word boundary to avoid overlapping of parameters
					if (preg_match("/\\b$field\\b/", $userField)) {
						$fieldValue = $this->getField($field);

						//	A value was successfully retrieved, so this {group} will be displayed
						if (strlen($fieldValue)) {
							$hasField = true;
							$fieldValue = $this->formatField($field, $fieldValue);

							//	Again use a word boundary to prevent overlapping
							$userField = preg_replace("/\\b$field\\b/", $fieldValue, $userField);
						}
					}

				}

				//	No field had any values in this {group}, so omit it entirely
				if (!$hasField)
					return "";

				return $userField;
			},
			$format);

		//	Fix extra spaces cause by missing groups
		return mb_ereg_replace('\s+', " ", $format);
	}

	protected abstract function getField($field);

	protected function formatField($field, $fieldValue) {
		return self::bold($fieldValue);
	}
} 
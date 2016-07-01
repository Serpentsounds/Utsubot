<?php
/**
 * Utsubot - InfoFormat.php
 * User: Benjamin
 * Date: 04/12/2014
 */

namespace Utsubot\Pokemon;
use Utsubot\Color;
use function Utsubot\{
    colorText,
    bold
};
use function Utsubot\Japanese\romanizeKana;


/**
 * Class InfoFormatException
 *
 * @package Utsubot\Pokemon
 */
class InfoFormatException extends \Exception {}

/**
 * Class InfoFormat
 *
 * @package Utsubot\Pokemon
 */
abstract class InfoFormat {

    /** @var PokemonBase $object */
    protected $object;

    protected static $defaultFormat = "";
    protected static $validFields = [ ];

    protected static $headerColor = Color::Teal;
    protected static $headerBackgroundColor = Color::Clear;

    /**
     * @return string
     */
    public static function getDefaultFormat(): string {
        return static::$defaultFormat;
    }

    /**
     * InfoFormat constructor.
     *
     * @param PokemonBase $object
     */
    public function __construct(PokemonBase $object) {
        $this->object = $object;
    }

    /**
     * Get the fully formatted information output based on the given format
     *
     * @param string $format Omit to use default format
     * @return string
     */
    public function parseFormat(string $format = ""): string {
        //	Standard format
        if (!$format)
            $format = static::$defaultFormat;

        //	Replace ^text^ as teal colored headers
        $headerColors = [new Color(static::$headerColor), new Color(static::$headerBackgroundColor)];
        $format = preg_replace_callback('/\^([^\^]*)\^/',
            function ($match) use ($headerColors) {
                return ($headerColors[0] === false) ? $match[1] : colorText($match[1], $headerColors[0], $headerColors[1]);
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
                    if (preg_match("/\\b$field/", $userField)) {
                        $fieldValue = $this->getField($field);

                        //	A value was successfully retrieved, so this {group} will be displayed
                        if (strlen($fieldValue)) {
                            $hasField = true;
                            $fieldValue = $this->formatField($field, $fieldValue);

                            //	Again use a word boundary to prevent overlapping
                            $userField = preg_replace("/\\b$field/", $fieldValue, $userField);
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

    /**
     * Given a valid field name, query the saved object to return the associated data
     *
     * @param string $field
     * @return string
     */
    protected function getField(string $field): string {
        $return = "";

        switch ($field) {
            case "english":
            case "japanese":
            case "official roomaji":
            case "chinese":
            case "german":
            case "french":
            case "spanish":
            case "korean":
            case "italian":
            case "czech":
                $return = $this->object->getName(
                    Language::fromName($field)
                );
                break;
            case "roumaji":
                return ucfirst(romanizeKana($this->object->getName(new Language(Language::Japanese))));
                break;

            case "generation":
                return (string)$this->object->getGeneration();
                break;
        }

        return $return;
    }

    /**
     * Apply additional formatting to data based on the value of the data and/or name of the field
     *
     * @param string $field
     * @param $fieldValue
     * @return string
     */
    protected function formatField(string $field, $fieldValue): string {
        return bold((string)$fieldValue);
    }
}
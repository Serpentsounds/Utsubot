<?php
/**
 * Utsubot - AbilityInfoFormat.php
 * User: Benjamin
 * Date: 05/12/2014
 */

namespace Utsubot\Pokemon\Ability;

use Utsubot\Pokemon\{
    InfoFormat,
    PokemonBaseException,
    Version,
    Language
};
use function Utsubot\bold;


/**
 * Class AbilityInfoFormat
 *
 * @property Ability $object
 *
 * @package Utsubot\Pokemon\Ability
 */
class AbilityInfoFormat extends InfoFormat {

    const Default_Format = "[^Ability^: {%english%}/{%japanese%}] [^Generation^: {%generation%}] [^Effect^: {%shortEffect%}]";
    const Verbose_Format = "[^Ability^: {%english%}/{%japanese%}] [^Generation^: {%generation%}] [^Effect^: {%effect%}]";

    const Valid_Fields = [
        "english", "japanese", "roumaji", "german", "french", "spanish", "korean", "italian", "czech",
        "xy", "bw", "bw2", "dp", "p", "hgss", "rs", "E", "frlg",
        "effect", "shortEffect",
        "generation"
    ];


    /**
     * Force an Ability to construct
     *
     * @param Ability $object
     */
    public function __construct(Ability $object) {
        parent::__construct($object);
    }


    /**
     * @param string $field
     * @param        $fieldValue
     * @return string
     */
    protected function formatField(string $field, $fieldValue): string {
        if ($field != "effect" && $field != "shortEffect")
            $fieldValue = bold($fieldValue);

        return $fieldValue;
    }


    /**
     * @param string $field
     * @return string
     */
    protected function getField(string $field): string {

        if ($return = parent::getField($field))
            return $return;

        switch ($field) {
            case "effect":
                return $this->object->getEffect();
                break;
            case "shortEffect":
                return $this->object->getShortEffect();
                break;

            case "oras":
            case "xy":
            case "bw":
            case "bw2":
            case "dp":
            case "p":
            case "hgss":
            case "rs":
            case "E":
            case "frlg":
                try {
                    return $this->object->getText(Version::fromName($field), new Language(Language::English));
                }
                catch (PokemonBaseException $e) {
                    return "";
                }
                break;
        }

        return "";
    }

}
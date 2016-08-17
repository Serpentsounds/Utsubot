<?php
/**
 * Utsubot - ItemInfoFormat.php
 * User: Benjamin
 * Date: 05/12/2014
 */

namespace Utsubot\Pokemon\Item;


use Utsubot\Pokemon\{
    InfoFormat,
    PokemonBaseException,
    Version,
    Language
};
use function Utsubot\bold;


/**
 * Class ItemInfoFormat
 *
 * @property Item $object
 *
 * @package Utsubot\Pokemon\Item
 */
class ItemInfoFormat extends InfoFormat {

    const Default_Format =
        "[^Item^: {%english%}/{%japanese%}] [^Cost^: {%cost%}] [^Category^: {%category%}{ (%pocket% Pocket)}] {[^Fling Power^: %flingPower%]} {[^Fling Effect^: %flingEffect%]} {[^Attributes^: %flags%]} {[^Effect^: %shortEffect%]}";

    const Verbose_Format =
        "[^Item^: {%english%}/{%japanese%}] [^Cost^: {%cost%}] [^Category^: {%category%}{ (%pocket% Pocket)}] {[^Fling Power^: %flingPower%]} {[^Fling Effect^: %flingEffect%]} {[^Attributes^: %flags%]} {[^Effect^: %effect%]}";

    const Valid_Fields = [
        "english", "japanese", "roumaji", "german", "french", "spanish", "korean", "italian", "czech",
        "xy", "bw", "bw2", "dp", "p", "hgss", "rs", "E", "frlg",
        "effect", "shortEffect",
        "cost",
        "flingPower", "flingEffect",
        "flags",
        "pocket",
        "category"
    ];


    /**
     * Force an Item to construct
     *
     * @param Item $object
     */
    public function __construct(Item $object) {
        parent::__construct($object);
    }


    /**
     * @param string $field
     * @param        $fieldValue
     * @return string
     */
    protected function formatField(string $field, $fieldValue): string {
        if ($field != "effect" && $field != "shortEffect" && $field != "flags" && $field != "flingEffect")
            $fieldValue = bold($fieldValue);

        return (string)$fieldValue;
    }


    /**
     * @param string $field
     * @return string
     */
    protected function getField(string $field): string {

        if ($return = parent::getField($field))
            return $return;

        switch ($field) {
            case "cost":
                return (string)$this->object->getCost();
                break;
            case "effect":
                return $this->object->getEffect();
                break;
            case "shortEffect":
                return $this->object->getShortEffect();
                break;
            case "flingPower":
                return (string)$this->object->getFlingPower();
                break;
            case "flingEffect":
                return $this->object->getFlingEffectDisplay();
                break;
            case "pocket":
                return $this->object->getPocketDisplay();
                break;
            case "category":
                return $this->object->getCategory();
                break;
            case "flags":
                return $this->object->formatFlags();
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
            case "c":
            case "gs":
            case "y":
            case "rb":
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

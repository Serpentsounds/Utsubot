<?php
/**
 * Utsubot - MoveInfoFormat.php
 * User: Benjamin
 * Date: 06/12/2014
 */

namespace Utsubot\Pokemon\Move;


use Utsubot\Pokemon\{
    InfoFormat,
    Language
};
use function Utsubot\bold;
use function Utsubot\Pokemon\Types\colorType;


/**
 * Class MoveInfoFormat
 *
 * @property Move $object
 *
 * @package Utsubot\Pokemon\Move
 */
class MoveInfoFormat extends InfoFormat {

    const Default_Format =
        "[^Move^: {%english%}/{%japanese%}] [^Type^: {%type%}] [^Power^: {%power%}] [^Damage Type^: {%damageType%}] [^Accuracy^: {%accuracy%}] [^PP^: {%pp%}] [^Target^: {%target%}] [^Priority^: {%priority%}] [^Effect^: {%shortEffect%}]";

    const Verbose_Format =
        "[^Move^: {%english%}/{%japanese%}] [^Type^: {%type%}] [^Power^: {%power%}] [^Damage Type^: {%damageType%}] [^Accuracy^: {%accuracy%}] [^PP^: {%pp%}] [^Target^: {%target%}] [^Priority^: {%priority%}] [^Effect^: {%effect%}]";

    const Contest_Format = <<<EOF
[^Move^: {%english%}/{%japanese%}] [^Type^: {%contestType%}] [^Appeal^: {%contestAppeal%}] [^Jam^: {%contestJam%}] [^Super Contest Appeal^: {%superContestAppeal%}] [^Effect^: {%contestEffect%}]
[^Super Contest Effect^: {%superContestFlavorText%}]
EOF;

    const Valid_Fields = [
        "english", "japanese", "roumaji", "german", "french", "spanish", "korean", "italian", "czech",
        "type", "power", "damageType", "accuracy", "target", "priority", "pp",
        "effect", "shortEffect",
        "contestType", "contestAppeal", "contestJam", "superContestAppeal",
        "contestEffect", "superContestFlavorText", "contestFlavorText"
    ];


    /**
     * Force a Move to construct
     *
     * @param Move $object
     */
    public function __construct(Move $object) {
        parent::__construct($object);
    }


    /**
     * @param string $field
     * @param        $fieldValue
     * @return string
     */
    protected function formatField(string $field, $fieldValue): string {
        if ($field == "type")
            $fieldValue = colorType($fieldValue);

        elseif (($field == "accuracy" || $field == "power") && $fieldValue == 0)
            $fieldValue = "-";

        if ($field != "effect" && $field != "shortEffect" && $field != "contestEffect" && $field != "superContestFlavorText" && $field != "contestFlavorText")
            $fieldValue = bold($fieldValue);

        return $fieldValue;
    }


    /**
     * @param string $field
     * @return string
     */
    protected function getField(string $field): string {
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
                return $this->object->getName(Language::fromName($field));
                break;

            case "type":
            case "power":
            case "damageType":
            case "accuracy":
            case "target":
            case "priority":
            case "contestType":
            case "contestAppeal":
            case "contestJam":
            case "superContestAppeal":
            case "contestEffect":
            case "superContestFlavorText":
            case "contestFlavorText":
            case "effect":
            case "shortEffect":
            case "pp":
                $method = "get".ucfirst($field);
                if (method_exists($this->object, $method))
                    return $this->object->{$method}();

                return "";
                break;

        }

        return "";
    }

}
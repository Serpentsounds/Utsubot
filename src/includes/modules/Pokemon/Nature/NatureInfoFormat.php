<?php
/**
 * Utsubot - NatureInfoFormat.php
 * User: Benjamin
 * Date: 04/12/2014
 */

namespace Utsubot\Pokemon\Nature;


use Utsubot\Pokemon\{
    InfoFormat,
    Attribute
};
use function Utsubot\bold;


/**
 * Class NatureInfoFormat
 *
 * @property Nature $object
 *
 * @package Utsubot\Pokemon\Nature
 */
class NatureInfoFormat extends InfoFormat {

    const Default_Format =
        "[^Nature^: {english}/{japanese}] [^Increases^: {increases}] [^Decreases^: {decreases}] [^Likes^: {likesFlavor}{ (likesAttr)}] [^Dislikes^: {dislikesFlavor}{ (dislikesAttr)}]";

    const Valid_Fields = [
        "english", "japanese", "roumaji", "german", "french", "spanish", "korean", "italian", "czech",
        "likesAttr", "dislikesAttr",
        "likesFlavor", "dislikesFlavor",
        "increases", "decreases"
    ];


    /**
     * Force a Nature to construct
     *
     * @param Nature $object
     */
    public function __construct(Nature $object) {
        parent::__construct($object);
    }


    /**
     * @param string $field
     * @param        $fieldValue
     * @return string
     */
    protected function formatField(string $field, $fieldValue): string {
        if ($field == "likesAttr" || $field == "dislikesAttr")
            $fieldValue = bold(Nature::colorAttribute(Attribute::fromName($fieldValue)));

        //  Default case, just bold
        else
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
            case "likesAttr":
            case "dislikesAttr":
            case "likesFlavor":
            case "dislikesFlavor":
            case "increases":
            case "decreases":
                $method = "get".ucfirst($field);
                if (method_exists($this->object, $method))
                    return $this->object->{$method}();

                return "";
                break;
        }

        return "";
    }

}
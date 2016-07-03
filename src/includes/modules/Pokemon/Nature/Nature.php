<?php
/**
 * Utsubot - Nature.php
 * User: Benjamin
 * Date: 03/12/2014
 */

namespace Utsubot\Pokemon\Nature;


use Utsubot\Pokemon\{
    Attribute,
    Flavor,
    Stat,
    PokemonBase,
    PokemonBaseException
};
use function Utsubot\colorText;


/**
 * Class NatureException
 *
 * @package Utsubot\Pokemon\Nature
 */
class NatureException extends PokemonBaseException {

}


/**
 * Class Nature
 *
 * @package Utsubot\Pokemon\Nature
 */
class Nature extends PokemonBase {

    private $likes;
    private $dislikes;
    private $likesFlavor;
    private $dislikesFlavor;
    private $increases;
    private $decreases;


    /**
     * Set contest attribute associated with the flavor of berry this nature likes
     *
     * @param Attribute $attribute
     */
    public function setLikesAttr(Attribute $attribute) {
        $this->likes = $attribute->getName();
    }


    /**
     * Set contest attribute associated with the flavor of berry this nature dislikes
     *
     * @param Attribute $attribute
     */
    public function setDislikesAttr(Attribute $attribute) {
        $this->dislikes = $attribute->getName();
    }


    /**
     * Set flavor of berry this nature likes
     *
     * @param Flavor $flavor
     */
    public function setLikesFlavor(Flavor $flavor) {
        $this->likesFlavor = $flavor->getName();
    }


    /**
     * Set flavor of berry this nature dislikes
     *
     * @param Flavor $flavor
     */
    public function setDislikesFlavor(Flavor $flavor) {
        $this->dislikesFlavor = $flavor->getName();
    }


    /**
     * Set the stat this nature increases
     *
     * @param Stat $stat
     */
    public function setIncreases(Stat $stat) {
        $this->increases = $stat->getName();
    }


    /**
     * Set the stat this nature decreases
     *
     * @param Stat $stat
     */
    public function setDecreases(Stat $stat) {
        $this->decreases = $stat->getName();
    }


    /**
     * @return string Contest attribute associated with the flavor of berry this nature likes
     */
    public function getLikesAttr(): string {
        return $this->likes ?? "None";
    }


    /**
     * @return string Contest attribute associated with the flavor of berry this nature dislikes
     */
    public function getDislikesAttr(): string {
        return $this->dislikes ?? "None";
    }


    /**
     * @return string Flavor of berry this nature likes
     */
    public function getLikesFlavor(): string {
        return $this->likesFlavor ?? "None";
    }


    /**
     * @return string Flavor of berry this nature dislikes
     */
    public function getDislikesFlavor(): string {
        return $this->dislikesFlavor ?? "None";
    }


    /**
     * @return string Stat this nature increases
     */
    public function getIncreases(): string {
        return $this->increases ?? "None";
    }


    /**
     * @return string Stat this nature decreases
     */
    public function getDecreases(): string {
        return $this->decreases ?? "None";
    }


    /**
     * Color the name of a contest attribute, based on in-game color
     *
     * @param Attribute $attribute Name of contest move category
     * @return string The attribute colored, or the original string if it's not a valid attribute
     */
    public static function colorAttribute(Attribute $attribute) {
        $colors = $attribute->getColors();

        return colorText($attribute->getName(), $colors[ 0 ], $colors[ 1 ]);
    }
}
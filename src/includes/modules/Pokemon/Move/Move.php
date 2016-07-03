<?php
/**
 * Utsubot - Move.php
 * User: Benjamin
 * Date: 06/12/2014
 */

namespace Utsubot\Pokemon\Move;


use Utsubot\Pokemon\{
    PokemonBase,
    PokemonBaseException
};


/**
 * Class MoveException
 *
 * @package Utsubot\Pokemon\Move
 */
class MoveException extends PokemonBaseException {

}


/**
 * Class Move
 *
 * @package Utsubot\Pokemon\Move
 */
class Move extends PokemonBase {

    private $power      = 0;
    private $PP         = 0;
    private $accuracy   = 0;
    private $priority   = 0;
    private $type       = "";
    private $damageType = "";
    private $target     = "";

    private $shortEffect = "";
    private $effect      = "";

    private $contestType            = "";
    private $contestAppeal          = 0;
    private $contestJam             = 0;
    private $contestEffect          = "";
    private $superContestAppeal     = 0;
    private $contestFlavorText      = "";
    private $superContestFlavorText = "";


    /**
     * @return int
     */
    public function getPower(): int {
        return $this->power;
    }


    /**
     * @return int
     */
    public function getPP(): int {
        return $this->PP;
    }


    /**
     * @return int
     */
    public function getAccuracy(): int {
        return $this->accuracy;
    }


    /**
     * @return int
     */
    public function getContestAppeal(): int {
        return $this->contestAppeal;
    }


    /**
     * @return string
     */
    public function getContestEffect(): string {
        return $this->contestEffect;
    }


    /**
     * @return string
     */
    public function getContestFlavorText(): string {
        return $this->contestFlavorText;
    }


    /**
     * @return string
     */
    public function getContestType(): string {
        return $this->contestType;
    }


    /**
     * @return int
     */
    public function getContestJam(): int {
        return $this->contestJam;
    }


    /**
     * @return string
     */
    public function getDamageType(): string {
        return $this->damageType;
    }


    /**
     * @return string
     */
    public function getEffect(): string {
        return $this->effect;
    }


    /**
     * @return int
     */
    public function getPriority(): int {
        return $this->priority;
    }


    /**
     * @return string
     */
    public function getShortEffect(): string {
        return $this->shortEffect;
    }


    /**
     * @return int
     */
    public function getSuperContestAppeal(): int {
        return $this->superContestAppeal;
    }


    /**
     * @return string
     */
    public function getSuperContestFlavorText(): string {
        return $this->superContestFlavorText;
    }


    /**
     * @return string
     */
    public function getTarget(): string {
        return $this->target;
    }


    /**
     * @return string
     */
    public function getType(): string {
        return $this->type;
    }


    /**
     * @param int $power
     * @throws MoveException
     */
    public function setPower(int $power) {
        if ($power < 0)
            throw new MoveException("Base power must be a non-negative integer.");

        $this->power = $power;
    }


    /**
     * @param int $PP
     * @throws MoveException
     */
    public function setPP(int $PP) {
        if ($PP < 0)
            throw new MoveException("PP must be a non-negative integer.");

        $this->PP = $PP;
    }


    /**
     * @param int $accuracy
     * @throws MoveException
     */
    public function setAccuracy(int $accuracy) {
        if ($accuracy < 0)
            throw new MoveException("Accuracy must be a non-negative integer.");

        $this->accuracy = $accuracy;
    }


    /**
     * @param int $priority
     */
    public function setPriority(int $priority) {
        $this->priority = $priority;
    }


    /**
     * @param string $type
     */
    public function setType(string $type) {
        $this->type = $type;
    }


    /**
     * @param string $damageType
     */
    public function setDamageType(string $damageType) {
        $this->damageType = $damageType;
    }


    /**
     * @param string $target
     */
    public function setTarget(string $target) {
        $this->target = $target;
    }


    /**
     * @param string $effect
     */
    public function setEffect(string $effect) {
        $this->effect = $effect;
    }


    /**
     * @param string $shortEffect
     */
    public function setShortEffect(string $shortEffect) {
        $this->shortEffect = $shortEffect;
    }


    /**
     * @param string $contestType
     */
    public function setContestType(string $contestType) {
        $this->contestType = $contestType;
    }


    /**
     * @param int $contestAppeal
     * @throws MoveException
     */
    public function setContestAppeal(int $contestAppeal) {
        if ($contestAppeal < 0)
            throw new MoveException("Contest appeal must be a non-negative integer.");

        $this->contestAppeal = $contestAppeal;
    }


    /**
     * @param int $contestJam
     * @throws MoveException
     */
    public function setContestJam(int $contestJam) {
        if ($contestJam < 0)
            throw new MoveException("Contest jam must be a non-negative integer.");

        $this->contestJam = $contestJam;
    }


    /**
     * @param string $contestEffect
     */
    public function setContestEffect(string $contestEffect) {
        $this->contestEffect = $contestEffect;
    }


    /**
     * @param int $superContestAppeal
     * @throws MoveException
     */
    public function setSuperContestAppeal(int $superContestAppeal) {
        if ($superContestAppeal < 0)
            throw new MoveException("Super contest appeal must be a non-negative integer.");

        $this->superContestAppeal = $superContestAppeal;
    }


    /**
     * @param string $contestFlavorText
     */
    public function setContestFlavorText(string $contestFlavorText) {
        $this->contestFlavorText = $contestFlavorText;
    }


    /**
     * @param string $superContestFlavorText
     */
    public function setSuperContestFlavorText(string $superContestFlavorText) {
        $this->superContestFlavorText = $superContestFlavorText;
    }
}
<?php
/**
 * Utsubot - Move.php
 * User: Benjamin
 * Date: 06/12/2014
 */

namespace Pokemon;

class Move {
	use PokemonCommon, \EasySetters;

	private $power = 0;
	private $PP = 0;
	private $accuracy = 0;
	private $priority = 0;
	private $type = "";
	private $damageType = "";
	private $target = "";

	private $shortEffect = "";
	private $effect = "";

	private $contestType = "";
	private $contestAppeal = 0;
	private $contestJam = 0;
	private $contestEffect = "";
	private $superContestAppeal = 0;
	private $contestFlavorText = "";
	private $superContestFlavorText = "";

	public function __construct($args) {
		if (is_numeric($args))
			$this->setId($args);

		//	Array of properties passed, parse to construct
		elseif (is_array($args)) {
			foreach ($args as $key => $val) {
				switch ($key) {
					case "names":
						$this->setName($val);
					break;

					case "pp":
					case "PP":
						$this->setPP($val);
					break;

					case "id":
					case "generation":
					case "power":
				  	case "accuracy":
				  	case "priority":
				  	case "type":
				  	case "damageType":
				  	case "target":
					case "effect":
					case "shortEffect":
					case "contestType":
					case "contestAppeal":
					case "contestJam":
					case "contestEffect":
					case "superContestAppeal":
					case "contestFlavorText":
					case "superContestFlavorText":
						$method = "set". ucfirst($key);
						if (method_exists($this, $method))
							$this->{$method}($val);
					break;
				}
			}
		}

	}

	private static function nonNegative($test) {
		return self::intRange($test, 0);
	}

	/**
	 * @return int
	 */
	public function getPower() {
		return $this->power;
	}

	/**
	 * @return int
	 */
	public function getPP() {
		return $this->PP;
	}

	/**
	 * @return int
	 */
	public function getAccuracy() {
		return $this->accuracy;
	}

	/**
	 * @return int
	 */
	public function getContestAppeal() {
		return $this->contestAppeal;
	}

	/**
	 * @return string
	 */
	public function getContestEffect() {
		return $this->contestEffect;
	}

	/**
	 * @return string
	 */
	public function getContestFlavorText() {
		return $this->contestFlavorText;
	}

	/**
	 * @return string
	 */
	public function getContestType() {
		return $this->contestType;
	}

	/**
	 * @return int
	 */
	public function getContestJam() {
		return $this->contestJam;
	}

	/**
	 * @return string
	 */
	public function getDamageType() {
		return $this->damageType;
	}

	/**
	 * @return string
	 */
	public function getEffect() {
		return $this->effect;
	}

	/**
	 * @return int
	 */
	public function getPriority() {
		return $this->priority;
	}

	/**
	 * @return string
	 */
	public function getShortEffect() {
		return $this->shortEffect;
	}

	/**
	 * @return int
	 */
	public function getSuperContestAppeal() {
		return $this->superContestAppeal;
	}

	/**
	 * @return string
	 */
	public function getSuperContestFlavorText() {
		return $this->superContestFlavorText;
	}

	/**
	 * @return string
	 */
	public function getTarget() {
		return $this->target;
	}

	/**
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}

	public function setPower($power) {
		return $this->setProperty("power", $power, "self::nonNegative");
	}

	public function setPP($PP) {
		return $this->setProperty("PP", $PP, "self::nonNegative");
	}

	public function setAccuracy($accuracy) {
		return $this->setProperty("accuracy", $accuracy, "self::nonNegative");
	}

	public function setPriority($priority) {
		return $this->setProperty("priority", $priority, "is_int");
	}

	public function setType($type) {
		return $this->setProperty("type", $type, "is_string");
	}

	public function setDamageType($damageType) {
		return $this->setProperty("damageType", $damageType, "is_string");
	}

	public function setTarget($target) {
		return $this->setProperty("target", $target, "is_string");
	}

	public function setEffect($effect) {
		return $this->setProperty("effect", $effect, "is_string");
	}

	public function setShortEffect($shortEffect) {
		return $this->setProperty("shortEffect", $shortEffect, "is_string");
	}

	public function setContestType($contestType) {
		return $this->setProperty("contestType", $contestType, "is_string");
	}

	public function setContestAppeal($contestAppeal) {
		return $this->setProperty("contestAppeal", $contestAppeal, "self::nonNegative");
	}

	public function setContestJam($contestJam) {
		return $this->setProperty("contestJam", $contestJam, "self::nonNegative");
	}

	public function setContestEffect($contestEffect) {
		return $this->setProperty("contestEffect", $contestEffect, "is_string");
	}

	public function setSuperContestAppeal($superContestAppeal) {
		return $this->setProperty("superContestAppeal", $superContestAppeal, "self::nonNegative");
	}

	public function setContestFlavorText($contestFlavorText) {
		return $this->setProperty("contestFlavorText", $contestFlavorText, "is_string");
	}

	public function setSuperContestFlavorText($superContestFlavorText) {
		return $this->setProperty("superContestFlavorText", $superContestFlavorText, "is_string");
	}
} 
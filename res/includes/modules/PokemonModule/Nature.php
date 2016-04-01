<?php
/**
 * Utsubot - Nature.php
 * User: Benjamin
 * Date: 03/12/2014
 */

namespace Pokemon;

class Nature extends PokemonBase implements \Manageable {

	private static $contestAttributes = array("Cool", "Beauty", "Cute", "Smart", "Tough");
	private static $contestAttributeColors = array(array("red", null), array("blue", null), array("fuchsia", null), array("green", null), array("yellow", "black"));

	private static $flavors = array("Spicy", "Dry", "Sweet", "Bitter", "Sour");
	private static $stats = array("Attack", "Defense", "Speed", "Special Attack", "Special Defense");
	
	private $likes = "";
	private $dislikes = "";
	private $likesFlavor = "";
	private $dislikesFlavor = "";
	private $increases = "";
	private $decreases = "";

	public function __construct($args) {
		if (is_numeric($args))
			$this->setId($args);

		//	Array of properties passed, parse to construct
		elseif (is_array($args)) {
			foreach ($args as $key => $val) {
				switch ($key) {
					case	"id":				$this->setId($val);				break;
					case	"names":			$this->setName($val);			break;
					case	"likes":			$this->setLikes($val);			break;
					case	"dislikes":			$this->setDislikes($val);		break;
					case	"likesFlavor":		$this->setLikesFlavor($val);	break;
					case	"dislikesFlavor":	$this->setDislikesFlavor($val);	break;
					case	"increases":		$this->setIncreases($val);		break;
					case	"decreases":		$this->setDecreases($val);		break;
				}
			}
		}

	}

	/**
	 * Used internally by setters since they all follow the same format. Normalize value, check it against static array, and attempt to set
	 *
	 * @param string $field The property name
	 * @param string $value Value to set it to
	 * @param array $valid A list of valid values
	 * @return bool True on success, false on failiure
	 */
	private function setField($field, $value, $valid) {
		$value = ucwords(strtolower($value));
		if (property_exists($this, $field) && in_array($value, $valid)) {
			$this->{$field} = $value;
			return true;
		}

		return false;
	}

	/**
	 * @param string $attribute Contest attribute associated with the flavor of berry this nature likes
	 * @return bool True on success, false on failure
	 */
	public function setLikes($attribute) {
		return $this->setField("likes", $attribute, self::$contestAttributes);
	}

	/**
	 * @param string $attribute Contest attribute associated with the flavor of berry this nature dislikes
	 * @return bool True on success, false on failure
	 */
	public function setDislikes($attribute) {
		return $this->setField("dislikes", $attribute, self::$contestAttributes);
	}

	/**
	 * @param string $flavor Flavor of berry this nature likes
	 * @return bool True on success, false on failure
	 */
	public function setLikesFlavor($flavor) {
		return $this->setField("likesFlavor", $flavor, self::$flavors);
	}

	/**
	 * @param string $flavor Flavor of berry this nature dislikes
	 * @return bool True on success, false on failure
	 */
	public function setDislikesFlavor($flavor) {
		return $this->setField("dislikesFlavor", $flavor, self::$flavors);
	}

	/**
	 * @param string $stat Stat this nature increases
	 * @return bool True on success, false on failure
	 */
	public function setIncreases($stat) {
		return $this->setField("increases", $stat, self::$stats);
	}

	/**
	 * @param string $stat Stat this nature decreases
	 * @return bool True on success, false on failure
	 */
	public function setDecreases($stat) {
		return $this->setField("decreases", $stat, self::$stats);
	}

	/**
	 * Used internally by getters since they all follow the same format
	 *
	 * @param string $field Property name
	 * @return bool|string Property value, "None" if property is blank, or false if property doesn't exist
	 */
	private function getField($field) {
		if (property_exists($this, $field)) {
			if ($this->{$field})
				return $this->{$field};
			return "None";
		}

		return false;
	}

	/**
	 * @return string Contest attribute associated with the flavor of berry this nature likes
	 */
	public function getLikes() {
		return $this->getField("likes");
	}

	/**
	 * @return string Contest attribute associated with the flavor of berry this nature dislikes
	 */
	public function getDislikes() {
		return $this->getField("dislikes");
	}

	/**
	 * @return string Flavor of berry this nature likes
	 */
	public function getLikesFlavor() {
		return $this->getField("likesFlavor");
	}

	/**
	 * @return string Flavor of berry this nature dislikes
	 */
	public function getDislikesFlavor() {
		return $this->getField("dislikesFlavor");
	}

	/**
	 * @return string Stat this nature increases
	 */
	public function getIncreases() {
		return $this->getField("increases");
	}

	/**
	 * @return string Stat this nature decreases
	 */
	public function getDecreases() {
		return $this->getField("decreases");
	}


	/**
	 * Color the name of a contest attribute, based on in-game color
	 *
	 * @param string $attribute Name of contest move category
	 * @return string The attribute colored, or the original string if it's not a valid attribute
	 * @throws \IRCFormattingException If a non-string is given
	 */
	public static function colorAttribute($attribute) {

		if (!method_exists("self", "color"))
			return $attribute;

		$index = array_search(ucfirst(strtolower($attribute)), self::$contestAttributes);

		//	No coloring to be applied
		if ($index === false)
			return $attribute;

		$colors = self::$contestAttributeColors[$index];
		list($foreground, $background) = $colors;

		return self::color($attribute, $foreground, $background);
	}
} 
<?php
/**
 * MEGASSBOT - Item.php
 * User: Benjamin
 * Date: 09/11/14
 */

namespace Pokemon;

class Item {
	use AbilityItemCommon;

	private $cost = 0;
	private $flingPower = null;
	private $flingEffect = "";
	private $category = "";
	private $pocket = "";
	private $flags = array();

	public function __construct($args) {
		if (is_numeric($args))
			$this->setId($args);

		//	Array of properties passed, parse to construct
		elseif (is_array($args)) {
			foreach ($args as $key => $val) {
				switch ($key) {
					case "id":				$this->setId($val);				break;
					case "generation":		$this->setGeneration($val);		break;
					case "names":			$this->setName($val);			break;
					case "effect":			$this->setEffect($val);			break;
					case "category":		$this->setcategory($val);		break;
					case "pocket":			$this->setPocket($val);			break;
					case "flags":			$this->setFlag($val);			break;
					case "flingPower":		$this->setFlingPower($val);		break;
					case "flingEffect":		$this->setFlingEffect($val);	break;

					case "cost":
					case "price":
						$this->setCost($val);
					break;

					case "short":
					case "shortEffect":
						$this->setShortEffect($val);
					break;

					case "text":
					case "flavorText":
						$this->setText($val);
					break;
				}
			}
		}

	}

	/**
	 * Get the cost of this item in the mart
	 *
	 * @return int Cost in pokedollars
	 */
	public function getCost() {
		return $this->cost;
	}

	/**
	 * Get the base power of Fling when using this item
	 *
	 * @return int The base power
	 */
	public function getFlingPower() {
		return $this->flingPower;
	}

	/**
	 * Get the extra effect of Flingg when using this item
	 *
	 * @return string The effect
	 */
	public function getFlingEffect() {
		return $this->flingEffect;
	}

	/**
	 * Get whch category this item falls under
	 *
	 * @return string The category name
	 */
	public function getCategory() {
		return $this->category;
	}

	/**
	 * Get which pocket this item is stored in in the bag
	 *
	 * @return string The pocket name
	 */
	public function getPocket() {
		return $this->pocket;
	}

	/**
	 * Check whether this item has a certaom flag and get the description
	 *
	 * @param string|int $flag The name of a flag, or an index of this item's nth flag, or "all" for the whole collection
	 * @return array|string|bool Array of all flags if "all" is passed, the flag description if retrieving a single flag, or false on failure
	 */
	public function getFlag($flag) {
		//	Description of flag name
		if (isset($this->flags[$flag]))
			return $this->flags[$flag];

		//	Index of flags
		$keys = array_keys($this->flags);
		if (self::intRange($flag, 0, count($keys) - 1))
			return $this->flags[$keys[$flag]];

		//	Return collection of flags
		elseif ($flag == "all")
			return $this->flags;

		//	Failure
		return false;
	}

	/**
	 * Set how much this item costs in the mart
	 *
	 * @param int $cost Value in pokedollars
	 * @return bool True on success, false on failure
	 */
	public function setCost($cost) {
		if (!self::intRange($cost, 0))
			return false;

		$this->cost = $cost;
		return true;
	}

	/**
	 * Set the base power of Fling if using this item
	 *
	 * @param int $flingPower The base power
	 * @return bool True on success, false on failure
	 */
	public function setFlingPower($flingPower) {
		if (!self::intRange($flingPower, 0))
			return false;

		$this->flingPower = $flingPower;
		return true;
	}


	/**
	 * Set the secondary effect Fling has it using this item
	 *
	 * @param string $flingEffect The effect
	 * @return bool True on succees, false on failure
	 */
	public function setFlingEffect($flingEffect) {
		if (!is_string($flingEffect))
			return false;

		$this->flingEffect = $flingEffect;
		return true;
	}

	/**
	 * Sets the category for this item
	 *
	 * @param string $category Category  name
	 * @return bool True on success, false on failure
	 */
	public function setCategory($category) {
		if (!is_string($category))
			return false;

		$this->category = $category;
		return true;
	}

	/**
	 * Sets which pocket this item is stored in in the bag
	 *
	 * @param string $pocket Pocket name
	 * @return bool True on success, false on failure
	 */
	public function setPocket($pocket) {
		if (!is_string($pocket))
			return false;

		$this->pocket = $pocket;
		return true;
	}

	/**
	 * Sets an item flag and a corresponding description
	 *
	 * @param array|string $flag The name of the flag to add, or an array of 'flag' => 'value' pairs. Pass an empty string to clear all flags
	 * @param $value string A description of the flag. Pass an empty string to remove the flag if it exists.
	 * @return bool True on success, false on any failure
	 */
	public function setFlag($flag, $value = "") {
		if (!$flag) {
			$this->flags = array();
			return true;
		}

		elseif (is_array($flag)) {
			$return = true;
			foreach ($flag as $newFlag => $newValue) {
				if (!$this->setFlag($newFlag, $newValue))
					$return = false;
			}
			return $return;
		}

		elseif (!is_string($flag) || !is_string($value))
			return false;

		elseif (!$value) {
			if (isset($this->flags[$flag])) {
				unset($this->flags[$flag]);
				return true;
			}
			else
				return false;
		}

		$this->flags[$flag] = $value;
		return true;
	}
}
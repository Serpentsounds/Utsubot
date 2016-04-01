<?php
/**
 * MEGASSBOT - AbilityItemCommon.php
 * User: Benjamin
 * Date: 09/11/14
 */

namespace Pokemon;

abstract class AbilityItemBase extends PokemonBase {

	private $text = array();
	private $effect = array();
	private $shortEffect = array();

	/**
	 * Get this ability or item's effect
	 *
	 * @return string Effect description
	 */
	public function getEffect() {
		return $this->effect;
	}

	/**
	 * Get a more brief description this ability or item's effect
	 *
	 * @return string Effect description
	 */
	public function getShortEffect() {
		return $this->shortEffect;
	}

	/**
	 * Get an ability or item's flavor text
	 *
	 * @param string|null $version The version name for $text, omit to return whole array
	 * @return bool True on success, false on any failure
	 */
	public function getText($version = null) {
		if (!$version)
			return $this->text;

		elseif (isset($this->text[$version]))
			return $this->text[$version];

		return false;
	}

	/**
	 * Set an ability or item's effect
	 *
	 * @param string $effect Description of effect
	 * @return bool True on success, false on failure
	 */
	public function setEffect($effect) {
		if (!is_string($effect))
			return false;

		$this->effect = $effect;
		return true;
	}

	/**
	 * Set an ability or item's effect (brief description)
	 *
	 * @param string $effect Description of effect
	 * @return bool True on success, false on failure
	 */
	public function setShortEffect($effect) {
		if (!is_string($effect))
			return false;

		$this->shortEffect = $effect;
		return true;
	}

	/**
	 * Set an ability or item's flavor text
	 *
	 * @param array|string $text Flavor text for version or an array of 'version' => 'text'
	 * @param string|null $version The version name for $text, unnecessary if using an array for $text
	 * @param string|null $language The language $text is in, unnecessary if using an array for $text
	 * @return bool True on success, false on any failure
	 */
	public function setText($text, $version = null, $language = "english") {
		//	Strings passed, set single version text
		if (is_string($text) && is_string($version) && ($language = self::getLanguage($language)) !== false) {
			$this->text[$version][$language] = $text;
			return true;
		}

		//	Loop through array of 'version' => 'language' => 'text'
		elseif (is_array($text)) {
			$return = true;
			foreach ($text as $newVersion => $languages) {
				foreach ($languages as $newLanguage => $newText) {
					if (!$this->setText($newText, $newVersion, $newLanguage))
						$return = false;
				}
			}
			return $return;
		}

		//	Invalid parameters
		return false;
	}
}
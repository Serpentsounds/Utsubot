<?php
/**
 * MEGASSBOT - AbilityItemCommon.php
 * User: Benjamin
 * Date: 09/11/14
 */

namespace Utsubot\Pokemon;

abstract class AbilityItemBase extends PokemonBase {

	private $text = [ ];
	private $effect = [ ];
	private $shortEffect = [ ];

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
	 * @param Version $version
     * @param Language $language
     * @return string
     * @throws PokemonBaseException
	 */
	public function getText(Version $version, Language $language): string {
        return $this->text[$version->getValue()][$language->getValue()] ?? "";
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
     * @param string $text
     * @param Version $version
     * @param Language $language
     * @throws PokemonBaseException
     */
	public function setText(string $text, Version $version, Language $language) {
        $this->text[$version->getValue()][$language->getValue()] = $text;
    }

}
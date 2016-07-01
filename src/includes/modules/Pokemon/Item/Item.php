<?php
/**
 * MEGASSBOT - Item.php
 * User: Benjamin
 * Date: 09/11/14
 */

namespace Utsubot\Pokemon\Item;

use Utsubot\Manageable;
use Utsubot\Pokemon\{
    AbilityItemBase,
    PokemonBaseException
};


/**
 * Class ItemException
 *
 * @package Utsubot\Pokemon\Item
 */
class ItemException extends PokemonBaseException {

}

/**
 * Class Item
 *
 * @package Utsubot\Pokemon\Item
 */
class Item extends AbilityItemBase implements Manageable {

    const FLAG_COUNTABLE       = 1;
    const FLAG_CONSUMABLE      = 2;
    const FLAG_USABLEOVERWORLD = 4;
    const FLAG_USABLEBATTLE    = 8;
    const FLAG_HOLDABLE        = 16;
    const FLAG_HOLDABLEPASSIVE = 32;
    const FLAG_HOLDABLEACTIVE  = 64;
    const FLAG_UNDERGROUND     = 128;

    const FLAG_DISPLAY = [
        1   => "Countable",
        2   => "Consumable",
        4   => "Usable in overworld",
        8   => "Usable in battle",
        16  => "Holdable",
        32  => "Passive while held",
        64  => "Activates while held",
        128 => "Found in underground"
    ];

    const POCKET_MISC      = 0;
    const POCKET_MEDICINE  = 1;
    const POCKET_POKEBALLS = 2;
    const POCKET_MACHINES  = 3;
    const POCKET_BERRIES   = 4;
    const POCKET_MAIL      = 5;
    const POCKET_BATTLE    = 6;
    const POCKET_KEY       = 7;

    const POCKET_DISPLAY = [
        "Items",
        "Medicine",
        "Poké Balls",
        "TMs and HMs",
        "Berries",
        "Mail",
        "Battle Items",
        "Key Items"
    ];

    const FLING_BADPOISON = 0;
    const FLING_BURN      = 1;
    const FLING_BERRY     = 2;
    const FLING_HERB      = 3;
    const FLING_PARALYZE  = 4;
    const FLING_POISON    = 5;
    const FLING_FLINCH    = 6;

    const FLING_DISPLAY = [
        "Badly poisons the target.",
        "Burns the target.",
        "Immediately activates the berry's effect on the target.",
        "Immediately activates the herb's effect on the target.",
        "Paralyzes the target.",
        "Poisons the target.",
        "Target will flinch if it has not yet gone this turn."
    ];

    private $cost        = 0;
    private $flingPower  = -1;
    private $flingEffect = -1;
    private $category    = "";
    private $pocket      = -1;
    private $flag        = 0;


    /**
     * Get the cost of this item in pokedollars in the mart
     *
     * @return int
     */
    public function getCost(): int {
        return $this->cost;
    }


    /**
     * Get the base power of Fling
     *
     * @return int
     */
    public function getFlingPower(): int {
        return $this->flingPower;
    }


    /**
     * Get the extra effect of Fling
     *
     * @return int
     */
    public function getFlingEffect(): int {
        return $this->flingEffect;
    }


    /**
     * Get fling's effect as a string
     *
     * @return string
     */
    public function getFlingEffectDisplay(): string {
        return self::FLING_DISPLAY[ $this->flingEffect ] ?? "";
    }


    /**
     * Get whch category this item falls under
     *
     * @return string
     */
    public function getCategory(): string {
        return $this->category;
    }


    /**
     * Get the identifier for the pocket this item is stored in in the bag
     *
     * @return int
     */
    public function getPocket(): int {
        return $this->pocket;
    }


    /**
     * Get the name of this item's pocket as a string
     *
     * @return string
     */
    public function getPocketDisplay(): string {
        return self::POCKET_DISPLAY[ $this->pocket ] ?? "";
    }


    /**
     * Check whether this item has a certain flag
     *
     * @param int $flag
     * @return bool
     * @throws ItemException
     */
    public function hasFlag(int $flag): bool {
        if (!array_key_exists($flag, self::FLAG_DISPLAY))
            throw new ItemException("Invalid item flag '$flag'.");

        return (bool)($this->flag & $flag);
    }


    /**
     * Get the list of flags formatted as a string
     *
     * @return string
     */
    public function formatFlags(): string {
        $return = [ ];
        foreach (self::FLAG_DISPLAY as $flag => $description) {
            if ($flag & $this->flag)
                $return[] = $description;
        }

        return implode(", ", $return);
    }


    /**
     * Set how much this item costs (in pokedollars) in the mart
     *
     * @param int $cost
     * @throws ItemException
     */
    public function setCost(int $cost) {
        if ($cost < 0)
            throw new ItemException("Invalid item cost '$cost'.");

        $this->cost = $cost;
    }


    /**
     * Set the base power of Fling
     *
     * @param int $flingPower
     * @throws ItemException
     */
    public function setFlingPower(int $flingPower) {
        if ($flingPower < 0)
            throw new ItemException("Invalid item fling base power '$flingPower'.");

        $this->flingPower = $flingPower;
    }


    /**
     * Set the secondary effect Fling has
     *
     * @param int $flingEffect
     * @throws ItemException
     */
    public function setFlingEffect(int $flingEffect) {
        if (!array_key_exists($flingEffect, self::FLING_DISPLAY))
            throw new ItemException("Invalid item fling effect id '$flingEffect'.");

        $this->flingEffect = $flingEffect;
    }


    /**
     * Sets the category
     *
     * @param string $category
     */
    public function setCategory(string $category) {
        $this->category = $category;
    }


    /**
     * Sets which pocket this item is stored in in the bag
     *
     * @param int $pocket
     * @throws ItemException
     */
    public function setPocket(int $pocket) {
        if (!array_key_exists($pocket, self::POCKET_DISPLAY))
            throw new ItemException("Invalid item pocket id '$pocket'.");

        $this->pocket = $pocket;
    }


    /**
     * Adds a flag
     *
     * @param int $flag
     * @throws ItemException
     */
    public function addFlag(int $flag) {
        if (!array_key_exists($flag, self::FLAG_DISPLAY))
            throw new ItemException("Invalid item flag id '$flag'.");

        $this->flag = $this->flag | $flag;
    }
}
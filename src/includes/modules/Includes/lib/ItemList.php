<?php
/**
 * Utsubot - ItemList.php
 * Date: 06/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Includes;


/**
 * Class ItemList
 * Base class for ClassList and FileList used in !includes processing
 *
 * @package Utsubot\Includes
 */
class ItemList {

    /** @var string[] $list */
    protected $list = array();

    /**
     * ItemList constructor.
     *
     * @param array $items Array of strings
     */
    public function __construct(array $items) {
        foreach ($items as $item)
            $this->add($item);
    }

    /**
     * Add an item to the list
     *
     * @param string $item
     */
    public function add(string $item) {
        $this->list[] = $item;
    }

    /**
     * Get the number of saved items
     *
     * @return int
     */
    public function getItemCount(): int {
        return count($this->list);
    }

    /**
     * Sort the internal list
     */
    public function sortList() {
        sort($this->list);
    }

    /**
     * Filter down the internal list to a subset if it contains a given string
     * 
     * @param string $search
     */
    public function filterList(string $search) {
        $this->list = array_filter(
            $this->list,
            function ($item) use ($search) {
                return stripos((string)$item, $search) !== false;
            }
        );
    }

    /**
     * Get a concatenated string of the saved classes
     *
     * @param int $cutoff Results after the cutoff index will be omitted
     * @return string
     */
    public function getFormattedList(int $cutoff): string {
        $this->sortList();

        //  Omit items past cutoff
        $output = array_slice($this->list, 0, $cutoff);

        //  Append the count of omitted items if any had to be omitted
        $numberOfItems = count($this->list);
        if ($cutoff > 0 && $numberOfItems > $cutoff)
            $output[] = "and ". ($numberOfItems - $cutoff). " more.";

        return "There are {$this->getItemCount()} items: ". implode(", ", $output);
    }
}
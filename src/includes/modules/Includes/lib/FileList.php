<?php
/**
 * Utsubot - FileList.php
 * Date: 06/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Includes;


/**
 * Class FileList
 * Represents a list of File objects
 *
 * @package Utsubot\Includes
 */
class FileList extends ItemList {

    /** @var File[] $list */
    protected $list = array();

    /**
     * Add a new file to the collection
     *
     * @param string $file
     * @throws FileException Invalid file name
     */
    public function add(string $file) {
        $this->list[] = new File($file);
    }

    /**
     * Sort the internal file list
     * Sort order is line count descending > alphabetical ascending
     */
    public function sortList() {
        usort($this->list,

            function(File $a, File $b) {
                $linesA = $a->getLineCount();
                $linesB = $b->getLineCount();

                if ($linesA != $linesB)
                    //    Reverse sort, longer files first
                    return $linesB - $linesA;

                //  Line count equal alphabetical
                return strcmp($a->getPath(), $b->getPath());
            }

        );
    }

    /**
     * Filter down the internal list to a subset if the path contains a given string
     *
     * @param string $search
     */
    public function filterList(string $search) {
        $this->list = array_filter(
            $this->list,
            function (File $item) use ($search) {
                return stripos($item->getPath(), $search) !== false;
            }
        );
    }

    /**
     * Sum of all file sizes in bytes
     *
     * @return int
     */
    public function getTotalSize(): int {
        $size = 0;
        foreach ($this->list as $file)
            $size += $file->getSize();

        return $size;
    }

    /**
     * Formatted sum of all file sizes
     *
     * @return string
     */
    public function getTotalFormattedSize(): string {
        return sprintf(File::SIZE_FORMAT, $this->getTotalSize() / File::SIZE_DIVISOR);
    }

    /**
     * Sum of all line counts
     *
     * @return int
     */
    public function getTotalLines(): int {
        $lines = 0;
        foreach ($this->list as $file)
            $lines += $file->getLineCount();

        return $lines;
    }

    /**
     * Average size of files in bytes
     *
     * @return float
     */
    public function getAverageSize(): float {
        return round($this->getTotalSize() / count($this->list), 2);
    }

    /**
     * Formatted average size of files
     *
     * @return string
     */
    public function getAverageFormattedSize(): string {
        return sprintf(File::SIZE_FORMAT, $this->getAverageSize() / File::SIZE_DIVISOR);
    }

    /**
     * Average line count of files
     *
     * @return float
     */
    public function getAverageLines(): float {
        return round($this->getTotalLines() / count($this->list), 2);
    }
}
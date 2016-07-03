<?php
/**
 * Utsubot - File.php
 * Date: 06/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Includes;

/**
 * Class FileException
 *
 * @package Utsubot\Includes
 */
class FileException extends \Exception {

}


/**
 * Class File
 * Represents a text file
 *
 * @package Utsubot\Includes
 */
class File {

    const SIZE_FORMAT  = "%.2fKiB";
    const SIZE_DIVISOR = 1024;

    /** @var int $lineCount */
    private $lineCount;
    /** @var int $size */
    private $size;
    /** @var string $path */
    private $path;


    /**
     * File constructor.
     *
     * @param string $path
     * @throws FileException
     */
    public function __construct(string $path) {
        if (!is_file($path))
            throw new FileException("$path is not a file.");

        $this->path      = $path;
        $this->lineCount = count(file($path));
        $this->size      = filesize($path);
    }


    /**
     * Formatted file name and line count
     *
     * @return string
     */
    public function __toString(): string {
        return sprintf("%s (%d li.)", basename($this->path), $this->lineCount);
    }


    /**
     * Number of lines in file
     *
     * @return int
     */
    public function getLineCount(): int {
        return $this->lineCount;
    }


    /**
     * Size of file in bytes
     *
     * @return int
     */
    public function getSize(): int {
        return $this->size;
    }


    /**
     * Formatted size of file
     *
     * @return string
     */
    public function getFormattedSize(): string {
        return sprintf(self::SIZE_FORMAT, $this->size / self::SIZE_DIVISOR);
    }


    /**
     * Full path and name of file
     *
     * @return string
     */
    public function getPath(): string {
        return $this->path;
    }


    /**
     * Base filename of file
     *
     * @return string
     */
    public function getName(): string {
        return basename($this->path);
    }
}
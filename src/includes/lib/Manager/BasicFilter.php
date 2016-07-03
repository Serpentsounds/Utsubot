<?php
/**
 * Utsubot - BasicFilter.php
 * Date: 21/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Manager;

/**
 * Class BasicFilter
 * A custom FilterIterator used in the Manager's search function
 *
 * @package Utsubot
 */
class BasicFilter extends \FilterIterator {

    protected $search;


    /**
     * ManagerFilter constructor.
     *
     * @param \Iterator $iterator
     * @param mixed     $search Item to match against. Can be anything the managed objects accept
     */
    public function __construct(\Iterator $iterator, $search) {
        parent::__construct($iterator);
        $this->search = $search;
    }


    /**
     * @return bool
     */
    public function accept(): bool {
        $obj = $this->current();
        if ($obj instanceof Manageable)
            return $obj->search($this->search);

        return false;
    }
    
}
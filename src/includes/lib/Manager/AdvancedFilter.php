<?php
/**
 * Utsubot - AdvancedFilter.php
 * Date: 02/07/2016
 */

declare(strict_types = 1);

namespace Utsubot\Manager;

/**
 * Class AdvancedFilter
 *
 * @package Utsubot\Manager
 */
class AdvancedFilter extends \FilterIterator {

    /** @var SearchCriterion[] $criteria */
    protected $criteria;

    /** @var SearchMode */
    protected $searchMode;


    /**
     * AdvancedManagerFilter constructor.
     *
     * @param \Iterator      $iterator
     * @param SearchCriteria $criteria
     * @param SearchMode     $searchMode
     */
    public function __construct(\Iterator $iterator, SearchCriteria $criteria, SearchMode $searchMode) {
        parent::__construct($iterator);
        $this->criteria   = $criteria;
        $this->searchMode = $searchMode;
    }


    /**
     * @return bool
     */
    public function accept(): bool {
        $object = $this->current();

        //  Keep a running total of criteria met to prepare results for any SearchMode
        $numberOfCriteria = count($this->criteria);
        $matchedCriteria  = 0;

        //  Compare all criteria one by one
        foreach ($this->criteria as $criterion) {
            if ($criterion->compare($object))
                $matchedCriteria++;
        }

        //  Result depends on SearchMode
        $return = false;
        switch ($this->searchMode->getValue()) {
            case SearchMode::Any:
                $return = $matchedCriteria > 0;
                break;

            case SearchMode::All:
                $return = $matchedCriteria >= $numberOfCriteria;
                break;
        }

        return $return;
    }

}
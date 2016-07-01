<?php
/**
 * Created by PhpStorm.
 * User: benny
 * Date: 6/30/2016
 * Time: 6:06 PM
 */

namespace Utsubot\Pokemon;

use \Utsubot\ManagerFilter;


/**
 * Class JaroFilter
 *
 * @package Utsubot\Pokemon
 */
class JaroFilter extends ManagerFilter {

    const MINIMUM_SIMILARITY = 0.80;

    protected $search;
    protected $language;


    /**
     * JaroFilter constructor.
     *
     * @param \Iterator $iterator
     * @param mixed     $search
     * @param Language  $language
     */
    public function __construct(\Iterator $iterator, $search, Language $language) {
        parent::__construct($iterator, $search);

        $this->language = $language;
    }


    /**
     * @return bool
     */
    public function accept(): bool {
        $obj = $this->current();
        if ($obj instanceof PokemonBase)
            return self::MINIMUM_SIMILARITY <= $obj->jaroSearch($this->search, $this->language);

        return false;
    }
}

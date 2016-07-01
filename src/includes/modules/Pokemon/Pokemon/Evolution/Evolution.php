<?php
/**
 * Utsubot - Evolution.php
 * Date: 13/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon\Pokemon;
use Utsubot\Pokemon\PokemonBaseException;
use function Utsubot\bold;


/**
 * Class EvolutionException
 *
 * @package Utsubot\Pokemon\Pokemon
 */
class EvolutionException extends PokemonBaseException {}


/**
 * Class Evolution
 *
 * @package Utsubot\Pokemon\Pokemon
 */
class Evolution {

    protected $from;
    protected $to;
    /** @var Method $method */
    protected $method;
    protected $requirement;
    protected $details;
    protected $isPre;


    /**
     * @param string $from
     */
    public function setFrom(string $from) {
        $this->from = $from;
    }


    /**
     * @param string $to
     */
    public function setTo(string $to) {
        $this->to = $to;
    }


    /**
     * @param Method $method
     * @throws EvolutionException
     */
    public function setMethod(Method $method) {
        $this->method = $method;
    }


    /**
     * @param bool $pre
     */
    public function setPre(bool $pre) {
        $this->isPre = $pre;
    }


    /**
     * Add a method requirement to the level up process
     *
     * @param Requirement $requirement
     * @param string $details
     * @throws EvolutionException If given method doesn't match up to one of the method constants
     */
    public function addRequirement(Requirement $requirement, $details) {
        $value = $requirement->getValue();
        $this->requirement     = $this->requirement | $value;
        $this->details[$value] = $details;
    }


    /**
     * Format evolution information into a human-readable string
     *
     * @return string
     */
    public function format(): string {
        $output = [ Method::findName($this->method->getValue()) ];

        //  Check each available requirement to see if it's included
        for ($binary = decbin($this->requirement), $length = strlen($binary), $i = 0; $i < $length; $i++) {
            if (intval($binary[$i])) {
                $value = 1 << ($length - 1 - $i);
                $display = (string)(new Requirement($value));

                //  Parameters to be filled
                if (strpos($display, "%") !== false)
                    $output[] = sprintf($display, $this->details[$value]);
                //  Static description
                else
                    $output[] = $display;
            }
        }

        //  Change output format depending on if this is set as a pre-evolution or not
        $name = ($this->isPre) ? $this->from : $this->to;
        return bold($name). "/". implode(" ", $output);
    }

}
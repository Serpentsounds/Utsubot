<?php
/**
 * MEGASSBOT - PokemonCommon.php
 * User: Benjamin
 * Date: 07/11/14
 */

namespace Utsubot\Pokemon;
use function Utsubot\Jaro\jaroWinklerDistance;


class PokemonBaseException extends \Exception {}

/**
 * Class PokemonBase
 * Method and members used by all substance classes of the pokemon extension (Pokemon, Ability, Nature, Item, Move, MetaPokemon)
 * @package Pokemon
 */
abstract class PokemonBase {

    protected $id = -1;
    protected $generation = -1;
    protected $names = [ ];
    protected $lastJaroResult;
    
    /**
     * @return string
     */
    public function __toString(): string {
        return $this->getName(new Language(Language::English));
    }
    
    /**
     * Test if a search term (usu. id number or name) matches this object
     *
     * @param mixed $search
     * @return bool
     */
    public function search($search): bool {

        //	Numeric search
        if (is_int($search)) {
            if ($search == $this->id)
                return true;
        }

        //	String search
        elseif (is_string($search)) {
            //	Case insensitive
            $normalize = function(string $str) {
                return strtolower(
                    str_replace(
                        [ " ", "-" ],
                        "",
                        $str
                    )
                );
            };

            //	Check search vs names, allowing wildcards
            foreach ($this->names as $name) {
                if (fnmatch($search, $name) || fnmatch($normalize($search), $normalize($name)))
                    return true;
            }

        }

        //	No match
        return false;
    }

    /**
     * Retieve this object's unique ID number
     *
     * @return int Id number
     */
    public function getId(): int {
        return $this->id;
    }

    /**
     * Retrieve a name for this object
     *
     * @param Language $language
     * @return string
     */
    public function getName(Language $language): string {
        return $this->names[$language->getValue()] ?? "";
    }

    /**
     * Return all saved names for this object
     *
     * @return array
     */
    public function getNames(): array {
        return $this->names;
    }

    /**
     * Get the first generation of games this object was introduced in
     *
     * @return int The generation number
     */
    public function getGeneration(): int {
        return $this->generation;
    }

    /**
     * Sets the id nunber for this object
     *
     * @param int $id A non-negative integer as the id number
     * @throws PokemonBaseException
     */
    public function setId(int $id) {
        if ($id < 0)
            throw new PokemonBaseException("Invalid ID number '$id'.");

        $this->id = $id;
    }

    /**
     * Set one of this object's name values (english, japanese, french, german, etc.)
     *
     * @param string $name
     * @param Language $language
     * @throws PokemonBaseException Invalid language
     */
    public function setName(string $name, Language $language) {
        $this->names[$language->getValue()] = $name;
    }

    /**
     * Set the first generation of games this ability was introduced in
     *
     * @param int $generation
     * @throws PokemonBaseException
     */
    public function setGeneration(int $generation) {
        if ($generation < 1 || $generation > 6)
            throw new PokemonBaseException("Invalid generation number '$generation'.");

        $this->generation = $generation;
    }

    /**
     * Get the Jaro-Winkler distance from a search to the closest 'name' this object has
     *
     * @param string $search
     * @param Language $language
     * @return float
     */
    public function jaroSearch(string $search, Language $language): float {
        $return = 0;
        $languageId = $language->getValue();
        foreach ($this->names as $compareLanguage => $name) {
            if (($languageId == Language::All || $languageId == $compareLanguage) && ($jaroWinkler = jaroWinklerDistance($name, $search)) > $return) {
                $return = $jaroWinkler;
                $this->lastJaroResult = new JaroResult($name, $search, $jaroWinkler);
            }
        }

        return $return;
    }

    /**
     * @return JaroResult
     * @throws PokemonBaseException
     */
    public function getLastJaroResult(): JaroResult {
        if (!($this->lastJaroResult instanceof JaroResult))
            throw new PokemonBaseException("Unable to get last Jaro result because no Jaro searches have been performed.");
        
        return $this->lastJaroResult;
    }
    
}

/**
 * Class JaroResult
 *
 * @package Utsubot\Pokemon
 */
class JaroResult {
    private $targetString;
    private $matchedString;
    private $similarity;

    /**
     * JaroResult constructor.
     *
     * @param string $targetString
     * @param string $matchedString
     * @param float $similarity
     */
    public function __construct(string $targetString, string $matchedString, float $similarity) {
        $this->targetString = $targetString;
        $this->matchedString = $matchedString;
        $this->similarity = $similarity;
    }

    /**
     * @return string
     */
    public function getTargetString(): string {
        return $this->targetString;
    }

    /**
     * @return string
     */
    public function getMatchedString(): string {
        return $this->matchedString;
    }

    /**
     * @return float
     */
    public function getSimilarity(): float {
        return $this->similarity;
    }
}
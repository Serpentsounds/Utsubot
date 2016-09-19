<?php
/**
 * PHPBot - pokemon.php
 * User: Benjamin
 * Date: 14/05/14
 */

namespace Utsubot\Pokemon\Pokemon;

use Utsubot\Pokemon\{
    Language, PokemonBase, Stat, Dex, Version, LearnedMoves
};
use Utsubot\Pokemon\PokemonBaseException;


/**
 * Class PokemonException
 *
 * @package Utsubot\Pokemon\Pokemon
 */
class PokemonException extends PokemonBaseException {

}


/**
 * Class Pokemon
 *
 * @package Utsubot\Pokemon\Pokemon
 */
class Pokemon extends PokemonBase {

    //  Max number of each attribute per pokemon
    const Number_of_Types     = 2;
    const Number_of_Abilities = 3;
    const Number_of_EggGroups = 2;

    //  Order of preference for default dex entries
    const Dex_Version_Preference = [
        Version::Omega_Ruby, Version::Alpha_Sapphire,
        Version::X, Version::Y,
        Version::Black_2, Version::White_2,
        Version::Black, Version::White,
        Version::HeartGold, Version::SoulSilver,
        Version::Platinum, Version::Diamond, Version::Pearl,
        Version::FireRed, Version::LeafGreen,
        Version::Emerald, Version::Ruby, Version::Sapphire,
        Version::Crystal, Version::Gold, Version::Silver,
        Version::Yellow, Version::Red, Version::Blue
    ];

    private $regexSearch = "//";

    private $abilities      = [ "", "", "" ];
    private $evolutions     = [ ];
    private $preEvolutions  = [ ];
    private $alternateForms = [ ];
    /** @var LearnedMoves $LearnedMoves */
    private $LearnedMoves   = null;

    //  Attributes
    private $baseStats     = [ 0, 0, 0, 0, 0, 0 ];
    private $evYield       = [ 0, 0, 0, 0, 0, 0 ];
    private $types         = [ "", "" ];
    private $eggGroups     = [ ];
    private $eggSteps      = 0;
    private $baseExp       = 0;
    private $catchRate     = 0;
    private $ratioMale     = 0;
    private $baseHappiness = 0;
    private $isBaby        = false;

    //  Pokemon Go Attributes
    private $goCatchRate   = 0.0;
    private $goFleeRate    = 0.0;
    private $candyToEvolve = 0;

    //  Pokedex Fields
    private $dexNumbers = [ ];
    private $dexEntries = [ ];
    private $species    = [ ];
    private $habitat    = "";
    private $color      = "";
    private $height     = 0;    //  In meters
    private $weight     = 0;    //  In kilograms


    /**
     * Pokemon constructor.
     */
    public function __construct() {
        $this->LearnedMoves = new LearnedMoves();
    }

    /**
     * @return string
     */
    public function __toString(): string {
        return $this->getName(new Language(Language::English));
    }


    /**
     * Test if a search term matches this pokemon
     *
     * @param mixed $search Term to search against (id number or name)
     * @return bool Search result
     */
    public function search($search): bool {
        //  Test base search function in PokemonBase
        if (parent::search($search))
            return true;

        //  If no match, attempt to match this pokemon's regular expression search
        elseif ($this->regexSearch != "//" && preg_match($this->regexSearch, $search))
            return true;

        //  No match
        return false;
    }


    /**
     * Get the LearnedMoves Manager which details the possible Moves a Pokemon can learn
     *
     * @return LearnedMoves
     */
    public function getLearnedMoves(): LearnedMoves {
        return $this->LearnedMoves;
    }


    /**
     * Retrieve one of the pokemon's dex numbers
     *
     * @param Dex $dex
     * @return int Returns -1 if there is no entry
     */
    public function getDexNumber(Dex $dex) {
        return $this->dexNumbers[ $dex->getValue() ] ?? -1;
    }


    /**
     * Search for a pokedex entry
     *
     * @param Version  $version
     * @param Language $language
     * @return string
     */
    public function getDexEntry(Version $version, Language $language): string {
        return $this->dexEntries[ $version->getValue() ][ $language->getValue() ] ?? "";
    }


    /**
     * Search for the most recent pokedex entry
     *
     * @param Language $language
     * @return string
     */
    public function getLatestDexEntry(Language $language): string {
        return $this->getDexEntry($this->getLatestValidDexVersion($language), $language);
    }


    /**
     * Format a Pokedex entry for output
     *
     * @param Version  $version
     * @param Language $language
     * @return string
     * @throws PokemonException
     */
    public function getFormattedDexEntry(Version $version, Language $language): string {
        $dexEntry = $this->getDexEntry($version, $language);
        $name     = $this->getName($language);
        $species  = $this->getSpecies($language);

        if (!$dexEntry || !$name || !$species)
            throw new PokemonException("Not enough information in database for a dex readout for {$this} using language {$language->getName()} and version {$version->getName()}.");

        return sprintf(
            "%03d: %s, the %s Pokémon. %s",
            $this->getDexNumber(new Dex(Dex::National)),
            $name,
            $species,
            $dexEntry
        );
    }


    /**
     * Format the most recent pokedex entry
     *
     * @param Language $language
     * @return string
     * @throws PokemonException
     */
    public function getLatestFormattedDexEntry(Language $language): string {
        return $this->getFormattedDexEntry($this->getLatestValidDexVersion($language), $language);
    }


    /**
     * @param Language $language
     * @return Version
     * @throws PokemonException
     */
    private function getLatestValidDexVersion(Language $language): Version {
        foreach (self::Dex_Version_Preference as $versionId) {
            $version = new Version($versionId);
            if ($this->getDexEntry($version, $language))
                return $version;
        }

        throw new PokemonException("No dex entries found in {$language->getName()} for $this.");
    }


    /**
     * Retrieve the regular expression used to match against failed searches (for alternate spellings or misspellings)
     *
     * @return string The regular expression
     */
    public function getRegexSearch() {
        return $this->regexSearch;
    }


    /**
     * Get the name of an ability
     *
     * @param int $index The ability number (1-3) to retrieve
     * @return string
     * @throws PokemonException
     */
    public function getAbility(int $index = 1): string {
        if ($index < 0 || $index > self::Number_of_Abilities - 1)
            throw new PokemonException("Invalid ability index '$index'.");

        return $this->abilities[ $index ] ?? "";
    }


    /**
     * Get all abilities as an array
     *
     * @return array
     */
    public function getAbilities(): array {
        return $this->abilities;
    }


    /**
     * @param string $ability
     * @return bool
     */
    public function hasAbility(string $ability): bool {
        return (in_array(strtolower($ability), array_map("strtolower", $this->abilities)));
    }


    /**
     * Get a base stat value
     *
     * @param Stat $stat
     * @return int
     * @throws PokemonException
     */
    public function getBaseStat(Stat $stat): int {
        return $this->baseStats[ $stat->getValue() ];
    }


    /**
     * Get all base stat values as an array
     *
     * @return array
     */
    public function getBaseStats(): array {
        return $this->baseStats;
    }


    /**
     * Get the base Stamina value in Pokemon Go
     *
     * @return int
     */
    public function getBaseGoStamina(): int {
        return 2 * $this->getBaseStat(new Stat(Stat::HP));
    }


    /**
     * Get the base Attack value in Pokemon Go
     *
     * @return int
     */
    public function getBaseGoAttack() {
        return 2 * (int)round(
            $this->getBaseStat(new Stat(Stat::Attack)) ** 0.5 *
            $this->getBaseStat(new Stat(Stat::Special_Attack)) ** 0.5 +
            $this->getBaseStat(new Stat(Stat::Speed)) ** 0.5
        );
    }


    /**
     * Get the base Defense value in Pokemon Go
     *
     * @return int
     */
    public function getBaseGoDefense() {
        return 2 * (int)round(
            $this->getBaseStat(new Stat(Stat::Defense)) ** 0.5 *
            $this->getBaseStat(new Stat(Stat::Special_Defense)) ** 0.5 +
            $this->getBaseStat(new Stat(Stat::Speed)) ** 0.5
        );
    }


    /**
     * Get the maximum possible CP for maxed trainer level and IVs
     *
     * @return int
     */
    public function getMaxCP(): int {
        $adjustedStamina = .7903 * ($this->getBaseGoStamina() + 15);
        $adjustedAttack  = .7903 * ($this->getBaseGoAttack() + 15);
        $adjustedDefense = .7903 * ($this->getBaseGoDefense() + 15);

        return (int)max(
            10, floor(
                  $adjustedStamina ** 0.5 *
                  $adjustedAttack *
                  $adjustedDefense ** 0.5 /
                  10
              )
        );
    }


    /**
     * Get the sum of all base stats
     *
     * @return int
     */
    public function getBaseStatTotal(): int {
        return array_sum($this->baseStats);
    }


    /**
     * Get the EV yield for a given stat
     *
     * @param Stat $index
     * @return int
     * @throws PokemonException
     */
    public function getEVYieldFor(Stat $index) {
        return $this->evYield[ $index->getValue() ];
    }


    /**
     * @return array
     */
    public function getEVYield(): array {
        return $this->evYield;
    }


    /**
     * Gets a single type of this pokemon
     *
     * @param int $index
     * @return string
     * @throws PokemonException
     */
    public function getType(int $index = 0): string {
        if (!array_key_exists($index, $this->types))
            throw new PokemonException("Invalid type index '$index'.");

        return $this->types[ $index ] ?? "";
    }


    /**
     * @return array
     */
    public function getTypes(): array {
        return array_filter($this->types);
    }


    /**
     * @param string $type
     * @return bool
     */
    public function hasType(string $type): bool {
        return (in_array(strtolower($type), array_map("strtolower", $this->types)));
    }


    /**
     * @return string
     */
    public function getFormattedType(): string {
        return implode("/", array_filter($this->types));
    }


    /**
     * Get an evolution
     *
     * @param int $index
     * @return Evolution
     * @throws PokemonException
     */
    public function getEvolution(int $index = 0): Evolution {
        if (!isset($this->evolutions[ $index ]))
            throw new PokemonException("Invalid evolution index '$index'.");

        return $this->evolutions[ $index ];
    }


    /**
     * @return bool
     */
    public function hasEvo(): bool {
        return (bool)count($this->evolutions);
    }


    /**
     * Get all evolutions as an array
     *
     * @return array
     */
    public function getEvolutions(): array {
        return $this->evolutions ?? [ ];
    }


    /**
     * Get a pre-evolution
     *
     * @param int $index
     * @return Evolution
     * @throws PokemonException
     */
    public function getPreEvolution(int $index = 0): Evolution {
        if (!isset($this->preEvolutions[ $index ]))
            throw new PokemonException("Invalid pre-evolution index '$index'.");

        return $this->preEvolutions[ $index ];
    }


    /**
     * Get all pre-evolutions as an array
     *
     * @return array
     */
    public function getPreEvolutions(): array {
        return $this->preEvolutions ?? [ ];
    }


    /**
     * @return bool
     */
    public function hasPreEvo(): bool {
        return (bool)count($this->preEvolutions);
    }


    /**
     * Get the base experience this pokemon awards upon defeat
     *
     * @return int
     */
    public function getBaseExp(): int {
        return $this->baseExp;
    }


    /**
     * Get the pokemon's capture rate, which determines how easy it is to catch (lower values = more difficult)
     *
     * @return int
     */
    public function getCatchRate(): int {
        return $this->catchRate;
    }


    /**
     * Get the pokemon's base capture rate in Pokemon Go, which corresponds to a percentage
     *
     * @return float
     */
    public function getGoCatchRate(): float {
        return $this->goCatchRate;
    }


    /**
     * Get the pokemon's base flee rate in Pokemon Go, which corresponds to a percentage
     *
     * @return float
     */
    public function getGoFleeRate(): float {
        return $this->goFleeRate;
    }


    /**
     * Get the number of candy this pokemon requires to evolve in Pokemon Go
     *
     * @return int
     */
    public function getCandyToEvolve(): int {
        return $this->candyToEvolve;
    }


    /**
     * Get the likelihood of this pokemon being male
     *
     * @return float Returns relevant rate, or false on failure
     */
    public function getGenderRatio(): float {
        return $this->ratioMale;
    }


    /**
     * Get the minimum number of steps required to hatch an egg holding this pokemon
     *
     * @return int
     */
    public function getEggSteps(): int {
        return $this->eggSteps;
    }


    /**
     * Get the number of egg cycles an egg of this pokemon will go through
     *
     * @return int
     */
    public function getEggCycles(): int {
        return intval(($this->eggSteps / 255) - 1);
    }


    /**
     * Get a pokemon's habitat as defined in the in-game pokedex of older versions
     *
     * @return string
     */
    public function getHabitat(): string {
        return $this->habitat;
    }


    /**
     * Get a pokemon's species as defined in the in-game pokedex
     *
     * @param Language $language
     * @return string
     */
    public function getSpecies(Language $language): string {
        return $this->species[ $language->getValue() ] ?? "";
    }


    /**
     * Get a pokemon's height in meters
     *
     * @return float
     */
    public function getHeight(): float {
        return $this->height;
    }


    /**
     * Get a pokemon's weight in kilograms
     *
     * @return float
     */
    public function getWeight(): float {
        return $this->weight;
    }


    /**
     * Get a pokemon's color as defined in the in-game pokedex
     *
     * @return string
     */
    public function getColor(): string {
        return $this->color;
    }


    /**
     * Get a pokemon's base happiness value
     *
     * @return int
     */
    public function getBaseHappiness(): int {
        return $this->baseHappiness;
    }


    /**
     * Determine whether or not this pokemon is considered a "baby" pokemon
     *
     * @return bool
     */
    public function isBaby(): bool {
        return $this->isBaby;
    }


    /**
     * Get an egg group of this pokemon
     *
     * @param int $index
     * @return string
     * @throws PokemonException Invalid index
     */
    public function getEggGroup(int $index) {
        if ($index < 0 || $index > self::Number_of_EggGroups - 1)
            throw new PokemonException("Invalid egg group index '$index'.");

        return $this->eggGroups[ $index ] ?? "";
    }


    /**
     * @return array
     */
    public function getEggGroups(): array {
        return $this->eggGroups;
    }


    /**
     * @param string $eggGroup
     * @return bool
     */
    public function hasEggGroup(string $eggGroup): bool {
        return (in_array(strtolower($eggGroup), array_map("strtolower", $this->eggGroups)));
    }


    /**
     * Save a new LearnedMoves Manager
     *
     * @param LearnedMoves $LearnedMoves
     */
    public function setLearnedMoves(LearnedMoves $LearnedMoves) {
        $this->LearnedMoves = $LearnedMoves;
    }


    /**
     * Sets a dex number for this pokemon
     *
     * @param int $number
     * @param Dex $dex
     * @throws PokemonException Invalid number
     */
    public function setDexNumber(int $number, Dex $dex) {
        if ($number < 0)
            throw new PokemonException("Invalid dex number '$number'.");

        $this->dexNumbers[ $dex->getValue() ] = $number;
    }


    /**
     * Add a pokedex entry
     *
     * @param string   $entry
     * @param Version  $version
     * @param Language $language
     * @return string
     */
    public function setDexEntry(string $entry, Version $version, Language $language) {
        $this->dexEntries[ $version->getValue() ][ $language->getValue() ] = $entry;
    }


    /**
     * Set the regular expression used to perform additional searches for this pokemon
     *
     * @param string $regex
     * @throws PokemonException Expression can't be compiled
     */
    public function setRegexSearch(string $regex) {
        //  Ensure regex delimeters are in place
        if (substr($regex, 0, 1) != substr($regex, -1))
            $regex = "/$regex/";

        //  Must be a valid regular expression
        if (@preg_match($regex, null) === false)
            throw new PokemonException("Invalid regular expression '$regex'.");

        $this->regexSearch = $regex;
    }


    /**
     * Sets an ability
     *
     * @param int    $index
     * @param string $ability
     * @throws PokemonException Invalid index
     */
    public function setAbility(int $index, string $ability) {
        if ($index < 0 || $index > self::Number_of_Abilities - 1)
            throw new PokemonException("Invalid ability index '$index'.");

        $this->abilities[ $index ] = $ability;
    }


    /**
     * Set all abilities at once via an array
     *
     * @param array $abilities
     * @throws PokemonException Invalid index
     */
    public function setAbilities(array $abilities) {
        foreach ($abilities as $index => $ability)
            $this->setAbility($index, $ability);
    }


    /**
     * Sets a base stat
     *
     * @param Stat $stat
     * @param int  $value
     * @throws PokemonException Invalid stat value
     */
    public function setBaseStat(Stat $stat, int $value) {
        if ($value < 1 || $value > 255)
            throw new PokemonException("Invalid stat number '$value'.");

        $this->baseStats[ $stat->getValue() ] = $value;
    }


    /**
     * Set the EV yield of a stat
     *
     * @param Stat $stat
     * @param int  $yield
     * @throws PokemonException Invalid index or yield value
     */
    public function setEVYield(Stat $stat, int $yield) {
        if ($yield < 0 || $yield > 3)
            throw new PokemonException("Invalid EV yield value '$yield'.");

        $this->evYield[ $stat->getValue() ] = $yield;
    }


    /**
     * Set a type
     *
     * @param int    $index
     * @param string $type
     * @throws PokemonException Invalid index
     */
    public function setType(int $index, string $type) {
        if ($index < 0 || $index > self::Number_of_Types - 1)
            throw new PokemonException("Invalid type index '$index'.");

        $this->types[ $index ] = $type;
    }


    /**
     * Set an evolution
     *
     * @param int       $index
     * @param Evolution $evolution
     */
    public function setEvolution(int $index, Evolution $evolution) {
        $evolution->setPre(false);
        $this->evolutions[ $index ] = $evolution;
    }


    /**
     * Append an evolution
     *
     * @param Evolution $evolution
     */
    public function addEvolution(Evolution $evolution) {
        $this->setEvolution(count($this->evolutions), $evolution);
    }


    /**
     * Set a pre-evolution
     *
     * @param int       $index
     * @param Evolution $evolution
     */
    public function setPreEvolution(int $index, Evolution $evolution) {
        $evolution->setPre(true);
        $this->preEvolutions[ $index ] = $evolution;
    }


    /**
     * Append a pre-evolution
     *
     * @param Evolution $evolution
     */
    public function addPreEvolution(Evolution $evolution) {
        $this->setPreEvolution(count($this->preEvolutions), $evolution);
    }


    /**
     * @param string $formName
     * @param array  $info
     */
    public function addToAlternateForm(string $formName, array $info) {
        $base                              = $this->alternateForms[ $formName ] ?? [ ];
        $this->alternateForms[ $formName ] = array_merge_recursive($base, $info);
    }


    /**
     * Set a pokemon's base experience awarded upon defeat
     *
     * @param int $baseExp
     * @throws PokemonException Invalid value
     */
    public function setBaseExp(int $baseExp) {
        if ($baseExp < 0)
            throw new PokemonException("Invalid base exp value '$baseExp'.");

        $this->baseExp = $baseExp;
    }


    /**
     * Set the pokemon's capture rate, which determines how easy it is to catch (lower values = more difficult)
     *
     * @param int $catchRate
     * @throws PokemonException Invalid value
     */
    public function setCatchRate(int $catchRate) {
        if ($catchRate < 3 || $catchRate > 255)
            throw new PokemonException("Invalid catch rate '$catchRate'.");

        $this->catchRate = $catchRate;
    }


    /**
     * Set the capture rate in Pokemon Go, which corresponds to a percentage
     *
     * @param float $goCatchRate
     * @throws PokemonException
     */
    public function setGoCatchRate(float $goCatchRate) {
        if ($goCatchRate < 0 || $goCatchRate > 1)
            throw new PokemonException("Invalid Go catch rate '$goCatchRate'.");

        $this->goCatchRate = $goCatchRate;
    }


    /**
     * Set the flee rate in Pokemon Go, which corresponds to a percentage
     *
     * @param float $goFleeRate
     * @throws PokemonException
     */
    public function setGoFleeRate(float $goFleeRate) {
        if ($goFleeRate < 0 || $goFleeRate > 1)
            throw new PokemonException("Invalid Go flee rate '$goFleeRate'.");

        $this->goFleeRate = $goFleeRate;
    }


    /**
     * Set the number of candy this pokemon requires to evolve in Pokemon Go
     *
     * @param int $candyToEvolve
     * @throws PokemonException
     */
    public function setCandyToEvolve(int $candyToEvolve) {
        if ($candyToEvolve < 0 || $candyToEvolve > 400)
            throw new PokemonException("Invalid evolution candy requirement '$candyToEvolve'.");

        $this->candyToEvolve = $candyToEvolve;
    }


    /**
     * Set the likelihood of this pokemon being male
     * A value of -1 specifies genderless
     *
     * @param float $genderRatio
     * @throws PokemonException Invalid value
     */
    public function setGenderRatio(float $genderRatio) {
        if ($genderRatio != -1 && ($genderRatio < 0 || $genderRatio > 1))
            throw new PokemonException("Invalid gender ratio '$genderRatio'.");

        $this->ratioMale = $genderRatio;
    }


    /**
     * Set the minimum number of steps required to hatch an egg holding this pokemon
     *
     * @param int $eggSteps
     * @throws PokemonException Invalid value
     */
    public function setEggSteps(int $eggSteps) {
        if ($eggSteps < 0 || $eggSteps > 30855)
            throw new PokemonException("Invalid egg steps value '$eggSteps'.");

        $this->eggSteps = $eggSteps;
    }


    /**
     * Set the egg steps by converting from number of cycles
     *
     * @param int $eggCycles
     * @throws PokemonException Invalid value
     */
    public function setEggCycles(int $eggCycles) {
        if ($eggCycles < 5 || $eggCycles > 120)
            throw new PokemonException("Invalid egg cycles value '$eggCycles'.");

        $this->eggSteps = ($eggCycles + 1) * 255;
    }


    /**
     * Set a pokemon's habitat as defined in the in-game pokedex of older versions
     *
     * @param string $habitat
     */
    public function setHabitat(string $habitat) {
        $this->habitat = $habitat;
    }


    /**
     * Set a pokemon's species as defined in the in-game pokedex
     *
     * @param string   $species
     * @param Language $language
     */
    public function setSpecies(string $species, Language $language) {
        $this->species[ $language->getValue() ] = $species;
    }


    /**
     * Set a pokemon's height in meters
     *
     * @param float $height
     * @throws PokemonException Invalid value
     */
    public function setHeight(float $height) {
        if ($height <= 0)
            throw new PokemonException("Invalid height value '$height'.");

        $this->height = $height;
    }


    /**
     * Set a pokemon's weight in kilograms
     *
     * @param float $weight
     * @throws PokemonException Invalid value
     */
    public function setWeight(float $weight) {
        if ($weight <= 0)
            throw new PokemonException("Invalid weight value '$weight'.");

        $this->weight = $weight;
    }


    /**
     * Set a pokemon's color as defined in the in-game pokedex
     *
     * @param string $color
     */
    public function setColor(string $color) {
        $this->color = $color;
    }


    /**
     * Set a pokemon's base happiness
     *
     * @param int $happiness
     * @throws PokemonException Invalid value
     */
    public function setBaseHappiness(int $happiness) {
        if ($happiness < 0 || $happiness > 255)
            throw new PokemonException("Invalid base happiness value '$happiness'.");

        $this->baseHappiness = $happiness;
    }


    /**
     * Set whether or not this pokemon is considered a "baby" pokemon
     *
     * @param bool $isBaby
     */
    public function setBaby(bool $isBaby) {
        $this->isBaby = $isBaby;
    }


    /**
     * Set an egg group used for breeding
     *
     * @param int    $index
     * @param string $eggGroup
     * @throws PokemonException
     */
    public function setEggGroup(int $index, string $eggGroup) {
        if ($index < 0 || $index > self::Number_of_EggGroups - 1)
            throw new PokemonException("Invalid egg group index '$index'.");

        $this->eggGroups[ $index ] = $eggGroup;
    }


    /**
     * Append an egg group without specifying the index
     *
     * @param string $eggGroup
     * @throws PokemonException
     */
    public function addEggGroup(string $eggGroup) {
        $this->setEggGroup(count($this->eggGroups), $eggGroup);
    }

}

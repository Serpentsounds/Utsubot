<?php
/**
 * Utsubot - newfunctions.php
 * Date: 16/05/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon\Types;

use Utsubot\EnumException;
use Utsubot\Pokemon\Pokemon\Pokemon;


/**
 * Get the composite multiplier of an attacking type vs. one or more defending types
 *
 * @param TypeChart $attacking
 * @param TypeGroup $defending
 * @return float
 */
function getCompoundEffectiveness(TypeChart $attacking, TypeGroup $defending): float {
    $multiplier = 1;

    foreach ($defending as $type) {
        $typeEffectiveness = $attacking->getTypeEffectivenessMultiplier($type);
        $multiplier *= $typeEffectiveness->getValue();
    }

    return $multiplier;
}

/**
 * @param TypeChart $attacking
 * @param Pokemon   $pokemon
 * @return PokemonMatchupResult
 */
function pokemonMatchup2(TypeChart $attacking, Pokemon $pokemon): PokemonMatchupResult {
    $result = new PokemonMatchupResult(
        getCompoundEffectiveness(
            $attacking,
            new TypeGroup($pokemon->getTypes())
        )
    );

    try {
        /*  Convert TypeChart to Type for use with ability classes, or throw an EnumException for non-Type charts
            e.g. Freeze-dry and Flying Press */
        /** @var Type $type */
        $type = Type::fromName($attacking->getName());

        $abilities = $pokemon->getAbilities();

        foreach ($abilities as $ability) {
            try {
                /** @var BasicAbilityDefense $abilityDefense
                Throws an EnumException if the ability name doens't have special type interactions */
                $abilityDefense = BasicAbilityDefense::fromName($ability);

                //  Throws a BasicAbilityDefenseException if the ability doesn't affect the given type
                $result->addBasicAbilityMultiplier($abilityDefense, $type, $result->getBaseMultiplier());
            }

            //  Type not affected by ability, but it was a valid ability, so we can start the loop over
            catch (BasicAbilityDefenseException $e) {
                continue;
            }

            //  Not a valid BasicAbilityDefense, attempt to match ability to AdvancedAbilityDefense
            catch (EnumException $e) {
                try {
                    /** @var AdvancedAbilityDefense $abilityDefense
                    Throws an EnumException if the ability name doesn't have special type interactions */
                    $abilityDefense = AdvancedAbilityDefense::fromName($ability);

                    //  Throws an AdvancedAbilityDefenseException if the ability doesn't affect this matchup
                    $result->addAdvancedAbilityMultiplier($abilityDefense, $result->getBaseMultiplier());
                }

                /*  Not a valid AdvancedAbilityDefense, or invalid trigger conditions
                    (AdvancedAbilityDefenseException extends EnumException) */
                catch (EnumException $e) {
                    continue;
                }

            }

        }

    }

    //  Invalid offensive Type name, continue to return base result without ability processing
    catch (EnumException $e) {
    }

    return $result;
}

/**
 * Class PokemonMatchupResult
 *
 * @package Utsubot\Pokemon\Types
 */
class PokemonMatchupResult {

    private $baseMultiplier;
    private $abilityMultipliers = [ ];


    /**
     * PokemonMatchupResult constructor.
     *
     * @param float $multiplier
     */
    public function __construct(float $multiplier) {
        $this->baseMultiplier = $multiplier;
    }


    /**
     * @param BasicAbilityDefense $ability
     * @param Type                $type
     * @param float               $value
     * @throws BasicAbilityDefenseException
     */
    public function addBasicAbilityMultiplier(BasicAbilityDefense $ability, Type $type, float $value) {
        $abilityName = $ability->getName();
        if (!isset($this->abilityMultipliers[ $abilityName ]))
            $this->abilityMultipliers[ $abilityName ] = 1;

        $this->abilityMultipliers[ $abilityName ] *= $ability->apply($type, $value);
    }


    /**
     * @param AdvancedAbilityDefense $ability
     * @param float                  $value
     * @throws AdvancedAbilityDefenseException
     */
    public function addAdvancedAbilityMultiplier(AdvancedAbilityDefense $ability, float $value) {
        $abilityName = $ability->getName();
        if (!isset($this->abilityMultipliers[ $abilityName ]))
            $this->abilityMultipliers[ $abilityName ] = 1;

        $this->abilityMultipliers[ $abilityName ] *= $ability->apply($value);
    }


    /**
     * @return float
     */
    public function getBaseMultiplier(): float {
        return $this->baseMultiplier;
    }


    /**
     * @return bool
     */
    public function hasAbilityMultiplier(): bool {
        return count($this->abilityMultipliers) > 0;
    }


    /**
     * @return array
     */
    public function getAbilityMultipliers(): array {
        return $this->abilityMultipliers;
    }
}
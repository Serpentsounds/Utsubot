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
 * Get the composite multiplier of one or more attacking types vs. one or more defending types
 *
 * @param array $attackingTypes
 * @param array $defendingTypes
 * @return float
 */
function getCompoundEffectiveness(array $attackingTypes, array $defendingTypes): float {
    $multiplier = 1;

    foreach ($attackingTypes as $type1) {
        /** @var TypeChart $typeChart */
        if (!($typeChart instanceof TypeChart))
            $typeChart = TypeChart::fromName($type1);

        foreach ($defendingTypes as $type2) {
            /** @var Type $type */
            if (!($type instanceof Type))
                $type = Type::fromName($type2);

            $typeEffectiveness = $typeChart->getTypeEffectivenessMultiplier($type);
            $multiplier *= $typeEffectiveness->getValue();
        }
    }

    return $multiplier;
}

/**
 * @param array   $attacking
 * @param Pokemon $pokemon
 * @return PokemonMatchupResult
 */
function pokemonMatchup2(array $attacking, Pokemon $pokemon): PokemonMatchupResult {
    $result = new PokemonMatchupResult(
        getCompoundEffectiveness(
            $attacking,
            $pokemon->getTypes()
        )
    );

    $abilities = $pokemon->getAbilities();

    foreach ($abilities as $ability) {
        try {
            /** @var BasicAbilityDefense $abilityDefense */
            $abilityDefense = BasicAbilityDefense::fromName($ability);

            foreach ($attacking as $typeName) {
                /** @var Type $type */
                $type = Type::fromName($typeName);
                $result->addBasicAbilityMultiplier($abilityDefense, $type, $result->getBaseMultiplier());
            }
        }

        //  Invalid trigger conditions for ability
        catch (BasicAbilityDefenseException $e) {
            continue;
        }

        //  Not a valid BasicAbilityDefense
        catch (EnumException $e) {
            try {
                /** @var AdvancedAbilityDefense $abilityDefense */
                $abilityDefense = AdvancedAbilityDefense::fromName($ability);
                $result->addAdvancedAbilityMultiplier($abilityDefense, $result->getBaseMultiplier());
            }

            //  Not a valid AdvancedAbilityDefense, or invalid trigger conditions
            catch (EnumException $e) {
                continue;
            }

        }

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
        if (!isset($this->abilityMultipliers[$abilityName]))
            $this->abilityMultipliers[$abilityName] = 1;

        $this->abilityMultipliers[$abilityName] *= $ability->apply($type, $value);
    }

    /**
     * @param AdvancedAbilityDefense $ability
     * @param float                  $value
     * @throws AdvancedAbilityDefenseException
     */
    public function addAdvancedAbilityMultiplier(AdvancedAbilityDefense $ability, float $value) {
        $abilityName = $ability->getName();
        if (!isset($this->abilityMultipliers[$abilityName]))
            $this->abilityMultipliers[$abilityName] = 1;

        $this->abilityMultipliers[$abilityName] *= $ability->apply($value);
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
<?php
/**
 * Created by PhpStorm.
 * User: benny
 * Date: 6/30/2016
 * Time: 6:16 PM
 */

namespace Utsubot\Pokemon;

/**
 * Interface PokemonObjectPopulator
 *
 * @package Utsubot\Pokemon
 */
interface PokemonObjectPopulator {

    /**
     * @return PokemonGroup
     */
    public function getPokemon(): PokemonGroup;


    /**
     * @return AbilityGroup
     */
    public function getAbilities(): AbilityGroup;


    /**
     * @return ItemGroup
     */
    public function getItems(): ItemGroup;


    /**
     * @return MoveGroup
     */
    public function getMoves(): MoveGroup;


    /**
     * @return NatureGroup
     */
    public function getNatures(): NatureGroup;

}
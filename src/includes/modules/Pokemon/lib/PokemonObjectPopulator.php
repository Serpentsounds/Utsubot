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
     * @param Pokemons $pokemons
     * @return Pokemons
     */
    public function getPokemon(Pokemons $pokemons): Pokemons;


    /**
     * @param Abilities $abilities
     * @return Abilities
     */
    public function getAbilities(Abilities $abilities): Abilities;


    /**
     * @param Items $items
     * @return Items
     */
    public function getItems(Items $items): Items;


    /**
     * @param Moves $moves
     * @return Moves
     */
    public function getMoves(Moves $moves): Moves;


    /**
     * @param Natures $natures
     * @return Natures
     */
    public function getNatures(Natures $natures): Natures;

}
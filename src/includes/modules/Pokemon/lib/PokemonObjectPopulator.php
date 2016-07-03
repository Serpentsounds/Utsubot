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
     * @return Pokemons
     */
    public function getPokemon(): Pokemons;


    /**
     * @return Abilities
     */
    public function getAbilities(): Abilities;


    /**
     * @return Items
     */
    public function getItems(): Items;


    /**
     * @return Moves
     */
    public function getMoves(): Moves;


    /**
     * @return Natures
     */
    public function getNatures(): Natures;

}
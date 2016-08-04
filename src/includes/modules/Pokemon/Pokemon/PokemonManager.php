<?php
/**
 * PHPBot - PokemonManager.php
 * User: Benjamin
 * Date: 24/05/14
 */

namespace Utsubot\Pokemon\Pokemon;


use Utsubot\Manager\{
    ManagerException
};
use Utsubot\Pokemon\{
    PokemonManagerBase,
    MethodInfo,
    MethodInfoWithParameters,
    Stat,
    Language
};


/**
 * Class PokemonManagerException
 *
 * @package Utsubot\Pokemon\Pokemon
 */
class PokemonManagerException extends ManagerException {

}


/**
 * Class PokemonManager
 *
 * @package Utsubot\Pokemon\Pokemon
 */
class PokemonManager extends PokemonManagerBase {

    const Manages          = "Utsubot\\Pokemon\\Pokemon\\Pokemon";
    const Populator_Method = "getPokemon";
    const TypedArray_Class = "Utsubot\\Pokemon\\Pokemons";


    /**
     * PokemonManager constructor.
     */
    public function __construct() {
        parent::__construct();
    }


    /**
     * @param string $field
     * @param array $parameters
     * @return MethodInfo
     * @throws PokemonManagerException
     */
    public function getMethodFor(string $field, array $parameters = [ ]): MethodInfo {

        switch (strtolower($field)) {
            case "hp":
            case "hit points":
            case "atk":
            case "attack":
            case "def":
            case "defense":
            case "spa":
            case "special attack":
            case "spd":
            case "special defense":
            case "spe":
            case "speed":
                $return = new MethodInfo("getBaseStat", [ Stat::fromName($field) ]);
                break;

            case "total":
            case "bst":
                $return = new MethodInfo("getBaseStatTotal", [ ]);
                break;

            case "goattack":
            case "goatk":
                $return = new MethodInfo("getBaseGoAttack", [ ]);
                break;

            case "godefense":
            case "godef":
                $return = new MethodInfo("getBaseGoDefense", [ ]);
                break;

            case "gostamina":
            case "gosta":
                $return = new MethodInfo("getBaseGoStamina", [ ]);
                break;

            case "maxcp":
            case "cp":
                $return = new MethodInfo("getMaxCP", [ ]);
                break;

            case "hpev":
            case "atkev":
            case "defev":
            case "spaev":
            case "spdev":
            case "speev":
                $return = new MethodInfo("getEVYieldFor", [ Stat::fromName(substr($field, 0, -2)) ]);
                break;

            case "ability1":
            case "ability2":
            case "ability3":
            case "abl1":
            case "abl2":
            case "abl3":
                $return = new MethodInfo("getAbility", [ intval(substr($field, -1)) - 1 ]);
                break;

            case "abilities":
            case "ability":
                $return = new MethodInfo("getAbilities", [ ]);
                break;

            case "hasabl":
            case "hasability":
                $return = new MethodInfoWithParameters("hasAbility", $parameters);
                break;

            case "type1":
            case "type2":
                $return = new MethodInfo("getType", [ intval(substr($field, -1)) - 1 ]);
                break;

            case "type":
            case "types":
                $return = new MethodInfo("getTypes", [ ]);
                break;

            case "hastype":
                $return = new MethodInfoWithParameters("hasType", $parameters);
                break;

            case "species":
                $return = new MethodInfo("getSpecies", [ new Language(Language::English) ]);
                break;

            case "height":
                $return = new MethodInfo("getHeight", [ ]);
                break;

            case "weight":
                $return = new MethodInfo("getWeight", [ ]);
                break;

            case "color":
                $return = new MethodInfo("getColor", [ ]);
                break;

            case "happiness":
            case "basehappiness":
                $return = new MethodInfo("getBaseHappiness", [ ]);
                break;

            case "hasegggroup":
            case "egggroup":
                $return = new MethodInfoWithParameters("hasEggGroup", $parameters);
                break;

            case "baby":
                $return = new MethodInfo("isBaby", [ ]);
                break;

            case "hasevo":
                $return = new MethodInfo("hasEvo", [ ]);
                break;

            case "haspreevo":
                $return = new MethodInfo("hasPreEvo", [ ]);
                break;

            case "exp":
            case "baseexp":
                $return = new MethodInfo("getBaseExp", [ ]);
                break;

            case "catchrate":
            case "basecatchrate":
            case "capturerate":
            case "basecapturerate":
                $return = new MethodInfo("getCatchRate", [ ]);
                break;

            case "gocatchrate":
            case "gobasecatchrate":
            case "gocapturerate":
            case "gobasecapturerate":
                $return = new MethodInfo("getGoCatchRate", [ ]);
                break;

            case "fleerate":
            case "gofleerate":
            case "flee":
            case "goflee":
                $return = new MethodInfo("getGoFleeRate", [ ]);
                break;

            case "candytoevolve":
            case "gocandytoevolve":
            case "candy":
            case "gocandy":
                $return = new MethodInfo("getCandyToEvolve", [ ]);
                break;

            case "genderratio":
            case "ratiomale":
                $return = new MethodInfo("getGenderRatio", [ ]);
                break;

            case "eggsteps":
            case "stepstohatch":
                $return = new MethodInfo("getEggSteps", [ ]);
                break;

            case "eggcycles":
                $return = new MethodInfo("getEggCycles", [ ]);
                break;

            case "habitat":
                $return = new MethodInfo("getHabitat", [ ]);
                break;

            default:
                $return = parent::getMethodFor($field, $parameters);
                break;
        }



        return $return;
    }

}
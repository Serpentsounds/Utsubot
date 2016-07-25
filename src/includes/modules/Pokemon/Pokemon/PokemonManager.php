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
    PokemonManagerBase, MethodInfo, Pokemons, Stat, Language
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
     * @return MethodInfo
     * @throws PokemonManagerException
     */
    public function getMethodFor(string $field): MethodInfo {

        switch (strtolower($field)) {
            case "id":
            case "pid":
                $return = new MethodInfo("getId", [ ]);
                break;

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

            case "maxcp":
            case "cp":
                $return = new MethodInfo("getMaxCP", [ ]);
                break;

            case "name":
                $return = new MethodInfo("getName", [ new Language(Language::English) ]);
                break;

            case "english":
            case "romaji":
            case "katakana":
            case "french":
            case "german":
            case "korean":
            case "italian":
            case "chinese":
            case "spanish":
            case "czech":
            case "official roomaji":
            case "roumaji":
            case "japanese":
                $return = new MethodInfo("getName", [ Language::fromName($field) ]);
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
                $return = new MethodInfo("hasAbility", [ ]);
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
                $return = new MethodInfo("hasType", [ ]);
                break;

            case "species":
                $return = new MethodInfo("getSpecies", [ ]);
                break;

            case "generation":
                $return = new MethodInfo("getGeneration", [ ]);
                break;

            case "baby":
                $return = new MethodInfo("isBaby", [ ]);
                break;

            default:
                throw new PokemonManagerException("Unsupported search field '$field'.");
                break;
        }

        return $return;
    }

}
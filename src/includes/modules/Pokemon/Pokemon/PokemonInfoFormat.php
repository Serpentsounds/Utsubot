<?php
/**
 * Utsubot - PokemonInfoFormat.php
 * User: Benjamin
 * Date: 04/12/2014
 */

namespace Utsubot\Pokemon\Pokemon;

use Utsubot\Converter\Converter;
use Utsubot\Pokemon\{
    Dex,
    Stat,
    Language,
    InfoFormat,
    InfoFormatException
};
use function Utsubot\{
    bold,
    italic,
    Japanese\romanizeKana,
    Pokemon\Types\colorType
};


/**
 * Class PokemonInfoFormat
 *
 * @package Utsubot\Pokemon\Pokemon
 */
class PokemonInfoFormat extends InfoFormat {

    const UNITS_IMPERIAL = 1;
    const UNITS_METRIC   = 2;
    const UNITS_BOTH     = 3;

    /** @var $object Pokemon */
    protected $object;

    private $units = "imperial";

    protected static $defaultFormat = <<<EOF
[^Name^: {english}/{japanese}] {[^Dex^: #national]} [^Type^: {type1}{/type2}] [^Abilities^: {ability1}{/ability2}{/ability3}]
{[^Evolves from^: preevolution]} {[^Evolution^: evolution]}
{[^Stats^: hpHP, atkAtk, defDef, spaSpA, spdSpD, speedSpe, totalTotal]}
EOF;

    protected static $semanticFormat = <<<EOF
[^Name^: {english}/{japanese}] {[^Species^: species]} {[^Color^: color]} {[^Habitat^: habitat]} [^Gender^: {male} Male/{female} Female]
{[^Height^: height]} {[^Weight^: weight]} {[^EVs^: evs]} {[^Catch Rate^: catchRate]} {[^Base Exp^: baseExp]} {[^Base Happiness^: baseHappiness]} {[^Egg Group^: eggGroup]} {[^Egg Steps^: eggSteps]}
EOF;

    protected static $namesFormat = <<<EOF
[^English^: {english}] [^Japanese^: {japanese} ({roumaji}{/officialroumaji})] {[^Spanish^: spanish]} {[^Italian^: italian]} {[^Korean^: korean]} {[^Chinese^: chinese]}
{[^German^: german]} {[^French^: french]}
EOF;

    protected static $dexesFormat = <<<EOF
[^Name^: {english}/{japanese}] {[^National^: national]} {[^Kanto^: kanto]} {[^Johto^: johto]} {[^Hoenn^: hoenn]} {[^Sinnoh^: sinnoh]} {[^Ext. Sinnoh^: extsinnoh]} {[^New Johto^: newjohto]}
{[^Unova^: unova]} {[^New Unova^: newunova]} {[^Central Kalos^: centralkalos]} {[^Coastal Kalos^: coastalkalos]} {[^Mountain Kalos^: mountainkalos]} {[^New Hoenn^: newhoenn]}
EOF;

    protected static $compareFormat = <<<EOF
{english}%n
{type1}{/type2}%n
{hp}%n
{atk}%n
{def}%n
{spa}%n
{spd}%n
{spe}%n
{ability1}{/ability2}%n
{ability3}
EOF;

    protected static $validFields = [
        "english", "japanese", "roumaji", "officialroumaji",
        "german", "french", "spanish", "korean", "chinese", "italian", "czech",
        "generation",
        "type1", "type2",
        "ability1", "ability2", "ability3",
        "hp", "atk", "def", "spa", "spd", "speed", "total",
        "preevolution", "evolution",
        "color", "species", "habitat",
        "male", "female",
        "height", "weight",
        "evs",
        "catchRate", "baseExp", "baseHappiness",
        "eggGroup", "eggSteps",
        "national", "kanto", "johto", "hoenn", "sinnoh", "extsinnoh", "newjohto",
        "unova", "newunova", "centralkalos", "coastalkalos", "mountainkalos", "newhoenn"
    ];


    /**
     * @return string
     */
    public static function getSemanticFormat(): string {
        return self::$semanticFormat;
    }


    /**
     * @return string
     */
    public static function getNamesFormat(): string {
        return self::$namesFormat;
    }


    /**
     * @return string
     */
    public static function getDexesFormat(): string {
        return self::$dexesFormat;
    }


    /**
     * @return string
     */
    public static function getCompareFormat(): string {
        return self::$compareFormat;
    }


    /**
     * @param int $units
     * @throws InfoFormatException
     */
    public function setUnits(int $units) {
        if ($units != self::UNITS_METRIC && $units != self::UNITS_BOTH && $units != self::UNITS_IMPERIAL)
            throw new InfoFormatException("Invalid units identifier '$units'.");

        $this->units = $units;
    }


    /**
     * @param string $field
     * @param        $fieldValue
     * @return string
     */
    protected function formatField(string $field, $fieldValue): string {
        if (substr($field, 0, 4) == "type")
            $fieldValue = colorType($fieldValue, true);

        elseif ($field == "ability3")
            $fieldValue = italic(bold($fieldValue));

        //	Special case for evolutions, bold already added
        elseif (substr($field, -9) == "evolution" || $field == "evs" || $field == "eggGroup" || (($field == "height" || $field == "weight") && $this->units == "both")) {
        }

        //	Default case, just bold
        else
            $fieldValue = bold($fieldValue);

        return $fieldValue;
    }


    /**
     * @param string $field
     * @return string
     * @throws PokemonException
     */
    protected function getField(string $field): string {

        if ($return = parent::getField($field))
            return $return;

        switch ($field) {
            case "national":
            case "kanto":
            case "johto":
            case "hoenn":
            case "sinnoh":
            case "unova":
            case "extsinnoh":
            case "newjohto":
            case "newunova":
            case "centralkalos":
            case "coastalkalos":
            case "mountainkalos":
            case "newhoenn":
            case "gallery":
                $dexes = [
                    'johto'         => "Original Johto",
                    'sinnoh'        => "Original Sinnoh",
                    'hoenn'         => "Original Hoenn",
                    'extsinnoh'     => "Extended Sinnoh",
                    'unova'         => "Original Unova",
                    'newjohto'      => "Updated Johto",
                    'newunova'      => "Updated Unova",
                    'centralkalos'  => "Central Kalos",
                    'coastalkalos'  => "Coastal Kalos",
                    'mountainkalos' => "Mountain Kalos",
                    'newhoenn'      => "New Hoenn"
                ];

                if (isset($dexes[ $field ]))
                    $field = $dexes[ $field ];
                else
                    $field = ucfirst($field);

                $dex = $this->object->getDexNumber(new Dex(Dex::findValue($field)));

                return ($dex > -1) ? $dex : "";
                break;

            case "type":
                return $this->object->getFormattedType();
                break;
            case "type1":
            case "type2":
                return $this->object->getType(substr($field, -1) - 1);
                break;

            case "ability1":
            case "ability2":
            case "ability3":
                return $this->object->getAbility(substr($field, -1) - 1);
                break;

            case "hp":
            case "atk":
            case "def":
            case "spa":
            case "spd":
            case "speed":
                return $this->object->getBaseStat(Stat::fromName($field));
                break;

            case "total":
                return $this->object->getBaseStatTotal();
                break;

            case "generation":
            case "color":
            case "habitat":
            case "catchRate":
            case "baseExp":
            case "eggSteps":
            case "baseHappiness":
                $method = "get".ucfirst($field);

                return $this->object->{$method}();
                break;

            case "species":
                return $this->object->getSpecies(new Language(Language::English));
                break;

            case "height":
                $value     = null;
                $converter = new Converter("distance", "m", "ft");
                $heightM   = $this->object->getHeight();
                $heightFt  = round($converter->convert($heightM), 2);

                switch ($this->units) {
                    case "metric":
                        $value = "{$heightM}m";
                        break;

                    case "both":
                        $value = implode("/", [
                            bold("{$heightFt}ft"),
                            bold("{$heightM}m")
                        ]);
                        break;

                    case "imperial":
                    default:
                        $value = "{$heightFt}ft";
                        break;
                }

                return $value;
                break;

            case "weight":
                $value     = null;
                $converter = new Converter("mass", "kg", "lb");
                $weightKg  = $this->object->getWeight();
                $weightLb  = round($converter->convert($weightKg), 2);
                switch ($this->units) {
                    case "metric":
                        $value = "{$weightKg}kg";
                        break;

                    case "both":
                        $value = implode("/", [
                            bold("{$weightLb}lb"),
                            bold("{$weightKg}kg")
                        ]);
                        break;

                    case "imperial":
                    default:
                        $value = "{$weightLb}lb";
                        break;
                }

                return $value;
                break;

            case "male":
            case "female":
                $ratio = 100 * $this->object->getGenderRatio();
                if ($ratio < 0 || $ratio > 100)
                    $ratio = 0;
                if ($field == "female")
                    $ratio = 100 - $ratio;

                return "$ratio%";
                break;

            case "evs":
                $evYield = array_filter($this->object->getEVYield());
                $return  = [ ];

                foreach ($evYield as $index => $EV)
                    $return[] = bold("$EV ".Stat::findName($index));

                return implode(", ", $return);
                break;

            case "eggGroup":
                return implode("/", array_map("Utsubot\\bold", $this->object->getEggGroups()));
                break;

            case "preevolution":
                /** @var Evolution[] $evolutions */
                $evolutions = $this->object->getPreEvolutions();

                $return = [ ];
                foreach ($evolutions as $evolution)
                    $return[] = $evolution->format();

                return implode("; ", $return);
                break;

            case "evolution":
                /** @var Evolution[] $evolutions */
                $evolutions = $this->object->getEvolutions();

                $return = [ ];
                foreach ($evolutions as $evolution)
                    $return[] = $evolution->format();

                return implode("; ", $return);
                break;

        }

        return "";
    }

}
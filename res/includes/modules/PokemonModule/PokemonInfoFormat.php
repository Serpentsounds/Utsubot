<?php
/**
 * Utsubot - PokemonInfoFormat.php
 * User: Benjamin
 * Date: 04/12/2014
 */

namespace Pokemon;

class PokemonInfoFormat extends InfoFormat {

	/** @var $object Pokemon */
	protected $object;

	private $units = "imperial";
	protected static $class = "Pokemon";

	protected static $defaultFormat = <<<EOF
[^Name^: {english}/{japanese}] [^Dex^: #{national}] [^Type^: {type1}{/type2}] [^Abilities^: {ability1}{/ability2}{/ability3 (Hidden)}]
{[^Evolves from^: preevolution]} {[^Evolution^: evolution]}
[^Stats^: {hp} HP/{atk} Atk/{def} Def/{spa} SpA/{spd} SpD/{spe} Spe/{total} Total]
EOF;

	protected static $semanticFormat = <<<EOF
[^Name^: {english}/{japanese}] [^Species^: {species}] {[^Color^: color]} {[^Habitat^: habitat]} [^Gender^: {male} Male/{female} Female]
[^Height^: {height}] [^Weight^: {weight}] [^EVs^: {evs}] [^Catch Rate^: {catchRate}] [^Base Exp^: {baseExp}] [^Base Happiness^: {baseHappiness}] {[^Egg Group^: eggGroup]} {[^Egg Steps^: eggSteps]}
EOF;

	protected static $namesFormat = <<<EOF
[^English^: {english}] [^Japanese^: {japanese} ({roumaji}{/officialroumaji})] {[^Spanish^: spanish]} {[^Italian^: italian]} {[^Korean^: korean]} {[^Chinese^: chinese]}
{[^German^: german]} {[^French^: french]}
EOF;

	protected static $dexesFormat = <<<EOF
[^Name^: {english}/{japanese}] {[^National^: national]} {[^Kanto^: kanto]} {[^Johto^: johto]} {[^Hoenn^: hoenn]} {[^Sinnoh^: sinnoh]} {[^Ext. Sinnoh^: extsinnoh]} {[^New Johto^: newjohto]}
{[^Unova^: unova]} {[^New Unova^: newunova]} {[^Central Kalos^: centralkalos]} {[^Coastal Kalos^: coastalkalos]} {[^Mountain Kalos^: mountainkalos]}
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
{ability3 (Hidden)}
EOF;



	protected static $validFields = array(	"english", "japanese", "roumaji", "officialroumaji", "german", "french", "spanish", "korean", "chinese", "italian", "czech", "national",
											"generation", "type1", "type2", "ability1", "ability2", "ability3", "hp", "atk", "def", "spa", "spd", "spe", "total",
											"preevolutionmethod", "preevolution", "evolutionmethod", "evolution",
											"color", "species", "habitat", "male", "female", "height", "weight", "evs", "catchRate", "baseExp", "baseHappiness", "eggGroup", "eggSteps",
											"kanto", "johto", "hoenn", "sinnoh", "extsinnoh", "newjohto", "unova", "newunova", "centralkalos", "coastalkalos", "mountainkalos");

	public static function getSemanticFormat() {
		return self::$semanticFormat;
	}

	public static function getNamesFormat() {
		return self::$namesFormat;
	}

	public static function getDexesFormat() {
		return self::$dexesFormat;
	}

	public static function getCompareFormat() {
		return self::$compareFormat;
	}

	public function setUnits($units) {
		$units = strtolower($units);
		if ($units != "metric" && $units != "both" && $units != "imperial")
			return false;

		$this->units = $units;
		return true;
	}

	protected function formatField($field, $fieldValue) {
		if (substr($field, 0, 4) == "type")
			$fieldValue = self::bold(Types::colorType($fieldValue));

		//	Special case for evolutions, bold already added
		elseif (substr($field, -9) == "evolution" || $field == "evs" || $field == "eggGroup" || (($field == "height" || $field == "weight") && $this->units == "both")) {}

		//	Default case, just bold
		else
			$fieldValue = self::bold($fieldValue);

		return $fieldValue;
	}

	protected function getField($field) {
		switch ($field) {
			case "english":
			case "japanese":
			case "german":
			case "french":
			case "spanish":
			case "korean":
			case "italian":
			case "czech":
			case "chinese":
				return $this->object->getName($field);
			break;
			case "officialroumaji":
				return $this->object->getName("roumaji");
			break;
			case "roumaji":
				return ucfirst(self::romanizeKana($this->object->getName("japanese")));
			break;

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
			 	$dexes = array(
					'johto' 		=> "Original Johto",
					'sinnoh' 		=> "Original Sinnoh",
					'extsinnoh' 	=> "Extended Sinnoh",
					'unova' 		=> "Original Unova",
					'newjohto'		=> "Updated Johto",
					'newunova'		=> "Updated Unova",
					'centralkalos'	=> "Central Kalos",
					'coastalkalos'	=> "Coastal Kalos",
					'mountainkalos'	=> "Mountain Kalos"
				);

				if (isset($dexes[$field]))
					$field = $dexes[$field];
				else
					$field = ucfirst($field);

				return $this->object->getDexNumber($field);
			break;

			case "type1":
			case "type2":
				return $this->object->getType(substr($field, -1));
			break;

			case "ability1":
			case "ability2":
			case "ability3":
				return $this->object->getAbility(substr($field, -1));
			break;

			case "hp":
			case "atk":
			case "def":
			case "spa":
			case "spd":
			case "spe":
				return $this->object->getBaseStat($field);
			break;

			case "total":
				return array_sum($this->object->getBaseStat());
			break;

			case "generation":
			case "color":
			case "species":
			case "habitat":
			case "catchRate":
			case "baseExp":
			case "eggSteps":
			case "baseHappiness":
			   $method = "get" . ucfirst($field);
				return $this->object->{$method}();
			break;

			case "height":
				$value = null;
				switch ($this->units) {
					case "metric":
						$value = round($this->object->getHeight("m"), 2). "m";
					break;

					case "both":
						$value = implode("/", array(
							self::bold(round($this->object->getHeight("ft"), 2). "ft"),
							self::bold(round($this->object->getHeight("m"), 2). "m")
						));
					break;

					case "imperial":
					default:
						$value = round($this->object->getHeight("ft"), 2). "ft";
					break;
				}

				return $value;
			break;

			case "weight":
				$value = null;
				switch ($this->units) {
					case "metric":
						$value = round($this->object->getWeight("kg"), 2). "kg";
					break;

					case "both":
						$value = implode("/", array(
							self::bold(round($this->object->getWeight("lb"), 2). "lb"),
							self::bold(round($this->object->getWeight("kg"), 2). "kg")
						));
					break;

					case "imperial":
					default:
						$value = round($this->object->getWeight("lb"), 2). "lb";
					break;
				}

				return $value;
			break;

			case "male":
			case "female":
				$ratio = $this->object->getGenderRatio($field, true);
				if ($ratio < 0 || $ratio > 100)
					$ratio = 0;

				return "$ratio%";
			break;

			case "evs":
				$evYield = array_filter($this->object->getEvYield("all"));
				$return = array();

				foreach ($evYield as $stat => $EV) {
					$stat = ucwords($stat);
					if ($stat == "Hp")
						$stat = "HP";

					$return[] = self::bold("$EV $stat");
				}

				return implode(", ", $return);
			break;

			case "eggGroup":
				return implode("/", array_map(array("self", "bold"), $this->object->getEggGroup("all")));
			break;

			//	Stick names and methods together
			case "preevolution":
				$names = $this->object->getPreEvolution("all", 1);
				//	No pre-evolutions
				if (!$names)
					return "";

				$names = explode(";", $names);
				$methods = explode(";", $this->object->getPreEvolution("all", 2));

				//	Suffix the methods, bold only the pokemon names for readability
				foreach ($names as $key => $name)
					$names[$key] = self::bold($name). "/". $methods[$key];

				return implode("; ", $names);
			break;

			//	Stick names and methods together
			case "evolution":
				$names = $this->object->getEvolution("all", 1);
				//	No evolutions
				if (!$names)
					return "";

				$names = explode(";", $names);
				$methods = explode(";", $this->object->getEvolution("all", 2));

				//	Suffix the methods, bold only the pokemon names for readability
				foreach ($names as $key => $name)
					$names[$key] = self::bold($name). "/". $methods[$key];

				return implode("; ", $names);
			break;

		}
		return "";
	}


} 
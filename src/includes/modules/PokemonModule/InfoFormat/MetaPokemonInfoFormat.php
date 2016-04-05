<?php
/**
 * Utsubot - MetaPokemonInfoFormat.php
 * User: Benjamin
 * Date: 22/09/2015
 */

namespace Utsubot\Pokemon;
use function Utsubot\Japanese\romanizeKana;

class MetaPokemonInfoFormat extends InfoFormat {
	/** @var $object MetaTier */
	protected $object;
	/** @var $MetaPokemon MetaPokemon */
	protected $MetaPokemon;
	protected $usages;

	protected static $class = "MetaTier";
	protected static $validFields = array(	"english", "japanese", "roumaji", "officialroumaji", "german", "french", "spanish", "korean", "chinese", "italian", "czech",
										  	"abilities", "moves", "items", "spreads", "teammates", "counters", "abilities1", "abilities2", "abilities3", "moves1", "moves2", "moves3", "moves4", "moves5",
										  	"items1", "items2", "items3", "items4", "items5", "spreads1", "spreads2", "spreads3", "spreads4", "spreads5", "teammates1", "teammates2",
											"teammates3", "teammates4", "teammates5", "counters1", "counters2", "counters3", "counters4", "counters5", "tier", "pickPercent", "pickRank");

	protected static $defaultFormat = <<<EOF
[^Name^: {english}/{japanese}] [Tier: {tier} ({pickPercent}% picked, rank {pickRank}] [^Abilities^: {abls2}] [^Moves^: {moves4}] [^Items^: {items2}] [^Spreads^: {spreads2}]
[^Teammates^: {teammates2}] [^Counters^: {counters3}]
EOF;

	public function __construct($object, $pokemon) {
		parent::__construct($object);

		$MetaPokemon = $this->object->search($pokemon);
		if ($MetaPokemon === false)
			throw new InfoFormatException("MetaPokemon '$pokemon' could not be found.");
		$this->MetaPokemon = $MetaPokemon[0];
		$this->usages = $MetaPokemon[1];
	}

	public function getField($field) {
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
				return $this->MetaPokemon->getName($field);
			break;
			case "officialroumaji":
				return $this->MetaPokemon->getName("roumaji");
			break;
			case "roumaji":
				return ucfirst(romanizeKana($this->MetaPokemon->getName("japanese")));
			break;

			case "pickPercent":

			break;
			case "pickRank":
			break;
		}

		if (preg_match('/^(abilities|items|moves|spreads|teammates|counters)([1-5])$', $field, $match) && in_array($match[1]. $match[2], self::$validFields)) {
			$data = $this->object->get($match[1]);
			arsort($data);
			$data = array_slice($data, 0, 5);
			$sum = array_sum($data);
			foreach ($data as $key => &$val)
				$val = $val. " (". round($key/$sum, 2). "%)";

			return implode(", ", $data);
		}

		return "";
	}
}
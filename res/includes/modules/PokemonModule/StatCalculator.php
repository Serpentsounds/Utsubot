<?php
/**
 * Utsubot - StatCalculator.php
 * User: Benjamin
 * Date: 20/12/2014
 */

namespace Pokemon;

class StatCalculatorException extends \Exception {}

class StatCalculator {

	private $PokemonModule;

	public function __construct(PokemonModule $pokemonModule) {
		$this->PokemonModule = $pokemonModule;
	}

	public function baseToMax($base, $level = 100, $HP = false) {
		return $this->calculateStat($base, 31, 252, $level, 1.1, $HP);
	}

	public function maxToBase($max, $level = 100, $HP = false) {
		return $this->calculateBase($max, 31, 252, $level, 1.1, $HP);
	}

	public function calculateStat($base, $IV, $EV, $level, $natureModifier = 1.0, $HP = false) {
		self::validateParameters($base, $IV, $EV, $level, $natureModifier);

		if ($HP)
			$result = floor((($IV + (2 * $base) + floor($EV/4) + 100) * $level) / 100 + 10);
		else
			$result = floor(floor(((($IV + (2 * $base) + floor($EV/4)) * $level) / 100 + 5)) * $natureModifier);

		return $result;
	}

	public function calculateBase($stat, $IV, $EV, $level, $natureModifier = 1.0, $HP = false) {
		self::validateParameters($stat, $IV, $EV, $level, $natureModifier);

		if ($HP)
			$base = ceil((($stat - 10) * (100/$level) - 100 - $IV - floor($EV/4)) / 2);
		else
			$base = ceil(((ceil($stat/$natureModifier) - 5) * (100/$level) - $IV - floor($EV/4)) / 2);

		return $base;
	}

	private function validateParameters($stat, $IV, $EV, $level, $natureModifier) {
		if (!is_int($stat) && $stat !== null)
			throw new StatCalculatorException("Invalid stat value: $stat.");

		if ((!is_int($IV) || $IV < 0 || $IV > 31) && $IV !== null)
			throw new StatCalculatorException("Invalid IV value: $IV.");

		if ((!is_int($EV) || $EV < 0 || $EV > 255) && $EV !== null)
			throw new StatCalculatorException("Invalid EV value: $EV.");

		if ((!is_int($level) || $level < 0 || $level > 255) && $level !== null)
			throw new StatCalculatorException("Invalid level value: $level.");

		if ((!is_numeric($natureModifier) || ($natureModifier != 0.9 && $natureModifier != 1 && $natureModifier != 1.1)) && $natureModifier !== null)
			throw new StatCalculatorException("Invalid nature modifier value: $natureModifier.");
	}

	public function parseIVStatParameters($parameters) {
		//	Parse first words into pokemon
		$pokemon = $this->PokemonModule->getFirstPokemon($parameters);

		//	Shave pokemon name off front of parameters
		$parameters = array_slice($parameters, substr_count($pokemon->getName(), " ") + 1);

		if (count($parameters) < 8)
			throw new \ModuleException("Not enough parameters.");

		$level = array_shift($parameters);
		if (!($level >= 1 && $level <= 100))
			throw new \ModuleException("Invalid level.");
		$level = intval($level);

		//	Validate Nature object can be fetched
		$natureName = array_shift($parameters);
		if (!($natureManager = $this->PokemonModule->getManager("Nature")) || !($natureManager instanceof NatureManager) ||
			!($nature = $natureManager->get($natureName)) || !($nature instanceof Nature))
			throw new \ModuleException("Invalid nature.");

		//	Initialize nature information
		$natureMultipliers = array('HP' => 1, 'Attack' => 1, 'Defense' => 1, 'Special Attack' => 1, 'Special Defense' => 1, 'Speed' => 1);
		$increases = $nature->getIncreases();
		$decreases = $nature->getDecreases();

		//	Update nature multipliers
		if (isset($natureMultipliers[$increases]))
			$natureMultipliers[$increases] = 1.1;
		if (isset($natureMultipliers[$decreases]))
			$natureMultipliers[$decreases] = 0.9;

		//	Separate and normalize arrays
		$statNames = array_keys($natureMultipliers);
		$natureMultipliers = array_values($natureMultipliers);

		//	Check each parameter individually
		$statValues = $EVs = array(0, 0, 0, 0, 0, 0);
		for ($i = 0; $i <= 5; $i++) {

			//	Effort values specified
			if (strpos($parameters[$i], ':') !== FALSE) {
				list($stat, $EV) = explode(':', $parameters[$i]);
				//	Stat and EV minimum values
				if (!($stat >= 0 && $EV >= 0 && $EV <= 255))
					throw new \ModuleException("Invalid stat or EV parameter.");


				$statValues[$i] = intval($stat);
				$EVs[$i] = intval($EV);
			}
			//	No effort value specified
			else {
				//	Stat minimum value
				if (!($parameters[$i] >= 0))
					throw new \ModuleException("Invalid stat or EV parameter.");

				$statValues[$i] = intval($parameters[$i]);
			}
		}

		return array(
			'pokemon'			=> $pokemon,
			'level'				=> $level,
			'increases'			=> $increases,
			'decreases'			=> $decreases,
			'natureMultipliers'	=> $natureMultipliers,
			'statNames'			=> $statNames,
			'statValues'		=> $statValues,
			'EVs'				=> $EVs
		);
	}

	public function parseBaseMaxParameters($parameters, $command) {
		if (!count($parameters))
			throw new StatCalculatorException("No base given.");

		//	Stat must be a positive integer
		if (!is_numeric($parameters[0]) || ($stat = intval($parameters[0])) != $parameters[0] || $stat < 0)
			throw new StatCalculatorException("Invalid stat value.");

		//	Optional HP switch
		$HP = false;
		if (isset($parameters[1]) && $parameters[1] == "-hp")
			$HP = true;

		$level = 0;
		$from = $to = "";
		switch ($command) {
			case "b2m":
			case "btom":
			case "basetomax":
				$level = 100;
				$from = "base";
				$to = "max";
			break;

			case "m2b":
			case "mtob":
			case "maxtobase":
				$level = 100;
				$from = "max";
				$to = "base";
			break;

			case "b2m50":
			case "btom50":
			case "basetomax50":
				$level = 50;
				$from = "base";
				$to = "max";
			break;

			case "m2b50":
			case "mtob50":
			case "maxtobase50":
				$level = 50;
				$from = "max";
				$to = "base";
			break;

			default:
				throw new StatCalculatorException("Invalid command.");
			break;
		}

		return array(
			'stat'	=> $stat,
			'level'	=> $level,
			'from'	=> $from,
			'to'	=> $to,
			'hp'	=> $HP
		);
	}
} 
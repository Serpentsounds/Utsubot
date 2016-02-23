<?php
/**
 * MEGASSBOT - Ability.php
 * User: Benjamin
 * Date: 06/11/14
 */

namespace Pokemon;

class Ability {
	use AbilityItemCommon;

	public function __construct($args) {
		if (is_numeric($args))
			$this->setId($args);

		//	Array of properties passed, parse to construct
		elseif (is_array($args)) {
			foreach ($args as $key => $val) {
				switch ($key) {
					case	"id":			$this->setId($val);				break;
					case	"generation":	$this->setGeneration($val);		break;
					case	"names":		$this->setName($val);			break;
					case	"effect":		$this->setEffect($val);			break;
					case	"short":		case	"shortEffect":
						$this->setShortEffect($val);
					break;
					case	"text":			case	"flavorText":
						$this->setText($val);
					break;
				}
			}
		}

	}
}
<?php
/**
 * Utsubot - EasySetters.php
 * User: Benjamin
 * Date: 15/11/14
 */

trait EasySetters {

	private function setProperty($property, $value, callable $check = null) {
		if (!property_exists($this, $property))
			return false;

		if ($check && !call_user_func($check, $value))
			return false;

		$this->{$property} = $value;
		return true;
	}

	private function setPropertyArray($property, $array, callable $check = null) {
		if (!property_exists($this, $property))
			return false;

		if (!is_array($array))
			$array = array($array);

		$return = true;
		$this->{$property} = array();
		foreach ($array as $element) {
			if ($check && !call_user_func($check, $element))
				$return = false;
			else
				array_push($this->{$property}, $element);
		}

		return $return;
	}

}
<?php
/**
 * MEGASSBOT - Pokedex.php
 * User: Benjamin
 * Date: 27/10/14
 */

namespace Utsubot\Pokemon;


class PokedexException extends \Exception {}

class Pokedex {

	//	Default language (9 for English)
	const DEFAULT_LANGUAGE = 9;

	private $interface;

	/**
	 * Create a new Pokedex
	 *
	 * @param VeekunDatabaseInterface $interface A valid link to Veekun's pokemon database
	 */
	public function __construct(VeekunDatabaseInterface $interface) {
		$this->interface = $interface;
	}

	/**
	 * Search for the most recent pokedex entry, as defined in order of preference in $versionPreference
	 *
	 * @param string|int $pokemon The name or dex number of a pokemon
	 * @param string|int $language The name or id of a language
	 * @return string|bool Returns the pokedex entry text, or false if none is available
	 * @throws PokedexException If a parameter is invalid
	 */
	public function getLatestDexEntry($pokemon, $language = self::DEFAULT_LANGUAGE) {
		$versionPreference = array("x", "y", "b2", "w2", "b", "w", "hg", "ss", "pt", "d", "p", "fr", "lg", "e", "r", "s", "g", "sv", "y", "rd", "bl");
		foreach ($versionPreference as $version) {
			if ($entry = $this->getDexEntry($pokemon, $version, $language))
				return $entry;
		}

		return false;
	}

	/**
	 * Search for a pokedex entry
	 *
	 * @param string|int $pokemon The name or dex number of a pokemon
	 * @param string|int $version The name or id of a version
	 * @param string|int $language The name or id of a language
	 * @return string|bool Returns the pokedex entry text, or false if none is available
	 * @throws PokedexException If a parameter is invalid
	 */
	public function getDexEntry($pokemon, $version, $language = self::DEFAULT_LANGUAGE) {
		//	Parse names of pokemon, versions, and languages into IDs, or throw an exception if none can be found
		if (!is_int($pokemon) && !($pokemon = self::getPokemonId($pokemon)))
			throw new PokedexException("Pokedex::getPokemonId: Invalid pokemon.");
		if (!is_int($version) && !($version = self::getVersionId($version)))
			throw new PokedexException("Pokedex::getVersionId: Invalid version.");
		if (!is_int($language) && !($language = self::getLanguageId($language)))
			throw new PokedexException("Pokedex::getLanguageId: Invalid language.");

		//	Search for the relevant entry
		$query = "SELECT flavor_text FROM pokemon_species_flavor_text WHERE species_id = ? AND version_id = ? AND language_id = ? LIMIT 1";
		$params = array($pokemon, $version, $language);
		$results = $this->interface->query($query, $params);
		if ($results[0])
			//	Clear out special formatting characters before returning
			return preg_replace("/\s+/", " ", $results[0]['flavor_text']);

		//	No entry available
		return false;
	}

	/**
	 * Gets a pokemon id (dex number) from a name/id string
	 *
	 * @param string $search The name or dex number as a string
	 * @return int|bool Returns the dex number, or false on failure
	 */
	private function getPokemonId($search) {
		//	Search for matches against the pokemon names in any language
		$query = "SELECT pokemon_species_id FROM pokemon_species_names WHERE `name` LIKE ? OR pokemon_species_id = ? LIMIT 1";
		$params = array($search, $search);
		$results = $this->interface->query($query, $params);
		if ($results[0])
			return $results[0]['pokemon_species_id'];

		//	No matches
		return false;
	}

	/**
	 * Gets a version id from a name/id string
	 *
	 * @param string $search The name as a string
	 * @return int|bool Returns the id, or false on failure
	 */
	public function getVersionId($search) {
		$search = strtolower($search);

		//	Parse abbreviations, X and Y omitted because they don't change
		$abbreviations = array(	'rd'	=> "red",		'bl'	=> "blue",		'ye'	=> "yellow",
								 'g'	=> "gold",		'sv'	=> "silver",	'c'		=> "crystal",
								 'r'	=> "ruby",		's'		=> "sapphire",	'e'		=> "emerald",	'fr'	=> "firered",	'lg'	=> "leafgreen",
								 'd'	=> "diamond",	'p'		=> "pearl",		'pt'	=> "platinum",	'hg'	=> "heartgold",	'ss'	=> "soulsilver",
								 'b'	=> "black",		'w'		=> "white",		'b2'	=> "black2",	'w2'	=> "white",
								 'or'	=> "omegaruby",	'as'	=> "alphasapphire");
		if (isset($abbreviations[$search]))
			$search = $abbreviations[$search];

		/*	Search for matches against the version names in any language
			Additional space-less check for multi-word versions	*/
		$query = "SELECT version_id FROM version_names WHERE `name` LIKE ? OR REPLACE(`name`, ' ', '') LIKE ? OR version_id = ? LIMIT 1";
		$params = array($search, $search, $search);
		$results = $this->interface->query($query, $params);
		if (!empty($results[0]))
			return $results[0]['version_id'];

		//	Search for matches against identifiers
		$query = "SELECT id FROM versions WHERE identifier LIKE ? LIMIT 1";
		$params = array($search);
		$results = $this->interface->query($query, $params);
		if (!empty($results[0]))
			return $results[0]['id'];

		//	No matches
		return false;
	}

	/**
	 * Gets a language id from a name/id string
	 *
	 * @param string $search The name as a string
	 * @return int|bool Returns the id, or false on failure
	 */
	private function getLanguageId($search) {
		//	Search for matches against the language names themselves in any language
		$query = "SELECT language_id FROM language_names WHERE `name` LIKE ? OR language_id = ? LIMIT 1";
		$params = array($search, $search);
		$results = $this->interface->query($query, $params);
		if ($results[0])
			return $results[0]['language_id'];

		//	Search for matches against abbreviations
		$query = "SELECT id FROM languages WHERE identifier LIKE ? OR iso3166 LIKE ? ORDER BY id ASC LIMIT 1";
		$params = array($search, $search);
		$results = $this->interface->query($query, $params);
		if ($results[0])
			return $results[0]['id'];

		//	No matches
		return false;
	}
}
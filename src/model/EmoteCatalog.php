<?php

declare(strict_types=1);

namespace NhanAZ\NPC\model;

use JsonException;
use function count;
use function json_decode;
use function preg_match;
use function preg_replace;
use function preg_split;
use function str_contains;
use function strtolower;
use function trim;

final class EmoteCatalog{
	/** @var array<string, string> */
	private const ALIASES = [
		"wave" => "4c8ae710-df2e-47cd-814d-cc7bf21a3d67",
		"waving" => "4c8ae710-df2e-47cd-814d-cc7bf21a3d67",
		"simple_clap" => "9a469a61-c83b-4ba9-b507-bdbe64430582",
		"clap" => "9a469a61-c83b-4ba9-b507-bdbe64430582",
		"over_there" => "ce5c0300-7f03-455d-aaf1-352e4927b54d",
		"over_here" => "71721c51-b7d1-46b1-b7ea-eb4c4126c3db",
		"bow" => "ddfa6f0e-88ca-46de-b189-2bd5b18e96a0",
		"saluting" => "1ea5f771-827d-408d-ae8e-1af00d7aa416",
		"thinking" => "20bcb500-af82-4c2f-9239-e78191c61375"
	];

	/** @var array<string, string> */
	private static array $catalog = self::ALIASES;

	/** @var array<string, string> */
	private static array $displayNames = [];

	/** @var list<array{title: string, uuid: string}> */
	private static array $entries = [];

	private function __construct(){
	}

	/**
	 * Loads the generated title-to-UUID catalog bundled in resources/emotes.jsonl.
	 * Duplicate Marketplace titles are kept addressable through their UUIDs; the
	 * first title wins for the convenient name lookup.
	 */
	public static function load(string $contents) : int{
		$loaded = 0;
		$lines = preg_split('/\R/u', $contents, -1, PREG_SPLIT_NO_EMPTY);
		if($lines === false){
			return 0;
		}
		foreach($lines as $line){
			try{
				$entry = json_decode(trim($line), true, 512, JSON_THROW_ON_ERROR);
			}catch(JsonException){
				continue;
			}
			if(!is_array($entry) || !is_string($entry["title"] ?? null) || !is_string($entry["uuid"] ?? null)){
				continue;
			}
			$uuid = strtolower(trim($entry["uuid"]));
			$alias = self::normalizeAlias($entry["title"]);
			if($alias === "" || !self::isUuid($uuid)){
				continue;
			}
			self::$entries[] = ["title" => $entry["title"], "uuid" => $uuid];
			if(!isset(self::$catalog[$alias])){
				self::$catalog[$alias] = $uuid;
				self::$displayNames[$alias] = $entry["title"];
			}
			++$loaded;
		}
		return $loaded;
	}

	public static function count() : int{
		return self::$entries === [] ? count(self::$catalog) : count(self::$entries);
	}

	/**
	 * Returns the official thumbnail URL for a catalog entry.
	 *
	 * The upstream catalog stores one 128x128 PNG per UUID. The form uses a URL
	 * icon, so the images remain outside the plugin PHAR and are fetched by the
	 * Bedrock client only when a player opens an emote menu.
	 */
	public static function imageUrl(string $value) : ?string{
		$uuid = self::resolve($value);
		return $uuid === null ? null : "https://raw.githubusercontent.com/TwistedAsylumMC/Bedrock-Emotes/main/images/" . $uuid . ".png";
	}

	/** @return list<array{title: string, uuid: string}> */
	public static function search(string $query = "", int $limit = 25, int $offset = 0) : array{
		$query = self::normalizeAlias($query);
		$offset = max(0, $offset);
		$matches = [];
		$skipped = 0;
		if(self::$entries !== []){
			foreach(self::$entries as $entry){
				if($query !== "" && !str_contains(self::normalizeAlias($entry["title"]), $query)){
					continue;
				}
				if($skipped < $offset){
					++$skipped;
					continue;
				}
				$matches[] = $entry;
				if(count($matches) >= max(1, $limit)){
					break;
				}
			}
			return $matches;
		}
		foreach(self::$catalog as $alias => $uuid){
			if($query !== "" && !str_contains($alias, $query)){
				continue;
			}
			if($skipped < $offset){
				++$skipped;
				continue;
			}
			$matches[] = [
				"title" => self::$displayNames[$alias] ?? $alias,
				"uuid" => $uuid
			];
			if(count($matches) >= max(1, $limit)){
				break;
			}
		}
		return $matches;
	}

	/**
	 * Resolves a friendly name or a raw Bedrock emote UUID.
	 *
	 * The UUID path intentionally remains open so Marketplace/custom emotes do
	 * not require a plugin release just to become usable by an NPC.
	 */
	public static function resolve(string $value) : ?string{
		$raw = trim($value);
		$alias = self::normalizeAlias($raw);
		if(isset(self::$catalog[$alias])){
			return self::$catalog[$alias];
		}
		$uuid = strtolower($raw);
		return self::isUuid($uuid) ? $uuid : null;
	}

	private static function normalizeAlias(string $value) : string{
		$value = strtolower(trim($value));
		$value = (string) preg_replace('/[^a-z0-9]+/', "_", $value);
		return trim($value, "_");
	}

	private static function isUuid(string $value) : bool{
		// Bedrock emote IDs look like UUIDs but are not guaranteed to follow
		// RFC 4122 version/variant bits (the Marketplace catalog contains IDs
		// such as f8721633-b6e8-b55c-fe38-984f52d756bc).
		return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $value) === 1;
	}
}

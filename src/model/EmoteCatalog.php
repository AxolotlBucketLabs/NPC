<?php

declare(strict_types=1);

namespace NhanAZ\NPC\model;

use function preg_match;
use function str_replace;
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

	private function __construct(){
	}

	/**
	 * Resolves a friendly name or a raw Bedrock emote UUID.
	 *
	 * The UUID path intentionally remains open so Marketplace/custom emotes do
	 * not require a plugin release just to become usable by an NPC.
	 */
	public static function resolve(string $value) : ?string{
		$raw = trim($value);
		$alias = strtolower(str_replace(["-", " "], "_", $raw));
		if(isset(self::ALIASES[$alias])){
			return self::ALIASES[$alias];
		}
		$uuid = strtolower($raw);
		return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $uuid) === 1 ? $uuid : null;
	}
}

<?php

declare(strict_types=1);

namespace AxolotlBucketLabs\NPC\model;

use function strtolower;

/**
 * Stable client-side visual variants for vanilla actors.
 *
 * The values mirror Bedrock's vanilla entity metadata.  Actor properties are
 * kept separate from metadata because Armadillo's rolled/unrolled state is an
 * enum property, while Axolotl colours and most other variants use
 * minecraft:variant.
 */
final class VanillaVariantCatalog{
	private const VARIANT_METADATA = 2;
	private const COLOR_METADATA = 3;
	private const MARK_VARIANT_METADATA = 43;
	private const COLOR_2_METADATA = 82;
	/**
	 * @var array<string, array<string, array{label: string, metadata: array<int, int>, properties: array<int, int>}>>
	 */
	private static array $variants = [
		"armadillo" => [
			"unrolled" => ["label" => "Unrolled", "metadata" => [], "properties" => [0 => 0]],
			"rolled_up" => ["label" => "Rolled up", "metadata" => [], "properties" => [0 => 1]]
		],
		"axolotl" => [
			"lucy" => ["label" => "Lucy (pink)", "metadata" => [self::VARIANT_METADATA => 0], "properties" => []],
			"cyan" => ["label" => "Cyan", "metadata" => [self::VARIANT_METADATA => 1], "properties" => []],
			"gold" => ["label" => "Gold", "metadata" => [self::VARIANT_METADATA => 2], "properties" => []],
			"wild" => ["label" => "Wild (brown)", "metadata" => [self::VARIANT_METADATA => 3], "properties" => []],
			"blue" => ["label" => "Blue", "metadata" => [self::VARIANT_METADATA => 4], "properties" => []]
		],
		"cat" => [
			"white" => ["label" => "White", "metadata" => [self::VARIANT_METADATA => 0], "properties" => []],
			"tuxedo" => ["label" => "Tuxedo", "metadata" => [self::VARIANT_METADATA => 1], "properties" => []],
			"red" => ["label" => "Red", "metadata" => [self::VARIANT_METADATA => 2], "properties" => []],
			"siamese" => ["label" => "Siamese", "metadata" => [self::VARIANT_METADATA => 3], "properties" => []],
			"british" => ["label" => "British shorthair", "metadata" => [self::VARIANT_METADATA => 4], "properties" => []],
			"calico" => ["label" => "Calico", "metadata" => [self::VARIANT_METADATA => 5], "properties" => []],
			"persian" => ["label" => "Persian", "metadata" => [self::VARIANT_METADATA => 6], "properties" => []],
			"ragdoll" => ["label" => "Ragdoll", "metadata" => [self::VARIANT_METADATA => 7], "properties" => []],
			"tabby" => ["label" => "Tabby", "metadata" => [self::VARIANT_METADATA => 8], "properties" => []],
			"black" => ["label" => "Black", "metadata" => [self::VARIANT_METADATA => 9], "properties" => []],
			"jellie" => ["label" => "Jellie", "metadata" => [self::VARIANT_METADATA => 10], "properties" => []]
		],
		"fox" => [
			"red" => ["label" => "Red fox", "metadata" => [self::VARIANT_METADATA => 0], "properties" => []],
			"arctic" => ["label" => "Arctic fox", "metadata" => [self::VARIANT_METADATA => 1], "properties" => []]
		],
		"frog" => [
			"temperate" => ["label" => "Temperate", "metadata" => [self::VARIANT_METADATA => 0], "properties" => []],
			"cold" => ["label" => "Cold", "metadata" => [self::VARIANT_METADATA => 1], "properties" => []],
			"warm" => ["label" => "Warm", "metadata" => [self::VARIANT_METADATA => 2], "properties" => []]
		],
		"llama" => [
			"creamy" => ["label" => "Creamy", "metadata" => [self::VARIANT_METADATA => 0], "properties" => []],
			"white" => ["label" => "White", "metadata" => [self::VARIANT_METADATA => 1], "properties" => []],
			"brown" => ["label" => "Brown", "metadata" => [self::VARIANT_METADATA => 2], "properties" => []],
			"gray" => ["label" => "Gray", "metadata" => [self::VARIANT_METADATA => 3], "properties" => []]
		],
		"mooshroom" => [
			"red" => ["label" => "Red", "metadata" => [self::VARIANT_METADATA => 0], "properties" => []],
			"brown" => ["label" => "Brown", "metadata" => [self::VARIANT_METADATA => 1], "properties" => []]
		],
		"panda" => [
			"normal" => ["label" => "Normal", "metadata" => [self::VARIANT_METADATA => 0], "properties" => []],
			"lazy" => ["label" => "Lazy", "metadata" => [self::VARIANT_METADATA => 1], "properties" => []],
			"worried" => ["label" => "Worried", "metadata" => [self::VARIANT_METADATA => 2], "properties" => []],
			"playful" => ["label" => "Playful", "metadata" => [self::VARIANT_METADATA => 3], "properties" => []],
			"brown" => ["label" => "Brown", "metadata" => [self::VARIANT_METADATA => 4], "properties" => []],
			"weak" => ["label" => "Weak", "metadata" => [self::VARIANT_METADATA => 5], "properties" => []],
			"aggressive" => ["label" => "Aggressive", "metadata" => [self::VARIANT_METADATA => 6], "properties" => []]
		],
		"parrot" => [
			"red" => ["label" => "Red", "metadata" => [self::VARIANT_METADATA => 0], "properties" => []],
			"blue" => ["label" => "Blue", "metadata" => [self::VARIANT_METADATA => 1], "properties" => []],
			"green" => ["label" => "Green", "metadata" => [self::VARIANT_METADATA => 2], "properties" => []],
			"cyan" => ["label" => "Cyan", "metadata" => [self::VARIANT_METADATA => 3], "properties" => []],
			"gray" => ["label" => "Gray", "metadata" => [self::VARIANT_METADATA => 4], "properties" => []]
		],
		"rabbit" => [
			"brown" => ["label" => "Brown", "metadata" => [self::VARIANT_METADATA => 0], "properties" => []],
			"white" => ["label" => "White", "metadata" => [self::VARIANT_METADATA => 1], "properties" => []],
			"black" => ["label" => "Black", "metadata" => [self::VARIANT_METADATA => 2], "properties" => []],
			"splotched" => ["label" => "Splotched", "metadata" => [self::VARIANT_METADATA => 3], "properties" => []],
			"desert" => ["label" => "Desert", "metadata" => [self::VARIANT_METADATA => 4], "properties" => []],
			"salt" => ["label" => "Salt", "metadata" => [self::VARIANT_METADATA => 5], "properties" => []]
		],
		"sheep" => [
			"white" => ["label" => "White", "metadata" => [self::COLOR_METADATA => 0], "properties" => []],
			"orange" => ["label" => "Orange", "metadata" => [self::COLOR_METADATA => 1], "properties" => []],
			"magenta" => ["label" => "Magenta", "metadata" => [self::COLOR_METADATA => 2], "properties" => []],
			"light_blue" => ["label" => "Light blue", "metadata" => [self::COLOR_METADATA => 3], "properties" => []],
			"yellow" => ["label" => "Yellow", "metadata" => [self::COLOR_METADATA => 4], "properties" => []],
			"lime" => ["label" => "Lime", "metadata" => [self::COLOR_METADATA => 5], "properties" => []],
			"pink" => ["label" => "Pink", "metadata" => [self::COLOR_METADATA => 6], "properties" => []],
			"gray" => ["label" => "Gray", "metadata" => [self::COLOR_METADATA => 7], "properties" => []],
			"light_gray" => ["label" => "Light gray", "metadata" => [self::COLOR_METADATA => 8], "properties" => []],
			"cyan" => ["label" => "Cyan", "metadata" => [self::COLOR_METADATA => 9], "properties" => []],
			"purple" => ["label" => "Purple", "metadata" => [self::COLOR_METADATA => 10], "properties" => []],
			"blue" => ["label" => "Blue", "metadata" => [self::COLOR_METADATA => 11], "properties" => []],
			"brown" => ["label" => "Brown", "metadata" => [self::COLOR_METADATA => 12], "properties" => []],
			"green" => ["label" => "Green", "metadata" => [self::COLOR_METADATA => 13], "properties" => []],
			"red" => ["label" => "Red", "metadata" => [self::COLOR_METADATA => 14], "properties" => []],
			"black" => ["label" => "Black", "metadata" => [self::COLOR_METADATA => 15], "properties" => []]
		],
		"shulker" => [
			"black" => ["label" => "Black", "metadata" => [self::VARIANT_METADATA => 0], "properties" => []],
			"red" => ["label" => "Red", "metadata" => [self::VARIANT_METADATA => 1], "properties" => []],
			"green" => ["label" => "Green", "metadata" => [self::VARIANT_METADATA => 2], "properties" => []],
			"brown" => ["label" => "Brown", "metadata" => [self::VARIANT_METADATA => 3], "properties" => []],
			"blue" => ["label" => "Blue", "metadata" => [self::VARIANT_METADATA => 4], "properties" => []],
			"purple" => ["label" => "Purple", "metadata" => [self::VARIANT_METADATA => 5], "properties" => []],
			"cyan" => ["label" => "Cyan", "metadata" => [self::VARIANT_METADATA => 6], "properties" => []],
			"silver" => ["label" => "Silver", "metadata" => [self::VARIANT_METADATA => 7], "properties" => []],
			"gray" => ["label" => "Gray", "metadata" => [self::VARIANT_METADATA => 8], "properties" => []],
			"pink" => ["label" => "Pink", "metadata" => [self::VARIANT_METADATA => 9], "properties" => []],
			"lime" => ["label" => "Lime", "metadata" => [self::VARIANT_METADATA => 10], "properties" => []],
			"yellow" => ["label" => "Yellow", "metadata" => [self::VARIANT_METADATA => 11], "properties" => []],
			"light_blue" => ["label" => "Light blue", "metadata" => [self::VARIANT_METADATA => 12], "properties" => []],
			"magenta" => ["label" => "Magenta", "metadata" => [self::VARIANT_METADATA => 13], "properties" => []],
			"orange" => ["label" => "Orange", "metadata" => [self::VARIANT_METADATA => 14], "properties" => []],
			"white" => ["label" => "White", "metadata" => [self::VARIANT_METADATA => 15], "properties" => []],
			"undyed" => ["label" => "Undyed", "metadata" => [self::VARIANT_METADATA => 16], "properties" => []]
		],
		"wolf" => [
			"pale" => ["label" => "Pale", "metadata" => [self::VARIANT_METADATA => 0], "properties" => []],
			"ashen" => ["label" => "Ashen", "metadata" => [self::VARIANT_METADATA => 1], "properties" => []],
			"black" => ["label" => "Black", "metadata" => [self::VARIANT_METADATA => 2], "properties" => []],
			"chestnut" => ["label" => "Chestnut", "metadata" => [self::VARIANT_METADATA => 3], "properties" => []],
			"rusty" => ["label" => "Rusty", "metadata" => [self::VARIANT_METADATA => 4], "properties" => []],
			"snowy" => ["label" => "Snowy", "metadata" => [self::VARIANT_METADATA => 5], "properties" => []],
			"spotted" => ["label" => "Spotted", "metadata" => [self::VARIANT_METADATA => 6], "properties" => []],
			"striped" => ["label" => "Striped", "metadata" => [self::VARIANT_METADATA => 7], "properties" => []],
			"woods" => ["label" => "Woods", "metadata" => [self::VARIANT_METADATA => 8], "properties" => []]
		],
		"tropicalfish" => [
			"clownfish" => ["label" => "Clownfish", "metadata" => [self::VARIANT_METADATA => 0, self::COLOR_METADATA => 1, self::COLOR_2_METADATA => 0, self::MARK_VARIANT_METADATA => 0], "properties" => []],
			"black_tang" => ["label" => "Black tang", "metadata" => [self::VARIANT_METADATA => 1, self::COLOR_METADATA => 7, self::COLOR_2_METADATA => 7, self::MARK_VARIANT_METADATA => 0], "properties" => []],
			"blue_dory" => ["label" => "Blue dory", "metadata" => [self::VARIANT_METADATA => 0, self::COLOR_METADATA => 7, self::COLOR_2_METADATA => 3, self::MARK_VARIANT_METADATA => 1], "properties" => []],
			"butterfly_fish" => ["label" => "Butterfly fish", "metadata" => [self::VARIANT_METADATA => 0, self::COLOR_METADATA => 0, self::COLOR_2_METADATA => 7, self::MARK_VARIANT_METADATA => 4], "properties" => []],
			"cichlid" => ["label" => "Cichlid", "metadata" => [self::VARIANT_METADATA => 0, self::COLOR_METADATA => 11, self::COLOR_2_METADATA => 7, self::MARK_VARIANT_METADATA => 1], "properties" => []],
			"red_snapper" => ["label" => "Red snapper", "metadata" => [self::VARIANT_METADATA => 1, self::COLOR_METADATA => 14, self::COLOR_2_METADATA => 0, self::MARK_VARIANT_METADATA => 3], "properties" => []],
			"yellow_tang" => ["label" => "Yellow tang", "metadata" => [self::VARIANT_METADATA => 1, self::COLOR_METADATA => 4, self::COLOR_2_METADATA => 4, self::MARK_VARIANT_METADATA => 1], "properties" => []],
			"triggerfish" => ["label" => "Triggerfish", "metadata" => [self::VARIANT_METADATA => 0, self::COLOR_METADATA => 7, self::COLOR_2_METADATA => 0, self::MARK_VARIANT_METADATA => 1], "properties" => []]
		]
	];

	/** @return array<string, array{label: string, metadata: array<int, int>, properties: array<int, int>}> */
	public static function options(string $entityType) : array{
		$type = VanillaMobCatalog::resolve($entityType) ?? strtolower($entityType);
		return self::$variants[$type] ?? [];
	}

	public static function hasVariants(string $entityType) : bool{
		return self::options($entityType) !== [];
	}

	/** @return array{label: string, metadata: array<int, int>, properties: array<int, int>}|null */
	public static function resolve(string $entityType, string $variant) : ?array{
		$variant = strtolower(trim($variant));
		return self::options($entityType)[$variant] ?? null;
	}

	public static function default(string $entityType) : string{
		$type = VanillaMobCatalog::resolve($entityType) ?? strtolower($entityType);
		$options = self::options($entityType);
		if($type === "shulker" && isset($options["undyed"])){
			return "undyed";
		}
		return $options === [] ? "" : (string) array_key_first($options);
	}

	public static function label(string $entityType, string $variant) : string{
		return self::resolve($entityType, $variant)["label"] ?? "Default";
	}
}

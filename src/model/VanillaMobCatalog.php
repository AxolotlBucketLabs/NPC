<?php

declare(strict_types=1);

namespace NhanAZ\NPC\model;

use InvalidArgumentException;
use function array_keys;
use function array_merge;
use function asort;
use function ctype_lower;
use function preg_match;
use function strtolower;
use function trim;

/**
 * Network-backed Vanilla actor catalog.
 *
 * NPC deliberately uses a passive visual actor instead of instantiating the
 * server's full mob AI. This keeps definitions persistent and makes it safe
 * to display every Bedrock mob, including mobs which PocketMine does not yet
 * implement as a server-side entity.
 */
final class VanillaMobCatalog{
	/** @var array<string, string> canonical type => Bedrock network identifier */
	private static array $types = [
		"falling_block" => "minecraft:falling_block",
		"boat" => "minecraft:boat",
		"minecart" => "minecraft:minecart",
		"primed_tnt" => "minecraft:tnt",
		"allay" => "minecraft:allay",
		"armadillo" => "minecraft:armadillo",
		"axolotl" => "minecraft:axolotl",
		"bat" => "minecraft:bat",
		"bee" => "minecraft:bee",
		"blaze" => "minecraft:blaze",
		"bogged" => "minecraft:bogged",
		"breeze" => "minecraft:breeze",
		"camel" => "minecraft:camel",
		"camel_husk" => "minecraft:camel_husk",
		"cat" => "minecraft:cat",
		"cave_spider" => "minecraft:cave_spider",
		"chicken" => "minecraft:chicken",
		"cod" => "minecraft:cod",
		"copper_golem" => "minecraft:copper_golem",
		"cow" => "minecraft:cow",
		"creaking" => "minecraft:creaking",
		"creeper" => "minecraft:creeper",
		"dolphin" => "minecraft:dolphin",
		"donkey" => "minecraft:donkey",
		"drowned" => "minecraft:drowned",
		"elder_guardian" => "minecraft:elder_guardian",
		"ender_dragon" => "minecraft:ender_dragon",
		"enderman" => "minecraft:enderman",
		"endermite" => "minecraft:endermite",
		"evocation_illager" => "minecraft:evocation_illager",
		"fox" => "minecraft:fox",
		"frog" => "minecraft:frog",
		"ghast" => "minecraft:ghast",
		"glow_squid" => "minecraft:glow_squid",
		"goat" => "minecraft:goat",
		"guardian" => "minecraft:guardian",
		"happy_ghast" => "minecraft:happy_ghast",
		"hoglin" => "minecraft:hoglin",
		"horse" => "minecraft:horse",
		"husk" => "minecraft:husk",
		"iron_golem" => "minecraft:iron_golem",
		"llama" => "minecraft:llama",
		"magma_cube" => "minecraft:magma_cube",
		"mooshroom" => "minecraft:mooshroom",
		"mule" => "minecraft:mule",
		"nautilus" => "minecraft:nautilus",
		"ocelot" => "minecraft:ocelot",
		"panda" => "minecraft:panda",
		"parched" => "minecraft:parched",
		"parrot" => "minecraft:parrot",
		"phantom" => "minecraft:phantom",
		"pig" => "minecraft:pig",
		"piglin" => "minecraft:piglin",
		"piglin_brute" => "minecraft:piglin_brute",
		"pillager" => "minecraft:pillager",
		"polar_bear" => "minecraft:polar_bear",
		"pufferfish" => "minecraft:pufferfish",
		"rabbit" => "minecraft:rabbit",
		"ravager" => "minecraft:ravager",
		"salmon" => "minecraft:salmon",
		"sheep" => "minecraft:sheep",
		"shulker" => "minecraft:shulker",
		"silverfish" => "minecraft:silverfish",
		"skeleton" => "minecraft:skeleton",
		"skeleton_horse" => "minecraft:skeleton_horse",
		"slime" => "minecraft:slime",
		"sniffer" => "minecraft:sniffer",
		"snow_golem" => "minecraft:snow_golem",
		"spider" => "minecraft:spider",
		"squid" => "minecraft:squid",
		"stray" => "minecraft:stray",
		"strider" => "minecraft:strider",
		"tadpole" => "minecraft:tadpole",
		"trader_llama" => "minecraft:trader_llama",
		"tropicalfish" => "minecraft:tropicalfish",
		"turtle" => "minecraft:turtle",
		"vex" => "minecraft:vex",
		"villager" => "minecraft:villager",
		"villager_v2" => "minecraft:villager_v2",
		"vindicator" => "minecraft:vindicator",
		"wandering_trader" => "minecraft:wandering_trader",
		"warden" => "minecraft:warden",
		"witch" => "minecraft:witch",
		"wither" => "minecraft:wither",
		"wither_skeleton" => "minecraft:wither_skeleton",
		"wolf" => "minecraft:wolf",
		"zoglin" => "minecraft:zoglin",
		"zombie" => "minecraft:zombie",
		"zombie_horse" => "minecraft:zombie_horse",
		"zombie_nautilus" => "minecraft:zombie_nautilus",
		"zombie_pigman" => "minecraft:zombie_pigman",
		"zombie_villager" => "minecraft:zombie_villager",
		"zombie_villager_v2" => "minecraft:zombie_villager_v2"
	];

	/** @var array<string, string> */
	private static array $aliases = [
		"human" => "human",
		"player" => "human",
		"npc" => "human",
		"mine_cart" => "minecart",
		"tnt" => "primed_tnt",
		"primedtnt" => "primed_tnt",
		"magmacube" => "magma_cube",
		"magma-cube" => "magma_cube",
		"mooshroom" => "mooshroom",
		"snowman" => "snow_golem",
		"iron_golem" => "iron_golem",
		"villager_golem" => "iron_golem",
		"zombiepigman" => "zombie_pigman",
		"zombie_pig_zman" => "zombie_pigman",
		"pig_zombie" => "zombie_pigman",
		"pigzombie" => "zombie_pigman",
		"evoker" => "evocation_illager",
		"evocation_illager" => "evocation_illager",
		"witherboss" => "wither",
		"tropical_fish" => "tropicalfish",
		"traderllama" => "trader_llama",
		"villager_v2" => "villager_v2",
		"zombie_villager_v2" => "zombie_villager_v2"
	];

	/** @var array<string, string> */
	private static array $custom = [];

	/** @return list<string> */
	public static function all(bool $includeHuman = true) : array{
		$types = array_merge(array_keys(self::$types), array_keys(self::$custom));
		if($includeHuman){
			$types[] = "human";
		}
		$types = array_values(array_unique($types));
		sort($types);
		if($includeHuman && in_array("human", $types, true)){
			$types = array_values(array_diff($types, ["human"]));
			array_unshift($types, "human");
		}
		return $types;
	}

	public static function resolve(string $type) : ?string{
		$type = strtolower(trim($type));
		if(str_starts_with($type, "minecraft:")){
			$type = substr($type, 10);
		}
		$type = self::$aliases[$type] ?? $type;
		return $type === "human" || isset(self::$types[$type]) || isset(self::$custom[$type]) ? $type : null;
	}

	public static function networkId(string $type) : ?string{
		$type = self::resolve($type);
		if($type === null || $type === "human"){
			return null;
		}
		return self::$types[$type] ?? self::$custom[$type] ?? null;
	}

	public static function isHuman(string $type) : bool{
		return self::resolve($type) === "human";
	}

	public static function isMob(string $type) : bool{
		$type = self::resolve($type) ?? "";
		return $type !== "" && $type !== "human";
	}

	public static function canFly(string $type) : bool{
		return in_array(self::resolve($type) ?? "", [
			"allay",
			"bat",
			"bee",
			"blaze",
			"ender_dragon",
			"ghast",
			"happy_ghast",
			"phantom",
			"vex"
		], true);
	}

	/** @return array{width: float, height: float} */
	public static function size(string $type) : array{
		$type = self::resolve($type) ?? "";
		$sizes = [
			"allay" => ["width" => 0.35, "height" => 0.60],
			"armadillo" => ["width" => 0.70, "height" => 0.65],
			"axolotl" => ["width" => 1.30, "height" => 0.60],
			"bat" => ["width" => 0.50, "height" => 0.90],
			"bee" => ["width" => 0.70, "height" => 0.60],
			"blaze" => ["width" => 0.60, "height" => 1.80],
			"bogged" => ["width" => 0.60, "height" => 1.99],
			"breeze" => ["width" => 0.60, "height" => 1.70],
			"camel" => ["width" => 1.70, "height" => 2.38],
			"camel_husk" => ["width" => 1.70, "height" => 2.38],
			"cat" => ["width" => 0.60, "height" => 0.70],
			"cave_spider" => ["width" => 0.70, "height" => 0.50],
			"chicken" => ["width" => 0.40, "height" => 0.70],
			"cod" => ["width" => 0.50, "height" => 0.30],
			"copper_golem" => ["width" => 0.70, "height" => 1.80],
			"cow" => ["width" => 0.90, "height" => 1.40],
			"creaking" => ["width" => 0.90, "height" => 2.50],
			"creeper" => ["width" => 0.60, "height" => 1.70],
			"dolphin" => ["width" => 0.90, "height" => 0.60],
			"donkey" => ["width" => 1.40, "height" => 1.50],
			"drowned" => ["width" => 0.60, "height" => 1.95],
			"elder_guardian" => ["width" => 2.00, "height" => 2.00],
			"ender_dragon" => ["width" => 16.00, "height" => 8.00],
			"enderman" => ["width" => 0.60, "height" => 2.90],
			"endermite" => ["width" => 0.40, "height" => 0.30],
			"evocation_illager" => ["width" => 0.60, "height" => 1.95],
			"fox" => ["width" => 0.70, "height" => 0.60],
			"frog" => ["width" => 0.50, "height" => 0.50],
			"ghast" => ["width" => 4.00, "height" => 4.00],
			"glow_squid" => ["width" => 0.80, "height" => 0.80],
			"goat" => ["width" => 0.90, "height" => 1.30],
			"guardian" => ["width" => 0.85, "height" => 0.85],
			"happy_ghast" => ["width" => 4.00, "height" => 4.00],
			"hoglin" => ["width" => 1.40, "height" => 1.40],
			"horse" => ["width" => 1.40, "height" => 1.60],
			"husk" => ["width" => 0.60, "height" => 1.95],
			"iron_golem" => ["width" => 1.40, "height" => 2.70],
			"llama" => ["width" => 0.90, "height" => 1.87],
			"magma_cube" => ["width" => 2.00, "height" => 2.00],
			"mooshroom" => ["width" => 0.90, "height" => 1.40],
			"mule" => ["width" => 1.40, "height" => 1.50],
			"nautilus" => ["width" => 1.40, "height" => 0.90],
			"ocelot" => ["width" => 0.60, "height" => 0.70],
			"panda" => ["width" => 1.30, "height" => 1.25],
			"parched" => ["width" => 0.60, "height" => 1.95],
			"parrot" => ["width" => 0.50, "height" => 0.90],
			"phantom" => ["width" => 0.90, "height" => 0.50],
			"pig" => ["width" => 0.90, "height" => 0.90],
			"piglin" => ["width" => 0.60, "height" => 1.95],
			"piglin_brute" => ["width" => 0.60, "height" => 1.95],
			"pillager" => ["width" => 0.60, "height" => 1.95],
			"polar_bear" => ["width" => 1.40, "height" => 1.40],
			"pufferfish" => ["width" => 0.70, "height" => 0.70],
			"rabbit" => ["width" => 0.40, "height" => 0.50],
			"ravager" => ["width" => 1.95, "height" => 2.20],
			"salmon" => ["width" => 0.70, "height" => 0.40],
			"sheep" => ["width" => 0.90, "height" => 1.30],
			"shulker" => ["width" => 1.00, "height" => 1.00],
			"silverfish" => ["width" => 0.40, "height" => 0.30],
			"skeleton" => ["width" => 0.60, "height" => 1.99],
			"skeleton_horse" => ["width" => 1.40, "height" => 1.60],
			"slime" => ["width" => 2.00, "height" => 2.00],
			"sniffer" => ["width" => 1.90, "height" => 1.90],
			"snow_golem" => ["width" => 0.70, "height" => 1.90],
			"spider" => ["width" => 1.40, "height" => 0.90],
			"squid" => ["width" => 0.80, "height" => 0.80],
			"stray" => ["width" => 0.60, "height" => 1.99],
			"strider" => ["width" => 0.90, "height" => 1.70],
			"tadpole" => ["width" => 0.40, "height" => 0.30],
			"trader_llama" => ["width" => 0.90, "height" => 1.87],
			"tropicalfish" => ["width" => 0.50, "height" => 0.40],
			"turtle" => ["width" => 1.20, "height" => 0.40],
			"vex" => ["width" => 0.40, "height" => 0.80],
			"villager" => ["width" => 0.60, "height" => 1.95],
			"villager_v2" => ["width" => 0.60, "height" => 1.95],
			"vindicator" => ["width" => 0.60, "height" => 1.95],
			"wandering_trader" => ["width" => 0.60, "height" => 1.95],
			"warden" => ["width" => 0.90, "height" => 2.90],
			"witch" => ["width" => 0.60, "height" => 1.95],
			"wither" => ["width" => 0.90, "height" => 3.50],
			"wither_skeleton" => ["width" => 0.70, "height" => 2.40],
			"wolf" => ["width" => 0.60, "height" => 0.85],
			"zoglin" => ["width" => 1.40, "height" => 1.40],
			"zombie" => ["width" => 0.60, "height" => 1.95],
			"zombie_horse" => ["width" => 1.40, "height" => 1.60],
			"zombie_nautilus" => ["width" => 1.40, "height" => 0.90],
			"zombie_pigman" => ["width" => 0.60, "height" => 1.95],
			"zombie_villager" => ["width" => 0.60, "height" => 1.95],
			"zombie_villager_v2" => ["width" => 0.60, "height" => 1.95]
		];
		return $sizes[$type] ?? ["width" => 0.60, "height" => 1.80];
	}

	public static function displayName(string $type) : string{
		$type = self::resolve($type) ?? $type;
		return ucwords(str_replace(["_", "-"], " ", $type));
	}

	/**
	 * Returns a texture path understood by Bedrock form icons.
	 *
	 * These paths are resolved by the client against its loaded Vanilla
	 * resource pack. They are deliberately not URLs: no image needs to be
	 * downloaded by the server or copied into the PHAR.
	 */
	public static function iconPath(string $type) : string{
		$type = self::resolve($type) ?? "";
		return match($type){
			"falling_block" => "textures/blocks/stone",
			"boat" => "textures/items/boat_oak",
			"minecart" => "textures/items/minecart",
			"primed_tnt" => "textures/blocks/tnt_side",
			"human" => "textures/items/egg_npc",
			"evocation_illager" => "textures/items/spawn_eggs/spawn_egg_evoker",
			"villager_v2" => "textures/items/spawn_eggs/spawn_egg_villager",
			"zombie_pigman" => "textures/items/spawn_eggs/spawn_egg_zombified_piglin",
			"zombie_villager_v2" => "textures/items/spawn_eggs/spawn_egg_zombie_villager",
			"" => "textures/items/egg",
			default => isset(self::$types[$type])
				? "textures/items/spawn_eggs/spawn_egg_" . $type
				: "textures/items/egg"
		};
	}

	/**
	 * Registers a passive custom visual actor. This does not load PHP classes or
	 * execute external code; the caller only supplies a Bedrock network ID.
	 */
	public static function registerCustom(string $name, string $networkId) : void{
		$name = strtolower(trim($name));
		$networkId = strtolower(trim($networkId));
		if(preg_match('/^[a-z0-9_:-]{1,64}$/D', $name) !== 1 || $name === "human" || isset(self::$types[$name])){
			throw new InvalidArgumentException("Invalid or reserved NPC entity type name");
		}
		if(preg_match('~^[a-z0-9_.-]+:[a-z0-9_./-]+$~D', $networkId) !== 1){
			throw new InvalidArgumentException("Invalid Bedrock network entity identifier");
		}
		self::$custom[$name] = $networkId;
	}

	/** @return array<string, string> */
	public static function custom() : array{
		return self::$custom;
	}
}

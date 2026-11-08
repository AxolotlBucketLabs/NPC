<?php

declare(strict_types=1);

namespace AxolotlBucketLabs\NPC\model;

use pocketmine\color\Color;
use pocketmine\item\VanillaItems;
use pocketmine\world\particle\AngryVillagerParticle;
use pocketmine\world\particle\BlockBreakParticle;
use pocketmine\world\particle\BlockForceFieldParticle;
use pocketmine\world\particle\BlockPunchParticle;
use pocketmine\world\particle\BubbleParticle;
use pocketmine\world\particle\CriticalParticle;
use pocketmine\world\particle\DragonEggTeleportParticle;
use pocketmine\world\particle\DustParticle;
use pocketmine\world\particle\EnchantmentTableParticle;
use pocketmine\world\particle\EnchantParticle;
use pocketmine\world\particle\EndermanTeleportParticle;
use pocketmine\world\particle\EntityFlameParticle;
use pocketmine\world\particle\ExplodeParticle;
use pocketmine\world\particle\FlameParticle;
use pocketmine\world\particle\FloatingTextParticle;
use pocketmine\world\particle\HappyVillagerParticle;
use pocketmine\world\particle\HeartParticle;
use pocketmine\world\particle\HugeExplodeParticle;
use pocketmine\world\particle\HugeExplodeSeedParticle;
use pocketmine\world\particle\InkParticle;
use pocketmine\world\particle\InstantEnchantParticle;
use pocketmine\world\particle\ItemBreakParticle;
use pocketmine\world\particle\LavaDripParticle;
use pocketmine\world\particle\LavaParticle;
use pocketmine\world\particle\MobSpawnParticle;
use pocketmine\world\particle\Particle;
use pocketmine\world\particle\PortalParticle;
use pocketmine\world\particle\PotionSplashParticle;
use pocketmine\world\particle\RainSplashParticle;
use pocketmine\world\particle\RedstoneParticle;
use pocketmine\world\particle\SmokeParticle;
use pocketmine\world\particle\SnowballPoofParticle;
use pocketmine\world\particle\SonicExplosionParticle;
use pocketmine\world\particle\SporeParticle;
use pocketmine\world\particle\SplashParticle;
use pocketmine\world\particle\TerrainParticle;
use pocketmine\world\particle\WaterDripParticle;
use pocketmine\world\particle\WaterParticle;
use pocketmine\block\VanillaBlocks;
use function array_keys;
use function str_contains;
use function strtolower;
use function str_replace;
use function trim;
use function ucwords;

/**
 * Particle catalog backed by the current PocketMine/Axolotl-PM particle API.
 *
 * Parameterized particles use conservative vanilla defaults so they can be
 * selected from a form without asking for protocol-specific constructor data.
 */
final class VanillaParticleCatalog{
	/** @var array<string, string> */
	private const TYPES = [
		"angry_villager" => AngryVillagerParticle::class,
		"block_break" => BlockBreakParticle::class,
		"block_force_field" => BlockForceFieldParticle::class,
		"block_punch" => BlockPunchParticle::class,
		"bubble" => BubbleParticle::class,
		"critical" => CriticalParticle::class,
		"dragon_egg_teleport" => DragonEggTeleportParticle::class,
		"dust" => DustParticle::class,
		"enchantment_table" => EnchantmentTableParticle::class,
		"enchant" => EnchantParticle::class,
		"enderman_teleport" => EndermanTeleportParticle::class,
		"entity_flame" => EntityFlameParticle::class,
		"explode" => ExplodeParticle::class,
		"flame" => FlameParticle::class,
		"floating_text" => FloatingTextParticle::class,
		"happy_villager" => HappyVillagerParticle::class,
		"heart" => HeartParticle::class,
		"huge_explode" => HugeExplodeParticle::class,
		"huge_explode_seed" => HugeExplodeSeedParticle::class,
		"ink" => InkParticle::class,
		"instant_enchant" => InstantEnchantParticle::class,
		"item_break" => ItemBreakParticle::class,
		"lava_drip" => LavaDripParticle::class,
		"lava" => LavaParticle::class,
		"mob_spawn" => MobSpawnParticle::class,
		"portal" => PortalParticle::class,
		"potion_splash" => PotionSplashParticle::class,
		"rain_splash" => RainSplashParticle::class,
		"redstone" => RedstoneParticle::class,
		"smoke" => SmokeParticle::class,
		"snowball_poof" => SnowballPoofParticle::class,
		"sonic_explosion" => SonicExplosionParticle::class,
		"spore" => SporeParticle::class,
		"splash" => SplashParticle::class,
		"terrain" => TerrainParticle::class,
		"water_drip" => WaterDripParticle::class,
		"water" => WaterParticle::class
	];

	/** @return list<string> */
	public static function all() : array{
		return array_keys(self::TYPES);
	}

	public static function resolve(string $type) : ?string{
		$type = strtolower(str_replace(["-", " "], "_", trim($type)));
		return isset(self::TYPES[$type]) ? $type : null;
	}

	public static function displayName(string $type) : string{
		$type = self::resolve($type) ?? $type;
		return ucwords(str_replace("_", " ", $type));
	}

	public static function create(string $type) : Particle{
		$type = self::resolve($type) ?? "flame";
		$white = static fn() : Color => new Color(255, 255, 255);
		return match($type){
			"angry_villager" => new AngryVillagerParticle(),
			"block_break" => new BlockBreakParticle(VanillaBlocks::STONE()),
			"block_force_field" => new BlockForceFieldParticle(),
			"block_punch" => new BlockPunchParticle(VanillaBlocks::STONE(), 1),
			"bubble" => new BubbleParticle(),
			"critical" => new CriticalParticle(),
			"dragon_egg_teleport" => new DragonEggTeleportParticle(0, 0, 0),
			"dust" => new DustParticle($white()),
			"enchantment_table" => new EnchantmentTableParticle(),
			"enchant" => new EnchantParticle($white()),
			"enderman_teleport" => new EndermanTeleportParticle(),
			"entity_flame" => new EntityFlameParticle(),
			"explode" => new ExplodeParticle(),
			"flame" => new FlameParticle(),
			"floating_text" => new FloatingTextParticle("NPC"),
			"happy_villager" => new HappyVillagerParticle(),
			"heart" => new HeartParticle(),
			"huge_explode" => new HugeExplodeParticle(),
			"huge_explode_seed" => new HugeExplodeSeedParticle(),
			"ink" => new InkParticle(),
			"instant_enchant" => new InstantEnchantParticle($white()),
			"item_break" => new ItemBreakParticle(VanillaItems::STICK()),
			"lava_drip" => new LavaDripParticle(),
			"lava" => new LavaParticle(),
			"mob_spawn" => new MobSpawnParticle(),
			"portal" => new PortalParticle(),
			"potion_splash" => new PotionSplashParticle($white()),
			"rain_splash" => new RainSplashParticle(),
			"redstone" => new RedstoneParticle(),
			"smoke" => new SmokeParticle(),
			"snowball_poof" => new SnowballPoofParticle(),
			"sonic_explosion" => new SonicExplosionParticle(),
			"spore" => new SporeParticle(),
			"splash" => new SplashParticle(),
			"terrain" => new TerrainParticle(VanillaBlocks::STONE()),
			"water_drip" => new WaterDripParticle(),
			"water" => new WaterParticle(),
			default => new FlameParticle()
		};
	}
}

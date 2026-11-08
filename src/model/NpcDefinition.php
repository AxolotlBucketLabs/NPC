<?php

declare(strict_types=1);

namespace AxolotlBucketLabs\NPC\model;

use InvalidArgumentException;
use function array_values;
use function count;
use function array_filter;
use function in_array;
use function is_array;
use function is_finite;
use function is_int;
use function is_numeric;
use function is_string;
use function max;
use function min;
use function preg_match;
use function str_contains;
use function str_starts_with;
use function str_replace;
use function strtolower;
use function trim;

final class NpcDefinition{
	/** @var list<string> */
	private array $lines;

	/** @var list<string> */
	private array $commands;

	/** @var array<string, string> */
	private array $equipment;

	/** @var list<string> */
	private array $hitCommands;

	/** @var list<string> */
	private array $interactCommands;

	/** @var list<string> */
	private array $emotePool;

	/**
	 * @param array<int|string, mixed> $lines
	 * @param array<int|string, mixed> $commands
	 * @param array<int|string, mixed> $equipment
	 * @param list<string> $hitCommands
	 * @param list<string> $interactCommands
	 * @param list<string> $emotePool
	 */
	public function __construct(
		private readonly string $id,
		private string $world,
		private float $x,
		private float $y,
		private float $z,
		private float $yaw,
		private float $pitch,
		array $lines,
		array $commands = [],
		private float $cooldown = 0.0,
		private bool $rotate = true,
		private bool $particle = false,
		private bool $sound = false,
		private float $scale = 1.0,
		array $equipment = [],
		private string $skinFile = "",
		private string $capeFile = "",
		private string $geometryName = "",
		private string $geometryData = "",
		private string $particleType = "flame",
		private string $soundType = "click",
		private string $entityType = "human",
		private string $animationType = "none",
		private string $animationId = "",
		private int $animationInterval = 100,
		private string $animationTrigger = "timer",
		private string $skinUrl = "",
		array $hitCommands = [],
		array $interactCommands = [],
		private string $interactionMode = "shared",
		private bool $nametagVisible = true,
		private float $rotationDistance = 64.0,
		private bool $randomEmoteEnabled = false,
		private float $randomEmoteCooldown = 60.0,
		array $emotePool = [],
		private string $playerCountMode = "server",
		private string $playerCountWorld = "",
		private string $queryHost = "",
		private int $queryPort = 19132,
		private int $queryInterval = 200,
		private string $blockId = "",
		private string $nametagVisibility = "always",
		private int $particleCount = 8,
		private float $particleSpread = 0.35,
		private bool $animationSound = true,
		private string $skinDataFile = "",
		private string $variant = ""
	){
		self::validateId($id);
		$this->lines = self::normalizeLines($lines);
		$this->commands = self::normalizeCommands($commands);
		$this->equipment = self::normalizeEquipment($equipment);
		$this->cooldown = self::normalizeCooldown($cooldown);
		$this->scale = self::normalizeScale($scale);
		$this->skinFile = self::normalizeAssetPath($skinFile);
		$this->capeFile = self::normalizeAssetPath($capeFile);
		$this->skinDataFile = self::normalizeAssetPath($skinDataFile);
		$this->variant = self::normalizeVariant($variant);
		$this->geometryName = self::normalizeGeometryName($geometryName);
		$this->geometryData = self::normalizeGeometryData($geometryData);
		$this->entityType = self::normalizeEntityType($entityType);
		$this->animationType = self::normalizeAnimationType($animationType);
		$this->animationId = self::normalizeAnimationId($animationId);
		$this->animationInterval = self::normalizeAnimationInterval($animationInterval);
		$this->animationTrigger = self::normalizeAnimationTrigger($animationTrigger);
		$this->skinUrl = self::normalizeUrl($skinUrl);
		$this->hitCommands = self::normalizeCommands($hitCommands);
		$this->interactCommands = self::normalizeCommands($interactCommands);
		$this->interactionMode = self::normalizeInteractionMode($interactionMode);
		$this->rotationDistance = self::normalizeRotationDistance($rotationDistance);
		$this->randomEmoteCooldown = self::normalizeRandomEmoteCooldown($randomEmoteCooldown);
		$this->emotePool = self::normalizeEmotePool($emotePool);
		$this->playerCountMode = self::normalizePlayerCountMode($playerCountMode);
		$this->queryPort = self::normalizePort($queryPort);
		$this->queryInterval = self::normalizeQueryInterval($queryInterval);
		$this->blockId = self::normalizeBlockId($blockId);
		$this->nametagVisibility = self::normalizeNametagVisibility($nametagVisibility);
		$this->nametagVisible = $this->nametagVisibility !== "never";
		$this->particleCount = self::normalizeParticleCount($particleCount);
		$this->particleSpread = self::normalizeParticleSpread($particleSpread);
	}

	/** @param array<int|string, mixed> $data */
	public static function fromArray(string $id, array $data) : self{
		return new self(
			$id,
			self::stringValue($data, "world", ""),
			self::floatValue($data, "x", 0.0),
			self::floatValue($data, "y", 0.0),
			self::floatValue($data, "z", 0.0),
			self::floatValue($data, "yaw", 0.0),
			self::floatValue($data, "pitch", 0.0),
			is_array($data["lines"] ?? null) ? $data["lines"] : [],
			is_array($data["commands"] ?? null) ? $data["commands"] : [],
			self::floatValue($data, "cooldown", 0.0),
			self::boolValue($data, "rotate", true),
			self::boolValue($data, "particle", false),
			self::boolValue($data, "sound", false),
			self::floatValue($data, "scale", 1.0),
			is_array($data["equipment"] ?? null) ? $data["equipment"] : [],
			self::stringValue($data, "skin", ""),
			self::stringValue($data, "cape", ""),
			self::stringValue($data, "geometry-name", ""),
			self::stringValue($data, "geometry-data", ""),
			self::stringValue($data, "particle-type", "flame"),
			self::normalizeSoundType(self::stringValue($data, "sound-type", "click")),
			self::stringValue($data, "entity-type", "human"),
			self::stringValue($data, "animation-type", "none"),
			self::stringValue($data, "animation-id", ""),
			self::intValue($data, "animation-interval", 100),
			self::stringValue($data, "animation-trigger", "timer"),
			self::stringValue($data, "skin-url", ""),
			self::normalizeCommands(is_array($data["hit-commands"] ?? null) ? $data["hit-commands"] : []),
			self::normalizeCommands(is_array($data["interact-commands"] ?? null) ? $data["interact-commands"] : []),
			self::stringValue($data, "interaction-mode", "shared"),
			self::boolValue($data, "nametag-visible", true),
			self::floatValue($data, "rotation-distance", 64.0),
			self::boolValue($data, "random-emote-enabled", false),
			self::floatValue($data, "random-emote-cooldown", 60.0),
			self::normalizeEmotePool(is_array($data["emote-pool"] ?? null) ? $data["emote-pool"] : []),
			self::stringValue($data, "player-count-mode", "server"),
			self::stringValue($data, "player-count-world", ""),
			self::stringValue($data, "query-host", ""),
			self::intValue($data, "query-port", 19132),
			self::intValue($data, "query-interval", 200),
			self::stringValue($data, "block-id", ""),
			self::nametagVisibilityValue($data),
			self::intValue($data, "particle-count", 8),
			self::floatValue($data, "particle-spread", 0.35),
			self::boolValue($data, "animation-sound", true),
			self::stringValue($data, "skin-data", ""),
			self::stringValue($data, "variant", "")
		);
	}

	/** @return array<string, mixed> */
	public function toArray() : array{
		return [
			"world" => $this->world,
			"x" => $this->x,
			"y" => $this->y,
			"z" => $this->z,
			"yaw" => $this->yaw,
			"pitch" => $this->pitch,
			"lines" => $this->lines,
			"commands" => $this->commands,
			"cooldown" => $this->cooldown,
			"rotate" => $this->rotate,
			"particle" => $this->particle,
			"sound" => $this->sound,
			"scale" => $this->scale,
			"equipment" => $this->equipment,
			"skin" => $this->skinFile,
			"cape" => $this->capeFile,
			"skin-data" => $this->skinDataFile,
			"variant" => $this->variant,
			"skin-url" => $this->skinUrl,
			"geometry-name" => $this->geometryName,
			"geometry-data" => $this->geometryData,
			"particle-type" => $this->particleType,
			"sound-type" => $this->soundType,
			"entity-type" => $this->entityType,
			"animation-type" => $this->animationType,
			"animation-id" => $this->animationId,
			"animation-interval" => $this->animationInterval,
			"animation-trigger" => $this->animationTrigger,
			"hit-commands" => $this->hitCommands,
			"interact-commands" => $this->interactCommands,
			"interaction-mode" => $this->interactionMode,
			"nametag-visible" => $this->nametagVisible,
			"nametag-visibility" => $this->nametagVisibility,
			"rotation-distance" => $this->rotationDistance,
			"random-emote-enabled" => $this->randomEmoteEnabled,
			"random-emote-cooldown" => $this->randomEmoteCooldown,
			"emote-pool" => $this->emotePool,
			"player-count-mode" => $this->playerCountMode,
			"player-count-world" => $this->playerCountWorld,
			"query-host" => $this->queryHost,
			"query-port" => $this->queryPort,
			"query-interval" => $this->queryInterval,
			"block-id" => $this->blockId,
			"particle-count" => $this->particleCount,
			"particle-spread" => $this->particleSpread,
			"animation-sound" => $this->animationSound
		];
	}

	public function getId() : string{ return $this->id; }
	public function getWorld() : string{ return $this->world; }
	public function getX() : float{ return $this->x; }
	public function getY() : float{ return $this->y; }
	public function getZ() : float{ return $this->z; }
	public function getYaw() : float{ return $this->yaw; }
	public function getPitch() : float{ return $this->pitch; }
	/** @return list<string> */
	public function getLines() : array{ return $this->lines; }
	/** @return list<string> */
	public function getCommands() : array{ return $this->commands; }
	public function getCooldown() : float{ return $this->cooldown; }
	public function isRotateEnabled() : bool{ return $this->rotate; }
	public function isParticleEnabled() : bool{ return $this->particle; }
	public function isSoundEnabled() : bool{ return $this->sound; }
	public function getScale() : float{ return $this->scale; }
	/** @return array<string, string> */
	public function getEquipment() : array{ return $this->equipment; }
	public function getSkinFile() : string{ return $this->skinFile; }
	public function getCapeFile() : string{ return $this->capeFile; }
	public function getSkinDataFile() : string{ return $this->skinDataFile; }
	public function getVariant() : string{ return $this->variant; }
	public function getSkinUrl() : string{ return $this->skinUrl; }
	public function getGeometryName() : string{ return $this->geometryName; }
	public function getGeometryData() : string{ return $this->geometryData; }
	public function getParticleType() : string{ return $this->particleType; }
	public function getSoundType() : string{ return $this->soundType; }
	public function getEntityType() : string{ return $this->entityType; }
	public function getAnimationType() : string{ return $this->animationType; }
	public function getAnimationId() : string{ return $this->animationId; }
	public function getAnimationInterval() : int{ return $this->animationInterval; }
	public function getAnimationTrigger() : string{ return $this->animationTrigger; }
	/** @return list<string> */
	public function getHitCommands() : array{ return $this->hitCommands; }
	/** @return list<string> */
	public function getInteractCommands() : array{ return $this->interactCommands; }
	public function getInteractionMode() : string{ return $this->interactionMode; }
	public function isNametagVisible() : bool{ return $this->nametagVisible; }
	public function getNametagVisibility() : string{ return $this->nametagVisibility; }
	public function getRotationDistance() : float{ return $this->rotationDistance; }
	public function isRandomEmoteEnabled() : bool{ return $this->randomEmoteEnabled; }
	public function getRandomEmoteCooldown() : float{ return $this->randomEmoteCooldown; }
	/** @return list<string> */
	public function getEmotePool() : array{ return $this->emotePool; }
	public function getPlayerCountMode() : string{ return $this->playerCountMode; }
	public function getPlayerCountWorld() : string{ return $this->playerCountWorld; }
	public function getQueryHost() : string{ return $this->queryHost; }
	public function getQueryPort() : int{ return $this->queryPort; }
	public function getQueryInterval() : int{ return $this->queryInterval; }
	public function getBlockId() : string{ return $this->blockId; }
	public function getParticleCount() : int{ return $this->particleCount; }
	public function getParticleSpread() : float{ return $this->particleSpread; }
	public function isAnimationSoundEnabled() : bool{ return $this->animationSound; }

	/** @param array<int|string, mixed> $lines */
	public function setLines(array $lines) : void{ $this->lines = self::normalizeLines($lines); }
	/** @param array<int|string, mixed> $commands */
	public function setCommands(array $commands) : void{ $this->commands = self::normalizeCommands($commands); }
	public function setCooldown(float $cooldown) : void{ $this->cooldown = self::normalizeCooldown($cooldown); }
	public function setRotate(bool $rotate) : void{ $this->rotate = $rotate; }
	public function setParticle(bool $particle) : void{ $this->particle = $particle; }
	public function setSound(bool $sound) : void{ $this->sound = $sound; }
	public function setScale(float $scale) : void{ $this->scale = self::normalizeScale($scale); }
	public function setLocation(string $world, float $x, float $y, float $z, float $yaw, float $pitch) : void{
		$this->world = trim($world);
		$this->x = $x;
		$this->y = $y;
		$this->z = $z;
		$this->yaw = $yaw;
		$this->pitch = $pitch;
	}
	/** @param array<int|string, mixed> $equipment */
	public function setEquipment(array $equipment) : void{ $this->equipment = self::normalizeEquipment($equipment); }
	public function setEquipmentSlot(string $slot, string $item) : bool{
		if(!in_array($slot, ["hand", "offhand", "helmet", "chestplate", "leggings", "boots"], true)){
			return false;
		}
		$item = trim($item);
		if($item === ""){
			unset($this->equipment[$slot]);
		}else{
			$this->equipment[$slot] = substr($item, 0, 128);
		}
		return true;
	}
	public function setSkinFile(string $skinFile) : void{ $this->skinFile = self::normalizeAssetPath($skinFile); }
	public function setCapeFile(string $capeFile) : void{ $this->capeFile = self::normalizeAssetPath($capeFile); }
	public function setSkinDataFile(string $skinDataFile) : void{ $this->skinDataFile = self::normalizeAssetPath($skinDataFile); }
	public function setVariant(string $variant) : void{ $this->variant = self::normalizeVariant($variant); }
	public function setSkinUrl(string $skinUrl) : void{ $this->skinUrl = self::normalizeUrl($skinUrl); }
	public function setGeometry(string $name, string $data) : void{
		$this->geometryName = self::normalizeGeometryName($name);
		$this->geometryData = self::normalizeGeometryData($data);
	}
	public function setParticleType(string $type) : void{ $this->particleType = self::normalizeEffectName($type, "flame"); }
	public function setSoundType(string $type) : void{ $this->soundType = self::normalizeSoundType($type); }
	public function setEntityType(string $entityType) : void{ $this->entityType = self::normalizeEntityType($entityType); }
	public function setAnimation(string $type, string $id = "", int $interval = 100, string $trigger = "timer") : void{
		$this->animationType = self::normalizeAnimationType($type);
		$this->animationId = self::normalizeAnimationId($id);
		$this->animationInterval = self::normalizeAnimationInterval($interval);
		$this->animationTrigger = self::normalizeAnimationTrigger($trigger);
	}
	/** @param array<int|string, mixed> $commands */
	public function setHitCommands(array $commands) : void{ $this->hitCommands = self::normalizeCommands($commands); }
	/** @param array<int|string, mixed> $commands */
	public function setInteractCommands(array $commands) : void{ $this->interactCommands = self::normalizeCommands($commands); }
	public function setInteractionMode(string $mode) : void{ $this->interactionMode = self::normalizeInteractionMode($mode); }
	public function setNametagVisible(bool $visible) : void{
		$this->nametagVisible = $visible;
		$this->nametagVisibility = $visible ? "always" : "never";
	}
	public function setNametagVisibility(string $visibility) : void{
		$this->nametagVisibility = self::normalizeNametagVisibility($visibility);
		$this->nametagVisible = $this->nametagVisibility !== "never";
	}
	public function setRotationDistance(float $distance) : void{ $this->rotationDistance = self::normalizeRotationDistance($distance); }
	public function setRandomEmoteEnabled(bool $enabled) : void{ $this->randomEmoteEnabled = $enabled; }
	public function setRandomEmoteCooldown(float $cooldown) : void{ $this->randomEmoteCooldown = self::normalizeRandomEmoteCooldown($cooldown); }
	/** @param array<int|string, mixed> $pool */
	public function setEmotePool(array $pool) : void{ $this->emotePool = self::normalizeEmotePool($pool); }
	public function setPlayerCountMode(string $mode) : void{ $this->playerCountMode = self::normalizePlayerCountMode($mode); }
	public function setPlayerCountWorld(string $world) : void{ $this->playerCountWorld = substr(str_replace(["\r", "\n"], " ", trim($world)), 0, 128); }
	public function setQueryTarget(string $host, int $port = 19132, int $interval = 200) : void{
		$this->queryHost = substr(str_replace(["\r", "\n"], "", trim($host)), 0, 253);
		$this->queryPort = self::normalizePort($port);
		$this->queryInterval = self::normalizeQueryInterval($interval);
	}
	public function setBlockId(string $blockId) : void{ $this->blockId = self::normalizeBlockId($blockId); }
	public function setParticleCount(int $count) : void{ $this->particleCount = self::normalizeParticleCount($count); }
	public function setParticleSpread(float $spread) : void{ $this->particleSpread = self::normalizeParticleSpread($spread); }
	public function setAnimationSound(bool $enabled) : void{ $this->animationSound = $enabled; }

	private static function validateId(string $id) : void{
		if(preg_match('/^[A-Za-z0-9._-]{1,32}$/D', $id) !== 1){
			throw new InvalidArgumentException("NPC id must contain only letters, numbers, dot, underscore or dash");
		}
	}

	/**
	 * @param array<int|string, mixed> $lines
	 * @return list<string>
	 */
	private static function normalizeLines(array $lines) : array{
		$result = [];
		foreach(array_values($lines) as $line){
			if(!is_string($line)){
				continue;
			}
			$line = trim($line);
			if($line !== ""){
				$result[] = substr($line, 0, 256);
			}
			if(count($result) >= 16){
				break;
			}
		}
		return $result === [] ? ["NPC"] : $result;
	}

	/**
	 * @param array<int|string, mixed> $commands
	 * @return list<string>
	 */
	private static function normalizeCommands(array $commands) : array{
		$result = [];
		foreach(array_values($commands) as $command){
			if(!is_string($command)){
				continue;
			}
			$command = trim($command);
			if($command !== ""){
				$result[] = substr($command, 0, 256);
			}
			if(count($result) >= 32){
				break;
			}
		}
		return $result;
	}

	/**
	 * @param array<int|string, mixed> $equipment
	 * @return array<string, string>
	 */
	private static function normalizeEquipment(array $equipment) : array{
		$result = [];
		foreach($equipment as $slot => $item){
			if(!is_string($slot) || !in_array($slot, ["hand", "offhand", "helmet", "chestplate", "leggings", "boots"], true) || !is_string($item)){
				continue;
			}
			$item = trim($item);
			if($item !== ""){
				$result[$slot] = substr($item, 0, 128);
			}
		}
		return $result;
	}

	private static function normalizeAssetPath(string $path) : string{
		$path = trim($path);
		if($path === "" || strlen($path) > 128 || str_contains($path, "..") || str_contains($path, "\\") || str_starts_with($path, "/") || preg_match('/^[A-Za-z0-9._\/-]+$/D', $path) !== 1){
			return "";
		}
		return $path;
	}

	private static function normalizeUrl(string $url) : string{
		$url = trim($url);
		if(strlen($url) > 2048 || preg_match('/^https:\/\/[^\s]+$/iD', $url) !== 1){
			return "";
		}
		return $url;
	}

	private static function normalizeGeometryName(string $name) : string{
		return substr(trim($name), 0, 256);
	}

	private static function normalizeGeometryData(string $data) : string{
		return strlen($data) <= 65536 ? $data : "";
	}

	private static function normalizeEffectName(string $name, string $default) : string{
		$name = strtolower(str_replace("-", "_", trim($name)));
		return preg_match('/^[a-z0-9_]{1,32}$/D', $name) === 1 ? $name : $default;
	}

	private static function normalizeSoundType(string $name) : string{
		$name = strtolower(str_replace("-", "_", trim($name)));
		if(in_array($name, ["click", "door_bump", "explode", "pop", "totem"], true)){
			return $name;
		}
		return VanillaSoundCatalog::resolve($name) ?? "click";
	}

	private static function normalizeEntityType(string $type) : string{
		return VanillaMobCatalog::resolve($type) ?? "human";
	}

	private static function normalizeAnimationType(string $type) : string{
		$type = strtolower(str_replace("-", "_", trim($type)));
		return in_array($type, ["none", "emote", "fly", "arm_swing", "hurt", "critical_hit", "magic_critical"], true) ? $type : "none";
	}

	private static function normalizeAnimationId(string $id) : string{
		$id = str_replace(["\r", "\n"], "", trim($id));
		return substr($id, 0, 128);
	}

	private static function normalizeAnimationInterval(int $interval) : int{
		return min(86400, max(1, $interval));
	}

	private static function normalizeAnimationTrigger(string $trigger) : string{
		$trigger = strtolower(str_replace("-", "_", trim($trigger)));
		return in_array($trigger, ["timer", "spawn", "right_click", "left_click"], true) ? $trigger : "timer";
	}

	private static function normalizeInteractionMode(string $mode) : string{
		$mode = strtolower(str_replace("-", "_", trim($mode)));
		return in_array($mode, ["shared", "separate"], true) ? $mode : "shared";
	}

	private static function normalizeRotationDistance(float $distance) : float{
		return is_finite($distance) ? min(256.0, max(0.0, $distance)) : 64.0;
	}

	private static function normalizeParticleCount(int $count) : int{
		return min(64, max(1, $count));
	}

	private static function normalizeParticleSpread(float $spread) : float{
		return is_finite($spread) ? min(2.0, max(0.0, $spread)) : 0.35;
	}

	private static function normalizeRandomEmoteCooldown(float $cooldown) : float{
		return is_finite($cooldown) ? min(86400.0, max(1.0, $cooldown)) : 60.0;
	}

	/**
	 * @param array<int|string, mixed> $pool
	 * @return list<string>
	 */
	private static function normalizeEmotePool(array $pool) : array{
		/** @var list<string> $result */
		$result = [];
		foreach(array_values($pool) as $emote){
			if(is_string($emote) && trim($emote) !== ""){
				$result[] = substr(str_replace(["\r", "\n"], "", trim($emote)), 0, 128);
			}
			if(count($result) >= 128){
				break;
			}
		}
		$normalized = array_values(array_unique($result));
		/** @var list<string> $normalized */
		return $normalized;
	}

	private static function normalizePlayerCountMode(string $mode) : string{
		$mode = strtolower(str_replace("-", "_", trim($mode)));
		return in_array($mode, ["none", "server", "world", "query"], true) ? $mode : "server";
	}

	private static function normalizePort(int $port) : int{
		return min(65535, max(1, $port));
	}

	private static function normalizeQueryInterval(int $interval) : int{
		return min(86400, max(20, $interval));
	}

	private static function normalizeBlockId(string $blockId) : string{
		$blockId = strtolower(trim($blockId));
		return preg_match('/^[a-z0-9_.-]+(?::[a-z0-9_.-]+)?$/D', $blockId) === 1 ? $blockId : "";
	}

	private static function normalizeNametagVisibility(string $visibility) : string{
		$visibility = strtolower(str_replace("-", "_", trim($visibility)));
		return in_array($visibility, ["never", "hover", "always"], true) ? $visibility : "always";
	}

	private static function normalizeVariant(string $variant) : string{
		return substr(strtolower(trim(str_replace(["\r", "\n"], "", $variant))), 0, 64);
	}

	/** @param array<int|string, mixed> $data */
	private static function nametagVisibilityValue(array $data) : string{
		if(isset($data["nametag-visibility"]) && is_string($data["nametag-visibility"])){
			return self::normalizeNametagVisibility($data["nametag-visibility"]);
		}
		// Keep old configs working: true was always visible, false was hidden.
		return self::boolValue($data, "nametag-visible", true) ? "always" : "never";
	}

	private static function normalizeCooldown(float $cooldown) : float{
		if(!is_finite($cooldown)){
			return 0.0;
		}
		return min(86400.0, max(0.0, $cooldown));
	}

	private static function normalizeScale(float $scale) : float{
		if(!is_finite($scale)){
			return 1.0;
		}
		return min(4.0, max(0.1, $scale));
	}

	/** @param array<int|string, mixed> $data */
	private static function stringValue(array $data, string $key, string $default) : string{
		$value = $data[$key] ?? $default;
		return is_string($value) ? $value : $default;
	}

	/** @param array<int|string, mixed> $data */
	private static function floatValue(array $data, string $key, float $default) : float{
		$value = $data[$key] ?? $default;
		return is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)) ? (float) $value : $default;
	}

	/** @param array<int|string, mixed> $data */
	private static function intValue(array $data, string $key, int $default) : int{
		$value = $data[$key] ?? $default;
		return is_int($value) || (is_string($value) && is_numeric($value)) ? (int) $value : $default;
	}

	/** @param array<int|string, mixed> $data */
	private static function boolValue(array $data, string $key, bool $default) : bool{
		$value = $data[$key] ?? $default;
		return is_bool($value) ? $value : $default;
	}
}

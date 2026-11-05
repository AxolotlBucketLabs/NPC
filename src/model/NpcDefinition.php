<?php

declare(strict_types=1);

namespace NhanAZ\NPC\model;

use InvalidArgumentException;
use function array_values;
use function count;
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

	/**
	 * @param array<int|string, mixed> $lines
	 * @param array<int|string, mixed> $commands
	 * @param array<int|string, mixed> $equipment
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
		private bool $rotate = false,
		private bool $particle = false,
		private bool $sound = true,
		private float $scale = 1.0,
		array $equipment = [],
		private string $skinFile = "",
		private string $capeFile = "",
		private string $geometryName = "",
		private string $geometryData = "",
		private string $particleType = "flame",
		private string $soundType = "click"
	){
		self::validateId($id);
		$this->lines = self::normalizeLines($lines);
		$this->commands = self::normalizeCommands($commands);
		$this->equipment = self::normalizeEquipment($equipment);
		$this->cooldown = self::normalizeCooldown($cooldown);
		$this->scale = self::normalizeScale($scale);
		$this->skinFile = self::normalizeAssetPath($skinFile);
		$this->capeFile = self::normalizeAssetPath($capeFile);
		$this->geometryName = self::normalizeGeometryName($geometryName);
		$this->geometryData = self::normalizeGeometryData($geometryData);
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
			self::boolValue($data, "rotate", false),
			self::boolValue($data, "particle", false),
			self::boolValue($data, "sound", true),
			self::floatValue($data, "scale", 1.0),
			is_array($data["equipment"] ?? null) ? $data["equipment"] : [],
			self::stringValue($data, "skin", ""),
			self::stringValue($data, "cape", ""),
			self::stringValue($data, "geometry-name", ""),
			self::stringValue($data, "geometry-data", ""),
			self::stringValue($data, "particle-type", "flame"),
			self::stringValue($data, "sound-type", "click")
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
			"geometry-name" => $this->geometryName,
			"geometry-data" => $this->geometryData,
			"particle-type" => $this->particleType,
			"sound-type" => $this->soundType
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
	public function getGeometryName() : string{ return $this->geometryName; }
	public function getGeometryData() : string{ return $this->geometryData; }
	public function getParticleType() : string{ return $this->particleType; }
	public function getSoundType() : string{ return $this->soundType; }

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
		if(!in_array($slot, ["hand", "helmet", "chestplate", "leggings", "boots"], true)){
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
	public function setGeometry(string $name, string $data) : void{
		$this->geometryName = self::normalizeGeometryName($name);
		$this->geometryData = self::normalizeGeometryData($data);
	}
	public function setParticleType(string $type) : void{ $this->particleType = self::normalizeEffectName($type, "flame"); }
	public function setSoundType(string $type) : void{ $this->soundType = self::normalizeEffectName($type, "click"); }

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
			if(!is_string($slot) || !in_array($slot, ["hand", "helmet", "chestplate", "leggings", "boots"], true) || !is_string($item)){
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
	private static function boolValue(array $data, string $key, bool $default) : bool{
		$value = $data[$key] ?? $default;
		return is_bool($value) ? $value : $default;
	}
}

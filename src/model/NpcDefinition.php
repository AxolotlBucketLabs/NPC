<?php

declare(strict_types=1);

namespace NhanAZ\NPC\model;

use InvalidArgumentException;
use function array_values;
use function count;
use function is_array;
use function is_finite;
use function is_int;
use function is_numeric;
use function is_string;
use function max;
use function min;
use function preg_match;
use function trim;

final class NpcDefinition{
	/** @var list<string> */
	private array $lines;

	/** @var list<string> */
	private array $commands;

	/**
	 * @param array<int|string, mixed> $lines
	 * @param array<int|string, mixed> $commands
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
		private float $scale = 1.0
	){
		self::validateId($id);
		$this->lines = self::normalizeLines($lines);
		$this->commands = self::normalizeCommands($commands);
		$this->cooldown = self::normalizeCooldown($cooldown);
		$this->scale = self::normalizeScale($scale);
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
			self::floatValue($data, "scale", 1.0)
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
			"scale" => $this->scale
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

	/** @param array<int|string, mixed> $lines */
	public function setLines(array $lines) : void{ $this->lines = self::normalizeLines($lines); }
	/** @param array<int|string, mixed> $commands */
	public function setCommands(array $commands) : void{ $this->commands = self::normalizeCommands($commands); }
	public function setCooldown(float $cooldown) : void{ $this->cooldown = self::normalizeCooldown($cooldown); }
	public function setRotate(bool $rotate) : void{ $this->rotate = $rotate; }
	public function setParticle(bool $particle) : void{ $this->particle = $particle; }
	public function setSound(bool $sound) : void{ $this->sound = $sound; }
	public function setScale(float $scale) : void{ $this->scale = self::normalizeScale($scale); }

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

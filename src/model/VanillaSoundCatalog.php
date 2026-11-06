<?php

declare(strict_types=1);

namespace NhanAZ\NPC\model;

use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use ReflectionClass;
use function is_string;
use function strtolower;
use function str_replace;
use function trim;

/**
 * Resolves every LevelSoundEvent exposed by the current Axolotl-PM protocol.
 * This keeps NPC sound support aligned with the server's vanilla event table
 * without copying a second, inevitably stale list into the plugin.
 */
final class VanillaSoundCatalog{
	/** @var array<string, string>|null */
	private static ?array $aliases = null;

	/** @return list<string> */
	public static function all() : array{
		self::load();
		return array_values(array_unique(array_values(self::$aliases ?? [])));
	}

	public static function resolve(string $name) : ?string{
		self::load();
		$key = self::key($name);
		return $key === "" ? null : (self::$aliases[$key] ?? null);
	}

	private static function load() : void{
		if(self::$aliases !== null){
			return;
		}
		$aliases = [];
		if(class_exists(LevelSoundEvent::class)){
			$reflection = new ReflectionClass(LevelSoundEvent::class);
			foreach($reflection->getConstants() as $constantName => $constantValue){
				if(!is_string($constantValue)){
					continue;
				}
				$aliases[self::key($constantValue)] = $constantValue;
				$aliases[self::key($constantName)] = $constantValue;
				$aliases[self::key(str_replace(".", "_", $constantValue))] = $constantValue;
			}
		}else{
			// The standalone definition smoke test intentionally does not boot the
			// PocketMine autoloader. Keep the model usable there while the live
			// plugin still discovers the complete protocol table above.
			foreach(["ambient", "ambient.in.water", "flap", "hurt", "hit", "click", "explode", "pop", "totem"] as $event){
				$aliases[self::key($event)] = $event;
				$aliases[self::key(str_replace(".", "_", $event))] = $event;
			}
		}
		self::$aliases = $aliases;
	}

	private static function key(string $value) : string{
		return strtolower(str_replace(["-", " "], "_", trim($value)));
	}
}

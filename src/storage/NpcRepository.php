<?php

declare(strict_types=1);

namespace NhanAZ\NPC\storage;

use NhanAZ\NPC\Main;
use NhanAZ\NPC\model\NpcDefinition;
use pocketmine\utils\Config;
use function is_array;
use function is_int;

/**
 * Owns the on-disk NPC definition schema.
 *
 * The repository deliberately knows nothing about spawned entities or player
 * interactions. This keeps persistence and schema migration independent from
 * the runtime actor implementation.
 */
final class NpcRepository{
	private const DATA_VERSION = 4;

	private string $dataFile;

	public function __construct(private Main $plugin){
		$this->dataFile = $plugin->getDataFolder() . "npcs.yml";
	}

	/** @return array<string, NpcDefinition> */
	public function load() : array{
		$config = new Config($this->dataFile, Config::YAML, ["version" => self::DATA_VERSION, "npcs" => []]);
		$version = is_int($config->get("version")) ? $config->get("version") : 1;
		$rawNpcs = $config->get("npcs", []);
		if(!is_array($rawNpcs)){
			$this->plugin->getLogger()->warning("npcs.yml has an invalid 'npcs' section; ignoring it");
			return [];
		}

		$definitions = [];
		foreach($rawNpcs as $id => $raw){
			if(!is_string($id) || !is_array($raw)){
				$this->plugin->getLogger()->warning("Skipping malformed NPC definition");
				continue;
			}
			try{
				$definitions[$id] = NpcDefinition::fromArray($id, $raw);
			}catch(\Throwable $error){
				$this->plugin->getLogger()->warning("Skipping NPC '$id': " . $error->getMessage());
			}
		}

		if($version < self::DATA_VERSION){
			$this->save($definitions);
		}
		return $definitions;
	}

	/** @param array<string, NpcDefinition> $definitions */
	public function save(array $definitions) : void{
		$data = ["version" => self::DATA_VERSION, "npcs" => []];
		foreach($definitions as $id => $definition){
			$data["npcs"][$id] = $definition->toArray();
		}
		$config = new Config($this->dataFile, Config::YAML, $data);
		$config->setAll($data);
		$config->save();
	}
}

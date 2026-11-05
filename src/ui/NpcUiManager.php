<?php

declare(strict_types=1);

namespace NhanAZ\NPC\ui;

use NhanAZ\NPC\manager\NpcManager;
use dktapps\pmforms\CustomForm;
use dktapps\pmforms\CustomFormResponse;
use dktapps\pmforms\MenuForm;
use dktapps\pmforms\MenuOption;
use dktapps\pmforms\ModalForm;
use dktapps\pmforms\element\Dropdown;
use dktapps\pmforms\element\Input;
use dktapps\pmforms\element\Toggle;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function array_merge;
use function count;
use function in_array;
use function is_numeric;
use function strtolower;
use function trim;

final class NpcUiManager{
	public function __construct(private NpcManager $manager){
	}

	public function openMain(Player $player) : void{
		$player->sendForm(new MenuForm(
			"NPC Manager",
			"Choose an NPC management action.",
			[
				new MenuOption("Create NPC"),
				new MenuOption("List NPCs"),
				new MenuOption("Migrate Slapper")
			],
			function(Player $player, int $selected) : void{
				switch($selected){
					case 0: $this->openCreate($player); break;
					case 1: $this->openList($player); break;
					case 2: $this->openMigration($player); break;
				}
			},
			static function(Player $player) : void{}
		));
	}

	private function openCreate(Player $player) : void{
		$player->sendForm(new CustomForm(
			"Create NPC",
			[
				new Input("id", "Stable NPC ID", "welcome"),
				new Input("name", "Nametag", "Welcome NPC"),
				new Dropdown("type", "Entity type", ["Human", "Bee"])
			],
			function(Player $player, CustomFormResponse $response) : void{
				$id = trim($response->getString("id"));
				$name = trim($response->getString("name"));
				$type = $response->getInt("type") === 1 ? "bee" : "human";
				if($id === ""){
					$player->sendMessage(TextFormat::RED . "NPC ID cannot be empty.");
					return;
				}
				try{
					$this->manager->create($player, $id, $name);
					$this->manager->setEntityType($id, $type);
					$player->sendMessage(TextFormat::GREEN . "NPC '$id' created.");
					$this->openEdit($player, $id);
				}catch(\Throwable $error){
					$player->sendMessage(TextFormat::RED . $error->getMessage());
				}
			},
			static function(Player $player) : void{}
		));
	}

	private function openList(Player $player) : void{
		$options = [new MenuOption("Back")];
		$ids = [];
		foreach($this->manager->getDefinitions() as $id => $definition){
			$ids[] = $id;
			$options[] = new MenuOption($id . " [" . $definition->getEntityType() . "]");
		}
		$player->sendForm(new MenuForm(
			"NPCs (" . count($ids) . ")",
			"Select an NPC to edit.",
			$options,
			function(Player $player, int $selected) use ($ids) : void{
				if($selected === 0){
					$this->openMain($player);
					return;
				}
				$this->openEdit($player, $ids[$selected - 1] ?? "");
			},
			static function(Player $player) : void{}
		));
	}

	private function openEdit(Player $player, string $id) : void{
		if($this->manager->getDefinition($id) === null){
			$player->sendMessage(TextFormat::RED . "NPC not found.");
			return;
		}
		$player->sendForm(new MenuForm(
			"Edit NPC: " . $id,
			"Choose what to edit.",
			[
				new MenuOption("Basic settings"),
				new MenuOption("Actions"),
				new MenuOption("Equipment"),
				new MenuOption("Delete NPC"),
				new MenuOption("Back")
			],
			function(Player $player, int $selected) use ($id) : void{
				switch($selected){
					case 0: $this->openBasic($player, $id); break;
					case 1: $this->openActions($player, $id); break;
					case 2: $this->openEquipment($player, $id); break;
					case 3: $this->openDelete($player, $id); break;
					case 4: $this->openList($player); break;
				}
			},
			static function(Player $player) : void{}
		));
	}

	private function openBasic(Player $player, string $id) : void{
		$definition = $this->manager->getDefinition($id);
		if($definition === null){
			return;
		}
		$typeOptions = ["Human", "Bee"];
		$animationOptions = ["None", "Emote", "Fly (Bee)", "Arm swing", "Hurt", "Critical hit", "Magic critical"];
		$animationTypes = ["none", "emote", "fly", "arm_swing", "hurt", "critical_hit", "magic_critical"];
		$triggers = ["Timer", "Spawn", "Right click", "Left click"];
		$triggerValues = ["timer", "spawn", "right_click", "left_click"];
		$player->sendForm(new CustomForm(
			"Basic NPC: " . $id,
			[
				new Input("name", "Nametag line 1", "NPC", $definition->getLines()[0] ?? "NPC"),
				new Dropdown("type", "Entity type", $typeOptions, $definition->getEntityType() === "bee" ? 1 : 0),
				new Input("skin", "Skin file, player, clear or empty", "skin.rgba", $definition->getSkinFile()),
				new Input("menu", "Player-list menu name", "Optional", $definition->getMenuName()),
				new Input("cooldown", "Cooldown seconds", "0", (string) $definition->getCooldown()),
				new Input("scale", "Scale", "1", (string) $definition->getScale()),
				new Toggle("rotate", "Look at nearby players", $definition->isRotateEnabled()),
				new Toggle("particle", "Show particles", $definition->isParticleEnabled()),
				new Toggle("sound", "Play hit sound", $definition->isSoundEnabled()),
				new Input("particle_type", "Particle type", "flame", $definition->getParticleType()),
				new Input("sound_type", "Sound type", "click", $definition->getSoundType()),
				new Dropdown("animation", "Animation", $animationOptions, max(0, array_search($definition->getAnimationType(), $animationTypes, true))),
				new Input("animation_id", "Emote ID (only for emote)", "wave", $definition->getAnimationId()),
				new Input("animation_interval", "Animation interval in ticks", "100", (string) $definition->getAnimationInterval()),
				new Dropdown("animation_trigger", "Animation trigger", $triggers, max(0, array_search($definition->getAnimationTrigger(), $triggerValues, true)))
			],
			function(Player $player, CustomFormResponse $response) use ($id, $animationTypes, $triggerValues) : void{
				$name = trim($response->getString("name"));
				if($name !== ""){
					$this->manager->setLines($id, 1, $name);
				}
				$this->manager->setEntityType($id, $response->getInt("type") === 1 ? "bee" : "human");
				$skin = trim($response->getString("skin"));
				if($skin === "player"){
					$this->manager->setSkinFromPlayer($player, $id);
				}elseif($skin !== ""){
					$this->manager->setSkinFile($id, strtolower($skin) === "clear" ? "" : $skin);
				}
				$this->manager->setMenuName($id, $response->getString("menu"));
				$this->manager->setCooldown($id, is_numeric($response->getString("cooldown")) ? (float) $response->getString("cooldown") : 0.0);
				$this->manager->setScale($id, is_numeric($response->getString("scale")) ? (float) $response->getString("scale") : 1.0);
				$this->manager->setBoolean($id, "rotate", $response->getBool("rotate"));
				$this->manager->setBoolean($id, "particle", $response->getBool("particle"));
				$this->manager->setBoolean($id, "sound", $response->getBool("sound"));
				$this->manager->setEffectType($id, "particle", $response->getString("particle_type"));
				$this->manager->setEffectType($id, "sound", $response->getString("sound_type"));
				$this->manager->setAnimation(
					$id,
					$animationTypes[$response->getInt("animation")] ?? "none",
					$response->getString("animation_id"),
					is_numeric($response->getString("animation_interval")) ? (int) $response->getString("animation_interval") : 100,
					$triggerValues[$response->getInt("animation_trigger")] ?? "timer"
				);
				$player->sendMessage(TextFormat::GREEN . "NPC '$id' updated.");
				$this->openEdit($player, $id);
			},
			static function(Player $player) : void{}
		));
	}

	private function openActions(Player $player, string $id) : void{
		$definition = $this->manager->getDefinition($id);
		if($definition === null){
			return;
		}
		$options = [new MenuOption("Add action"), new MenuOption("Back")];
		$commands = $definition->getCommands();
		foreach($commands as $command){
			$options[] = new MenuOption("Remove: " . substr($command, 0, 45));
		}
		$player->sendForm(new MenuForm(
			"Actions: " . $id,
			"Actions run on click. Prefix console: for server actions.",
			$options,
			function(Player $player, int $selected) use ($id, $commands) : void{
				if($selected === 0){
					$this->openAddAction($player, $id);
				}elseif($selected === 1){
					$this->openEdit($player, $id);
				}else{
					$command = $commands[$selected - 2] ?? null;
					if($command !== null){
						$this->playerConfirmRemoveAction($player, $id, $command);
					}
				}
			},
			static function(Player $player) : void{}
		));
	}

	private function openAddAction(Player $player, string $id) : void{
		$player->sendForm(new CustomForm(
			"Add action: " . $id,
			[new Input("command", "Command", "say hello", "")],
			function(Player $player, CustomFormResponse $response) use ($id) : void{
				$command = trim($response->getString("command"));
				$definition = $this->manager->getDefinition($id);
				if($command === "" || $definition === null){
					$player->sendMessage(TextFormat::RED . "Command cannot be empty.");
					return;
				}
				$this->manager->setCommands($id, array_merge($definition->getCommands(), [$command]));
				$player->sendMessage(TextFormat::GREEN . "Action added.");
				$this->openActions($player, $id);
			},
			static function(Player $player) : void{}
		));
	}

	private function playerConfirmRemoveAction(Player $player, string $id, string $command) : void{
		$player->sendForm(new ModalForm(
			"Remove action",
			"Remove this action?\n" . $command,
			function(Player $player, bool $choice) use ($id, $command) : void{
				if($choice){
					$this->manager->removeCommand($id, $command);
					$player->sendMessage(TextFormat::GREEN . "Action removed.");
				}
				$this->openActions($player, $id);
			},
			"Remove",
			"Cancel",
			static function(Player $player) : void{}
		));
	}

	private function openEquipment(Player $player, string $id) : void{
		$definition = $this->manager->getDefinition($id);
		if($definition === null){
			return;
		}
		$equipment = $definition->getEquipment();
		$player->sendForm(new CustomForm(
			"Equipment: " . $id,
			[
				new Input("hand", "Hand item", "clear", $equipment["hand"] ?? ""),
				new Input("helmet", "Helmet", "clear", $equipment["helmet"] ?? ""),
				new Input("chestplate", "Chestplate", "clear", $equipment["chestplate"] ?? ""),
				new Input("leggings", "Leggings", "clear", $equipment["leggings"] ?? ""),
				new Input("boots", "Boots", "clear", $equipment["boots"] ?? "")
			],
			function(Player $player, CustomFormResponse $response) use ($id) : void{
				foreach(["hand", "helmet", "chestplate", "leggings", "boots"] as $slot){
					$value = trim($response->getString($slot));
					$this->manager->setEquipment($id, $slot, strtolower($value) === "clear" ? "" : $value);
				}
				$player->sendMessage(TextFormat::GREEN . "Equipment updated.");
				$this->openEdit($player, $id);
			},
			static function(Player $player) : void{}
		));
	}

	private function openDelete(Player $player, string $id) : void{
		$player->sendForm(new ModalForm(
			"Delete NPC",
			"Delete NPC '$id'? This cannot be undone from the form.",
			function(Player $player, bool $choice) use ($id) : void{
				if($choice){
					$this->manager->remove($id);
					$player->sendMessage(TextFormat::GREEN . "NPC '$id' removed.");
				}
				$this->openMain($player);
			},
			"Delete",
			"Cancel",
			static function(Player $player) : void{}
		));
	}

	private function openMigration(Player $player) : void{
		$player->sendForm(new ModalForm(
			"Migrate Slapper",
			"Import loaded Slapper Human entities and keep the old entities? Choose No to import and remove old entities.",
			function(Player $player, bool $keepOld) : void{
				$result = $this->manager->migrateSlapper(!$keepOld);
				$player->sendMessage(TextFormat::GREEN . "Migration imported=" . $result["imported"] . " skipped=" . $result["skipped"] . " errors=" . $result["errors"] . " removed=" . $result["removed"]);
				$this->openMain($player);
			},
			"Keep old",
			"Remove old",
			static function(Player $player) : void{}
		));
	}
}

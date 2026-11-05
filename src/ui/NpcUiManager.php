<?php

declare(strict_types=1);

namespace NhanAZ\NPC\ui;

use NhanAZ\NPC\manager\NpcManager;
use NhanAZ\NPC\model\EmoteCatalog;
use NhanAZ\NPC\model\VanillaMobCatalog;
use dktapps\pmforms\CustomForm;
use dktapps\pmforms\CustomFormResponse;
use dktapps\pmforms\FormIcon;
use dktapps\pmforms\MenuForm;
use dktapps\pmforms\MenuOption;
use dktapps\pmforms\ModalForm;
use dktapps\pmforms\element\Dropdown;
use dktapps\pmforms\element\Input;
use dktapps\pmforms\element\Label;
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
				new MenuOption("Browse Emotes"),
				new MenuOption("Migrate Slapper")
			],
			function(Player $player, int $selected) : void{
				switch($selected){
					case 0: $this->openCreate($player); break;
					case 1: $this->openList($player); break;
					case 2: $this->openEmoteSearch($player); break;
					case 3: $this->openMigration($player); break;
				}
			},
			static function(Player $player) : void{}
		));
	}

	/** Opens the same editor used by the list, for the sneak-right-click editor. */
	public function openEditor(Player $player, string $id) : void{
		$this->openEdit($player, $id);
	}

	private function openCreate(Player $player) : void{
		$player->sendForm(new CustomForm(
			"Create NPC",
			[
				new Input("id", "Stable NPC ID", "welcome"),
				new Input("name", "Nametag", "Welcome NPC"),
				new Input("type", "Entity type", "human, bee, zombie...", "human")
			],
			function(Player $player, CustomFormResponse $response) : void{
				$id = trim($response->getString("id"));
				$name = trim($response->getString("name"));
				$type = strtolower(trim($response->getString("type")));
				if($id === ""){
					$player->sendMessage(TextFormat::RED . "NPC ID cannot be empty.");
					return;
				}
				if(VanillaMobCatalog::resolve($type) === null){
					$player->sendMessage(TextFormat::RED . "Unknown entity type. Use /npc entities in chat.");
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
				new MenuOption("Player emotes (with images)"),
				new MenuOption("Actions"),
				new MenuOption("Equipment"),
				new MenuOption("Delete NPC"),
				new MenuOption("Back")
			],
			function(Player $player, int $selected) use ($id) : void{
				switch($selected){
					case 0: $this->openBasic($player, $id); break;
					case 1: $this->openEmoteSearch($player, $id); break;
					case 2: $this->openActions($player, $id); break;
					case 3: $this->openEquipment($player, $id); break;
					case 4: $this->openDelete($player, $id); break;
					case 5: $this->openList($player); break;
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
		$actorAnimationOptions = ["None", "Fly (Bee: wing/flight)", "Arm swing", "Hurt", "Critical hit", "Magic critical"];
		$actorAnimationTypes = ["none", "fly", "arm_swing", "hurt", "critical_hit", "magic_critical"];
		$currentAnimationType = $definition->getAnimationType();
		$triggers = ["Timer", "Spawn", "Right click", "Left click"];
		$triggerValues = ["timer", "spawn", "right_click", "left_click"];
		$player->sendForm(new CustomForm(
			"Basic NPC: " . $id,
			[
				new Input("name", "Nametag line 1", "NPC", $definition->getLines()[0] ?? "NPC"),
				new Input("type", "Entity type", "human, bee, zombie...", $definition->getEntityType()),
				new Input("skin", "Skin file, player, clear or empty", "skin.rgba", $definition->getSkinFile()),
				new Input("skin_url", "HTTPS skin URL (allowlisted host)", "https://textures.minecraft.net/...", $definition->getSkinUrl()),
				new Input("menu", "Player-list menu name", "Optional", $definition->getMenuName()),
				new Input("cooldown", "Cooldown seconds", "0", (string) $definition->getCooldown()),
				new Input("scale", "Scale", "1", (string) $definition->getScale()),
				new Toggle("nametag", "Show nametag", $definition->isNametagVisible()),
				new Toggle("rotate", "Look at nearby players", $definition->isRotateEnabled()),
				new Input("rotation_distance", "Rotation distance (blocks)", "16", (string) $definition->getRotationDistance()),
				new Input("interaction_mode", "Interaction mode: shared or separate", "shared", $definition->getInteractionMode()),
				new Toggle("particle", "Show particles", $definition->isParticleEnabled()),
				new Toggle("sound", "Play hit sound", $definition->isSoundEnabled()),
				new Input("particle_type", "Particle type", "flame", $definition->getParticleType()),
				new Input("sound_type", "Sound type", "click", $definition->getSoundType()),
				new Dropdown("actor_animation", "Actor animation (Bee fly is not a player emote)", $actorAnimationOptions, max(0, array_search(in_array($currentAnimationType, $actorAnimationTypes, true) ? $currentAnimationType : "none", $actorAnimationTypes, true))),
				new Input("emote_id", "Player emote name or UUID (blank disables)", "wave", $currentAnimationType === "emote" ? $definition->getAnimationId() : ""),
				new Input("animation_interval", "Animation interval in ticks", "100", (string) $definition->getAnimationInterval()),
				new Dropdown("animation_trigger", "Animation trigger", $triggers, max(0, array_search($definition->getAnimationTrigger(), $triggerValues, true))),
				new Input("player_count_mode", "Player count: none/server/world/query", "server", $definition->getPlayerCountMode()),
				new Input("player_count_target", "World name or query host", "", $definition->getPlayerCountMode() === "world" ? $definition->getPlayerCountWorld() : $definition->getQueryHost()),
				new Input("query_port", "Query port", "19132", (string) $definition->getQueryPort()),
				new Toggle("walk", "Enable walking path (off by default)", $definition->isWalkEnabled()),
				new Input("walk_speed", "Walk speed", "0.06", (string) $definition->getWalkSpeed()),
				new Toggle("random_emote", "Enable random emote (off by default)", $definition->isRandomEmoteEnabled()),
				new Input("random_emote_pool", "Random emote names/UUIDs, comma separated", "wave, simple clap", implode(", ", $definition->getEmotePool())),
				new Input("random_emote_cooldown", "Random emote cooldown seconds", "60", (string) $definition->getRandomEmoteCooldown())
			],
			function(Player $player, CustomFormResponse $response) use ($id, $actorAnimationTypes, $triggerValues) : void{
				$emote = trim($response->getString("emote_id"));
				if($emote !== "" && EmoteCatalog::resolve($emote) === null){
					$player->sendMessage(TextFormat::RED . "Unknown emote. Use a supported alias or a valid Bedrock emote UUID.");
					return;
				}
				$name = trim($response->getString("name"));
				if($name !== ""){
					$this->manager->setLines($id, 1, $name);
				}
				$this->manager->setEntityType($id, trim($response->getString("type")));
				$skin = trim($response->getString("skin"));
				if($skin === "player"){
					$this->manager->setSkinFromPlayer($player, $id);
				}elseif($skin !== ""){
					$this->manager->setSkinFile($id, strtolower($skin) === "clear" ? "" : $skin);
				}
				$skinUrl = trim($response->getString("skin_url"));
				if($skinUrl !== ""){
					$this->manager->setSkinUrl($id, strtolower($skinUrl) === "clear" ? "" : $skinUrl);
				}
				$this->manager->setMenuName($id, $response->getString("menu"));
				$this->manager->setCooldown($id, is_numeric($response->getString("cooldown")) ? (float) $response->getString("cooldown") : 0.0);
				$this->manager->setScale($id, is_numeric($response->getString("scale")) ? (float) $response->getString("scale") : 1.0);
				$this->manager->setNametagVisible($id, $response->getBool("nametag"));
				$this->manager->setBoolean($id, "rotate", $response->getBool("rotate"));
				if(is_numeric($response->getString("rotation_distance"))){
					$this->manager->setRotationDistance($id, (float) $response->getString("rotation_distance"));
				}
				$this->manager->setInteractionMode($id, strtolower(trim($response->getString("interaction_mode"))));
				$this->manager->setBoolean($id, "particle", $response->getBool("particle"));
				$this->manager->setBoolean($id, "sound", $response->getBool("sound"));
				$this->manager->setEffectType($id, "particle", $response->getString("particle_type"));
				$this->manager->setEffectType($id, "sound", $response->getString("sound_type"));
				$animationType = $emote !== "" ? "emote" : ($actorAnimationTypes[$response->getInt("actor_animation")] ?? "none");
				$this->manager->setAnimation(
					$id,
					$animationType,
					$emote,
					is_numeric($response->getString("animation_interval")) ? (int) $response->getString("animation_interval") : 100,
					$triggerValues[$response->getInt("animation_trigger")] ?? "timer"
				);
				$mode = strtolower(trim($response->getString("player_count_mode")));
				$target = trim($response->getString("player_count_target"));
				$this->manager->setPlayerCount($id, $mode, $mode === "world" ? $target : "", $mode === "query" ? $target : "", is_numeric($response->getString("query_port")) ? (int) $response->getString("query_port") : 19132);
				$this->manager->setBoolean($id, "walk", $response->getBool("walk"));
				if(is_numeric($response->getString("walk_speed"))){
					$this->manager->setWalkSpeed($id, (float) $response->getString("walk_speed"));
				}
				$pool = array_values(array_filter(array_map("trim", explode(",", $response->getString("random_emote_pool"))), static fn(string $value) : bool => $value !== ""));
				$this->manager->setEmotePool($id, $pool, $response->getBool("random_emote"), is_numeric($response->getString("random_emote_cooldown")) ? (float) $response->getString("random_emote_cooldown") : 60.0);
				$player->sendMessage(TextFormat::GREEN . "NPC '$id' updated.");
				$this->openEdit($player, $id);
			},
			static function(Player $player) : void{}
		));
	}

	private function openEmoteSearch(Player $player, ?string $npcId = null) : void{
		$title = $npcId === null ? "Browse player emotes" : "Choose emote: " . $npcId;
		$player->sendForm(new CustomForm(
			$title,
			[
				new Label("info", "Each result has its official Bedrock thumbnail. Select one to configure it for the NPC."),
				new Input("query", "Search title", "air guitar or wave", ""),
				new Input("page", "Page", "1", "1")
			],
			function(Player $player, CustomFormResponse $response) use ($npcId) : void{
				$query = trim($response->getString("query"));
				$page = is_numeric($response->getString("page")) ? max(1, (int) $response->getString("page")) : 1;
				$this->openEmoteResults($player, $npcId, $query, $page - 1);
			},
			static function(Player $player) : void{}
		));
	}

	private function openEmoteResults(Player $player, ?string $npcId, string $query, int $page) : void{
		$pageSize = 24;
		$matches = EmoteCatalog::search($query, $pageSize + 1, $page * $pageSize);
		$hasNext = count($matches) > $pageSize;
		if($hasNext){
			array_pop($matches);
		}
		if($matches === [] && $page > 0){
			$this->openEmoteResults($player, $npcId, $query, $page - 1);
			return;
		}

		$options = [new MenuOption("Search again")];
		$actions = ["search"];
		if($page > 0){
			$options[] = new MenuOption("Previous page");
			$actions[] = "previous";
		}
		foreach($matches as $index => $entry){
			$icon = EmoteCatalog::imageUrl($entry["uuid"]);
			$options[] = new MenuOption(
				$entry["title"] . "\n" . $entry["uuid"],
				$icon === null ? null : new FormIcon($icon)
			);
			$actions[] = "emote:" . $index;
		}
		if($hasNext){
			$options[] = new MenuOption("Next page");
			$actions[] = "next";
		}

		$first = $page * $pageSize + 1;
		$last = $first + count($matches) - 1;
		$target = $npcId === null ? "" : " for " . $npcId;
		$player->sendForm(new MenuForm(
			"Emotes " . ($page + 1) . $target,
			"Showing " . (count($matches) === 0 ? "0" : $first . "-" . $last) . " of " . EmoteCatalog::count() . ($query === "" ? "" : "\nSearch: " . $query),
			$options,
			function(Player $player, int $selected) use ($actions, $npcId, $query, $page, $matches) : void{
				$action = $actions[$selected] ?? "";
				switch($action){
					case "search":
						$this->openEmoteSearch($player, $npcId);
						return;
					case "previous":
						$this->openEmoteResults($player, $npcId, $query, max(0, $page - 1));
						return;
					case "next":
						$this->openEmoteResults($player, $npcId, $query, $page + 1);
						return;
				}
				if(str_starts_with($action, "emote:")){
					$index = (int) substr($action, 6);
					$entry = $matches[$index] ?? null;
					if($entry !== null){
						if($npcId === null){
							$player->sendMessage(TextFormat::GREEN . $entry["title"] . TextFormat::GRAY . " => " . $entry["uuid"]);
							$this->openEmoteResults($player, null, $query, $page);
						}else{
							$this->openEmoteConfigure($player, $npcId, $entry["title"], $entry["uuid"]);
						}
					}
				}
			},
			function(Player $player) use ($npcId) : void{
				if($npcId === null){
					$this->openMain($player);
				}else{
					$this->openEdit($player, $npcId);
				}
			}
		));
	}

	private function openEmoteConfigure(Player $player, string $npcId, string $title, string $uuid) : void{
		$player->sendForm(new CustomForm(
			"Apply emote: " . $title,
			[
				new Label("info", "UUID: " . $uuid . "\nThe thumbnail was shown in the previous menu."),
				new Input("interval", "Interval in ticks", "100", "100"),
				new Dropdown("trigger", "Trigger", ["Timer", "Spawn", "Right click", "Left click"])
			],
			function(Player $player, CustomFormResponse $response) use ($npcId, $uuid) : void{
				$triggers = ["timer", "spawn", "right_click", "left_click"];
				$interval = is_numeric($response->getString("interval")) ? max(1, (int) $response->getString("interval")) : 100;
				$trigger = $triggers[$response->getInt("trigger")] ?? "timer";
				if($this->manager->setAnimation($npcId, "emote", $uuid, $interval, $trigger)){
					$player->sendMessage(TextFormat::GREEN . "NPC player emote updated.");
					$this->openEdit($player, $npcId);
				}else{
					$player->sendMessage(TextFormat::RED . "NPC not found or emote is invalid.");
				}
			},
			function(Player $player) use ($npcId) : void{
				$this->openEdit($player, $npcId);
			}
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

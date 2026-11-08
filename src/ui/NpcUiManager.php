<?php

declare(strict_types=1);

namespace AxolotlBucketLabs\NPC\ui;

use AxolotlBucketLabs\NPC\manager\NpcManager;
use AxolotlBucketLabs\NPC\model\EmoteCatalog;
use AxolotlBucketLabs\NPC\model\VanillaMobCatalog;
use AxolotlBucketLabs\NPC\model\VanillaParticleCatalog;
use AxolotlBucketLabs\NPC\model\VanillaSoundCatalog;
use AxolotlBucketLabs\NPC\model\VanillaVariantCatalog;
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
use function array_filter;
use function array_keys;
use function array_merge;
use function array_slice;
use function array_values;
use function ceil;
use function count;
use function in_array;
use function is_numeric;
use function str_contains;
use function sort;
use function strtolower;
use function trim;

final class NpcUiManager{
	/** @var array<string, bool> */
	private array $editReturnsToList = [];

	public function __construct(private NpcManager $manager){
	}

	public function openMain(Player $player) : void{
		$this->editReturnsToList[strtolower($player->getName())] = true;
		$player->sendForm(new MenuForm(
			"NPC Manager",
			"Choose an NPC management action.",
			[
				new MenuOption("Create NPC"),
				new MenuOption("List NPCs")
			],
			function(Player $player, int $selected) : void{
				switch($selected){
					case 0: $this->openCreate($player); break;
					case 1: $this->openList($player); break;
				}
			},
			static function(Player $player) : void{}
		));
	}

	/** Opens the same editor used by the list, for the sneak-right-click editor. */
	public function openEditor(Player $player, string $id) : void{
		// Direct NPC editing has no list screen to return to. Closing the form is
		// enough, and nested editor screens retain this origin through the map.
		$this->editReturnsToList[strtolower($player->getName())] = false;
		$this->openEdit($player, $id);
	}

	private function openCreate(Player $player) : void{
		$suggestedId = $this->manager->getNextSuggestedId();
		$player->sendForm(new CustomForm(
			"Create NPC",
			[
				new Input("id", "Stable NPC ID", "NPC_0", $suggestedId),
				new Input("name", "Nametag", "Welcome NPC")
			],
			function(Player $player, CustomFormResponse $response) : void{
				$id = trim($response->getString("id"));
				$name = trim($response->getString("name"));
				if($id === ""){
					$player->sendMessage(TextFormat::RED . "NPC ID cannot be empty.");
					return;
				}
				try{
					$this->manager->create($player, $id, $name);
					$player->sendMessage(TextFormat::GREEN . "NPC '$id' created.");
				}catch(\Throwable $error){
					$player->sendMessage(TextFormat::RED . $error->getMessage());
				}
			},
			static function(Player $player) : void{}
		));
	}

	private function openEntityBrowser(Player $player, ?string $npcId = null) : void{
		$this->openEntityResults($player, $npcId, "", 0);
	}

	private function openEntitySearch(Player $player, ?string $npcId = null, string $initialQuery = "") : void{
		$title = $npcId === null ? "Search entity types" : "Search entity types: " . $npcId;
		$player->sendForm(new CustomForm(
			$title,
			[
				new Label("info", "Search is optional. The entity type menu also has pages and Vanilla icons."),
				new Input("query", "Search entity", "zombie, golem, falling...", $initialQuery)
			],
			function(Player $player, CustomFormResponse $response) use ($npcId) : void{
				$query = trim($response->getString("query"));
				$this->openEntityResults($player, $npcId, $query, 0);
			},
			static function(Player $player) : void{}
		));
	}

	private function openEntityResults(Player $player, ?string $npcId, string $query, int $page) : void{
		$types = VanillaMobCatalog::all();
		$normalizedQuery = strtolower(trim($query));
		$specialTypes = VanillaMobCatalog::specialActorTypes();
		if($normalizedQuery !== ""){
			$types = array_values(array_filter(
				$types,
				static fn(string $type) : bool => str_contains($type, $normalizedQuery) || str_contains(strtolower(VanillaMobCatalog::displayName($type)), $normalizedQuery)
			));
		}else{
			// Keep the object-like actors together in a dedicated submenu at the
			// end of the normal picker. Search still exposes their individual types.
			$types = array_values(array_filter(
				$types,
				static fn(string $type) : bool => !in_array($type, $specialTypes, true)
			));
		}

		$pageSize = 20;
		$pageCount = max(1, (int) ceil(count($types) / $pageSize));
		$page = min(max(0, $page), $pageCount - 1);
		$offset = $page * $pageSize;
		$pageTypes = array_slice($types, $offset, $pageSize);
		$options = [new MenuOption("Back"), new MenuOption("Search entity types")];
		$actions = ["back", "search"];
		if($page > 0){
			$options[] = new MenuOption("Previous page");
			$actions[] = "previous";
		}
		foreach($pageTypes as $type){
			$options[] = new MenuOption(
				VanillaMobCatalog::displayName($type) . "\n" . $type,
				new FormIcon(VanillaMobCatalog::iconPath($type), FormIcon::IMAGE_TYPE_PATH)
			);
			$actions[] = "entity:" . $type;
		}
		if($page + 1 < $pageCount){
			$options[] = new MenuOption("Next page");
			$actions[] = "next";
		}elseif($normalizedQuery === ""){
			$options[] = new MenuOption(
				"Special actors\nBoat, Falling Block, Minecart, Primed TNT",
				new FormIcon(VanillaMobCatalog::iconPath("boat"), FormIcon::IMAGE_TYPE_PATH)
			);
			$actions[] = "special";
		}

		$target = $npcId === null ? "" : " for " . $npcId;
		$description = "Select an entity" . $target . ".\n" . count($types) . " result(s), page " . ($page + 1) . "/" . $pageCount;
		if($normalizedQuery !== ""){
			$description .= "\nSearch: " . $query;
		}
		$player->sendForm(new MenuForm(
			"Entity types" . $target,
			$description,
			$options,
			function(Player $player, int $selected) use ($actions, $npcId, $query, $page) : void{
				$action = $actions[$selected] ?? "back";
				switch($action){
					case "back":
						if($npcId === null){
							$this->openMain($player);
						}else{
							$this->openEdit($player, $npcId);
						}
						return;
					case "search":
						$this->openEntitySearch($player, $npcId, $query);
						return;
					case "previous":
						$this->openEntityResults($player, $npcId, $query, max(0, $page - 1));
						return;
					case "next":
						$this->openEntityResults($player, $npcId, $query, $page + 1);
						return;
					case "special":
						$this->openSpecialEntityBrowser($player, $npcId);
						return;
				}
				if(str_starts_with($action, "entity:")){
					$this->selectEntityType($player, $npcId, substr($action, 7), $query, $page);
				}
			},
			static function(Player $player) : void{}
		));
	}

	private function openSpecialEntityBrowser(Player $player, ?string $npcId = null) : void{
		$options = [new MenuOption("Back")];
		$actions = ["back"];
		foreach(VanillaMobCatalog::specialActorTypes() as $type){
			$options[] = new MenuOption(
				VanillaMobCatalog::displayName($type) . "\n" . $type,
				new FormIcon(VanillaMobCatalog::iconPath($type), FormIcon::IMAGE_TYPE_PATH)
			);
			$actions[] = "entity:" . $type;
		}
		$target = $npcId === null ? "" : " for " . $npcId;
		$player->sendForm(new MenuForm(
			"Special actors" . $target,
			"These object-like actors are kept together because they use dedicated visual origins.",
			$options,
			function(Player $player, int $selected) use ($actions, $npcId) : void{
				$action = $actions[$selected] ?? "back";
				if($action === "back"){
					$this->openEntityBrowser($player, $npcId);
					return;
				}
				if(str_starts_with($action, "entity:")){
					$this->selectEntityType($player, $npcId, substr($action, 7), "", 0);
				}
			},
			static function(Player $player) : void{}
		));
	}

	private function selectEntityType(Player $player, ?string $npcId, string $type, string $query, int $page) : void{
		if($npcId === null){
			$player->sendMessage(TextFormat::GREEN . VanillaMobCatalog::displayName($type) . TextFormat::GRAY . " => " . VanillaMobCatalog::iconPath($type));
			$this->openEntityResults($player, null, $query, $page);
			return;
		}
		if($this->manager->setEntityType($npcId, $type)){
			$player->sendMessage(TextFormat::GREEN . "NPC entity type updated to " . VanillaMobCatalog::displayName($type) . ".");
		}else{
			$player->sendMessage(TextFormat::RED . "NPC not found.");
		}
		if($type === "falling_block"){
			$this->openBlockVisual($player, $npcId);
		}elseif(VanillaVariantCatalog::hasVariants($type)){
			$this->openVariant($player, $npcId);
		}else{
			$this->openEdit($player, $npcId);
		}
	}

	private function openList(Player $player) : void{
		$this->editReturnsToList[strtolower($player->getName())] = true;
		$worldCounts = [];
		foreach($this->manager->getDefinitions() as $definition){
			$world = $definition->getWorld();
			$worldCounts[$world] = ($worldCounts[$world] ?? 0) + 1;
		}
		$worlds = array_keys($worldCounts);
		sort($worlds, SORT_NATURAL | SORT_FLAG_CASE);
		$options = [new MenuOption("Back")];
		foreach($worlds as $world){
			$options[] = new MenuOption($world . " (" . $worldCounts[$world] . ")");
		}
		$player->sendForm(new MenuForm(
			"NPC worlds (" . count($this->manager->getDefinitions()) . ")",
			$worlds === [] ? "No NPCs have been created yet." : "Select a world to browse its NPCs.",
			$options,
			function(Player $player, int $selected) use ($worlds) : void{
				if($selected === 0){
					$this->openMain($player);
					return;
				}
				$this->openWorldList($player, $worlds[$selected - 1] ?? "");
			},
			static function(Player $player) : void{}
		));
	}

	private function openWorldList(Player $player, string $world) : void{
		$ids = [];
		foreach($this->manager->getDefinitions() as $id => $definition){
			if($definition->getWorld() === $world){
				$ids[] = $id;
			}
		}
		sort($ids, SORT_NATURAL | SORT_FLAG_CASE);
		$options = [new MenuOption("Back")];
		foreach($ids as $id){
			$definition = $this->manager->getDefinition($id);
			if($definition !== null){
				$options[] = new MenuOption($id . " [" . $definition->getEntityType() . "]");
			}
		}
		$player->sendForm(new MenuForm(
			"NPCs: " . $world . " (" . count($ids) . ")",
			$ids === [] ? "No NPCs exist in this world." : "Select an NPC to edit.",
			$options,
			function(Player $player, int $selected) use ($ids) : void{
				if($selected === 0){
					$this->openList($player);
					return;
				}
				$this->editReturnsToList[strtolower($player->getName())] = true;
				$this->openEdit($player, $ids[$selected - 1] ?? "");
			},
			static function(Player $player) : void{}
		));
	}

	private function openEdit(Player $player, string $id) : void{
		$definition = $this->manager->getDefinition($id);
		if($definition === null){
			$player->sendMessage(TextFormat::RED . "NPC not found.");
			return;
		}
		$entityType = VanillaMobCatalog::resolve($definition->getEntityType()) ?? $definition->getEntityType();
		$profile = VanillaMobCatalog::profile($entityType);
		$returnToList = $this->editReturnsToList[strtolower($player->getName())] ?? true;
		$isHuman = $profile->supports("appearance") && VanillaMobCatalog::isHuman($entityType);
		$canAnimateSound = $profile->supports("flight");
		$isBlock = $entityType === "falling_block";
		$hasActorAnimation = $profile->supports("actor_animation");
		$options = [
			new MenuOption("Identity & display"),
			new MenuOption("Entity type")
		];
		$actions = ["identity", "entity"];
		if(VanillaVariantCatalog::hasVariants($entityType)){
			$currentVariant = $definition->getVariant() !== "" ? $definition->getVariant() : VanillaVariantCatalog::default($entityType);
			$options[] = new MenuOption("Variant: " . VanillaVariantCatalog::label($entityType, $currentVariant));
			$actions[] = "variant";
		}
		if($isHuman || $isBlock){
			$options[] = new MenuOption("Appearance");
			$actions[] = "appearance";
		}
		if($isHuman || $hasActorAnimation){
			$options[] = new MenuOption($isHuman ? "Animations & emotes" : "Animation");
			$actions[] = "animation";
		}
		$options[] = new MenuOption("Sound & particles");
		$actions[] = "effects";
		$options[] = new MenuOption("Interaction & actions");
		$actions[] = "interaction";
		if($profile->supports("look_at")){
			$options[] = new MenuOption("Look at nearby players");
			$actions[] = "look_at";
		}
		$options[] = new MenuOption("Player count");
		$actions[] = "player_count";
		$options[] = new MenuOption("Location");
		$actions[] = "location";
		$options[] = new MenuOption("Delete NPC");
		$actions[] = "delete";
		if($returnToList){
			$options[] = new MenuOption("Back");
			$actions[] = "back";
		}
		$player->sendForm(new MenuForm(
			"Edit NPC: " . $id,
			"Type: " . VanillaMobCatalog::displayName($entityType) . ". Only compatible settings are shown.",
			$options,
			function(Player $player, int $selected) use ($id, $actions, $returnToList) : void{
				switch($actions[$selected] ?? "back"){
					case "identity": $this->openIdentity($player, $id); break;
					case "entity": $this->openEntityBrowser($player, $id); break;
					case "variant": $this->openVariant($player, $id); break;
					case "appearance": $this->openAppearance($player, $id); break;
					case "interaction": $this->openInteraction($player, $id); break;
					case "animation": $this->openAnimation($player, $id); break;
					case "effects": $this->openEffects($player, $id); break;
					case "look_at": $this->openLookAt($player, $id); break;
					case "player_count": $this->openPlayerCount($player, $id); break;
					case "location": $this->openLocation($player, $id); break;
					case "delete": $this->openDelete($player, $id); break;
					case "back":
						if($returnToList){
							$this->openList($player);
						}
						break;
				}
			},
			static function(Player $player) : void{}
		));
	}

	private function openVariant(Player $player, string $id) : void{
		$definition = $this->manager->getDefinition($id);
		if($definition === null){
			return;
		}
		$entityType = VanillaMobCatalog::resolve($definition->getEntityType()) ?? "";
		$variants = VanillaVariantCatalog::options($entityType);
		if($variants === []){
			$this->openEdit($player, $id);
			return;
		}
		$currentVariant = $definition->getVariant() !== "" ? $definition->getVariant() : VanillaVariantCatalog::default($entityType);
		$options = [new MenuOption("Back")];
		$actions = ["back"];
		foreach($variants as $variantId => $variant){
			$options[] = new MenuOption($variant["label"] . ($variantId === $currentVariant ? " (current)" : ""));
			$actions[] = $variantId;
		}
		$player->sendForm(new MenuForm(
			"Variant: " . $id,
			"Choose the saved visual/state variant for " . VanillaMobCatalog::displayName($entityType) . ".",
			$options,
			function(Player $player, int $selected) use ($id, $actions, $entityType) : void{
				$action = $actions[$selected] ?? "back";
				if($action === "back"){
					$this->openEdit($player, $id);
					return;
				}
				if($this->manager->setVariant($id, $action)){
					$player->sendMessage(TextFormat::GREEN . "NPC variant updated to " . VanillaVariantCatalog::label($entityType, $action) . ".");
				}else{
					$player->sendMessage(TextFormat::RED . "That variant is not valid for this entity.");
				}
				$this->openEdit($player, $id);
			},
			static function(Player $player) : void{}
		));
	}

	private function openIdentity(Player $player, string $id) : void{
		$definition = $this->manager->getDefinition($id);
		if($definition === null){
			return;
		}
		$nametagVisibilityValues = ["never", "hover", "always"];
		$elements = [
			new Input("name", "Nametag line 1 (blank clears)", "NPC", $definition->getLines()[0] ?? "NPC"),
			new Input("scale", "Scale", "1", (string) $definition->getScale()),
			new Dropdown("nametag_visibility", "Nametag visibility", ["Never", "Hover", "Always"], max(0, array_search($definition->getNametagVisibility(), $nametagVisibilityValues, true)))
		];
		$player->sendForm(new CustomForm(
			"Identity & display: " . $id,
			$elements,
			function(Player $player, CustomFormResponse $response) use ($id, $nametagVisibilityValues) : void{
				$name = trim($response->getString("name"));
				$this->manager->setLines($id, 1, $name);
				if(is_numeric($response->getString("scale"))){
					$this->manager->setScale($id, (float) $response->getString("scale"));
				}
				$this->manager->setNametagVisibility($id, $nametagVisibilityValues[$response->getInt("nametag_visibility")] ?? "always");
				$player->sendMessage(TextFormat::GREEN . "NPC '$id' updated.");
				$this->openEdit($player, $id);
			},
			static function(Player $player) : void{}
		));
	}

	private function openAppearance(Player $player, string $id) : void{
		$definition = $this->manager->getDefinition($id);
		if($definition === null){
			return;
		}
		$entityType = VanillaMobCatalog::resolve($definition->getEntityType()) ?? $definition->getEntityType();
		$options = [];
		$actions = [];
		if(VanillaMobCatalog::isHuman($entityType)){
			$options[] = new MenuOption("Equipment");
			$actions[] = "equipment";
			$options[] = new MenuOption("Skin source");
			$actions[] = "skin";
		}elseif($entityType === "falling_block"){
			$options[] = new MenuOption("Block visual");
			$actions[] = "block";
		}
		$options[] = new MenuOption("Back");
		$actions[] = "back";
		$player->sendForm(new MenuForm(
			"Appearance: " . $id,
			"Only settings supported by " . VanillaMobCatalog::displayName($entityType) . " are shown.",
			$options,
			function(Player $player, int $selected) use ($id, $actions) : void{
				switch($actions[$selected] ?? "back"){
					case "skin": $this->openSkin($player, $id); break;
					case "equipment": $this->openEquipment($player, $id); break;
					case "block": $this->openBlockVisual($player, $id); break;
					case "back": $this->openEdit($player, $id); break;
				}
			},
			static function(Player $player) : void{}
		));
	}

	private function openBlockVisual(Player $player, string $id) : void{
		$definition = $this->manager->getDefinition($id);
		if($definition === null || (VanillaMobCatalog::resolve($definition->getEntityType()) ?? "") !== "falling_block"){
			$this->openEdit($player, $id);
			return;
		}
		$player->sendForm(new CustomForm(
			"Block visual: " . $id,
			[
				new Label("info", "Use a modern block name such as stone or minecraft:oak_planks. Numeric legacy IDs are compatibility-only."),
				new Input("block", "Block name", "stone", $definition->getBlockId())
			],
			function(Player $player, CustomFormResponse $response) use ($id) : void{
				$block = trim($response->getString("block"));
				if($block === "" || strtolower($block) === "clear"){
					$player->sendMessage(TextFormat::RED . "A falling_block NPC needs a valid block name.");
					return;
				}
				if(!$this->manager->setBlockId($id, $block)){
					$player->sendMessage(TextFormat::RED . "That block name is invalid.");
					return;
				}
				$player->sendMessage(TextFormat::GREEN . "Block visual updated.");
				$this->openAppearance($player, $id);
			},
			static function(Player $player) : void{}
		));
	}

	private function openSkin(Player $player, string $id) : void{
		$definition = $this->manager->getDefinition($id);
		if($definition === null){
			return;
		}
		$currentSource = $definition->getSkinUrl() !== "" ? "HTTPS URL" : ($definition->getSkinFile() !== "" ? "Local skin file" : "Current/default skin");
		$player->sendForm(new MenuForm(
			"Skin source: " . $id,
			"Current source: " . $currentSource . ". Choose a saved skin source.",
			[
				new MenuOption("Use local skin file"),
				new MenuOption("Use HTTPS skin URL"),
				new MenuOption("Player skin"),
				new MenuOption("Clear custom skin"),
				new MenuOption("Back")
			],
			function(Player $player, int $selected) use ($id) : void{
				switch($selected){
					case 0:
						$this->openSkinFile($player, $id);
						break;
					case 1:
						$this->openSkinUrl($player, $id);
						break;
					case 2:
						$this->openPlayerSkin($player, $id);
						break;
					case 3:
						$ok = $this->manager->setSkinUrl($id, "") && $this->manager->setSkinFile($id, "");
						$player->sendMessage($ok ? TextFormat::GREEN . "Custom skin cleared." : TextFormat::RED . "Custom skin could not be cleared.");
						$this->openAppearance($player, $id);
						break;
					case 4:
						$this->openAppearance($player, $id);
						break;
				}
			},
			static function(Player $player) : void{}
		));
	}

	private function openPlayerSkin(Player $player, string $id) : void{
		$options = [new MenuOption("Use my current player skin")];
		$actions = ["current"];
		foreach($player->getServer()->getOnlinePlayers() as $onlinePlayer){
			if(strtolower($onlinePlayer->getName()) === strtolower($player->getName())){
				continue;
			}
			$options[] = new MenuOption("Use " . $onlinePlayer->getName() . "'s skin");
			$actions[] = "player:" . $onlinePlayer->getName();
		}
		$options[] = new MenuOption("Back");
		$actions[] = "back";
		$player->sendForm(new MenuForm(
			"Player skin: " . $id,
			"Choose the player skin to persist on this Human NPC. The other player must be online now.",
			$options,
			function(Player $player, int $selected) use ($id, $actions) : void{
				$action = $actions[$selected] ?? "back";
				if($action === "back"){
					$this->openSkin($player, $id);
					return;
				}
				if($action === "current"){
					$ok = $this->manager->setSkinUrl($id, "") && $this->manager->setSkinFromPlayer($player, $id);
					$player->sendMessage($ok ? TextFormat::GREEN . "Your player skin was saved to the NPC." : TextFormat::RED . "Your player skin could not be saved.");
					$this->openAppearance($player, $id);
					return;
				}
				if(str_starts_with($action, "player:")){
					$name = substr($action, 7);
					$target = $player->getServer()->getPlayerExact($name);
					$ok = $target !== null && $this->manager->setSkinUrl($id, "") && $this->manager->setSkinFromPlayer($target, $id);
					$player->sendMessage($ok ? TextFormat::GREEN . "Player skin from " . $name . " was saved to the NPC." : TextFormat::RED . "That player is no longer online or their skin could not be saved.");
					$this->openAppearance($player, $id);
				}
			},
			static function(Player $player) : void{}
		));
	}

	private function openSkinFile(Player $player, string $id) : void{
		$definition = $this->manager->getDefinition($id);
		if($definition === null){
			return;
		}
		$player->sendForm(new CustomForm(
			"Local skin file: " . $id,
			[
				new Label("info", "Enter a validated file under plugin_data/NPC/skins."),
				new Input("file", "Skin file", "skin.rgba", $definition->getSkinFile())
			],
			function(Player $player, CustomFormResponse $response) use ($id) : void{
				$ok = $this->manager->setSkinUrl($id, "") && $this->manager->setSkinFile($id, trim($response->getString("file")));
				$player->sendMessage($ok ? TextFormat::GREEN . "Local skin file updated." : TextFormat::RED . "Local skin file could not be updated.");
				$this->openAppearance($player, $id);
			},
			static function(Player $player) : void{}
		));
	}

	private function openSkinUrl(Player $player, string $id) : void{
		$definition = $this->manager->getDefinition($id);
		if($definition === null){
			return;
		}
		$player->sendForm(new CustomForm(
			"HTTPS skin URL: " . $id,
			[
				new Label("info", "The URL must pass the configured HTTPS host allowlist."),
				new Input("url", "Skin URL", "https://textures.minecraft.net/...", $definition->getSkinUrl())
			],
			function(Player $player, CustomFormResponse $response) use ($id) : void{
				$ok = $this->manager->setSkinFile($id, "") && $this->manager->setSkinUrl($id, trim($response->getString("url")));
				$player->sendMessage($ok ? TextFormat::GREEN . "HTTPS skin URL updated." : TextFormat::RED . "HTTPS skin URL could not be updated.");
				$this->openAppearance($player, $id);
			},
			static function(Player $player) : void{}
		));
	}

	private function openInteraction(Player $player, string $id) : void{
		$definition = $this->manager->getDefinition($id);
		if($definition === null){
			return;
		}
		$player->sendForm(new MenuForm(
			"Interaction: " . $id,
			"Shared actions run for both clicks. Separate mode additionally runs the matching click list.",
			[
				new MenuOption("Shared actions (both clicks)"),
				new MenuOption("Right-click actions"),
				new MenuOption("Left-click actions"),
				new MenuOption("Interaction mode: " . $definition->getInteractionMode()),
				new MenuOption("Cooldown: " . $definition->getCooldown() . "s"),
				new MenuOption("Back")
			],
			function(Player $player, int $selected) use ($id) : void{
				switch($selected){
					case 0: $this->openActionList($player, $id, "shared"); break;
					case 1: $this->openActionList($player, $id, "right_click"); break;
					case 2: $this->openActionList($player, $id, "left_click"); break;
					case 3: $this->openInteractionMode($player, $id); break;
					case 4: $this->openCooldown($player, $id); break;
					case 5: $this->openEdit($player, $id); break;
				}
			},
			static function(Player $player) : void{}
		));
	}

	private function openInteractionMode(Player $player, string $id) : void{
		$definition = $this->manager->getDefinition($id);
		if($definition === null){
			return;
		}
		$values = ["shared", "separate"];
		$player->sendForm(new CustomForm(
			"Interaction mode: " . $id,
			[
				new Label("info", "Shared mode runs one action list for both clicks. Separate mode also runs the matching click list."),
				new Dropdown("mode", "Mode", ["Shared", "Separate"], max(0, array_search($definition->getInteractionMode(), $values, true)))
			],
			function(Player $player, CustomFormResponse $response) use ($id, $values) : void{
				$this->manager->setInteractionMode($id, $values[$response->getInt("mode")] ?? "shared");
				$player->sendMessage(TextFormat::GREEN . "Interaction mode updated.");
				$this->openInteraction($player, $id);
			},
			static function(Player $player) : void{}
		));
	}

	private function openCooldown(Player $player, string $id) : void{
		$definition = $this->manager->getDefinition($id);
		if($definition === null){
			return;
		}
		$player->sendForm(new CustomForm(
			"Cooldown: " . $id,
			[new Input("seconds", "Seconds per player (0 disables)", "0", (string) $definition->getCooldown())],
			function(Player $player, CustomFormResponse $response) use ($id) : void{
				$value = $response->getString("seconds");
				if(!is_numeric($value) || (float) $value < 0){
					$player->sendMessage(TextFormat::RED . "Cooldown must be a non-negative number.");
					return;
				}
				$this->manager->setCooldown($id, (float) $value);
				$player->sendMessage(TextFormat::GREEN . "Cooldown updated.");
				$this->openInteraction($player, $id);
			},
			static function(Player $player) : void{}
		));
	}

	private function openAnimation(Player $player, string $id) : void{
		$definition = $this->manager->getDefinition($id);
		if($definition === null){
			return;
		}
		$entityType = VanillaMobCatalog::resolve($definition->getEntityType()) ?? $definition->getEntityType();
		$isHuman = VanillaMobCatalog::isHuman($entityType);
		if(!$isHuman && !$this->supportsActorAnimation($entityType)){
			$player->sendMessage(TextFormat::YELLOW . "This NPC type has no supported animation controls.");
			$this->openEdit($player, $id);
			return;
		}
		$options = [new MenuOption("Actor animation")];
		$actions = ["actor_animation"];
		if($isHuman){
			$options[] = new MenuOption("Player emotes");
			$actions[] = "player_emotes";
			$options[] = new MenuOption("Random emotes");
			$actions[] = "random_emotes";
		}
		$options[] = new MenuOption("Clear active animation");
		$actions[] = "clear";
		$options[] = new MenuOption("Back");
		$actions[] = "back";
		$player->sendForm(new MenuForm(
			$isHuman ? "Animation & emotes: " . $id : "Animation: " . $id,
			$isHuman ? "Actor animations and player emotes use separate controls and triggers." : "This visual mob supports actor animations only.",
			$options,
			function(Player $player, int $selected) use ($id, $actions) : void{
				switch($actions[$selected] ?? "back"){
					case "actor_animation": $this->openActorAnimationSettings($player, $id); break;
					case "player_emotes": $this->openEmoteResults($player, $id, "", 0); break;
					case "random_emotes": $this->openRandomEmotes($player, $id); break;
					case "clear":
						$this->manager->setAnimation($id, "none");
						$player->sendMessage(TextFormat::GREEN . "Active NPC animation cleared.");
						$this->openAnimation($player, $id);
						break;
					case "back": $this->openEdit($player, $id); break;
				}
			},
			static function(Player $player) : void{}
		));
	}

	private function openActorAnimationSettings(Player $player, string $id) : void{
		$definition = $this->manager->getDefinition($id);
		if($definition === null){
			return;
		}
		$entityType = VanillaMobCatalog::resolve($definition->getEntityType()) ?? $definition->getEntityType();
		$profile = VanillaMobCatalog::profile($entityType);
		$canAnimateSound = $profile->supports("flight");
		$actorValues = ["none"];
		$actorLabels = ["None"];
		if($profile->supports("flight")){
			$actorValues[] = "fly";
			$actorLabels[] = "Fly (wing/flight)";
		}
		foreach(["arm_swing" => "Arm swing", "hurt" => "Hurt", "critical_hit" => "Critical hit", "magic_critical" => "Magic critical"] as $type => $label){
			$actorValues[] = $type;
			$actorLabels[] = $label;
		}
		$triggerValues = ["timer", "spawn", "right_click", "left_click"];
		$elements = [
			new Label("info", "This screen only controls actor animations. Player emotes have their own menu and trigger settings."),
			new Dropdown("actor", "Actor animation", $actorLabels, max(0, array_search($definition->getAnimationType(), $actorValues, true)))
		];
		$elements[] = new Input("interval", "Interval in ticks", "100", (string) $definition->getAnimationInterval());
		$elements[] = new Dropdown("trigger", "Trigger", ["Timer", "Spawn", "Right click", "Left click"], max(0, array_search($definition->getAnimationTrigger(), $triggerValues, true)));
		if($canAnimateSound){
			$elements[] = new Toggle("animation_sound", "Play nearby ambient sound while flying", $definition->isAnimationSoundEnabled());
		}
		$player->sendForm(new CustomForm(
			"Actor animation: " . $id,
			$elements,
			function(Player $player, CustomFormResponse $response) use ($id, $actorValues, $triggerValues, $canAnimateSound) : void{
				$interval = is_numeric($response->getString("interval")) ? max(1, (int) $response->getString("interval")) : 100;
				$trigger = $triggerValues[$response->getInt("trigger")] ?? "timer";
				$this->manager->setAnimation($id, $actorValues[$response->getInt("actor")] ?? "none", "", $interval, $trigger);
				if($canAnimateSound){
					$this->manager->setBoolean($id, "animation_sound", $response->getBool("animation_sound"));
				}
				$player->sendMessage(TextFormat::GREEN . "Actor animation updated.");
				$this->openAnimation($player, $id);
			},
			static function(Player $player) : void{}
		));
	}

	private function openRandomEmotes(Player $player, string $id) : void{
		$definition = $this->manager->getDefinition($id);
		if($definition === null){
			return;
		}
		$player->sendForm(new CustomForm(
			"Random emotes: " . $id,
			[
				new Label("info", "This is independent from the main animation. It is disabled by default."),
				new Toggle("enabled", "Enable random emotes", $definition->isRandomEmoteEnabled()),
				new Input("pool", "Emote names/UUIDs, comma separated", "wave, simple clap", implode(", ", $definition->getEmotePool())),
				new Input("cooldown", "Cooldown seconds", "60", (string) $definition->getRandomEmoteCooldown())
			],
			function(Player $player, CustomFormResponse $response) use ($id) : void{
				$pool = array_values(array_filter(array_map("trim", explode(",", $response->getString("pool"))), static fn(string $value) : bool => $value !== ""));
				foreach($pool as $emote){
					if(EmoteCatalog::resolve($emote) === null){
						$player->sendMessage(TextFormat::RED . "Unknown emote in pool: " . $emote);
						return;
					}
				}
				$cooldown = is_numeric($response->getString("cooldown")) ? max(0.0, (float) $response->getString("cooldown")) : 60.0;
				$this->manager->setEmotePool($id, $pool, $response->getBool("enabled"), $cooldown);
				$player->sendMessage(TextFormat::GREEN . "Random emote settings updated.");
				$this->openAnimation($player, $id);
			},
			static function(Player $player) : void{}
		));
	}

	private function openEffects(Player $player, string $id) : void{
		$player->sendForm(new MenuForm(
			"Sound & particles: " . $id,
			"Sound and particle settings are independent. Choose one category.",
			[
				new MenuOption("Sound"),
				new MenuOption("Particles"),
				new MenuOption("Back")
			],
			function(Player $player, int $selected) use ($id) : void{
				switch($selected){
					case 0: $this->openSoundSettings($player, $id); break;
					case 1: $this->openParticleSettings($player, $id); break;
					default: $this->openEdit($player, $id); break;
				}
			},
			static function(Player $player) : void{}
		));
	}

	private function openSoundSettings(Player $player, string $id) : void{
		$definition = $this->manager->getDefinition($id);
		if($definition === null){
			return;
		}
		$player->sendForm(new MenuForm(
			"Sound: " . $id,
			"Choose whether interaction sounds are enabled or select a vanilla sound event.",
			[
				new MenuOption("Enabled: " . ($definition->isSoundEnabled() ? "On" : "Off")),
				new MenuOption("Sound type: " . $definition->getSoundType()),
				new MenuOption("Back")
			],
			function(Player $player, int $selected) use ($id) : void{
				switch($selected){
					case 0: $this->openSoundToggle($player, $id); break;
					case 1: $this->openSoundBrowser($player, $id); break;
					default: $this->openEffects($player, $id); break;
				}
			},
			static function(Player $player) : void{}
		));
	}

	private function openSoundToggle(Player $player, string $id) : void{
		$definition = $this->manager->getDefinition($id);
		if($definition === null){
			return;
		}
		$player->sendForm(new CustomForm(
			"Sound enabled: " . $id,
			[new Toggle("enabled", "Play interaction sound", $definition->isSoundEnabled())],
			function(Player $player, CustomFormResponse $response) use ($id) : void{
				$this->manager->setBoolean($id, "sound", $response->getBool("enabled"));
				$this->openSoundSettings($player, $id);
			},
			static function(Player $player) : void{}
		));
	}

	/** @return list<string> */
	private function soundTypes() : array{
		$types = array_merge(["click", "door_bump", "explode", "pop", "totem"], VanillaSoundCatalog::all());
		$types = array_values(array_unique($types));
		sort($types, SORT_NATURAL | SORT_FLAG_CASE);
		return $types;
	}

	private function openSoundBrowser(Player $player, string $id, string $query = "", int $page = 0) : void{
		$types = $this->soundTypes();
		$normalizedQuery = strtolower(trim($query));
		if($normalizedQuery !== ""){
			$types = array_values(array_filter($types, static fn(string $type) : bool => str_contains(strtolower($type), $normalizedQuery)));
		}
		$pageSize = 20;
		$pageCount = max(1, (int) ceil(count($types) / $pageSize));
		$page = min(max(0, $page), $pageCount - 1);
		$pageTypes = array_slice($types, $page * $pageSize, $pageSize);
		$options = [new MenuOption("Back"), new MenuOption("Search sounds")];
		$actions = ["back", "search"];
		if($page > 0){
			$options[] = new MenuOption("Previous page");
			$actions[] = "previous";
		}
		foreach($pageTypes as $type){
			$options[] = new MenuOption($type);
			$actions[] = "sound:" . $type;
		}
		if($page + 1 < $pageCount){
			$options[] = new MenuOption("Next page");
			$actions[] = "next";
		}
		$player->sendForm(new MenuForm(
			"Sound types: " . $id,
			"Showing page " . ($page + 1) . "/" . $pageCount . ($normalizedQuery === "" ? "" : "\nSearch: " . $query),
			$options,
			function(Player $player, int $selected) use ($id, $actions, $query, $page) : void{
				$action = $actions[$selected] ?? "back";
				switch($action){
					case "back": $this->openSoundSettings($player, $id); return;
					case "search": $this->openSoundSearch($player, $id, $query); return;
					case "previous": $this->openSoundBrowser($player, $id, $query, max(0, $page - 1)); return;
					case "next": $this->openSoundBrowser($player, $id, $query, $page + 1); return;
				}
				if(str_starts_with($action, "sound:")){
					$type = substr($action, 6);
					$this->manager->setEffectType($id, "sound", $type);
					$player->sendMessage(TextFormat::GREEN . "Sound type updated to " . $type . ".");
					$this->openSoundSettings($player, $id);
				}
			},
			static function(Player $player) : void{}
		));
	}

	private function openSoundSearch(Player $player, string $id, string $initialQuery = "") : void{
		$player->sendForm(new CustomForm(
			"Search sound types: " . $id,
			[new Input("query", "Search sound", "ambient, hurt, flap", $initialQuery)],
			function(Player $player, CustomFormResponse $response) use ($id) : void{
				$this->openSoundBrowser($player, $id, trim($response->getString("query")));
			},
			static function(Player $player) : void{}
		));
	}

	private function openParticleSettings(Player $player, string $id) : void{
		$definition = $this->manager->getDefinition($id);
		if($definition === null){
			return;
		}
		$player->sendForm(new MenuForm(
			"Particles: " . $id,
			"Choose whether particles are enabled, select a vanilla particle, or edit burst size.",
			[
				new MenuOption("Enabled: " . ($definition->isParticleEnabled() ? "On" : "Off")),
				new MenuOption("Particle type: " . VanillaParticleCatalog::displayName($definition->getParticleType())),
				new MenuOption("Burst settings"),
				new MenuOption("Back")
			],
			function(Player $player, int $selected) use ($id) : void{
				switch($selected){
					case 0: $this->openParticleToggle($player, $id); break;
					case 1: $this->openParticleBrowser($player, $id); break;
					case 2: $this->openParticleBurst($player, $id); break;
					default: $this->openEffects($player, $id); break;
				}
			},
			static function(Player $player) : void{}
		));
	}

	private function openParticleToggle(Player $player, string $id) : void{
		$definition = $this->manager->getDefinition($id);
		if($definition === null){
			return;
		}
		$player->sendForm(new CustomForm(
			"Particles enabled: " . $id,
			[new Toggle("enabled", "Show particles", $definition->isParticleEnabled())],
			function(Player $player, CustomFormResponse $response) use ($id) : void{
				$this->manager->setBoolean($id, "particle", $response->getBool("enabled"));
				$this->openParticleSettings($player, $id);
			},
			static function(Player $player) : void{}
		));
	}

	private function openParticleBurst(Player $player, string $id) : void{
		$definition = $this->manager->getDefinition($id);
		if($definition === null){
			return;
		}
		$player->sendForm(new CustomForm(
			"Particle burst: " . $id,
			[
				new Input("count", "Particles per burst", "8", (string) $definition->getParticleCount()),
				new Input("spread", "Particle spread", "0.35", (string) $definition->getParticleSpread())
			],
			function(Player $player, CustomFormResponse $response) use ($id) : void{
				$count = is_numeric($response->getString("count")) ? (int) $response->getString("count") : 8;
				$spread = is_numeric($response->getString("spread")) ? (float) $response->getString("spread") : 0.35;
				$this->manager->setParticleSettings($id, $count, $spread);
				$this->openParticleSettings($player, $id);
			},
			static function(Player $player) : void{}
		));
	}

	private function openParticleBrowser(Player $player, string $id, string $query = "", int $page = 0) : void{
		$types = VanillaParticleCatalog::all();
		$normalizedQuery = strtolower(trim($query));
		if($normalizedQuery !== ""){
			$types = array_values(array_filter($types, static fn(string $type) : bool => str_contains($type, $normalizedQuery) || str_contains(strtolower(VanillaParticleCatalog::displayName($type)), $normalizedQuery)));
		}
		$pageSize = 20;
		$pageCount = max(1, (int) ceil(count($types) / $pageSize));
		$page = min(max(0, $page), $pageCount - 1);
		$pageTypes = array_slice($types, $page * $pageSize, $pageSize);
		$options = [new MenuOption("Back"), new MenuOption("Search particles")];
		$actions = ["back", "search"];
		if($page > 0){
			$options[] = new MenuOption("Previous page");
			$actions[] = "previous";
		}
		foreach($pageTypes as $type){
			$options[] = new MenuOption(VanillaParticleCatalog::displayName($type) . "\n" . $type);
			$actions[] = "particle:" . $type;
		}
		if($page + 1 < $pageCount){
			$options[] = new MenuOption("Next page");
			$actions[] = "next";
		}
		$player->sendForm(new MenuForm(
			"Particle types: " . $id,
			"Showing page " . ($page + 1) . "/" . $pageCount . ($normalizedQuery === "" ? "" : "\nSearch: " . $query),
			$options,
			function(Player $player, int $selected) use ($id, $actions, $query, $page) : void{
				$action = $actions[$selected] ?? "back";
				switch($action){
					case "back": $this->openParticleSettings($player, $id); return;
					case "search": $this->openParticleSearch($player, $id, $query); return;
					case "previous": $this->openParticleBrowser($player, $id, $query, max(0, $page - 1)); return;
					case "next": $this->openParticleBrowser($player, $id, $query, $page + 1); return;
				}
				if(str_starts_with($action, "particle:")){
					$type = substr($action, 9);
					$this->manager->setEffectType($id, "particle", $type);
					$player->sendMessage(TextFormat::GREEN . "Particle type updated to " . VanillaParticleCatalog::displayName($type) . ".");
					$this->openParticleSettings($player, $id);
				}
			},
			static function(Player $player) : void{}
		));
	}

	private function openParticleSearch(Player $player, string $id, string $initialQuery = "") : void{
		$player->sendForm(new CustomForm(
			"Search particle types: " . $id,
			[new Input("query", "Search particle", "flame, enchant, smoke", $initialQuery)],
			function(Player $player, CustomFormResponse $response) use ($id) : void{
				$this->openParticleBrowser($player, $id, trim($response->getString("query")));
			},
			static function(Player $player) : void{}
		));
	}

	private function openLookAt(Player $player, string $id) : void{
		$definition = $this->manager->getDefinition($id);
		if($definition === null){
			return;
		}
		$player->sendForm(new CustomForm(
			"Look at nearby players: " . $id,
			[
				new Toggle("rotate", "Look at nearby players", $definition->isRotateEnabled()),
				new Input("rotation_distance", "Look-at distance (blocks)", "64", (string) $definition->getRotationDistance())
			],
			function(Player $player, CustomFormResponse $response) use ($id) : void{
				$this->manager->setBoolean($id, "rotate", $response->getBool("rotate"));
				if(is_numeric($response->getString("rotation_distance"))){
					$this->manager->setRotationDistance($id, max(0.0, (float) $response->getString("rotation_distance")));
				}
				$player->sendMessage(TextFormat::GREEN . "Look-at settings updated.");
				$this->openEdit($player, $id);
			},
			static function(Player $player) : void{}
		));
	}

	private function openPlayerCount(Player $player, string $id) : void{
		$definition = $this->manager->getDefinition($id);
		if($definition === null){
			return;
		}
		$modeLabels = [
			"none" => "Disabled",
			"server" => "This server",
			"world" => "This world",
			"query" => "BedrockQuery host"
		];
		$options = [];
		$actions = [];
		foreach($modeLabels as $mode => $label){
			$options[] = new MenuOption($label . ($definition->getPlayerCountMode() === $mode ? " (current)" : ""));
			$actions[] = $mode;
		}
		$options[] = new MenuOption("Back");
		$actions[] = "back";
		$player->sendForm(new MenuForm(
			"Player count: " . $id,
			"Choose one mode. World and query modes open their own target form.",
			$options,
			function(Player $player, int $selected) use ($id, $actions) : void{
				$action = $actions[$selected] ?? "back";
				switch($action){
					case "none":
					case "server":
						$this->manager->setPlayerCount($id, $action);
						$player->sendMessage(TextFormat::GREEN . "Player count mode updated.");
						$this->openPlayerCount($player, $id);
						break;
					case "world": $this->openPlayerCountWorld($player, $id); break;
					case "query": $this->openPlayerCountQuery($player, $id); break;
					default: $this->openEdit($player, $id); break;
				}
			},
			static function(Player $player) : void{}
		));
	}

	private function openPlayerCountWorld(Player $player, string $id) : void{
		$definition = $this->manager->getDefinition($id);
		if($definition === null){
			return;
		}
		$player->sendForm(new CustomForm(
			"Player count world: " . $id,
			[
				new Label("info", "Count players in one loaded world."),
				new Input("world", "World name", "world", $definition->getPlayerCountWorld())
			],
			function(Player $player, CustomFormResponse $response) use ($id, $definition) : void{
				$world = trim($response->getString("world"));
				if($world === ""){
					$player->sendMessage(TextFormat::RED . "Enter a world name.");
					return;
				}
				$this->manager->setPlayerCount($id, "world", $world, "", $definition->getQueryPort(), $definition->getQueryInterval());
				$player->sendMessage(TextFormat::GREEN . "Player count world updated.");
				$this->openPlayerCount($player, $id);
			},
			static function(Player $player) : void{}
		));
	}

	private function openPlayerCountQuery(Player $player, string $id) : void{
		$definition = $this->manager->getDefinition($id);
		if($definition === null){
			return;
		}
		$player->sendForm(new CustomForm(
			"Player count query: " . $id,
			[
				new Label("info", "Query a Bedrock server using its host and port."),
				new Input("host", "Query host", "127.0.0.1", $definition->getQueryHost()),
				new Input("port", "Query port", "19132", (string) $definition->getQueryPort())
			],
			function(Player $player, CustomFormResponse $response) use ($id, $definition) : void{
				$host = trim($response->getString("host"));
				$port = is_numeric($response->getString("port")) ? max(1, (int) $response->getString("port")) : 19132;
				if($host === ""){
					$player->sendMessage(TextFormat::RED . "Enter a query host.");
					return;
				}
				$this->manager->setPlayerCount($id, "query", "", $host, $port, $definition->getQueryInterval());
				$player->sendMessage(TextFormat::GREEN . "Player count query target updated.");
				$this->openPlayerCount($player, $id);
			},
			static function(Player $player) : void{}
		));
	}

	private function openLocation(Player $player, string $id) : void{
		$player->sendForm(new MenuForm(
			"Location: " . $id,
			"Move the NPC to your position, or teleport yourself to it.",
			[
				new MenuOption("Move NPC here"),
				new MenuOption("Teleport to NPC"),
				new MenuOption("Back")
			],
			function(Player $player, int $selected) use ($id) : void{
				switch($selected){
					case 0:
						if($this->manager->move($player, $id)){
							$player->sendMessage(TextFormat::GREEN . "NPC moved to your position.");
						}else{
							$player->sendMessage(TextFormat::RED . "NPC not found.");
						}
						$this->openLocation($player, $id);
						break;
					case 1:
						$player->sendMessage($this->manager->teleport($player, $id) ? TextFormat::GREEN . "Teleported to NPC." : TextFormat::RED . "NPC is not spawned.");
						$this->openLocation($player, $id);
						break;
					case 2: $this->openEdit($player, $id); break;
				}
			},
			static function(Player $player) : void{}
		));
	}

	private function openEmoteSearch(Player $player, ?string $npcId = null) : void{
		$title = $npcId === null ? "Player emotes" : "Choose emote: " . $npcId;
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

		$options = [new MenuOption("Search emotes")];
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
			static function(Player $player) : void{}
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
					$this->openAnimation($player, $npcId);
				}else{
					$player->sendMessage(TextFormat::RED . "NPC not found or emote is invalid.");
				}
			},
			static function(Player $player) : void{}
		));
	}

	private function openActionList(Player $player, string $id, string $trigger) : void{
		$definition = $this->manager->getDefinition($id);
		if($definition === null){
			return;
		}
		$commands = match($trigger){
			"left_click" => $definition->getHitCommands(),
			"right_click" => $definition->getInteractCommands(),
			default => $definition->getCommands()
		};
		$label = match($trigger){
			"left_click" => "Left-click actions",
			"right_click" => "Right-click actions",
			default => "Shared actions"
		};
		$options = [new MenuOption("Add action"), new MenuOption("Back")];
		foreach($commands as $command){
			$options[] = new MenuOption("Remove: " . substr($command, 0, 45));
		}
		$player->sendForm(new MenuForm(
			$label . ": " . $id,
			$trigger === "shared" ? "Runs for both clicks. Prefix console: for server actions." : "Runs only for this click when interaction mode is separate.",
			$options,
			function(Player $player, int $selected) use ($id, $trigger, $commands) : void{
				if($selected === 0){
					$this->openAddAction($player, $id, $trigger);
				}elseif($selected === 1){
					$this->openInteraction($player, $id);
				}else{
					$command = $commands[$selected - 2] ?? null;
					if($command !== null){
						$this->playerConfirmRemoveAction($player, $id, $trigger, $command);
					}
				}
			},
			static function(Player $player) : void{}
		));
	}

	private function openAddAction(Player $player, string $id, string $trigger) : void{
		$label = match($trigger){
			"left_click" => "left-click",
			"right_click" => "right-click",
			default => "shared"
		};
		$player->sendForm(new CustomForm(
			"Add $label action: " . $id,
			[new Input("command", "Command", "say hello", "")],
			function(Player $player, CustomFormResponse $response) use ($id, $trigger) : void{
				$command = trim($response->getString("command"));
				$definition = $this->manager->getDefinition($id);
				if($command === "" || $definition === null){
					$player->sendMessage(TextFormat::RED . "Command cannot be empty.");
					return;
				}
				if($trigger === "shared"){
					$this->manager->setCommands($id, array_merge($definition->getCommands(), [$command]));
				}else{
					$commands = $trigger === "left_click" ? $definition->getHitCommands() : $definition->getInteractCommands();
					$this->manager->setTriggerCommands($id, $trigger, array_merge($commands, [$command]));
				}
				$player->sendMessage(TextFormat::GREEN . "Action added.");
				$this->openActionList($player, $id, $trigger);
			},
			static function(Player $player) : void{}
		));
	}

	private function playerConfirmRemoveAction(Player $player, string $id, string $trigger, string $command) : void{
		$player->sendForm(new ModalForm(
			"Remove action",
			"Remove this action?\n" . $command,
			function(Player $player, bool $choice) use ($id, $trigger, $command) : void{
				if($choice){
					$definition = $this->manager->getDefinition($id);
					if($definition !== null){
						if($trigger === "shared"){
							$this->manager->removeCommand($id, $command);
						}else{
							$commands = $trigger === "left_click" ? $definition->getHitCommands() : $definition->getInteractCommands();
							$commands = array_values(array_filter($commands, static fn(string $configured) : bool => $configured !== $command));
							$this->manager->setTriggerCommands($id, $trigger, $commands);
						}
					}
					$player->sendMessage(TextFormat::GREEN . "Action removed.");
				}
				$this->openActionList($player, $id, $trigger);
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
				new Label("info", "Toggle slots to copy the matching item from your current player loadout."),
				new Toggle("sync_all", "Copy all equipment from me", false),
				new Toggle("hand", "Main hand" . $this->equipmentSummary($equipment, "hand"), false),
				new Toggle("offhand", "Off-hand" . $this->equipmentSummary($equipment, "offhand"), false),
				new Toggle("helmet", "Helmet" . $this->equipmentSummary($equipment, "helmet"), false),
				new Toggle("chestplate", "Chestplate" . $this->equipmentSummary($equipment, "chestplate"), false),
				new Toggle("leggings", "Leggings" . $this->equipmentSummary($equipment, "leggings"), false),
				new Toggle("boots", "Boots" . $this->equipmentSummary($equipment, "boots"), false)
			],
			function(Player $player, CustomFormResponse $response) use ($id) : void{
				$slots = null;
				if(!$response->getBool("sync_all")){
					$slots = [];
					foreach(["hand", "offhand", "helmet", "chestplate", "leggings", "boots"] as $slot){
						if($response->getBool($slot)){
							$slots[] = $slot;
						}
					}
				}
				if($slots === []){
					$player->sendMessage(TextFormat::YELLOW . "No equipment slot was selected. Nothing changed.");
					$this->openAppearance($player, $id);
					return;
				}
				$ok = $this->manager->setEquipmentFromPlayer($player, $id, $slots);
				$player->sendMessage($ok ? TextFormat::GREEN . "Equipment copied from your current loadout." : TextFormat::RED . "Equipment could not be updated.");
				$this->openAppearance($player, $id);
			},
			static function(Player $player) : void{}
		));
	}

	/** @param array<string, string> $equipment */
	private function equipmentSummary(array $equipment, string $slot) : string{
		return " [current: " . ($equipment[$slot] ?? "empty") . "]";
	}

	private function openDelete(Player $player, string $id) : void{
		$player->sendForm(new ModalForm(
			"Delete NPC",
			"Delete NPC '$id'? This cannot be undone from the form.",
			function(Player $player, bool $choice) use ($id) : void{
				if($choice){
					if($this->manager->remove($id)){
						$player->sendMessage(TextFormat::GREEN . "NPC '$id' removed.");
					}else{
						$player->sendMessage(TextFormat::RED . "NPC '$id' no longer exists.");
					}
					return;
				}
				$this->openEdit($player, $id);
			},
			"Delete",
			"Cancel",
			static function(Player $player) : void{}
		));
	}

	private function supportsActorAnimation(string $entityType) : bool{
		return VanillaMobCatalog::profile($entityType)->supports("actor_animation");
	}

}

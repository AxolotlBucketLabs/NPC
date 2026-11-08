<?php

declare(strict_types=1);

namespace AxolotlBucketLabs\NPC\ui;

use AxolotlBucketLabs\NPC\manager\NpcManager;
use AxolotlBucketLabs\NPC\model\EmoteCatalog;
use AxolotlBucketLabs\NPC\model\VanillaMobCatalog;
use AxolotlBucketLabs\NPC\model\VanillaParticleCatalog;
use AxolotlBucketLabs\NPC\model\VanillaSoundCatalog;
use AxolotlBucketLabs\NPC\model\VanillaVariantCatalog;
use AxolotlBucketLabs\NPC\translation\Translator;
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

	public function __construct(private NpcManager $manager, private Translator $translator){
	}

	/** @param array<string, scalar> $parameters */
	private function t(string $key, array $parameters = []) : string{
		return $this->translator->translate($key, $parameters);
	}

	public function openMain(Player $player) : void{
		$this->editReturnsToList[strtolower($player->getName())] = true;
		$player->sendForm(new MenuForm(
			$this->t("ui.manager_title"),
			$this->t("ui.manager_description"),
			[
				new MenuOption($this->t("ui.create_npc")),
				new MenuOption($this->t("ui.list_npcs"))
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
			$this->t("ui.create_title"),
			[
				new Input("id", $this->t("ui.stable_id"), "NPC_0", $suggestedId),
				new Input("name", $this->t("ui.nametag"), $this->t("ui.welcome_npc"))
			],
			function(Player $player, CustomFormResponse $response) : void{
				$id = trim($response->getString("id"));
				$name = trim($response->getString("name"));
				if($id === ""){
					$player->sendMessage(TextFormat::RED . $this->t("ui.id_empty"));
					return;
				}
				try{
					$this->manager->create($player, $id, $name);
					$player->sendMessage(TextFormat::GREEN . $this->t("ui.created", ["id" => $id]));
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
		$title = $npcId === null ? $this->t("ui.search_entity_types") : $this->t("ui.search_entity_types_for", ["id" => $npcId]);
		$player->sendForm(new CustomForm(
			$title,
			[
				new Label("info", $this->t("ui.entity_search_info")),
				new Input("query", $this->t("ui.search_entity"), "zombie, golem, falling...", $initialQuery)
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
		$options = [new MenuOption($this->t("ui.back")), new MenuOption($this->t("ui.search_entity_types"))];
		$actions = ["back", "search"];
		if($page > 0){
			$options[] = new MenuOption($this->t("ui.previous_page"));
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
			$options[] = new MenuOption($this->t("ui.next_page"));
			$actions[] = "next";
		}elseif($normalizedQuery === ""){
			$options[] = new MenuOption(
				$this->t("ui.special_actors") . "\nBoat, Falling Block, Minecart, Primed TNT",
				new FormIcon(VanillaMobCatalog::iconPath("boat"), FormIcon::IMAGE_TYPE_PATH)
			);
			$actions[] = "special";
		}

		$target = $npcId === null ? "" : $this->t("ui.for_id", ["id" => $npcId]);
		$description = $this->t("ui.entity_results", ["target" => $target, "count" => count($types), "page" => $page + 1, "pages" => $pageCount]);
		if($normalizedQuery !== ""){
			$description .= "\n" . $this->t("ui.search_value", ["query" => $query]);
		}
		$player->sendForm(new MenuForm(
			$this->t("ui.entity_types_title", ["target" => $target]),
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
		$options = [new MenuOption($this->t("ui.back"))];
		$actions = ["back"];
		foreach(VanillaMobCatalog::specialActorTypes() as $type){
			$options[] = new MenuOption(
				VanillaMobCatalog::displayName($type) . "\n" . $type,
				new FormIcon(VanillaMobCatalog::iconPath($type), FormIcon::IMAGE_TYPE_PATH)
			);
			$actions[] = "entity:" . $type;
		}
		$target = $npcId === null ? "" : $this->t("ui.for_id", ["id" => $npcId]);
		$player->sendForm(new MenuForm(
			$this->t("ui.special_actors_title", ["target" => $target]),
			$this->t("ui.special_actors_description"),
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
			$player->sendMessage(TextFormat::GREEN . $this->t("ui.entity_type_updated", ["type" => VanillaMobCatalog::displayName($type)]));
		}else{
			$player->sendMessage(TextFormat::RED . $this->t("cmd.not_found"));
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
		$options = [new MenuOption($this->t("ui.back"))];
		foreach($worlds as $world){
			$options[] = new MenuOption($world . " (" . $worldCounts[$world] . ")");
		}
		$player->sendForm(new MenuForm(
			$this->t("ui.npc_worlds", ["count" => count($this->manager->getDefinitions())]),
			$worlds === [] ? $this->t("ui.no_npcs") : $this->t("ui.select_world"),
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
		$options = [new MenuOption($this->t("ui.back"))];
		foreach($ids as $id){
			$definition = $this->manager->getDefinition($id);
			if($definition !== null){
				$options[] = new MenuOption($id . " [" . $definition->getEntityType() . "]");
			}
		}
		$player->sendForm(new MenuForm(
			$this->t("ui.npcs_in_world", ["world" => $world, "count" => count($ids)]),
			$ids === [] ? $this->t("ui.no_npcs_in_world") : $this->t("ui.select_npc"),
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
			$player->sendMessage(TextFormat::RED . $this->t("cmd.not_found"));
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
			new MenuOption($this->t("ui.identity_display")),
			new MenuOption($this->t("ui.entity_type"))
		];
		$actions = ["identity", "entity"];
		if(VanillaVariantCatalog::hasVariants($entityType)){
			$currentVariant = $definition->getVariant() !== "" ? $definition->getVariant() : VanillaVariantCatalog::default($entityType);
			$options[] = new MenuOption($this->t("ui.variant", ["value" => VanillaVariantCatalog::label($entityType, $currentVariant)]));
			$actions[] = "variant";
		}
		if($isHuman || $isBlock){
			$options[] = new MenuOption($this->t("ui.appearance"));
			$actions[] = "appearance";
		}
		if($isHuman || $hasActorAnimation){
			$options[] = new MenuOption($isHuman ? $this->t("ui.animations_emotes") : $this->t("ui.animation"));
			$actions[] = "animation";
		}
		$options[] = new MenuOption($this->t("ui.sound_particles"));
		$actions[] = "effects";
		$options[] = new MenuOption($this->t("ui.interaction_actions"));
		$actions[] = "interaction";
		if($profile->supports("look_at")){
			$options[] = new MenuOption($this->t("ui.look_at_nearby_players"));
			$actions[] = "look_at";
		}
		$options[] = new MenuOption($this->t("ui.player_count"));
		$actions[] = "player_count";
		$options[] = new MenuOption($this->t("ui.location"));
		$actions[] = "location";
		$options[] = new MenuOption($this->t("ui.delete_npc"));
		$actions[] = "delete";
		if($returnToList){
			$options[] = new MenuOption($this->t("ui.back"));
			$actions[] = "back";
		}
		$player->sendForm(new MenuForm(
			$this->t("ui.edit_title", ["id" => $id]),
			$this->t("ui.edit_description", ["type" => VanillaMobCatalog::displayName($entityType)]),
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
		$options = [new MenuOption($this->t("ui.back"))];
		$actions = ["back"];
		foreach($variants as $variantId => $variant){
			$options[] = new MenuOption($variant["label"] . ($variantId === $currentVariant ? " (" . $this->t("ui.current") . ")" : ""));
			$actions[] = $variantId;
		}
		$player->sendForm(new MenuForm(
			$this->t("ui.variant_title", ["id" => $id]),
			$this->t("ui.variant_description", ["type" => VanillaMobCatalog::displayName($entityType)]),
			$options,
			function(Player $player, int $selected) use ($id, $actions, $entityType) : void{
				$action = $actions[$selected] ?? "back";
				if($action === "back"){
					$this->openEdit($player, $id);
					return;
				}
				if($this->manager->setVariant($id, $action)){
					$player->sendMessage(TextFormat::GREEN . $this->t("ui.variant_updated", ["value" => VanillaVariantCatalog::label($entityType, $action)]));
				}else{
					$player->sendMessage(TextFormat::RED . $this->t("ui.variant_invalid"));
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
			new Input("name", $this->t("ui.nametag_line"), "NPC", $definition->getLines()[0] ?? "NPC"),
			new Input("scale", $this->t("ui.scale"), "1", (string) $definition->getScale()),
			new Dropdown("nametag_visibility", $this->t("ui.nametag_visibility"), [$this->t("ui.never"), $this->t("ui.hover"), $this->t("ui.always")], max(0, array_search($definition->getNametagVisibility(), $nametagVisibilityValues, true)))
		];
		$player->sendForm(new CustomForm(
			$this->t("ui.identity_title", ["id" => $id]),
			$elements,
			function(Player $player, CustomFormResponse $response) use ($id, $nametagVisibilityValues) : void{
				$name = trim($response->getString("name"));
				$this->manager->setLines($id, 1, $name);
				if(is_numeric($response->getString("scale"))){
					$this->manager->setScale($id, (float) $response->getString("scale"));
				}
				$this->manager->setNametagVisibility($id, $nametagVisibilityValues[$response->getInt("nametag_visibility")] ?? "always");
				$player->sendMessage(TextFormat::GREEN . $this->t("ui.updated", ["id" => $id]));
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
			$options[] = new MenuOption($this->t("ui.equipment"));
			$actions[] = "equipment";
			$options[] = new MenuOption($this->t("ui.skin_source"));
			$actions[] = "skin";
		}elseif($entityType === "falling_block"){
			$options[] = new MenuOption($this->t("ui.block_visual"));
			$actions[] = "block";
		}
		$options[] = new MenuOption($this->t("ui.back"));
		$actions[] = "back";
		$player->sendForm(new MenuForm(
			$this->t("ui.appearance_title", ["id" => $id]),
			$this->t("ui.appearance_description", ["type" => VanillaMobCatalog::displayName($entityType)]),
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
			$this->t("ui.block_visual_title", ["id" => $id]),
			[
				new Label("info", $this->t("ui.block_visual_info")),
				new Input("block", $this->t("ui.block_name"), "stone", $definition->getBlockId())
			],
			function(Player $player, CustomFormResponse $response) use ($id) : void{
				$block = trim($response->getString("block"));
				if($block === "" || strtolower($block) === "clear"){
					$player->sendMessage(TextFormat::RED . $this->t("ui.block_required"));
					return;
				}
				if(!$this->manager->setBlockId($id, $block)){
					$player->sendMessage(TextFormat::RED . $this->t("ui.block_invalid"));
					return;
				}
				$player->sendMessage(TextFormat::GREEN . $this->t("ui.block_updated"));
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
			$this->t("ui.skin_source_title", ["id" => $id]),
			$this->t("ui.skin_source_description", ["source" => $currentSource]),
			[
				new MenuOption($this->t("ui.use_local_skin")),
				new MenuOption($this->t("ui.use_https_skin")),
				new MenuOption($this->t("ui.player_skin")),
				new MenuOption($this->t("ui.clear_custom_skin")),
				new MenuOption($this->t("ui.back"))
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
						$player->sendMessage($ok ? TextFormat::GREEN . $this->t("ui.custom_skin_cleared") : TextFormat::RED . $this->t("ui.custom_skin_clear_failed"));
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
		$options = [new MenuOption($this->t("ui.use_current_skin"))];
		$actions = ["current"];
		foreach($player->getServer()->getOnlinePlayers() as $onlinePlayer){
			if(strtolower($onlinePlayer->getName()) === strtolower($player->getName())){
				continue;
			}
			$options[] = new MenuOption($this->t("ui.use_player_skin", ["name" => $onlinePlayer->getName()]));
			$actions[] = "player:" . $onlinePlayer->getName();
		}
		$options[] = new MenuOption($this->t("ui.back"));
		$actions[] = "back";
		$player->sendForm(new MenuForm(
			$this->t("ui.player_skin_title", ["id" => $id]),
			$this->t("ui.player_skin_description"),
			$options,
			function(Player $player, int $selected) use ($id, $actions) : void{
				$action = $actions[$selected] ?? "back";
				if($action === "back"){
					$this->openSkin($player, $id);
					return;
				}
				if($action === "current"){
					$ok = $this->manager->setSkinUrl($id, "") && $this->manager->setSkinFromPlayer($player, $id);
					$player->sendMessage($ok ? TextFormat::GREEN . $this->t("ui.current_skin_saved") : TextFormat::RED . $this->t("ui.current_skin_save_failed"));
					$this->openAppearance($player, $id);
					return;
				}
				if(str_starts_with($action, "player:")){
					$name = substr($action, 7);
					$target = $player->getServer()->getPlayerExact($name);
					$ok = $target !== null && $this->manager->setSkinUrl($id, "") && $this->manager->setSkinFromPlayer($target, $id);
					$player->sendMessage($ok ? TextFormat::GREEN . $this->t("ui.other_skin_saved", ["name" => $name]) : TextFormat::RED . $this->t("ui.other_skin_save_failed"));
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
			$this->t("ui.local_skin_title", ["id" => $id]),
			[
				new Label("info", $this->t("ui.local_skin_info")),
				new Input("file", $this->t("ui.skin_file"), "skin.rgba", $definition->getSkinFile())
			],
			function(Player $player, CustomFormResponse $response) use ($id) : void{
				$ok = $this->manager->setSkinUrl($id, "") && $this->manager->setSkinFile($id, trim($response->getString("file")));
				$player->sendMessage($ok ? TextFormat::GREEN . $this->t("ui.local_skin_updated") : TextFormat::RED . $this->t("ui.local_skin_update_failed"));
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
			$this->t("ui.skin_url_title", ["id" => $id]),
			[
				new Label("info", $this->t("ui.skin_url_info")),
				new Input("url", $this->t("ui.skin_url"), "https://textures.minecraft.net/...", $definition->getSkinUrl())
			],
			function(Player $player, CustomFormResponse $response) use ($id) : void{
				$ok = $this->manager->setSkinFile($id, "") && $this->manager->setSkinUrl($id, trim($response->getString("url")));
				$player->sendMessage($ok ? TextFormat::GREEN . $this->t("ui.skin_url_updated") : TextFormat::RED . $this->t("ui.skin_url_update_failed"));
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
			$this->t("ui.interaction_title", ["id" => $id]),
			$this->t("ui.interaction_description"),
			[
				new MenuOption($this->t("ui.shared_actions")),
				new MenuOption($this->t("ui.right_click_actions")),
				new MenuOption($this->t("ui.left_click_actions")),
				new MenuOption($this->t("ui.interaction_mode", ["mode" => $definition->getInteractionMode()])),
				new MenuOption($this->t("ui.cooldown", ["seconds" => $definition->getCooldown()])),
				new MenuOption($this->t("ui.back"))
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
			$this->t("ui.interaction_mode_title", ["id" => $id]),
			[
				new Label("info", $this->t("ui.interaction_mode_info")),
				new Dropdown("mode", $this->t("ui.mode"), [$this->t("ui.shared"), $this->t("ui.separate")], max(0, array_search($definition->getInteractionMode(), $values, true)))
			],
			function(Player $player, CustomFormResponse $response) use ($id, $values) : void{
				$this->manager->setInteractionMode($id, $values[$response->getInt("mode")] ?? "shared");
				$player->sendMessage(TextFormat::GREEN . $this->t("cmd.interaction_updated"));
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
			$this->t("ui.cooldown_title", ["id" => $id]),
			[new Input("seconds", $this->t("ui.seconds_per_player"), "0", (string) $definition->getCooldown())],
			function(Player $player, CustomFormResponse $response) use ($id) : void{
				$value = $response->getString("seconds");
				if(!is_numeric($value) || (float) $value < 0){
					$player->sendMessage(TextFormat::RED . $this->t("ui.cooldown_invalid"));
					return;
				}
				$this->manager->setCooldown($id, (float) $value);
				$player->sendMessage(TextFormat::GREEN . $this->t("ui.cooldown_updated"));
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
			$player->sendMessage(TextFormat::YELLOW . $this->t("ui.no_animation_controls"));
			$this->openEdit($player, $id);
			return;
		}
		$options = [new MenuOption($this->t("ui.actor_animation"))];
		$actions = ["actor_animation"];
		if($isHuman){
			$options[] = new MenuOption($this->t("ui.player_emotes"));
			$actions[] = "player_emotes";
			$options[] = new MenuOption($this->t("ui.random_emotes"));
			$actions[] = "random_emotes";
		}
		$options[] = new MenuOption($this->t("ui.clear_animation"));
		$actions[] = "clear";
		$options[] = new MenuOption($this->t("ui.back"));
		$actions[] = "back";
		$player->sendForm(new MenuForm(
			$isHuman ? $this->t("ui.animation_emotes_title", ["id" => $id]) : $this->t("ui.animation_title", ["id" => $id]),
			$isHuman ? $this->t("ui.animation_emotes_description") : $this->t("ui.animation_description"),
			$options,
			function(Player $player, int $selected) use ($id, $actions) : void{
				switch($actions[$selected] ?? "back"){
					case "actor_animation": $this->openActorAnimationSettings($player, $id); break;
					case "player_emotes": $this->openEmoteResults($player, $id, "", 0); break;
					case "random_emotes": $this->openRandomEmotes($player, $id); break;
					case "clear":
						$this->manager->setAnimation($id, "none");
						$player->sendMessage(TextFormat::GREEN . $this->t("ui.animation_cleared"));
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
		$actorLabels = [$this->t("ui.none")];
		if($profile->supports("flight")){
			$actorValues[] = "fly";
			$actorLabels[] = $this->t("ui.fly_animation");
		}
		foreach(["arm_swing" => $this->t("ui.arm_swing"), "hurt" => $this->t("ui.hurt"), "critical_hit" => $this->t("ui.critical_hit"), "magic_critical" => $this->t("ui.magic_critical")] as $type => $label){
			$actorValues[] = $type;
			$actorLabels[] = $label;
		}
		$triggerValues = ["timer", "spawn", "right_click", "left_click"];
		$elements = [
			new Label("info", $this->t("ui.actor_animation_info")),
			new Dropdown("actor", $this->t("ui.actor_animation"), $actorLabels, max(0, array_search($definition->getAnimationType(), $actorValues, true)))
		];
		$elements[] = new Input("interval", $this->t("ui.interval_ticks"), "100", (string) $definition->getAnimationInterval());
		$elements[] = new Dropdown("trigger", $this->t("ui.trigger"), [$this->t("ui.timer"), $this->t("ui.spawn"), $this->t("ui.right_click"), $this->t("ui.left_click")], max(0, array_search($definition->getAnimationTrigger(), $triggerValues, true)));
		if($canAnimateSound){
			$elements[] = new Toggle("animation_sound", $this->t("ui.animation_sound"), $definition->isAnimationSoundEnabled());
		}
		$player->sendForm(new CustomForm(
			$this->t("ui.actor_animation_title", ["id" => $id]),
			$elements,
			function(Player $player, CustomFormResponse $response) use ($id, $actorValues, $triggerValues, $canAnimateSound) : void{
				$interval = is_numeric($response->getString("interval")) ? max(1, (int) $response->getString("interval")) : 100;
				$trigger = $triggerValues[$response->getInt("trigger")] ?? "timer";
				$this->manager->setAnimation($id, $actorValues[$response->getInt("actor")] ?? "none", "", $interval, $trigger);
				if($canAnimateSound){
					$this->manager->setBoolean($id, "animation_sound", $response->getBool("animation_sound"));
				}
				$player->sendMessage(TextFormat::GREEN . $this->t("ui.actor_animation_updated"));
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
			$this->t("ui.random_emotes_title", ["id" => $id]),
			[
				new Label("info", $this->t("ui.random_emotes_info")),
				new Toggle("enabled", $this->t("ui.enable_random_emotes"), $definition->isRandomEmoteEnabled()),
				new Input("pool", $this->t("ui.emote_pool"), "wave, simple clap", implode(", ", $definition->getEmotePool())),
				new Input("cooldown", $this->t("ui.cooldown_seconds"), "60", (string) $definition->getRandomEmoteCooldown())
			],
			function(Player $player, CustomFormResponse $response) use ($id) : void{
				$pool = array_values(array_filter(array_map("trim", explode(",", $response->getString("pool"))), static fn(string $value) : bool => $value !== ""));
				foreach($pool as $emote){
					if(EmoteCatalog::resolve($emote) === null){
						$player->sendMessage(TextFormat::RED . $this->t("ui.unknown_emote_pool", ["emote" => $emote]));
						return;
					}
				}
				$cooldown = is_numeric($response->getString("cooldown")) ? max(0.0, (float) $response->getString("cooldown")) : 60.0;
				$this->manager->setEmotePool($id, $pool, $response->getBool("enabled"), $cooldown);
				$player->sendMessage(TextFormat::GREEN . $this->t("ui.random_emotes_updated"));
				$this->openAnimation($player, $id);
			},
			static function(Player $player) : void{}
		));
	}

	private function openEffects(Player $player, string $id) : void{
		$player->sendForm(new MenuForm(
			$this->t("ui.sound_particles_title", ["id" => $id]),
			$this->t("ui.sound_particles_description"),
			[
				new MenuOption($this->t("ui.sound")),
				new MenuOption($this->t("ui.particles")),
				new MenuOption($this->t("ui.back"))
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
			$this->t("ui.sound_title", ["id" => $id]),
			$this->t("ui.sound_description"),
			[
				new MenuOption($this->t("ui.enabled", ["value" => $definition->isSoundEnabled() ? $this->t("ui.on") : $this->t("ui.off")])),
				new MenuOption($this->t("ui.sound_type", ["value" => $definition->getSoundType()])),
				new MenuOption($this->t("ui.back"))
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
			$this->t("ui.sound_enabled_title", ["id" => $id]),
			[new Toggle("enabled", $this->t("ui.play_interaction_sound"), $definition->isSoundEnabled())],
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
		$options = [new MenuOption($this->t("ui.back")), new MenuOption($this->t("ui.search_sounds"))];
		$actions = ["back", "search"];
		if($page > 0){
			$options[] = new MenuOption($this->t("ui.previous_page"));
			$actions[] = "previous";
		}
		foreach($pageTypes as $type){
			$options[] = new MenuOption($type);
			$actions[] = "sound:" . $type;
		}
		if($page + 1 < $pageCount){
			$options[] = new MenuOption($this->t("ui.next_page"));
			$actions[] = "next";
		}
		$player->sendForm(new MenuForm(
			$this->t("ui.sound_types_title", ["id" => $id]),
			$this->t("ui.page_description", ["page" => $page + 1, "pages" => $pageCount, "search" => $normalizedQuery === "" ? "" : "\n" . $this->t("ui.search_value", ["query" => $query])]),
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
					$player->sendMessage(TextFormat::GREEN . $this->t("ui.sound_updated", ["type" => $type]));
					$this->openSoundSettings($player, $id);
				}
			},
			static function(Player $player) : void{}
		));
	}

	private function openSoundSearch(Player $player, string $id, string $initialQuery = "") : void{
		$player->sendForm(new CustomForm(
			$this->t("ui.search_sound_title", ["id" => $id]),
			[new Input("query", $this->t("ui.search_sound"), "ambient, hurt, flap", $initialQuery)],
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
			$this->t("ui.particles_title", ["id" => $id]),
			$this->t("ui.particles_description"),
			[
				new MenuOption($this->t("ui.enabled", ["value" => $definition->isParticleEnabled() ? $this->t("ui.on") : $this->t("ui.off")])),
				new MenuOption($this->t("ui.particle_type", ["value" => VanillaParticleCatalog::displayName($definition->getParticleType())])),
				new MenuOption($this->t("ui.burst_settings")),
				new MenuOption($this->t("ui.back"))
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
			$this->t("ui.particles_enabled_title", ["id" => $id]),
			[new Toggle("enabled", $this->t("ui.show_particles"), $definition->isParticleEnabled())],
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
			$this->t("ui.particle_burst_title", ["id" => $id]),
			[
				new Input("count", $this->t("ui.particles_per_burst"), "8", (string) $definition->getParticleCount()),
				new Input("spread", $this->t("ui.particle_spread"), "0.35", (string) $definition->getParticleSpread())
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
		$options = [new MenuOption($this->t("ui.back")), new MenuOption($this->t("ui.search_particles"))];
		$actions = ["back", "search"];
		if($page > 0){
			$options[] = new MenuOption($this->t("ui.previous_page"));
			$actions[] = "previous";
		}
		foreach($pageTypes as $type){
			$options[] = new MenuOption(VanillaParticleCatalog::displayName($type) . "\n" . $type);
			$actions[] = "particle:" . $type;
		}
		if($page + 1 < $pageCount){
			$options[] = new MenuOption($this->t("ui.next_page"));
			$actions[] = "next";
		}
		$player->sendForm(new MenuForm(
			$this->t("ui.particle_types_title", ["id" => $id]),
			$this->t("ui.page_description", ["page" => $page + 1, "pages" => $pageCount, "search" => $normalizedQuery === "" ? "" : "\n" . $this->t("ui.search_value", ["query" => $query])]),
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
					$player->sendMessage(TextFormat::GREEN . $this->t("ui.particle_updated", ["type" => VanillaParticleCatalog::displayName($type)]));
					$this->openParticleSettings($player, $id);
				}
			},
			static function(Player $player) : void{}
		));
	}

	private function openParticleSearch(Player $player, string $id, string $initialQuery = "") : void{
		$player->sendForm(new CustomForm(
			$this->t("ui.search_particle_title", ["id" => $id]),
			[new Input("query", $this->t("ui.search_particle"), "flame, enchant, smoke", $initialQuery)],
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
			$this->t("ui.look_at_title", ["id" => $id]),
			[
				new Toggle("rotate", $this->t("ui.look_at_nearby_players"), $definition->isRotateEnabled()),
				new Input("rotation_distance", $this->t("ui.look_at_distance"), "64", (string) $definition->getRotationDistance())
			],
			function(Player $player, CustomFormResponse $response) use ($id) : void{
				$this->manager->setBoolean($id, "rotate", $response->getBool("rotate"));
				if(is_numeric($response->getString("rotation_distance"))){
					$this->manager->setRotationDistance($id, max(0.0, (float) $response->getString("rotation_distance")));
				}
				$player->sendMessage(TextFormat::GREEN . $this->t("ui.look_at_updated"));
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
			"none" => $this->t("ui.disabled"),
			"server" => $this->t("ui.this_server"),
			"world" => $this->t("ui.this_world"),
			"query" => $this->t("ui.bedrock_query_host")
		];
		$options = [];
		$actions = [];
		foreach($modeLabels as $mode => $label){
			$options[] = new MenuOption($label . ($definition->getPlayerCountMode() === $mode ? " (" . $this->t("ui.current") . ")" : ""));
			$actions[] = $mode;
		}
		$options[] = new MenuOption($this->t("ui.back"));
		$actions[] = "back";
		$player->sendForm(new MenuForm(
			$this->t("ui.player_count_title", ["id" => $id]),
			$this->t("ui.player_count_description"),
			$options,
			function(Player $player, int $selected) use ($id, $actions) : void{
				$action = $actions[$selected] ?? "back";
				switch($action){
					case "none":
					case "server":
						$this->manager->setPlayerCount($id, $action);
						$player->sendMessage(TextFormat::GREEN . $this->t("ui.player_count_updated"));
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
			$this->t("ui.player_count_world_title", ["id" => $id]),
			[
				new Label("info", $this->t("ui.player_count_world_info")),
				new Input("world", $this->t("ui.world_name"), "world", $definition->getPlayerCountWorld())
			],
			function(Player $player, CustomFormResponse $response) use ($id, $definition) : void{
				$world = trim($response->getString("world"));
				if($world === ""){
					$player->sendMessage(TextFormat::RED . $this->t("ui.world_required"));
					return;
				}
				$this->manager->setPlayerCount($id, "world", $world, "", $definition->getQueryPort(), $definition->getQueryInterval());
				$player->sendMessage(TextFormat::GREEN . $this->t("ui.player_count_world_updated"));
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
			$this->t("ui.player_count_query_title", ["id" => $id]),
			[
				new Label("info", $this->t("ui.player_count_query_info")),
				new Input("host", $this->t("ui.query_host"), "127.0.0.1", $definition->getQueryHost()),
				new Input("port", $this->t("ui.query_port"), "19132", (string) $definition->getQueryPort())
			],
			function(Player $player, CustomFormResponse $response) use ($id, $definition) : void{
				$host = trim($response->getString("host"));
				$port = is_numeric($response->getString("port")) ? max(1, (int) $response->getString("port")) : 19132;
				if($host === ""){
					$player->sendMessage(TextFormat::RED . $this->t("ui.query_host_required"));
					return;
				}
				$this->manager->setPlayerCount($id, "query", "", $host, $port, $definition->getQueryInterval());
				$player->sendMessage(TextFormat::GREEN . $this->t("ui.player_count_query_updated"));
				$this->openPlayerCount($player, $id);
			},
			static function(Player $player) : void{}
		));
	}

	private function openLocation(Player $player, string $id) : void{
		$player->sendForm(new MenuForm(
			$this->t("ui.location_title", ["id" => $id]),
			$this->t("ui.location_description"),
			[
				new MenuOption($this->t("ui.move_npc_here")),
				new MenuOption($this->t("ui.teleport_to_npc")),
				new MenuOption($this->t("ui.back"))
			],
			function(Player $player, int $selected) use ($id) : void{
				switch($selected){
					case 0:
						if($this->manager->move($player, $id)){
							$player->sendMessage(TextFormat::GREEN . $this->t("ui.npc_moved"));
						}else{
							$player->sendMessage(TextFormat::RED . $this->t("cmd.not_found"));
						}
						$this->openLocation($player, $id);
						break;
					case 1:
						$player->sendMessage($this->manager->teleport($player, $id) ? TextFormat::GREEN . $this->t("ui.teleported_to_npc") : TextFormat::RED . $this->t("cmd.not_spawned"));
						$this->openLocation($player, $id);
						break;
					case 2: $this->openEdit($player, $id); break;
				}
			},
			static function(Player $player) : void{}
		));
	}

	private function openEmoteSearch(Player $player, ?string $npcId = null) : void{
		$title = $npcId === null ? $this->t("ui.player_emotes") : $this->t("ui.choose_emote", ["id" => $npcId]);
		$player->sendForm(new CustomForm(
			$title,
			[
				new Label("info", $this->t("ui.emote_search_info")),
				new Input("query", $this->t("ui.search_title"), "air guitar or wave", ""),
				new Input("page", $this->t("ui.page"), "1", "1")
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

		$options = [new MenuOption($this->t("ui.search_emotes"))];
		$actions = ["search"];
		if($page > 0){
			$options[] = new MenuOption($this->t("ui.previous_page"));
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
			$options[] = new MenuOption($this->t("ui.next_page"));
			$actions[] = "next";
		}

		$first = $page * $pageSize + 1;
		$last = $first + count($matches) - 1;
		$target = $npcId === null ? "" : $this->t("ui.for_id", ["id" => $npcId]);
		$player->sendForm(new MenuForm(
			$this->t("ui.emotes_title", ["page" => $page + 1, "target" => $target]),
			$this->t("ui.emotes_description", ["first" => count($matches) === 0 ? "0" : $first, "last" => $last, "count" => EmoteCatalog::count(), "search" => $query === "" ? "" : "\n" . $this->t("ui.search_value", ["query" => $query])]),
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
			$this->t("ui.apply_emote_title", ["title" => $title]),
			[
				new Label("info", $this->t("ui.emote_uuid_info", ["uuid" => $uuid])),
				new Input("interval", $this->t("ui.interval_ticks"), "100", "100"),
				new Dropdown("trigger", $this->t("ui.trigger"), [$this->t("ui.timer"), $this->t("ui.spawn"), $this->t("ui.right_click"), $this->t("ui.left_click")])
			],
			function(Player $player, CustomFormResponse $response) use ($npcId, $uuid) : void{
				$triggers = ["timer", "spawn", "right_click", "left_click"];
				$interval = is_numeric($response->getString("interval")) ? max(1, (int) $response->getString("interval")) : 100;
				$trigger = $triggers[$response->getInt("trigger")] ?? "timer";
				if($this->manager->setAnimation($npcId, "emote", $uuid, $interval, $trigger)){
					$player->sendMessage(TextFormat::GREEN . $this->t("cmd.emote_updated"));
					$this->openAnimation($player, $npcId);
				}else{
					$player->sendMessage(TextFormat::RED . $this->t("cmd.emote_invalid"));
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
		$options = [new MenuOption($this->t("ui.add_action")), new MenuOption($this->t("ui.back"))];
		foreach($commands as $command){
			$options[] = new MenuOption($this->t("ui.remove_action", ["command" => substr($command, 0, 45)]));
		}
		$player->sendForm(new MenuForm(
			$this->t("ui.action_list_title", ["label" => $label, "id" => $id]),
			$trigger === "shared" ? $this->t("ui.shared_actions_info") : $this->t("ui.separate_actions_info"),
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
			$this->t("ui.add_action_title", ["label" => $label, "id" => $id]),
			[new Input("command", $this->t("ui.command"), "say hello", "")],
			function(Player $player, CustomFormResponse $response) use ($id, $trigger) : void{
				$command = trim($response->getString("command"));
				$definition = $this->manager->getDefinition($id);
				if($command === "" || $definition === null){
					$player->sendMessage(TextFormat::RED . $this->t("ui.command_empty"));
					return;
				}
				if($trigger === "shared"){
					$this->manager->setCommands($id, array_merge($definition->getCommands(), [$command]));
				}else{
					$commands = $trigger === "left_click" ? $definition->getHitCommands() : $definition->getInteractCommands();
					$this->manager->setTriggerCommands($id, $trigger, array_merge($commands, [$command]));
				}
				$player->sendMessage(TextFormat::GREEN . $this->t("ui.action_added"));
				$this->openActionList($player, $id, $trigger);
			},
			static function(Player $player) : void{}
		));
	}

	private function playerConfirmRemoveAction(Player $player, string $id, string $trigger, string $command) : void{
		$player->sendForm(new ModalForm(
			$this->t("ui.remove_action_title"),
			$this->t("ui.remove_action_description", ["command" => $command]),
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
					$player->sendMessage(TextFormat::GREEN . $this->t("ui.action_removed"));
				}
				$this->openActionList($player, $id, $trigger);
			},
			$this->t("ui.remove"),
			$this->t("ui.cancel"),
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
			$this->t("ui.equipment_title", ["id" => $id]),
			[
				new Label("info", $this->t("ui.equipment_info")),
				new Toggle("sync_all", $this->t("ui.copy_all_equipment"), false),
				new Toggle("hand", $this->t("ui.main_hand") . $this->equipmentSummary($equipment, "hand"), false),
				new Toggle("offhand", $this->t("ui.off_hand") . $this->equipmentSummary($equipment, "offhand"), false),
				new Toggle("helmet", $this->t("ui.helmet") . $this->equipmentSummary($equipment, "helmet"), false),
				new Toggle("chestplate", $this->t("ui.chestplate") . $this->equipmentSummary($equipment, "chestplate"), false),
				new Toggle("leggings", $this->t("ui.leggings") . $this->equipmentSummary($equipment, "leggings"), false),
				new Toggle("boots", $this->t("ui.boots") . $this->equipmentSummary($equipment, "boots"), false)
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
					$player->sendMessage(TextFormat::YELLOW . $this->t("ui.no_equipment_slot"));
					$this->openAppearance($player, $id);
					return;
				}
				$ok = $this->manager->setEquipmentFromPlayer($player, $id, $slots);
				$player->sendMessage($ok ? TextFormat::GREEN . $this->t("ui.equipment_copied") : TextFormat::RED . $this->t("ui.equipment_update_failed"));
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
			$this->t("ui.delete_title"),
			$this->t("ui.delete_description", ["id" => $id]),
			function(Player $player, bool $choice) use ($id) : void{
				if($choice){
					if($this->manager->remove($id)){
						$player->sendMessage(TextFormat::GREEN . $this->t("ui.deleted", ["id" => $id]));
					}else{
						$player->sendMessage(TextFormat::RED . $this->t("ui.delete_missing", ["id" => $id]));
					}
					return;
				}
				$this->openEdit($player, $id);
			},
			$this->t("ui.delete"),
			$this->t("ui.cancel"),
			static function(Player $player) : void{}
		));
	}

	private function supportsActorAnimation(string $entityType) : bool{
		return VanillaMobCatalog::profile($entityType)->supports("actor_animation");
	}

}

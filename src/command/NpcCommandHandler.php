<?php

declare(strict_types=1);

namespace AxolotlBucketLabs\NPC\command;

use AxolotlBucketLabs\NPC\manager\NpcManager;
use AxolotlBucketLabs\NPC\model\EmoteCatalog;
use AxolotlBucketLabs\NPC\model\VanillaMobCatalog;
use AxolotlBucketLabs\NPC\translation\Translator;
use AxolotlBucketLabs\NPC\ui\NpcUiManager;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function array_shift;
use function count;
use function implode;
use function in_array;
use function is_numeric;
use function str_replace;
use function strtolower;
use function trim;

final class NpcCommandHandler{
	private NpcUiManager $ui;

	public function __construct(private NpcManager $manager, private Translator $translator){
		$this->ui = new NpcUiManager($manager, $translator);
	}

	/** @param array<string, scalar> $parameters */
	private function t(string $key, array $parameters = []) : string{
		return $this->translator->translate($key, $parameters);
	}

	/** @param array<string, scalar> $parameters */
	private function send(CommandSender $sender, string $color, string $key, array $parameters = []) : void{
		$sender->sendMessage($color . $this->t($key, $parameters));
	}

	public function openEditor(Player $player, string $id) : void{
		$this->ui->openEditor($player, $id);
	}

	/** @param list<string> $args */
	public function handleNpc(CommandSender $sender, array $args) : bool{
		$subcommand = strtolower(array_shift($args) ?? "");
		if($subcommand === ""){
			if(!$sender instanceof Player){
				return $this->help($sender);
			}
			if(!$sender->hasPermission("npc.admin")){
				$sender->sendMessage(TextFormat::RED . $this->t("cmd.permission.open_ui"));
				return true;
			}
			$this->ui->openMain($sender);
			return true;
		}
		if(in_array($subcommand, ["help", "list"], true)){
			return $subcommand === "help" ? $this->help($sender) : $this->list($sender);
		}
		if($subcommand === "ui" || $subcommand === "menu"){
			if(!$sender instanceof Player){
				$sender->sendMessage(TextFormat::RED . $this->t("cmd.ui.player_only"));
				return true;
			}
			if(!$sender->hasPermission("npc.admin")){
				$sender->sendMessage(TextFormat::RED . $this->t("cmd.permission.open_ui"));
				return true;
			}
			$this->ui->openMain($sender);
			return true;
		}
		if(!$sender->hasPermission("npc.admin")){
			$sender->sendMessage(TextFormat::RED . $this->t("cmd.permission.edit"));
			return true;
		}
		// Slapper users commonly copy the numeric entity ID shown by /npc id.
		// Normalize it to the persistent definition ID before dispatching any
		// ID-first command, while retaining the original error for stale IDs.
		$idFirstCommands = [
			"edit", "remove", "delete", "setname", "setline", "addcommand", "clearcommands",
			"equipment", "skin", "skin-url", "cooldown", "scale", "particle-type", "sound-type",
			"hitsound", "type", "entity-type", "animation", "emote",
			"hitcommand", "interactcommand", "interaction-mode", "rotation-distance", "playercount",
			"block", "random-emote", "rotate", "particle", "sound", "namevisible",
			"namevisibility", "customnamevisible", "tagvisible", "tp"
		];
		if(in_array($subcommand, $idFirstCommands, true) && isset($args[0])){
			$args[0] = $this->manager->resolveId((string) $args[0]) ?? (string) $args[0];
		}

		switch($subcommand){
			case "id": return $this->idSession($sender);
			case "spawn": case "add": case "make": return $this->spawn($sender, $args);
			case "edit": return $this->edit($sender, $args);
			case "entities": return $this->entities($sender);
			case "updateall": return $this->updateAll($sender);
			case "version": $this->send($sender, TextFormat::GREEN, "cmd.version", ["version" => $this->manager->getVersion()]); return true;
			case "cancel": case "stopid": case "stopremove":
				$this->send($sender, TextFormat::GREEN, $sender instanceof Player && $this->manager->cancelSession($sender) ? "cmd.cancelled" : "cmd.no_pending");
				return true;
			case "create": return $this->create($sender, $args);
			case "remove": case "delete": return $this->remove($sender, $args);
			case "setname": return $this->setName($sender, $args);
			case "setline": return $this->setLine($sender, $args);
			case "addcommand": return $this->addCommand($sender, $args);
			case "clearcommands": return $this->clearCommands($sender, $args);
			case "equipment": return $this->equipment($sender, $args);
			case "skin": return $this->skin($sender, $args);
			case "skin-url": return $this->skinUrl($sender, $args);
			case "cooldown": return $this->cooldown($sender, $args);
			case "scale": return $this->scale($sender, $args);
			case "particle-type": return $this->effectType($sender, "particle", $args);
			case "sound-type": case "hitsound": return $this->effectType($sender, "sound", $args);
			case "type": case "entity-type": return $this->entityType($sender, $args);
			case "animation": return $this->animation($sender, $args);
			case "emote": return $this->emote($sender, $args);
			case "emotes": case "emote-list": return $this->emotes($sender, $args);
			case "hitcommand": return $this->triggerCommand($sender, "left_click", $args);
			case "interactcommand": return $this->triggerCommand($sender, "right_click", $args);
			case "interaction-mode": return $this->interactionMode($sender, $args);
			case "rotation-distance": return $this->rotationDistance($sender, $args);
			case "playercount": return $this->playerCount($sender, $args);
			case "block": return $this->block($sender, $args);
			case "random-emote": return $this->randomEmote($sender, $args);
			case "rotate": case "particle": case "sound": return $this->toggle($sender, $subcommand, $args);
			case "namevisible": return $this->toggle($sender, "nametag", $args);
			case "namevisibility": case "customnamevisible": case "tagvisible": return $this->nameVisibility($sender, $args);
			case "tp": return $this->teleport($sender, $args);
			case "reload": $this->manager->reload(); $sender->sendMessage(TextFormat::GREEN . $this->t("cmd.reloaded")); return true;
			case "debug": return $this->debug($sender);
			default: $sender->sendMessage(TextFormat::RED . $this->t("cmd.unknown_subcommand")); return true;
		}
	}

	/** @param list<string> $args */
	public function handleRca(CommandSender $sender, array $args) : bool{
		if(!$sender instanceof Player){
			$sender->sendMessage(TextFormat::RED . $this->t("cmd.rca.player_only"));
			return true;
		}
		if(count($args) < 2){
			$sender->sendMessage(TextFormat::YELLOW . $this->t("cmd.rca.usage"));
			return true;
		}
		$target = $sender->getServer()->getPlayerExact(array_shift($args));
		if($target === null){
			$sender->sendMessage(TextFormat::RED . $this->t("cmd.player_offline"));
			return true;
		}
		if(!$this->manager->executeRca($target, implode(" ", $args))){
			$sender->sendMessage(TextFormat::RED . $this->t("cmd.command_failed"));
		}else{
			$this->send($sender, TextFormat::GREEN, "cmd.command_executed", ["player" => $target->getName()]);
		}
		return true;
	}

	private function help(CommandSender $sender) : bool{
		$sender->sendMessage(TextFormat::AQUA . $this->t("cmd.help_title"));
		foreach([
			"/npc create <id> [name]",
			"/npc spawn <entity-type> [name]",
			"/npc edit <id> <name|addcommand|delcommand|listcommands|scale|tphere|tpto|equipment>",
			"/npc remove <id>",
			"/npc setname <id> <name>",
			"/npc setline <id> <line> <text>",
			"/npc addcommand <id> <command|console:command|message:text>",
			"/npc hitcommand|interactcommand <id> <action> (after interaction-mode separate)",
			"/npc clearcommands <id>",
			"/npc equipment <id> <hand|helmet|chestplate|leggings|boots> <item|clear>",
			"/npc skin <id> <relative-raw-skin-file|player|clear>",
			"/npc skin-url <id> <https-url|clear> (allowlist in config.yml)",
			"/npc cooldown <id> <seconds>",
			"/npc scale <id> <size>",
			"/npc particle-type <id> <flame|heart|smoke|...>",
			"/npc sound-type <id> <click|pop|door_bump|...>",
			"/npc type <id> <human|any Vanilla mob|boat|minecart|primed_tnt|falling_block>",
			"/npc animation <id> <none|fly|arm_swing|hurt|critical_hit|magic_critical> [interval] [trigger]",
			"/npc emote <id> <none|wave|simple_clap|over_there|over_here|bow|saluting|thinking|UUID> [interval] [trigger]",
			"/npc emotes [search]",
			"/npc random-emote <id> <on|off> [seconds] [emote... ]",
			"/npc interaction-mode <id> <shared|separate>",
			"/npc namevisibility <id> <never|hover|always> (namevisible on/off is legacy-compatible)",
			"/npc rotation-distance <id> <blocks>",
			"/npc playercount <id> <none|server|world|query> [world|host] [port]",
			"/npc block <id> <block-name|legacy-id:meta|clear> (prefer stone or minecraft:stone)",
			"/npc ui",
			"/npc rotate|particle|sound <id> <on|off>",
			"/npc tp <id> | /npc list | /npc entities | /npc updateall | /npc debug | /npc reload"
		] as $line){
			$sender->sendMessage(TextFormat::GRAY . $line);
		}
		return true;
	}

	private function list(CommandSender $sender) : bool{
		$definitions = $this->manager->getDefinitions();
		$this->send($sender, TextFormat::AQUA, "cmd.list_title", ["count" => count($definitions)]);
		foreach($definitions as $id => $definition){
			$this->send($sender, TextFormat::GRAY, "cmd.list_item", ["id" => $id, "world" => $definition->getWorld()]);
		}
		return true;
	}

	/** @param list<string> $args */
	private function spawn(CommandSender $sender, array $args) : bool{
		if(!$sender instanceof Player || count($args) < 1){
			$sender->sendMessage(TextFormat::YELLOW . $this->t("cmd.spawn_usage"));
			return true;
		}
		$type = strtolower((string) array_shift($args));
		if(VanillaMobCatalog::resolve($type) === null){
			$sender->sendMessage(TextFormat::RED . $this->t("cmd.unknown_entity"));
			return true;
		}
		$name = trim(implode(" ", $args));
		$name = str_replace(["{color}", "{line}"], ["§", "\n"], $name);
		if($name === ""){
			$name = $sender->getDisplayName();
		}
		$id = $this->nextId();
		try{
			$this->manager->create($sender, $id, $name);
			$this->manager->setEntityType($id, $type);
			$this->send($sender, TextFormat::GREEN, "cmd.spawned", ["type" => VanillaMobCatalog::displayName($type), "id" => $id]);
		}catch(\Throwable $error){
			$sender->sendMessage(TextFormat::RED . $error->getMessage());
		}
		return true;
	}

	private function nextId() : string{
		return $this->manager->getNextSuggestedId();
	}

	/** @param list<string> $args */
	private function edit(CommandSender $sender, array $args) : bool{
		$id = array_shift($args);
		$edit = strtolower(array_shift($args) ?? "");
		if($id === null || $edit === ""){
			$sender->sendMessage(TextFormat::YELLOW . $this->t("cmd.edit_usage"));
			return true;
		}
		switch($edit){
			case "name": case "customname":
				return $this->setName($sender, [$id, ...$args]);
			case "addcommand": case "addc": case "adduced":
				return $this->addCommand($sender, [$id, ...$args]);
			case "delcommand": case "delc": case "delcmd": case "remove": case "removecommand":
				return $this->removeCommand($sender, $id, $args);
			case "listcommands": case "listcmds": case "listcs":
				return $this->listCommands($sender, $id);
			case "helm": case "helmet": case "head": case "hat": case "cap":
				return $this->equipment($sender, [$id, "helmet", ...$args]);
			case "chest": case "shirt": case "chestplate":
				return $this->equipment($sender, [$id, "chestplate", ...$args]);
			case "pants": case "legs": case "leggings":
				return $this->equipment($sender, [$id, "leggings", ...$args]);
			case "feet": case "boots": case "shoes":
				return $this->equipment($sender, [$id, "boots", ...$args]);
			case "item": case "hand": case "holding": case "arm": case "held":
				return $this->equipment($sender, [$id, "hand", ...$args]);
			case "scale":
				return $this->scale($sender, [$id, ...$args]);
			case "tphere": case "teleporthere": case "movehere": case "bringer":
				if(!$sender instanceof Player){
					$sender->sendMessage(TextFormat::RED . $this->t("cmd.edit_player_only"));
					return true;
				}
				$sender->sendMessage($this->manager->move($sender, $id) ? TextFormat::GREEN . $this->t("cmd.moved") : TextFormat::RED . $this->t("cmd.not_found"));
				return true;
			case "tpto": case "goto": case "teleport": case "tp":
				return $this->teleport($sender, [$id]);
			case "update": case "fix": case "migrate":
				$this->manager->reload();
				$sender->sendMessage(TextFormat::GREEN . $this->t("cmd.reloaded"));
				return true;
			case "skin":
				return $this->skin($sender, [$id, ...$args]);
			case "namevisibility":
				return $this->nameVisibility($sender, [$id, ...$args]);
			case "namevisible": case "customnamevisible": case "tagvisible":
				return $this->toggle($sender, "nametag", [$id, ...$args]);
			default:
				$sender->sendMessage(TextFormat::RED . $this->t("cmd.unknown_edit"));
				return true;
		}
	}

	private function entities(CommandSender $sender) : bool{
		$types = VanillaMobCatalog::all();
		$this->send($sender, TextFormat::AQUA, "cmd.entities_title", ["count" => count($types), "types" => implode(", ", $types)]);
		$sender->sendMessage(TextFormat::GRAY . $this->t("cmd.entities_help"));
		return true;
	}

	private function updateAll(CommandSender $sender) : bool{
		$this->send($sender, TextFormat::GREEN, "cmd.updated_all", ["count" => $this->manager->updateAll()]);
		return true;
	}

	/** @param list<string> $args */
	private function removeCommand(CommandSender $sender, string $id, array $args) : bool{
		$command = trim(implode(" ", $args));
		if($command === ""){
			$sender->sendMessage(TextFormat::YELLOW . $this->t("cmd.delcommand_usage"));
			return true;
		}
		$sender->sendMessage($this->manager->removeCommand($id, $command) ? TextFormat::GREEN . $this->t("cmd.action_removed") : TextFormat::RED . $this->t("cmd.action_not_found"));
		return true;
	}

	private function listCommands(CommandSender $sender, string $id) : bool{
		$definition = $this->manager->getDefinition($id);
		if($definition === null){
			$sender->sendMessage(TextFormat::RED . $this->t("cmd.not_found"));
			return true;
		}
		$this->send($sender, TextFormat::AQUA, "cmd.actions_title", ["id" => $id]);
		foreach($definition->getCommands() as $command){
			$sender->sendMessage(TextFormat::GRAY . "- " . $command);
		}
		return true;
	}

	/** @param list<string> $args */
	private function scale(CommandSender $sender, array $args) : bool{
		$id = array_shift($args);
		$value = array_shift($args);
		if($id === null || $value === null || !is_numeric($value)){
			$sender->sendMessage(TextFormat::YELLOW . $this->t("cmd.scale_usage"));
			return true;
		}
		$sender->sendMessage($this->manager->setScale($id, (float) $value) ? TextFormat::GREEN . $this->t("cmd.scale_updated") : TextFormat::RED . $this->t("cmd.not_found"));
		return true;
	}

	/** @param list<string> $args */
	private function create(CommandSender $sender, array $args) : bool{
		if(!$sender instanceof Player || count($args) < 1){
			$sender->sendMessage(TextFormat::YELLOW . $this->t("cmd.create_usage"));
			return true;
		}
		$id = array_shift($args);
		$name = trim(implode(" ", $args));
		try{
			$this->manager->create($sender, $id, $name);
			$this->send($sender, TextFormat::GREEN, "cmd.created", ["id" => $id]);
		}catch(\Throwable $error){
			$sender->sendMessage(TextFormat::RED . $error->getMessage());
		}
		return true;
	}

	/** @param list<string> $args */
	private function remove(CommandSender $sender, array $args) : bool{
		$id = array_shift($args);
		if($id === null){
			if($sender instanceof Player){
				$this->manager->startRemoveSession($sender);
				$sender->sendMessage(TextFormat::YELLOW . $this->t("cmd.remove_hit"));
			}else{
				$sender->sendMessage(TextFormat::YELLOW . $this->t("cmd.remove_usage"));
			}
			return true;
		}
		$sender->sendMessage($this->manager->remove($id) ? TextFormat::GREEN . $this->t("cmd.removed") : TextFormat::RED . $this->t("cmd.not_found"));
		return true;
	}

	private function idSession(CommandSender $sender) : bool{
		if(!$sender instanceof Player){
			$sender->sendMessage(TextFormat::RED . $this->t("cmd.rca.player_only"));
			return true;
		}
		$this->manager->startIdSession($sender);
		$sender->sendMessage(TextFormat::YELLOW . $this->t("cmd.id_hit"));
		return true;
	}

	/** @param list<string> $args */
	private function setName(CommandSender $sender, array $args) : bool{
		$id = array_shift($args); $text = trim(implode(" ", $args));
		if($id === null || $text === ""){
			$sender->sendMessage(TextFormat::YELLOW . $this->t("cmd.setname_usage")); return true;
		}
		$ok = $this->manager->setLines($id, 1, $text);
		$sender->sendMessage($ok ? TextFormat::GREEN . $this->t("cmd.name_updated") : TextFormat::RED . $this->t("cmd.not_found")); return true;
	}

	/** @param list<string> $args */
	private function setLine(CommandSender $sender, array $args) : bool{
		$id = array_shift($args); $line = array_shift($args); $text = trim(implode(" ", $args));
		if($id === null || $line === null || !ctype_digit($line) || $text === ""){
			$sender->sendMessage(TextFormat::YELLOW . $this->t("cmd.setline_usage")); return true;
		}
		$ok = $this->manager->setLines($id, (int) $line, $text);
		$sender->sendMessage($ok ? TextFormat::GREEN . $this->t("cmd.line_updated") : TextFormat::RED . $this->t("cmd.line_invalid")); return true;
	}

	/** @param list<string> $args */
	private function addCommand(CommandSender $sender, array $args) : bool{
		$id = array_shift($args); $action = trim(implode(" ", $args));
		$definition = $id !== null ? $this->manager->getDefinition($id) : null;
		if($id === null || $action === "" || $definition === null){
			$sender->sendMessage(TextFormat::YELLOW . $this->t("cmd.addcommand_usage")); return true;
		}
		$commands = $definition->getCommands(); $commands[] = $action;
		$this->manager->setCommands($id, $commands);
		$sender->sendMessage(TextFormat::GREEN . $this->t("cmd.action_added")); return true;
	}

	/** @param list<string> $args */
	private function clearCommands(CommandSender $sender, array $args) : bool{
		$id = array_shift($args);
		$ok = $id !== null && $this->manager->setCommands($id, []);
		$sender->sendMessage($ok ? TextFormat::GREEN . $this->t("cmd.actions_cleared") : TextFormat::RED . $this->t("cmd.not_found")); return true;
	}

	/** @param list<string> $args */
	private function equipment(CommandSender $sender, array $args) : bool{
		$id = array_shift($args);
		$slot = strtolower(array_shift($args) ?? "");
		$item = trim(implode(" ", $args));
		if($id === null || $slot === "" || $item === ""){
			$sender->sendMessage(TextFormat::YELLOW . $this->t("cmd.equipment_usage")); return true;
		}
		if(strtolower($item) === "clear"){
			$item = "";
		}
		$ok = $this->manager->setEquipment($id, $slot, $item);
		$sender->sendMessage($ok ? TextFormat::GREEN . $this->t("cmd.equipment_updated") : TextFormat::RED . $this->t("cmd.equipment_invalid")); return true;
	}

	/** @param list<string> $args */
	private function skin(CommandSender $sender, array $args) : bool{
		$id = array_shift($args);
		$file = trim(implode(" ", $args));
		if($id === null){
			$sender->sendMessage(TextFormat::YELLOW . $this->t("cmd.skin_usage")); return true;
		}
		if($file === "" || in_array(strtolower($file), ["player", "self"], true)){
			if(!$sender instanceof Player){
				$sender->sendMessage(TextFormat::YELLOW . $this->t("cmd.skin_player_usage")); return true;
			}
			$ok = $this->manager->setSkinFromPlayer($sender, $id);
			$sender->sendMessage($ok ? TextFormat::GREEN . $this->t("cmd.skin_copied") : TextFormat::RED . $this->t("cmd.skin_copy_failed")); return true;
		}
		$ok = $this->manager->setSkinFile($id, strtolower($file) === "clear" ? "" : $file);
		$sender->sendMessage($ok ? TextFormat::GREEN . $this->t("cmd.skin_updated") : TextFormat::RED . $this->t("cmd.skin_invalid")); return true;
	}

	/** @param list<string> $args */
	private function skinUrl(CommandSender $sender, array $args) : bool{
		$id = array_shift($args);
		$url = trim(implode(" ", $args));
		if($id === null || $url === ""){
			$sender->sendMessage(TextFormat::YELLOW . $this->t("cmd.skin_url_usage"));
			return true;
		}
		if(strtolower($url) === "clear"){
			$url = "";
		}
		$ok = $this->manager->setSkinUrl($id, $url);
		$sender->sendMessage($ok ? TextFormat::GREEN . $this->t("cmd.skin_url_saved") : TextFormat::RED . $this->t("cmd.skin_url_invalid"));
		return true;
	}

	/** @param list<string> $args */
	private function cooldown(CommandSender $sender, array $args) : bool{
		$id = array_shift($args); $seconds = array_shift($args);
		if($id === null || $seconds === null || !is_numeric($seconds)){
			$sender->sendMessage(TextFormat::YELLOW . $this->t("cmd.cooldown_usage")); return true;
		}
		$ok = $this->manager->setCooldown($id, (float) $seconds);
		$sender->sendMessage($ok ? TextFormat::GREEN . $this->t("cmd.cooldown_updated") : TextFormat::RED . $this->t("cmd.not_found")); return true;
	}

	/** @param list<string> $args */
	private function effectType(CommandSender $sender, string $field, array $args) : bool{
		$id = array_shift($args);
		$type = strtolower(trim(array_shift($args) ?? ""));
		if($id === null || $type === ""){
			$sender->sendMessage(TextFormat::YELLOW . $this->t("cmd.effect_type_usage", ["command" => $field === "particle" ? "particle-type" : "sound-type"]));
			return true;
		}
		$ok = $this->manager->setEffectType($id, $field, $type);
		$sender->sendMessage($ok ? TextFormat::GREEN . $this->t("cmd.effect_type_updated", ["field" => $field]) : TextFormat::RED . $this->t("cmd.effect_type_invalid"));
		return true;
	}

	/** @param list<string> $args */
	private function entityType(CommandSender $sender, array $args) : bool{
		$id = array_shift($args);
		$type = strtolower(array_shift($args) ?? "");
		if($id === null || VanillaMobCatalog::resolve($type) === null){
			$sender->sendMessage(TextFormat::YELLOW . $this->t("cmd.entity_type_usage"));
			return true;
		}
		$ok = $this->manager->setEntityType($id, $type);
		$sender->sendMessage($ok ? TextFormat::GREEN . $this->t("cmd.entity_type_updated") : TextFormat::RED . $this->t("cmd.not_found"));
		return true;
	}

	/** @param list<string> $args */
	private function animation(CommandSender $sender, array $args) : bool{
		$id = array_shift($args);
		$type = strtolower(str_replace("-", "_", array_shift($args) ?? ""));
		if($id === null){
			$sender->sendMessage(TextFormat::YELLOW . $this->t("cmd.animation_usage"));
			return true;
		}
		// Accept the old combined syntax while making the concepts explicit.
		if($type === "emote"){
			return $this->emote($sender, [$id, ...$args]);
		}
		if(!in_array($type, ["none", "fly", "arm_swing", "hurt", "critical_hit", "magic_critical"], true)){
			$sender->sendMessage(TextFormat::YELLOW . $this->t("cmd.animation_usage"));
			return true;
		}
		// Pre-v0.4 accepted an unused animation ID before interval/trigger.
		if(isset($args[0]) && !is_numeric($args[0])){
			array_shift($args);
		}
		$interval = array_shift($args);
		$trigger = strtolower(str_replace("-", "_", (string) (array_shift($args) ?? "timer")));
		$ok = $this->manager->setAnimation($id, $type, "", $interval !== null && is_numeric($interval) ? (int) $interval : 100, $trigger);
		$sender->sendMessage($ok ? TextFormat::GREEN . $this->t("cmd.animation_updated") : TextFormat::RED . $this->t("cmd.animation_invalid"));
		return true;
	}

	/** @param list<string> $args */
	private function emote(CommandSender $sender, array $args) : bool{
		$id = array_shift($args);
		$emote = trim(implode(" ", $args));
		if($id === null || $emote === ""){
			$sender->sendMessage(TextFormat::YELLOW . $this->t("cmd.emote_usage"));
			return true;
		}
		$interval = 100;
		$trigger = "timer";
		// A title may contain spaces. First try the complete remaining argument
		// list as the emote name, then peel off optional trigger metadata only if
		// the complete title does not resolve.
		if(EmoteCatalog::resolve($emote) === null && strtolower($emote) !== "none"){
			$last = $args[count($args) - 1] ?? null;
			if(count($args) > 1 && is_string($last) && in_array(strtolower(str_replace("-", "_", $last)), ["timer", "spawn", "right_click", "left_click"], true)){
				$trigger = strtolower(str_replace("-", "_", (string) array_pop($args)));
			}
			$last = $args[count($args) - 1] ?? null;
			if(count($args) > 1 && is_string($last) && is_numeric($last)){
				$interval = (int) array_pop($args);
			}
			$emote = trim(implode(" ", $args));
		}
		if(strtolower($emote) === "none"){
			$ok = $this->manager->setAnimation($id, "none");
			$sender->sendMessage($ok ? TextFormat::GREEN . $this->t("cmd.emote_disabled") : TextFormat::RED . $this->t("cmd.not_found"));
			return true;
		}
		if(EmoteCatalog::resolve($emote) === null){
			$sender->sendMessage(TextFormat::RED . $this->t("cmd.emote_unknown"));
			return true;
		}
		$ok = $this->manager->setAnimation($id, "emote", $emote, $interval, $trigger);
		$sender->sendMessage($ok ? TextFormat::GREEN . $this->t("cmd.emote_updated") : TextFormat::RED . $this->t("cmd.emote_invalid"));
		return true;
	}

	/** @param list<string> $args */
	private function emotes(CommandSender $sender, array $args) : bool{
		$query = trim(implode(" ", $args));
		$matches = EmoteCatalog::search($query, 30);
		$sender->sendMessage(TextFormat::AQUA . $this->t("cmd.emote_catalog", ["count" => EmoteCatalog::count(), "shown" => count($matches), "search" => $query === "" ? "" : " search=" . $query]));
		foreach($matches as $match){
			$sender->sendMessage(TextFormat::GRAY . "- " . $match["title"] . TextFormat::DARK_GRAY . " => " . $match["uuid"]);
		}
		$sender->sendMessage(TextFormat::YELLOW . $this->t("cmd.emote_hint"));
		return true;
	}

	/** @param list<string> $args */
	private function triggerCommand(CommandSender $sender, string $trigger, array $args) : bool{
		$id = array_shift($args);
		$action = trim(implode(" ", $args));
		$definition = $id !== null ? $this->manager->getDefinition($id) : null;
		if($id === null || $action === "" || $definition === null){
			$sender->sendMessage(TextFormat::YELLOW . $this->t("cmd.trigger_usage", ["command" => $trigger === "left_click" ? "hitcommand" : "interactcommand"]));
			return true;
		}
		$commands = $trigger === "left_click" ? $definition->getHitCommands() : $definition->getInteractCommands();
		$commands[] = $action;
		$ok = $this->manager->setTriggerCommands($id, $trigger, $commands);
		$sender->sendMessage($ok ? TextFormat::GREEN . $this->t("cmd.trigger_added") : TextFormat::RED . $this->t("cmd.not_found"));
		return true;
	}

	/** @param list<string> $args */
	private function interactionMode(CommandSender $sender, array $args) : bool{
		$id = array_shift($args);
		$mode = strtolower(array_shift($args) ?? "");
		$ok = $id !== null && $this->manager->setInteractionMode($id, $mode);
		$sender->sendMessage($ok ? TextFormat::GREEN . $this->t("cmd.interaction_updated") : TextFormat::YELLOW . $this->t("cmd.interaction_usage"));
		return true;
	}

	/** @param list<string> $args */
	private function rotationDistance(CommandSender $sender, array $args) : bool{
		$id = array_shift($args);
		$value = array_shift($args);
		$ok = $id !== null && $value !== null && is_numeric($value) && $this->manager->setRotationDistance($id, (float) $value);
		$sender->sendMessage($ok ? TextFormat::GREEN . $this->t("cmd.rotation_updated") : TextFormat::YELLOW . $this->t("cmd.rotation_usage"));
		return true;
	}

	/** @param list<string> $args */
	private function playerCount(CommandSender $sender, array $args) : bool{
		$id = array_shift($args);
		$mode = strtolower(array_shift($args) ?? "");
		$target = array_shift($args) ?? "";
		$port = array_shift($args);
		if($id === null || !in_array($mode, ["none", "server", "world", "query"], true)){
			$sender->sendMessage(TextFormat::YELLOW . $this->t("cmd.playercount_usage"));
			return true;
		}
		$world = $mode === "world" ? $target : "";
		$host = $mode === "query" ? $target : "";
		$ok = $this->manager->setPlayerCount($id, $mode, $world, $host, $port !== null && is_numeric($port) ? (int) $port : 19132);
		$sender->sendMessage($ok ? TextFormat::GREEN . $this->t("cmd.playercount_updated") : TextFormat::RED . $this->t("cmd.not_found"));
		return true;
	}

	/** @param list<string> $args */
	private function block(CommandSender $sender, array $args) : bool{
		$id = array_shift($args);
		$block = trim(implode(" ", $args));
		if($id === null || $block === ""){
			$sender->sendMessage(TextFormat::YELLOW . $this->t("cmd.block_usage"));
			return true;
		}
		$ok = $this->manager->setBlockId($id, strtolower($block) === "clear" ? "" : $block);
		$sender->sendMessage($ok ? TextFormat::GREEN . $this->t("cmd.block_updated") : TextFormat::RED . $this->t("cmd.block_invalid"));
		return true;
	}

	/** @param list<string> $args */
	private function randomEmote(CommandSender $sender, array $args) : bool{
		$id = array_shift($args);
		$state = strtolower(array_shift($args) ?? "");
		$cooldown = array_shift($args);
		$definition = $id !== null ? $this->manager->getDefinition($id) : null;
		if($id === null || $definition === null || !in_array($state, ["on", "off"], true)){
			$sender->sendMessage(TextFormat::YELLOW . $this->t("cmd.random_emote_usage"));
			return true;
		}
		$pool = $args;
		if($pool === []){
			$pool = $definition->getEmotePool();
		}
		$ok = $this->manager->setEmotePool($id, $pool, $state === "on", $cooldown !== null && is_numeric($cooldown) ? (float) $cooldown : $definition->getRandomEmoteCooldown());
		$sender->sendMessage($ok ? TextFormat::GREEN . $this->t("cmd.random_emote_updated") : TextFormat::RED . $this->t("cmd.random_emote_invalid"));
		return true;
	}

	/** @param list<string> $args */
	private function nameVisibility(CommandSender $sender, array $args) : bool{
		$id = array_shift($args);
		$value = strtolower(str_replace("-", "_", array_shift($args) ?? ""));
		if($id === null || $value === ""){
			$sender->sendMessage(TextFormat::YELLOW . $this->t("cmd.name_visibility_usage"));
			return true;
		}
		if(in_array($value, ["on", "true", "1"], true)){
			$value = "always";
		}elseif(in_array($value, ["off", "false", "0"], true)){
			$value = "never";
		}
		$ok = $this->manager->setNametagVisibility($id, $value);
		$sender->sendMessage($ok ? TextFormat::GREEN . $this->t("cmd.name_visibility_updated", ["value" => $value]) : TextFormat::YELLOW . $this->t("cmd.name_visibility_usage"));
		return true;
	}

	/** @param list<string> $args */
	private function toggle(CommandSender $sender, string $field, array $args) : bool{
		$id = array_shift($args); $value = strtolower(array_shift($args) ?? "");
		if($id === null || !in_array($value, ["on", "off", "true", "false", "1", "0"], true)){
			$sender->sendMessage(TextFormat::YELLOW . $this->t("cmd.toggle_usage", ["field" => $field])); return true;
		}
		$ok = $this->manager->setBoolean($id, $field, in_array($value, ["on", "true", "1"], true));
		$sender->sendMessage($ok ? TextFormat::GREEN . $this->t("cmd.toggle_updated", ["field" => $field]) : TextFormat::RED . $this->t("cmd.not_found")); return true;
	}

	/** @param list<string> $args */
	private function teleport(CommandSender $sender, array $args) : bool{
		$id = array_shift($args);
		if(!$sender instanceof Player || $id === null){
			$sender->sendMessage(TextFormat::YELLOW . $this->t("cmd.teleport_usage")); return true;
		}
		$sender->sendMessage($this->manager->teleport($sender, $id) ? TextFormat::GREEN . $this->t("cmd.teleported") : TextFormat::RED . $this->t("cmd.not_spawned")); return true;
	}

	private function debug(CommandSender $sender) : bool{
		foreach($this->manager->debugLines() as $line){ $sender->sendMessage(TextFormat::GRAY . $line); }
		return true;
	}
}

<?php

declare(strict_types=1);

namespace NhanAZ\NPC\command;

use NhanAZ\NPC\manager\NpcManager;
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
	public function __construct(private NpcManager $manager){}

	/** @param list<string> $args */
	public function handleNpc(CommandSender $sender, array $args) : bool{
		$subcommand = strtolower(array_shift($args) ?? "help");
		if(in_array($subcommand, ["help", "list"], true)){
			return $subcommand === "help" ? $this->help($sender) : $this->list($sender);
		}
		if(!$sender->hasPermission("npc.admin")){
			$sender->sendMessage(TextFormat::RED . "You do not have permission to edit NPCs.");
			return true;
		}

		switch($subcommand){
			case "spawn": case "add": case "make": return $this->spawn($sender, $args);
			case "edit": return $this->edit($sender, $args);
			case "entities": return $this->entities($sender);
			case "version": $sender->sendMessage(TextFormat::GREEN . "NPC " . $this->manager->getVersion()); return true;
			case "cancel": $sender->sendMessage(TextFormat::GREEN . "No pending NPC action needs cancelling."); return true;
			case "create": return $this->create($sender, $args);
			case "remove": case "delete": return $this->remove($sender, $args);
			case "setname": return $this->setName($sender, $args);
			case "setline": return $this->setLine($sender, $args);
			case "addcommand": return $this->addCommand($sender, $args);
			case "clearcommands": return $this->clearCommands($sender, $args);
			case "equipment": return $this->equipment($sender, $args);
			case "skin": return $this->skin($sender, $args);
			case "cooldown": return $this->cooldown($sender, $args);
			case "scale": return $this->scale($sender, $args);
			case "particle-type": return $this->effectType($sender, "particle", $args);
			case "sound-type": case "hitsound": return $this->effectType($sender, "sound", $args);
			case "rotate": case "particle": case "sound": return $this->toggle($sender, $subcommand, $args);
			case "tp": return $this->teleport($sender, $args);
			case "reload": $this->manager->reload(); $sender->sendMessage(TextFormat::GREEN . "NPC definitions reloaded."); return true;
			case "debug": return $this->debug($sender);
			default: $sender->sendMessage(TextFormat::RED . "Unknown subcommand. Use /npc help."); return true;
		}
	}

	/** @param list<string> $args */
	public function handleRca(CommandSender $sender, array $args) : bool{
		if(!$sender instanceof Player){
			$sender->sendMessage(TextFormat::RED . "This compatibility command must be run by a player.");
			return true;
		}
		if(count($args) < 2){
			$sender->sendMessage(TextFormat::YELLOW . "Usage: /rca <player> <command>");
			return true;
		}
		$target = $sender->getServer()->getPlayerExact(array_shift($args));
		if($target === null){
			$sender->sendMessage(TextFormat::RED . "That player is not online.");
			return true;
		}
		if(!$this->manager->executeRca($target, implode(" ", $args))){
			$sender->sendMessage(TextFormat::RED . "The command could not be executed.");
		}else{
			$sender->sendMessage(TextFormat::GREEN . "Command executed as " . $target->getName() . ".");
		}
		return true;
	}

	private function help(CommandSender $sender) : bool{
		$sender->sendMessage(TextFormat::AQUA . "NPC commands:");
		foreach([
			"/npc create <id> [name]",
			"/slapper spawn <Human|Player> [name]",
			"/slapper edit <id> <name|addcommand|delcommand|listcommands|scale|tphere|tpto|equipment>",
			"/npc remove <id>",
			"/npc setname <id> <name>",
			"/npc setline <id> <line> <text>",
			"/npc addcommand <id> <command|console:command>",
			"/npc clearcommands <id>",
			"/npc equipment <id> <hand|helmet|chestplate|leggings|boots> <item|clear>",
			"/npc skin <id> <relative-raw-skin-file|clear>",
			"/npc cooldown <id> <seconds>",
			"/npc scale <id> <size>",
			"/npc particle-type <id> <flame|heart|smoke|...>",
			"/npc sound-type <id> <click|pop|door_bump|...>",
			"/npc rotate|particle|sound <id> <on|off>",
			"/npc tp <id> | /npc list | /npc debug | /npc reload"
		] as $line){
			$sender->sendMessage(TextFormat::GRAY . $line);
		}
		return true;
	}

	private function list(CommandSender $sender) : bool{
		$definitions = $this->manager->getDefinitions();
		$sender->sendMessage(TextFormat::AQUA . "NPCs (" . count($definitions) . "):");
		foreach($definitions as $id => $definition){
			$sender->sendMessage(TextFormat::GRAY . "- " . $id . " @ " . $definition->getWorld());
		}
		return true;
	}

	/** @param list<string> $args */
	private function spawn(CommandSender $sender, array $args) : bool{
		if(!$sender instanceof Player || count($args) < 1){
			$sender->sendMessage(TextFormat::YELLOW . "Usage: /slapper spawn <Human|Player> [name]");
			return true;
		}
		$type = strtolower((string) array_shift($args));
		if(!in_array($type, ["human", "player"], true)){
			$sender->sendMessage(TextFormat::RED . "This compatibility layer currently supports Human/Player NPCs only. Use /npc create for the same core.");
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
			$sender->sendMessage(TextFormat::GREEN . "Human NPC '$id' spawned here.");
		}catch(\Throwable $error){
			$sender->sendMessage(TextFormat::RED . $error->getMessage());
		}
		return true;
	}

	private function nextId() : string{
		$number = 1;
		while($this->manager->getDefinition("npc_" . $number) !== null){
			++$number;
		}
		return "npc_" . $number;
	}

	/** @param list<string> $args */
	private function edit(CommandSender $sender, array $args) : bool{
		$id = array_shift($args);
		$edit = strtolower(array_shift($args) ?? "");
		if($id === null || $edit === ""){
			$sender->sendMessage(TextFormat::YELLOW . "Usage: /slapper edit <id> <property> [value]");
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
					$sender->sendMessage(TextFormat::RED . "This edit action must be run by a player.");
					return true;
				}
				$sender->sendMessage($this->manager->move($sender, $id) ? TextFormat::GREEN . "NPC moved here." : TextFormat::RED . "NPC not found.");
				return true;
			case "tpto": case "goto": case "teleport": case "tp":
				return $this->teleport($sender, [$id]);
			case "update": case "fix": case "migrate":
				$this->manager->reload();
				$sender->sendMessage(TextFormat::GREEN . "NPC definitions reloaded.");
				return true;
			case "skin":
				return $this->skin($sender, [$id, ...$args]);
			case "namevisible": case "customnamevisible": case "tagvisible":
				$sender->sendMessage(TextFormat::YELLOW . "NPC name tags are always visible in this implementation.");
				return true;
			default:
				$sender->sendMessage(TextFormat::RED . "Unknown edit property. Use /npc help.");
				return true;
		}
	}

	private function entities(CommandSender $sender) : bool{
		$sender->sendMessage(TextFormat::AQUA . "Supported NPC entity types: Human, Player");
		$sender->sendMessage(TextFormat::GRAY . "Other historical Slapper entity types are not silently emulated by the Human core.");
		return true;
	}

	/** @param list<string> $args */
	private function removeCommand(CommandSender $sender, string $id, array $args) : bool{
		$command = trim(implode(" ", $args));
		if($command === ""){
			$sender->sendMessage(TextFormat::YELLOW . "Usage: /slapper edit <id> delcommand <command>");
			return true;
		}
		$sender->sendMessage($this->manager->removeCommand($id, $command) ? TextFormat::GREEN . "NPC action removed." : TextFormat::RED . "NPC or exact action not found.");
		return true;
	}

	private function listCommands(CommandSender $sender, string $id) : bool{
		$definition = $this->manager->getDefinition($id);
		if($definition === null){
			$sender->sendMessage(TextFormat::RED . "NPC not found.");
			return true;
		}
		$sender->sendMessage(TextFormat::AQUA . "NPC actions for $id:");
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
			$sender->sendMessage(TextFormat::YELLOW . "Usage: /slapper edit <id> scale <size>");
			return true;
		}
		$sender->sendMessage($this->manager->setScale($id, (float) $value) ? TextFormat::GREEN . "NPC scale updated." : TextFormat::RED . "NPC not found.");
		return true;
	}

	/** @param list<string> $args */
	private function create(CommandSender $sender, array $args) : bool{
		if(!$sender instanceof Player || count($args) < 1){
			$sender->sendMessage(TextFormat::YELLOW . "Usage: /npc create <id> [name]");
			return true;
		}
		$id = array_shift($args);
		$name = trim(implode(" ", $args));
		try{
			$this->manager->create($sender, $id, $name);
			$sender->sendMessage(TextFormat::GREEN . "NPC '$id' created here.");
		}catch(\Throwable $error){
			$sender->sendMessage(TextFormat::RED . $error->getMessage());
		}
		return true;
	}

	/** @param list<string> $args */
	private function remove(CommandSender $sender, array $args) : bool{
		$id = array_shift($args);
		if($id === null){
			$sender->sendMessage(TextFormat::YELLOW . "Usage: /npc remove <id>");
			return true;
		}
		$sender->sendMessage($this->manager->remove($id) ? TextFormat::GREEN . "NPC removed." : TextFormat::RED . "NPC not found.");
		return true;
	}

	/** @param list<string> $args */
	private function setName(CommandSender $sender, array $args) : bool{
		$id = array_shift($args); $text = trim(implode(" ", $args));
		if($id === null || $text === ""){
			$sender->sendMessage(TextFormat::YELLOW . "Usage: /npc setname <id> <name>"); return true;
		}
		$ok = $this->manager->setLines($id, 1, $text);
		$sender->sendMessage($ok ? TextFormat::GREEN . "NPC name updated." : TextFormat::RED . "NPC not found."); return true;
	}

	/** @param list<string> $args */
	private function setLine(CommandSender $sender, array $args) : bool{
		$id = array_shift($args); $line = array_shift($args); $text = trim(implode(" ", $args));
		if($id === null || $line === null || !ctype_digit($line) || $text === ""){
			$sender->sendMessage(TextFormat::YELLOW . "Usage: /npc setline <id> <line> <text>"); return true;
		}
		$ok = $this->manager->setLines($id, (int) $line, $text);
		$sender->sendMessage($ok ? TextFormat::GREEN . "NPC line updated." : TextFormat::RED . "NPC not found or line is invalid."); return true;
	}

	/** @param list<string> $args */
	private function addCommand(CommandSender $sender, array $args) : bool{
		$id = array_shift($args); $action = trim(implode(" ", $args));
		$definition = $id !== null ? $this->manager->getDefinition($id) : null;
		if($id === null || $action === "" || $definition === null){
			$sender->sendMessage(TextFormat::YELLOW . "Usage: /npc addcommand <id> <command|console:command>"); return true;
		}
		$commands = $definition->getCommands(); $commands[] = $action;
		$this->manager->setCommands($id, $commands);
		$sender->sendMessage(TextFormat::GREEN . "NPC action added."); return true;
	}

	/** @param list<string> $args */
	private function clearCommands(CommandSender $sender, array $args) : bool{
		$id = array_shift($args);
		$ok = $id !== null && $this->manager->setCommands($id, []);
		$sender->sendMessage($ok ? TextFormat::GREEN . "NPC actions cleared." : TextFormat::RED . "NPC not found."); return true;
	}

	/** @param list<string> $args */
	private function equipment(CommandSender $sender, array $args) : bool{
		$id = array_shift($args);
		$slot = strtolower(array_shift($args) ?? "");
		$item = trim(implode(" ", $args));
		if($id === null || $slot === "" || $item === ""){
			$sender->sendMessage(TextFormat::YELLOW . "Usage: /npc equipment <id> <slot> <item|clear>"); return true;
		}
		if(strtolower($item) === "clear"){
			$item = "";
		}
		$ok = $this->manager->setEquipment($id, $slot, $item);
		$sender->sendMessage($ok ? TextFormat::GREEN . "NPC equipment updated." : TextFormat::RED . "NPC or equipment slot is invalid."); return true;
	}

	/** @param list<string> $args */
	private function skin(CommandSender $sender, array $args) : bool{
		$id = array_shift($args);
		$file = trim(implode(" ", $args));
		if($id === null){
			$sender->sendMessage(TextFormat::YELLOW . "Usage: /npc skin <id> <relative-raw-skin-file|player|clear>"); return true;
		}
		if($file === "" || in_array(strtolower($file), ["player", "self"], true)){
			if(!$sender instanceof Player){
				$sender->sendMessage(TextFormat::YELLOW . "Usage: /npc skin <id> <relative-raw-skin-file|clear>"); return true;
			}
			$ok = $this->manager->setSkinFromPlayer($sender, $id);
			$sender->sendMessage($ok ? TextFormat::GREEN . "NPC skin copied from your current player skin." : TextFormat::RED . "NPC not found or player skin could not be saved."); return true;
		}
		$ok = $this->manager->setSkinFile($id, strtolower($file) === "clear" ? "" : $file);
		$sender->sendMessage($ok ? TextFormat::GREEN . "NPC skin setting updated. Put raw RGBA data under plugin_data/NPC/skins." : TextFormat::RED . "NPC not found or skin path is invalid."); return true;
	}

	/** @param list<string> $args */
	private function cooldown(CommandSender $sender, array $args) : bool{
		$id = array_shift($args); $seconds = array_shift($args);
		if($id === null || $seconds === null || !is_numeric($seconds)){
			$sender->sendMessage(TextFormat::YELLOW . "Usage: /npc cooldown <id> <seconds>"); return true;
		}
		$ok = $this->manager->setCooldown($id, (float) $seconds);
		$sender->sendMessage($ok ? TextFormat::GREEN . "NPC cooldown updated." : TextFormat::RED . "NPC not found."); return true;
	}

	/** @param list<string> $args */
	private function effectType(CommandSender $sender, string $field, array $args) : bool{
		$id = array_shift($args);
		$type = strtolower(trim(array_shift($args) ?? ""));
		if($id === null || $type === ""){
			$sender->sendMessage(TextFormat::YELLOW . "/npc " . ($field === "particle" ? "particle-type" : "sound-type") . " <id> <type>");
			return true;
		}
		$ok = $this->manager->setEffectType($id, $field, $type);
		$sender->sendMessage($ok ? TextFormat::GREEN . "NPC " . $field . " type updated." : TextFormat::RED . "NPC or effect type is invalid.");
		return true;
	}

	/** @param list<string> $args */
	private function toggle(CommandSender $sender, string $field, array $args) : bool{
		$id = array_shift($args); $value = strtolower(array_shift($args) ?? "");
		if($id === null || !in_array($value, ["on", "off", "true", "false", "1", "0"], true)){
			$sender->sendMessage(TextFormat::YELLOW . "/npc $field <id> <on|off>"); return true;
		}
		$ok = $this->manager->setBoolean($id, $field, in_array($value, ["on", "true", "1"], true));
		$sender->sendMessage($ok ? TextFormat::GREEN . "NPC $field setting updated." : TextFormat::RED . "NPC not found."); return true;
	}

	/** @param list<string> $args */
	private function teleport(CommandSender $sender, array $args) : bool{
		$id = array_shift($args);
		if(!$sender instanceof Player || $id === null){
			$sender->sendMessage(TextFormat::YELLOW . "Usage: /npc tp <id>"); return true;
		}
		$sender->sendMessage($this->manager->teleport($sender, $id) ? TextFormat::GREEN . "Teleported." : TextFormat::RED . "NPC is not spawned."); return true;
	}

	private function debug(CommandSender $sender) : bool{
		foreach($this->manager->debugLines() as $line){ $sender->sendMessage(TextFormat::GRAY . $line); }
		return true;
	}
}

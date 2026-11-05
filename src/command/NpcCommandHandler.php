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
use function is_numeric;
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
			case "create": return $this->create($sender, $args);
			case "remove": case "delete": return $this->remove($sender, $args);
			case "setname": return $this->setName($sender, $args);
			case "setline": return $this->setLine($sender, $args);
			case "addcommand": return $this->addCommand($sender, $args);
			case "clearcommands": return $this->clearCommands($sender, $args);
			case "cooldown": return $this->cooldown($sender, $args);
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
			"/npc remove <id>",
			"/npc setname <id> <name>",
			"/npc setline <id> <line> <text>",
			"/npc addcommand <id> <command|console:command>",
			"/npc clearcommands <id>",
			"/npc cooldown <id> <seconds>",
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
	private function cooldown(CommandSender $sender, array $args) : bool{
		$id = array_shift($args); $seconds = array_shift($args);
		if($id === null || $seconds === null || !is_numeric($seconds)){
			$sender->sendMessage(TextFormat::YELLOW . "Usage: /npc cooldown <id> <seconds>"); return true;
		}
		$ok = $this->manager->setCooldown($id, (float) $seconds);
		$sender->sendMessage($ok ? TextFormat::GREEN . "NPC cooldown updated." : TextFormat::RED . "NPC not found."); return true;
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

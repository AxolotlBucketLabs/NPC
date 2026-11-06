<?php

declare(strict_types=1);

namespace NhanAZ\NPC\integration;

use NhanAZ\NPC\Main;
use NhanAZ\NPC\model\NpcDefinition;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function call_user_func_array;
use function count;
use function is_float;
use function is_int;
use function is_object;
use function is_string;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function substr;
use function trim;

/** Executes NPC commands and resolves optional plugin placeholders. */
final class NpcCommandService{
	public function __construct(private Main $plugin){ }

	public function execute(NpcDefinition $definition, Player $player, string $trigger = "shared") : void{
		$placeholders = $this->resolvePlayerPlaceholders($definition, $player);
		$commands = $definition->getCommands();
		if($definition->getInteractionMode() === "separate"){
			$commands = array_merge($commands, $trigger === "left_click" ? $definition->getHitCommands() : $definition->getInteractCommands());
		}
		foreach($commands as $configured){
			$command = str_replace(array_keys($placeholders), array_values($placeholders), trim($configured));
			if($command === ""){
				continue;
			}
			if(str_starts_with(strtolower($command), "message:")){
				$player->sendMessage(TextFormat::colorize(trim(substr($command, 8))));
			}elseif(str_starts_with(strtolower($command), "console:")){
				$command = trim(substr($command, 8));
				if($command !== ""){
					$this->plugin->getServer()->dispatchCommand(
						new ConsoleCommandSender($this->plugin->getServer(), $this->plugin->getServer()->getLanguage()),
						ltrim($command, "/")
					);
				}
			}else{
				$this->plugin->getServer()->dispatchCommand($player, ltrim($command, "/"));
			}
		}
	}

	/** @return array<string, string> */
	private function resolvePlayerPlaceholders(NpcDefinition $definition, Player $player) : array{
		$prefix = "";
		$suffix = "";
		$rank = "";
		$balance = "";
		$money = "";

		try{
			$pluginManager = $this->plugin->getServer()->getPluginManager();
			$pureChat = $pluginManager->getPlugin("PureChat");
			if($pureChat !== null){
				$prefix = $this->optionalStringCall($pureChat, "getPrefix", [$player]);
				$suffix = $this->optionalStringCall($pureChat, "getSuffix", [$player]);
			}

			$purePerms = $pluginManager->getPlugin("PurePerms");
			if($purePerms !== null){
				$userDataManager = $this->optionalObjectCall($purePerms, "getUserDataMgr", []);
				if($userDataManager !== null){
					$group = $this->optionalObjectCall($userDataManager, "getGroup", [$player]);
					if($group !== null){
						$rank = $this->optionalStringCall($group, "getName", []);
					}
				}
			}

			foreach(["SimpleEconomy", "EcoAPI", "TownCoins", "TownEconomy"] as $economyName){
				$economy = $pluginManager->getPlugin($economyName);
				if($economy === null){
					continue;
				}
				$value = $this->optionalScalarCall($economy, "getMoney", [$player->getName()]);
				$value ??= $this->optionalScalarCall($economy, "getBalance", [$player->getName()]);
				$value ??= $this->optionalScalarCall($economy, "getCoins", [$player->getName()]);
				if($value !== null){
					$balance = (string) $value;
					if(is_int($value) || is_float($value)){
						$money = $this->optionalStringCall($economy, "formatMoney", [$value], $balance);
					}else{
						$money = $balance;
					}
					break;
				}
			}
		}catch(\Throwable $error){
			$this->plugin->getLogger()->debug("Optional NPC integration failed: " . $error->getMessage());
		}

		return [
			"{player}" => $player->getName(),
			"{name}" => $player->getName(),
			"{display_name}" => $player->getDisplayName(),
			"{npc}" => $definition->getId(),
			"{world}" => $definition->getWorld(),
			"{online}" => (string) count($this->plugin->getServer()->getOnlinePlayers()),
			"{players}" => (string) count($this->plugin->getServer()->getOnlinePlayers()),
			"{prefix}" => $prefix,
			"{suffix}" => $suffix,
			"{rank}" => $rank,
			"{balance}" => $balance,
			"{money}" => $money,
			"{coins}" => $balance
		];
	}

	/** @param list<mixed> $arguments */
	private function optionalObjectCall(object $target, string $method, array $arguments) : ?object{
		$callback = [$target, $method];
		if(!is_callable($callback)){
			return null;
		}
		/** @var callable $callback */
		$result = call_user_func_array($callback, $arguments);
		return is_object($result) ? $result : null;
	}

	/** @param list<mixed> $arguments */
	private function optionalStringCall(object $target, string $method, array $arguments, string $default = "") : string{
		$callback = [$target, $method];
		if(!is_callable($callback)){
			return $default;
		}
		/** @var callable $callback */
		$result = call_user_func_array($callback, $arguments);
		return is_string($result) ? $result : $default;
	}

	/** @param list<mixed> $arguments */
	private function optionalScalarCall(object $target, string $method, array $arguments) : int|float|string|null{
		$callback = [$target, $method];
		if(!is_callable($callback)){
			return null;
		}
		/** @var callable $callback */
		$result = call_user_func_array($callback, $arguments);
		return is_int($result) || is_float($result) || is_string($result) ? $result : null;
	}
}

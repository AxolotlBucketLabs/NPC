<?php

declare(strict_types=1);

namespace NhanAZ\NPC\manager;

use NhanAZ\NPC\entity\NpcEntity;
use NhanAZ\NPC\model\NpcDefinition;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\entity\Skin;
use pocketmine\event\player\PlayerEntityInteractEvent;
use pocketmine\item\StringToItemParser;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\entity\Location;
use pocketmine\world\particle\EntityFlameParticle;
use pocketmine\world\sound\ClickSound;
use pocketmine\Server;
use NhanAZ\NPC\Main;
use function atan2;
use function count;
use function deg2rad;
use function file_get_contents;
use function filesize;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function microtime;
use function realpath;
use function rad2deg;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function trim;
use const DIRECTORY_SEPARATOR;

final class NpcManager{
	/** @var array<string, NpcDefinition> */
	private array $definitions = [];

	/** @var array<string, NpcEntity> */
	private array $entities = [];

	/** @var array<string, array<string, float>> */
	private array $cooldowns = [];

	private string $dataFile;

	public function __construct(private Main $plugin){
		$this->dataFile = $plugin->getDataFolder() . "npcs.yml";
	}

	public function load() : void{
		$this->despawnAll();
		$this->definitions = [];

		$config = new Config($this->dataFile, Config::YAML, ["version" => 1, "npcs" => []]);
		$rawNpcs = $config->get("npcs", []);
		if(!is_array($rawNpcs)){
			$this->plugin->getLogger()->warning("npcs.yml has an invalid 'npcs' section; ignoring it");
			return;
		}

		foreach($rawNpcs as $id => $raw){
			if(!is_string($id) || !is_array($raw)){
				$this->plugin->getLogger()->warning("Skipping malformed NPC definition");
				continue;
			}
			try{
				$this->definitions[$id] = NpcDefinition::fromArray($id, $raw);
			}catch(\Throwable $error){
				$this->plugin->getLogger()->warning("Skipping NPC '$id': " . $error->getMessage());
			}
		}
	}

	public function save() : void{
		$data = ["version" => 1, "npcs" => []];
		foreach($this->definitions as $id => $definition){
			$data["npcs"][$id] = $definition->toArray();
		}
		$config = new Config($this->dataFile, Config::YAML, $data);
		$config->setAll($data);
		$config->save();
	}

	public function spawnAll() : void{
		foreach($this->definitions as $definition){
			$this->spawn($definition);
		}
	}

	public function despawnAll() : void{
		foreach($this->entities as $entity){
			if(!$entity->isClosed()){
				$entity->close();
			}
		}
		$this->entities = [];
	}

	public function tick() : void{
		$tick = $this->plugin->getServer()->getTick();
		if($tick % 10 !== 0){
			return;
		}

		foreach($this->definitions as $id => $definition){
			$entity = $this->entities[$id] ?? null;
			if($entity === null || $entity->isClosed()){
				$this->spawn($definition);
				$entity = $this->entities[$id] ?? null;
			}
			if($entity === null){
				continue;
			}

			$entity->setNameTag($this->renderLines($definition));
			if($definition->isRotateEnabled()){
				$this->rotateTowardsNearestPlayer($entity);
			}
			if($definition->isParticleEnabled()){
				$entity->getWorld()->addParticle($entity->getPosition()->add(0, 1.8, 0), new EntityFlameParticle());
			}
		}
	}

	public function getDefinition(string $id) : ?NpcDefinition{
		return $this->definitions[$id] ?? null;
	}

	/** @return array<string, NpcDefinition> */
	public function getDefinitions() : array{
		return $this->definitions;
	}

	public function create(Player $player, string $id, string $displayName) : NpcDefinition{
		$definition = new NpcDefinition(
			$id,
			$player->getWorld()->getFolderName(),
			$player->getLocation()->x,
			$player->getLocation()->y,
			$player->getLocation()->z,
			$player->getLocation()->yaw,
			$player->getLocation()->pitch,
			[$displayName !== "" ? $displayName : $id]
		);
		$this->remove($id);
		$this->definitions[$id] = $definition;
		$this->save();
		$this->spawn($definition);
		return $definition;
	}

	public function remove(string $id) : bool{
		$removed = isset($this->definitions[$id]);
		unset($this->definitions[$id]);
		if(isset($this->entities[$id])){
			$this->entities[$id]->close();
			unset($this->entities[$id]);
			$removed = true;
		}
		if($removed){
			$this->save();
		}
		return $removed;
	}

	public function spawn(NpcDefinition $definition) : ?NpcEntity{
		$world = $this->plugin->getServer()->getWorldManager()->getWorldByName($definition->getWorld());
		if($world === null){
			$this->plugin->getLogger()->warning("World '" . $definition->getWorld() . "' for NPC '" . $definition->getId() . "' is not loaded");
			return null;
		}

		if(isset($this->entities[$definition->getId()]) && !$this->entities[$definition->getId()]->isClosed()){
			$this->entities[$definition->getId()]->close();
		}
		$location = new Location(
			$definition->getX(),
			$definition->getY(),
			$definition->getZ(),
			$world,
			$definition->getYaw(),
			$definition->getPitch()
		);
		$entity = new NpcEntity($location, $definition->getId(), $this->loadSkin($definition));
		$entity->setScale($definition->getScale());
		$this->applyEquipment($entity, $definition);
		$entity->setNameTag($this->renderLines($definition));
		$entity->setNameTagVisible(true);
		$entity->setNameTagAlwaysVisible(true);
		$this->entities[$definition->getId()] = $entity;
		return $entity;
	}

	private function respawn(string $id) : void{
		$definition = $this->definitions[$id] ?? null;
		if($definition !== null){
			$this->spawn($definition);
		}
	}

	public function reload() : void{
		$this->load();
		$this->spawnAll();
	}

	public function setLines(string $id, int $line, string $text) : bool{
		$definition = $this->definitions[$id] ?? null;
		if($definition === null || $line < 1 || $line > 16){
			return false;
		}
		$lines = $definition->getLines();
		while(count($lines) < $line){
			$lines[] = "";
		}
		$lines[$line - 1] = $text;
		$definition->setLines($lines);
		$this->save();
		$this->refresh($id);
		return true;
	}

	/** @param array<int, mixed> $commands */
	public function setCommands(string $id, array $commands) : bool{
		$definition = $this->definitions[$id] ?? null;
		if($definition === null){
			return false;
		}
		$definition->setCommands($commands);
		$this->save();
		return true;
	}

	public function setEquipment(string $id, string $slot, string $item) : bool{
		$definition = $this->definitions[$id] ?? null;
		if($definition === null || !$definition->setEquipmentSlot($slot, $item)){
			return false;
		}
		$this->save();
		$this->respawn($id);
		return true;
	}

	public function setSkinFile(string $id, string $file) : bool{
		$definition = $this->definitions[$id] ?? null;
		if($definition === null){
			return false;
		}
		$definition->setSkinFile($file);
		$this->save();
		$this->respawn($id);
		return true;
	}

	public function setBoolean(string $id, string $field, bool $value) : bool{
		$definition = $this->definitions[$id] ?? null;
		if($definition === null){
			return false;
		}
		switch($field){
			case "rotate": $definition->setRotate($value); break;
			case "particle": $definition->setParticle($value); break;
			case "sound": $definition->setSound($value); break;
			default: return false;
		}
		$this->save();
		return true;
	}

	public function setCooldown(string $id, float $seconds) : bool{
		$definition = $this->definitions[$id] ?? null;
		if($definition === null){
			return false;
		}
		$definition->setCooldown($seconds);
		$this->save();
		return true;
	}

	public function teleport(Player $player, string $id) : bool{
		$entity = $this->entities[$id] ?? null;
		if($entity === null || $entity->isClosed()){
			return false;
		}
		return $player->teleport($entity->getPosition());
	}

	public function handleInteract(PlayerEntityInteractEvent $event) : void{
		$entity = $event->getEntity();
		if(!$entity instanceof NpcEntity || !isset($this->entities[$entity->getNpcId()])){
			return;
		}
		$event->cancel();
		$definition = $this->definitions[$entity->getNpcId()] ?? null;
		if($definition === null){
			return;
		}
		$player = $event->getPlayer();
		$now = microtime(true);
		$playerKey = strtolower($player->getName());
		$last = $this->cooldowns[$definition->getId()][$playerKey] ?? 0.0;
		if($definition->getCooldown() > 0 && $now - $last < $definition->getCooldown()){
			return;
		}
		$this->cooldowns[$definition->getId()][$playerKey] = $now;

		if($definition->isSoundEnabled()){
			$entity->getWorld()->addSound($entity->getPosition(), new ClickSound(), [$player]);
		}
		$this->executeCommands($definition, $player);
	}

	public function executeRca(Player $actor, string $command) : bool{
		$command = trim($command);
		if($command === ""){
			return false;
		}
		return $this->plugin->getServer()->dispatchCommand($actor, ltrim($command, "/"));
	}

	/** @return list<string> */
	public function debugLines() : array{
		$lines = [
			"NPC debug:",
			"definitions=" . count($this->definitions),
			"spawned=" . count($this->entities),
			"server-tick=" . $this->plugin->getServer()->getTick()
		];
		foreach($this->definitions as $id => $definition){
			$entity = $this->entities[$id] ?? null;
			$lines[] = sprintf(
				"%s: world=%s entity=%s lines=%d actions=%d equipment=%d skin=%s cooldown=%.2f",
				$id,
				$definition->getWorld(),
				$entity !== null && !$entity->isClosed() ? "yes" : "no",
				count($definition->getLines()),
				count($definition->getCommands()),
				count($definition->getEquipment()),
				$definition->getSkinFile() === "" ? "default" : $definition->getSkinFile(),
				$definition->getCooldown()
			);
		}
		return $lines;
	}

	private function refresh(string $id) : void{
		$definition = $this->definitions[$id] ?? null;
		$entity = $this->entities[$id] ?? null;
		if($definition !== null && $entity !== null && !$entity->isClosed()){
			$entity->setNameTag($this->renderLines($definition));
		}
	}

	private function renderLines(NpcDefinition $definition) : string{
		$online = (string) count($this->plugin->getServer()->getOnlinePlayers());
		$lines = [];
		foreach($definition->getLines() as $line){
			$line = str_replace(
				["{npc}", "{world}", "{online}", "{players}"],
				[$definition->getId(), $definition->getWorld(), $online, $online],
				$line
			);
			$lines[] = TextFormat::colorize($line);
		}
		return implode("\n", $lines);
	}

	private function executeCommands(NpcDefinition $definition, Player $player) : void{
		$placeholders = $this->resolvePlayerPlaceholders($definition, $player);
		foreach($definition->getCommands() as $configured){
			$command = str_replace(array_keys($placeholders), array_values($placeholders), trim($configured));
			if($command === ""){
				continue;
			}
			if(str_starts_with(strtolower($command), "console:")){
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
		if(!is_callable([$target, $method])){
			return null;
		}
		$result = (\Closure::fromCallable([$target, $method]))(...$arguments);
		return is_object($result) ? $result : null;
	}

	/** @param list<mixed> $arguments */
	private function optionalStringCall(object $target, string $method, array $arguments, string $default = "") : string{
		if(!is_callable([$target, $method])){
			return $default;
		}
		$result = (\Closure::fromCallable([$target, $method]))(...$arguments);
		return is_string($result) ? $result : $default;
	}

	/** @param list<mixed> $arguments */
	private function optionalScalarCall(object $target, string $method, array $arguments) : int|float|string|null{
		if(!is_callable([$target, $method])){
			return null;
		}
		$result = (\Closure::fromCallable([$target, $method]))(...$arguments);
		return is_int($result) || is_float($result) || is_string($result) ? $result : null;
	}

	private function loadSkin(NpcDefinition $definition) : Skin{
		$skinData = $this->readSkinAsset($definition->getSkinFile(), max(Skin::ACCEPTED_SKIN_SIZES));
		if($skinData === "" || !in_array(strlen($skinData), Skin::ACCEPTED_SKIN_SIZES, true)){
			if($definition->getSkinFile() !== ""){
				$this->plugin->getLogger()->warning("NPC '" . $definition->getId() . "' has an invalid or missing raw skin file; using fallback skin");
			}
			return NpcEntity::defaultSkin();
		}

		$capeData = $this->readSkinAsset($definition->getCapeFile(), 8192);
		if($capeData !== "" && strlen($capeData) !== 8192){
			$capeData = "";
		}
		try{
			return new Skin(
				"NPC_" . $definition->getId(),
				$skinData,
				$capeData,
				$definition->getGeometryName(),
				$definition->getGeometryData()
			);
		}catch(\Throwable $error){
			$this->plugin->getLogger()->warning("NPC '" . $definition->getId() . "' skin rejected: " . $error->getMessage());
			return NpcEntity::defaultSkin();
		}
	}

	private function readSkinAsset(string $relativePath, int $maxBytes) : string{
		if($relativePath === ""){
			return "";
		}
		$root = realpath($this->plugin->getDataFolder() . "skins");
		if($root === false || !is_dir($root)){
			return "";
		}
		$path = realpath($root . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $relativePath));
		if($path === false || !is_file($path) || !str_starts_with($path, $root . DIRECTORY_SEPARATOR)){
			return "";
		}
		$size = filesize($path);
		if($size === false || $size > $maxBytes){
			return "";
		}
		$data = file_get_contents($path);
		return is_string($data) ? $data : "";
	}

	private function applyEquipment(NpcEntity $entity, NpcDefinition $definition) : void{
		$parser = StringToItemParser::getInstance();
		foreach($definition->getEquipment() as $slot => $itemName){
			$item = $parser->parse($itemName);
			if($item === null){
				$this->plugin->getLogger()->warning("NPC '" . $definition->getId() . "' has an unknown equipment item '" . $itemName . "'");
				continue;
			}
			switch($slot){
				case "hand": $entity->getInventory()->setItemInHand($item); break;
				case "helmet": $entity->getArmorInventory()->setHelmet($item); break;
				case "chestplate": $entity->getArmorInventory()->setChestplate($item); break;
				case "leggings": $entity->getArmorInventory()->setLeggings($item); break;
				case "boots": $entity->getArmorInventory()->setBoots($item); break;
			}
		}
	}

	private function rotateTowardsNearestPlayer(NpcEntity $entity) : void{
		$nearest = null;
		$nearestDistance = 16.0 * 16.0;
		foreach($entity->getWorld()->getPlayers() as $player){
			$distance = $player->getPosition()->distanceSquared($entity->getPosition());
			if($distance < $nearestDistance){
				$nearest = $player;
				$nearestDistance = $distance;
			}
		}
		if($nearest === null){
			return;
		}
		$dx = $nearest->getPosition()->x - $entity->getPosition()->x;
		$dz = $nearest->getPosition()->z - $entity->getPosition()->z;
		$entity->setRotation(rad2deg(atan2(-$dx, $dz)), 0.0);
	}
}

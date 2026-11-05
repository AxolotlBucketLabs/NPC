<?php

declare(strict_types=1);

namespace NhanAZ\NPC\manager;

use NhanAZ\NPC\entity\NpcEntity;
use NhanAZ\NPC\entity\MobNpcEntity;
use NhanAZ\NPC\entity\BlockNpcEntity;
use NhanAZ\NPC\model\EmoteCatalog;
use NhanAZ\NPC\model\NpcDefinition;
use NhanAZ\NPC\model\VanillaMobCatalog;
use NhanAZ\NPC\task\SkinFetchTask;
use pocketmine\block\Block;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\entity\Living;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Skin;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\animation\CriticalHitAnimation;
use pocketmine\entity\animation\HurtAnimation;
use pocketmine\entity\animation\MagicHitAnimation;
use pocketmine\event\player\PlayerEntityInteractEvent;
use pocketmine\item\StringToItemParser;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\entity\Location;
use pocketmine\world\particle\BubbleParticle;
use pocketmine\world\particle\CriticalParticle;
use pocketmine\world\particle\EntityFlameParticle;
use pocketmine\world\particle\ExplodeParticle;
use pocketmine\world\particle\FlameParticle;
use pocketmine\world\particle\HappyVillagerParticle;
use pocketmine\world\particle\HeartParticle;
use pocketmine\world\particle\LavaParticle;
use pocketmine\world\particle\Particle;
use pocketmine\world\particle\PortalParticle;
use pocketmine\world\particle\SmokeParticle;
use pocketmine\world\particle\SplashParticle;
use pocketmine\world\particle\WaterParticle;
use pocketmine\world\sound\ClickSound;
use pocketmine\world\sound\DoorBumpSound;
use pocketmine\world\sound\ExplodeSound;
use pocketmine\world\sound\PopSound;
use pocketmine\world\sound\Sound;
use pocketmine\world\sound\TotemUseSound;
use pocketmine\Server;
use NhanAZ\NPC\Main;
use function atan2;
use function array_rand;
use function array_filter;
use function array_values;
use function floor;
use function is_finite;
use function is_int;
use function is_object;
use function json_encode;
use function mt_rand;
use function random_int;
use function count;
use function deg2rad;
use function file_get_contents;
use function file_put_contents;
use function filesize;
use function is_array;
use function is_dir;
use function is_file;
use function in_array;
use function is_string;
use function mkdir;
use function microtime;
use function realpath;
use function rad2deg;
use function sprintf;
use function strlen;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function trim;
use const DIRECTORY_SEPARATOR;

final class NpcManager{
	private const DATA_VERSION = 2;
	/** @var array<string, NpcDefinition> */
	private array $definitions = [];

	/** @var array<string, NpcEntity|MobNpcEntity|BlockNpcEntity> */
	private array $entities = [];

	/** @var array<string, array<string, float>> */
	private array $cooldowns = [];

	/** @var array<string, true> */
	private array $idSessions = [];

	/** @var array<string, true> */
	private array $removeSessions = [];

	private string $dataFile;

	/** @var array<string, array{ok: bool, players: int, max: int, status: string}> */
	private array $queryResults = [];

	/** @var array<string, true> */
	private array $queryInFlight = [];

	/** @var array<string, int> */
	private array $walkIndices = [];

	/** @var array<string, float> */
	private array $lastRandomEmote = [];

	/** @var array<string, true> */
	private array $skinFetchInFlight = [];

	public function __construct(private Main $plugin){
		$this->dataFile = $plugin->getDataFolder() . "npcs.yml";
	}

	public function load() : void{
		$this->despawnAll();
		$this->definitions = [];

		$config = new Config($this->dataFile, Config::YAML, ["version" => self::DATA_VERSION, "npcs" => []]);
		$version = is_int($config->get("version")) ? $config->get("version") : 1;
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
		if($version < self::DATA_VERSION){
			$this->save();
		}
	}

	public function save() : void{
		$data = ["version" => self::DATA_VERSION, "npcs" => []];
		foreach($this->definitions as $id => $definition){
			$data["npcs"][$id] = $definition->toArray();
		}
		$config = new Config($this->dataFile, Config::YAML, $data);
		$config->setAll($data);
		$config->save();
	}

	/** Rewrites every loaded definition using the current schema and respawns it. */
	public function updateAll() : int{
		$count = count($this->definitions);
		$this->save();
		$this->despawnAll();
		$this->spawnAll();
		return $count;
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
		$this->idSessions = [];
		$this->removeSessions = [];
		$this->walkIndices = [];
		$this->lastRandomEmote = [];
		$this->skinFetchInFlight = [];
	}

	public function tick() : void{
		$tick = $this->plugin->getServer()->getTick();
		foreach($this->definitions as $id => $definition){
			$entity = $this->entities[$id] ?? null;
			if($entity === null || $entity->isClosed()){
				$this->spawn($definition);
				$entity = $this->entities[$id] ?? null;
			}
			if($entity === null){
				continue;
			}
			if($entity instanceof MobNpcEntity && $definition->getAnimationType() === "fly"){
				$entity->tickFlight($tick);
			}
			if($definition->isWalkEnabled() && $definition->getWalkPath() !== [] && $tick % 2 === 0){
				$this->tickWalking($definition, $entity);
			}
			if($definition->isRandomEmoteEnabled() && $definition->getEmotePool() !== [] && $tick % 20 === 0){
				$this->tickRandomEmote($definition, $entity);
			}
			if($definition->getPlayerCountMode() === "query" && $definition->getQueryHost() !== "" && $tick % $definition->getQueryInterval() === 0){
				$this->queryPlayerCount($definition);
			}

			if($definition->getAnimationTrigger() === "timer" && $tick % $definition->getAnimationInterval() === 0){
				$this->playAnimation($entity, $definition);
			}

			if($tick % 10 !== 0){
				continue;
			}
			$entity->setNameTag($this->renderLines($definition));
			$entity->setNameTagVisible($definition->isNametagVisible());
			if($definition->isRotateEnabled()){
				$this->rotateTowardsNearestPlayer($entity);
			}
			if($definition->isParticleEnabled()){
				$entity->getWorld()->addParticle($entity->getPosition()->add(0, 1.8, 0), $this->createParticle($definition->getParticleType()));
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

	public function getVersion() : string{
		return $this->plugin->getDescription()->getVersion();
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

	/** @return NpcEntity|MobNpcEntity|BlockNpcEntity|null */
	public function spawn(NpcDefinition $definition) : NpcEntity|MobNpcEntity|BlockNpcEntity|null{
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
		$entityType = VanillaMobCatalog::resolve($definition->getEntityType()) ?? "human";
		if($entityType === "falling_block"){
			$block = $this->parseBlock($definition->getBlockId());
			if($block === null){
				$this->plugin->getLogger()->warning("NPC '" . $definition->getId() . "' needs a valid block-id for falling_block; using Human fallback");
				$entity = new NpcEntity($location, $definition->getId(), $this->loadSkin($definition), $definition->getMenuName());
			}else{
				$entity = new BlockNpcEntity($location, $definition->getId(), $block);
			}
		}elseif($entityType !== "human"){
			$networkId = VanillaMobCatalog::networkId($entityType);
			if($networkId === null){
				return null;
			}
			$entity = new MobNpcEntity($location, $definition->getId(), $networkId, $this->canFly($entityType));
		}else{
			$entity = new NpcEntity($location, $definition->getId(), $this->loadSkin($definition), $definition->getMenuName());
		}
		$entity->setScale($definition->getScale());
		if($entity instanceof NpcEntity){
			$this->applyEquipment($entity, $definition);
		}
		$entity->setNameTag($this->renderLines($definition));
		$entity->setNameTagVisible($definition->isNametagVisible());
		$entity->setNameTagAlwaysVisible(true);
		$this->entities[$definition->getId()] = $entity;
		if($definition->getAnimationTrigger() === "spawn"){
			$this->playAnimation($entity, $definition);
		}
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

	public function setEffectType(string $id, string $field, string $type) : bool{
		$definition = $this->definitions[$id] ?? null;
		$type = strtolower(str_replace("-", "_", trim($type)));
		if($definition === null){
			return false;
		}
		switch($field){
			case "particle":
				if(!in_array($type, ["bubble", "critical", "entity_flame", "explode", "flame", "happy_villager", "heart", "lava", "portal", "smoke", "splash", "water"], true)){
					return false;
				}
				$definition->setParticleType($type);
				break;
			case "sound":
				if(!in_array($type, ["click", "door_bump", "explode", "pop", "totem"], true)){
					return false;
				}
				$definition->setSoundType($type);
				break;
			default:
				return false;
		}
		$this->save();
		return true;
	}

	public function setMenuName(string $id, string $menuName) : bool{
		$definition = $this->definitions[$id] ?? null;
		if($definition === null){
			return false;
		}
		$definition->setMenuName($menuName);
		$entity = $this->entities[$id] ?? null;
		if($entity instanceof NpcEntity && !$entity->isClosed()){
			$entity->setMenuName($definition->getMenuName());
		}
		$this->save();
		return true;
	}

	public function setEntityType(string $id, string $entityType) : bool{
		$definition = $this->definitions[$id] ?? null;
		$resolved = VanillaMobCatalog::resolve($entityType);
		if($definition === null || $resolved === null){
			return false;
		}
		$definition->setEntityType($resolved);
		$this->save();
		$this->respawn($id);
		return true;
	}

	public function setAnimation(string $id, string $type, string $animationId = "", int $interval = 100, string $trigger = "timer") : bool{
		$definition = $this->definitions[$id] ?? null;
		if($definition === null || !in_array(strtolower(str_replace("-", "_", $type)), ["none", "emote", "fly", "arm_swing", "hurt", "critical_hit", "magic_critical"], true)){
			return false;
		}
		$definition->setAnimation($type, $animationId, $interval, $trigger);
		$this->save();
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

	public function removeCommand(string $id, string $command) : bool{
		$definition = $this->definitions[$id] ?? null;
		$command = trim($command);
		if($definition === null || $command === ""){
			return false;
		}
		$original = $definition->getCommands();
		$commands = array_values(array_filter(
			$original,
			static fn(string $configured) : bool => $configured !== $command
		));
		if(count($commands) === count($original)){
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
		if($definition === null || !VanillaMobCatalog::isHuman($definition->getEntityType())){
			return false;
		}
		$definition->setSkinFile($file);
		$this->save();
		$this->respawn($id);
		return true;
	}

	public function setSkinFromPlayer(Player $player, string $id) : bool{
		$definition = $this->definitions[$id] ?? null;
		if($definition === null || !VanillaMobCatalog::isHuman($definition->getEntityType())){
			return false;
		}
		$stored = $this->persistSkin($player->getSkin(), $id);
		if($stored === null){
			$this->plugin->getLogger()->warning("Player skin for NPC '$id' has an unsupported size");
			return false;
		}
		$definition->setSkinFile($stored["skin"]);
		$definition->setCapeFile($stored["cape"]);
		$definition->setGeometry($stored["geometry-name"], $stored["geometry-data"]);
		$this->save();
		$this->respawn($id);
		return true;
	}

	/** @return array{skin: string, cape: string, geometry-name: string, geometry-data: string}|null */
	private function persistSkin(Skin $skin, string $baseName) : ?array{
		$skinData = $skin->getSkinData();
		if(!in_array(strlen($skinData), Skin::ACCEPTED_SKIN_SIZES, true)){
			return null;
		}
		$skinsDirectory = $this->plugin->getDataFolder() . "skins";
		if(!is_dir($skinsDirectory) && !mkdir($skinsDirectory, 0775, true) && !is_dir($skinsDirectory)){
			return null;
		}
		$skinFile = $baseName . ".rgba";
		if(file_put_contents($skinsDirectory . DIRECTORY_SEPARATOR . $skinFile, $skinData, LOCK_EX) === false){
			return null;
		}
		$capeFile = "";
		if(strlen($skin->getCapeData()) === 8192){
			$capeFile = $baseName . ".cape.rgba";
			if(file_put_contents($skinsDirectory . DIRECTORY_SEPARATOR . $capeFile, $skin->getCapeData(), LOCK_EX) === false){
				$capeFile = "";
			}
		}
		return [
			"skin" => $skinFile,
			"cape" => $capeFile,
			"geometry-name" => $skin->getGeometryName(),
			"geometry-data" => $skin->getGeometryData()
		];
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
			case "nametag": $definition->setNametagVisible($value); break;
			case "walk": $definition->setWalkEnabled($value); break;
			case "random_emote": $definition->setRandomEmoteEnabled($value); break;
			default: return false;
		}
		$this->save();
		return true;
	}

	public function setRotationDistance(string $id, float $distance) : bool{
		$definition = $this->definitions[$id] ?? null;
		if($definition === null || !is_finite($distance)){
			return false;
		}
		$definition->setRotationDistance($distance);
		$this->save();
		return true;
	}

	public function setInteractionMode(string $id, string $mode) : bool{
		$definition = $this->definitions[$id] ?? null;
		if($definition === null || !in_array(strtolower($mode), ["shared", "separate"], true)){
			return false;
		}
		$definition->setInteractionMode($mode);
		$this->save();
		return true;
	}

	/** @param array<int, mixed> $commands */
	public function setTriggerCommands(string $id, string $trigger, array $commands) : bool{
		$definition = $this->definitions[$id] ?? null;
		if($definition === null){
			return false;
		}
		if($trigger === "left_click"){
			$definition->setHitCommands($commands);
		}elseif($trigger === "right_click"){
			$definition->setInteractCommands($commands);
		}else{
			return false;
		}
		$this->save();
		return true;
	}

	public function setNametagVisible(string $id, bool $visible) : bool{
		if(!$this->setBoolean($id, "nametag", $visible)){
			return false;
		}
		$entity = $this->entities[$id] ?? null;
		if($entity !== null && !$entity->isClosed()){
			$entity->setNameTagVisible($visible);
		}
		return true;
	}

	public function setSkinUrl(string $id, string $url) : bool{
		$definition = $this->definitions[$id] ?? null;
		if($definition === null || !VanillaMobCatalog::isHuman($definition->getEntityType())){
			return false;
		}
		$definition->setSkinUrl($url);
		if($url !== ""){
			$definition->setSkinFile("");
		}
		$this->save();
		$this->fetchSkinUrl($definition);
		return true;
	}

	public function setPlayerCount(string $id, string $mode, string $world = "", string $host = "", int $port = 19132, int $interval = 200) : bool{
		$definition = $this->definitions[$id] ?? null;
		if($definition === null){
			return false;
		}
		$definition->setPlayerCountMode($mode);
		$definition->setPlayerCountWorld($world);
		$definition->setQueryTarget($host, $port, $interval);
		$this->save();
		return true;
	}

	public function setBlockId(string $id, string $blockId) : bool{
		$definition = $this->definitions[$id] ?? null;
		if($definition === null){
			return false;
		}
		$definition->setBlockId($blockId);
		$this->save();
		$this->respawn($id);
		return true;
	}

	/** @param array<int, mixed> $path */
	public function setWalkPath(string $id, array $path) : bool{
		$definition = $this->definitions[$id] ?? null;
		if($definition === null){
			return false;
		}
		$definition->setWalkPath($path);
		$this->save();
		return true;
	}

	public function setWalkSpeed(string $id, float $speed) : bool{
		$definition = $this->definitions[$id] ?? null;
		if($definition === null || !is_finite($speed)){
			return false;
		}
		$definition->setWalkSpeed($speed);
		$this->save();
		return true;
	}

	/** @param array<int, mixed> $pool */
	public function setEmotePool(string $id, array $pool, bool $enabled = true, float $cooldown = 60.0) : bool{
		$definition = $this->definitions[$id] ?? null;
		if($definition === null || !VanillaMobCatalog::isHuman($definition->getEntityType())){
			return false;
		}
		$definition->setEmotePool($pool);
		$definition->setRandomEmoteEnabled($enabled);
		$definition->setRandomEmoteCooldown($cooldown);
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

	public function setScale(string $id, float $scale) : bool{
		$definition = $this->definitions[$id] ?? null;
		if($definition === null){
			return false;
		}
		$definition->setScale($scale);
		$this->save();
		$this->respawn($id);
		return true;
	}

	public function move(Player $player, string $id) : bool{
		$definition = $this->definitions[$id] ?? null;
		if($definition === null){
			return false;
		}
		$location = $player->getLocation();
		$definition->setLocation(
			$player->getWorld()->getFolderName(),
			$location->x,
			$location->y,
			$location->z,
			$location->yaw,
			$location->pitch
		);
		$this->save();
		$this->respawn($id);
		return true;
	}

	/** @return array{imported: int, skipped: int, removed: int, errors: int} */
	public function migrateSlapper(bool $removeOld = false) : array{
		$result = ["imported" => 0, "skipped" => 0, "removed" => 0, "errors" => 0];
		foreach($this->plugin->getServer()->getWorldManager()->getWorlds() as $world){
			foreach($world->getEntities() as $entity){
				$entityType = strtolower(get_debug_type($entity));
				if(!str_contains($entityType, "slapper")){
					continue;
				}
				if(!$entity instanceof Human){
					++$result["skipped"];
					$this->plugin->getLogger()->warning("Skipped non-Human Slapper entity " . $entityType . " (NPC currently imports Human only)");
					continue;
				}
				try{
					$id = $this->nextMigrationId($entity->getId());
					$location = $entity->getLocation();
					$name = trim($entity->getNameTag());
					$definition = new NpcDefinition(
						$id,
						$entity->getWorld()->getFolderName(),
						$location->x,
						$location->y,
						$location->z,
						$location->yaw,
						$location->pitch,
						[$name === "" ? "NPC" : $name],
						$this->optionalListCall($entity, "getCommands"),
						0.0,
						false,
						false,
						true,
						$entity->getScale(),
						$this->readHumanEquipment($entity),
						"",
						"",
						"",
						"",
						"flame",
						"click",
						$this->optionalStringCall($entity, "getNameName", [])
					);
					$stored = $this->persistSkin($entity->getSkin(), $id);
					if($stored !== null){
						$definition->setSkinFile($stored["skin"]);
						$definition->setCapeFile($stored["cape"]);
						$definition->setGeometry($stored["geometry-name"], $stored["geometry-data"]);
					}
					$this->definitions[$id] = $definition;
					$this->spawn($definition);
					++$result["imported"];
					if($removeOld){
						$entity->close();
						++$result["removed"];
					}
				}catch(\Throwable $error){
					++$result["errors"];
					$this->plugin->getLogger()->warning("Failed to migrate Slapper entity: " . $error->getMessage());
				}
			}
		}
		if($result["imported"] > 0){
			$this->save();
		}
		return $result;
	}

	private function nextMigrationId(int $entityId) : string{
		$base = "slapper_" . $entityId;
		$id = $base;
		$suffix = 1;
		while(isset($this->definitions[$id])){
			$tail = "_" . $suffix;
			$id = substr($base, 0, 32 - strlen($tail)) . $tail;
			++$suffix;
		}
		return $id;
	}

	/** @return array<string, string> */
	private function readHumanEquipment(Human $entity) : array{
		$equipment = [];
		$items = [
			"hand" => $entity->getInventory()->getItemInHand(),
			"helmet" => $entity->getArmorInventory()->getHelmet(),
			"chestplate" => $entity->getArmorInventory()->getChestplate(),
			"leggings" => $entity->getArmorInventory()->getLeggings(),
			"boots" => $entity->getArmorInventory()->getBoots()
		];
		$parser = StringToItemParser::getInstance();
		foreach($items as $slot => $item){
			if($item->isNull()){
				continue;
			}
			$aliases = $parser->lookupAliases($item);
			if(isset($aliases[0])){
				$equipment[$slot] = $aliases[0];
			}
		}
		return $equipment;
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
		if(!($entity instanceof NpcEntity || $entity instanceof MobNpcEntity || $entity instanceof BlockNpcEntity) || !isset($this->entities[$entity->getNpcId()])){
			return;
		}
		$event->cancel();
		$this->handleInteraction($entity, $event->getPlayer(), "right_click");
	}

	public function handleHit(NpcEntity|MobNpcEntity|BlockNpcEntity $entity, Player $player) : void{
		if(!isset($this->entities[$entity->getNpcId()])){
			return;
		}
		$playerKey = strtolower($player->getName());
		if(isset($this->removeSessions[$playerKey])){
			unset($this->removeSessions[$playerKey], $this->idSessions[$playerKey]);
			$this->remove($entity->getNpcId());
			$player->sendMessage(TextFormat::GREEN . "NPC '" . $entity->getNpcId() . "' removed.");
			return;
		}
		if(isset($this->idSessions[$playerKey])){
			unset($this->idSessions[$playerKey]);
			$player->sendMessage(TextFormat::GREEN . "NPC ID: " . $entity->getNpcId() . " (entity ID: " . $entity->getId() . ")");
			return;
		}
		$this->handleInteraction($entity, $player, "left_click");
	}

	public function startIdSession(Player $player) : void{
		$playerKey = strtolower($player->getName());
		$this->idSessions[$playerKey] = true;
		unset($this->removeSessions[$playerKey]);
	}

	public function startRemoveSession(Player $player) : void{
		$playerKey = strtolower($player->getName());
		$this->removeSessions[$playerKey] = true;
		unset($this->idSessions[$playerKey]);
	}

	public function cancelSession(Player $player) : bool{
		$playerKey = strtolower($player->getName());
		$hadSession = isset($this->idSessions[$playerKey]) || isset($this->removeSessions[$playerKey]);
		unset($this->idSessions[$playerKey], $this->removeSessions[$playerKey]);
		return $hadSession;
	}

	private function handleInteraction(NpcEntity|MobNpcEntity|BlockNpcEntity $entity, Player $player, string $trigger) : void{
		$definition = $this->definitions[$entity->getNpcId()] ?? null;
		if($definition === null){
			return;
		}
		$now = microtime(true);
		$playerKey = strtolower($player->getName());
		$last = $this->cooldowns[$definition->getId()][$playerKey] ?? 0.0;
		if($definition->getCooldown() > 0 && $now - $last < $definition->getCooldown()){
			return;
		}
		$this->cooldowns[$definition->getId()][$playerKey] = $now;
		if($definition->getAnimationTrigger() === $trigger){
			$this->playAnimation($entity, $definition);
		}

		if($definition->isSoundEnabled()){
			$entity->getWorld()->addSound($entity->getPosition(), $this->createSound($definition->getSoundType()), [$player]);
		}
		$this->executeCommands($definition, $player, $trigger);
	}

	private function playAnimation(NpcEntity|MobNpcEntity|BlockNpcEntity $entity, NpcDefinition $definition) : void{
		if($entity instanceof MobNpcEntity){
			$entity->playAnimation($definition->getAnimationType(), $definition->getAnimationId());
			return;
		}
		if(!($entity instanceof NpcEntity)){
			return;
		}
		switch($definition->getAnimationType()){
			case "emote":
				$emoteId = EmoteCatalog::resolve($definition->getAnimationId());
				if($emoteId !== null){
					$entity->emote($emoteId);
				}
				break;
			case "arm_swing":
				$entity->broadcastAnimation(new ArmSwingAnimation($entity));
				break;
			case "hurt":
				$entity->broadcastAnimation(new HurtAnimation($entity));
				break;
			case "critical_hit":
				$entity->broadcastAnimation(new CriticalHitAnimation($entity));
				break;
			case "magic_critical":
				$entity->broadcastAnimation(new MagicHitAnimation($entity));
				break;
		}
	}

	public function resolveScoreTag(Player $player, string $tag) : ?string{
		$tag = strtolower(trim($tag, "{} "));
		$world = $player->getWorld();
		$actors = [];
		foreach($this->entities as $id => $entity){
			if(!$entity->isClosed() && $entity->getWorld() === $world){
				$actors[$id] = $entity;
			}
		}
		if($tag === "npc.count"){
			return (string) count($actors);
		}
		if($tag === "npc.ids"){
			return implode(",", array_keys($actors));
		}
		if($tag === "npc.server_players"){
			return (string) count($this->plugin->getServer()->getOnlinePlayers());
		}
		if($tag === "npc.world_players"){
			return (string) count($world->getPlayers());
		}
		$nearestId = null;
		$nearestEntity = null;
		$nearestDistance = PHP_FLOAT_MAX;
		foreach($actors as $id => $entity){
			$distance = $player->getPosition()->distanceSquared($entity->getPosition());
			if($distance < $nearestDistance){
				$nearestId = $id;
				$nearestEntity = $entity;
				$nearestDistance = $distance;
			}
		}
		if($nearestId === null || $nearestEntity === null){
			return in_array($tag, ["npc.nearest_id", "npc.nearest_name", "npc.nearest_distance", "npc.nearest_entity_id", "npc.nearest_type", "npc.nearest_animation", "npc.nearest_emote", "npc.nearest_motion"], true) ? "" : null;
		}
		$definition = $this->definitions[$nearestId] ?? null;
		return match($tag){
			"npc.nearest_id" => $nearestId,
			"npc.nearest_name" => $nearestEntity->getNameTag(),
			"npc.nearest_distance" => sprintf("%.1f", sqrt($nearestDistance)),
			"npc.nearest_entity_id" => (string) $nearestEntity->getId(),
			"npc.nearest_type" => $definition?->getEntityType() ?? "",
			"npc.nearest_animation" => $definition !== null && in_array($definition->getAnimationType(), ["fly", "arm_swing", "hurt", "critical_hit", "magic_critical"], true) ? $definition->getAnimationType() : "none",
			"npc.nearest_emote" => $definition?->getAnimationType() === "emote" ? $definition->getAnimationId() : "",
			"npc.nearest_motion" => $definition?->getAnimationType() === "fly" ? "fly" : "none",
			default => null
		};
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
			$query = $this->queryResults[$id] ?? null;
			$lines[] = sprintf(
				"%s: world=%s type=%s entity=%s lines=%d actions=%d equipment=%d skin=%s cooldown=%.2f interaction=%s nametag=%s rotate=%.1f count=%s",
				$id,
				$definition->getWorld(),
				$definition->getEntityType(),
				$entity !== null && !$entity->isClosed() ? "yes" : "no",
				count($definition->getLines()),
				count($definition->getCommands()),
				count($definition->getEquipment()),
				$definition->getSkinFile() === "" ? "default" : $definition->getSkinFile(),
				$definition->getCooldown(),
				$definition->getInteractionMode(),
				$definition->isNametagVisible() ? "yes" : "no",
				$definition->getRotationDistance(),
				$query === null ? "n/a" : ($query["players"] . "/" . $query["max"] . " " . $query["status"])
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
		$worldOnline = (string) count(array_filter(
			$this->plugin->getServer()->getOnlinePlayers(),
			static fn(Player $player) : bool => $player->getWorld()->getFolderName() === $definition->getWorld()
		));
		$query = $this->queryResults[$definition->getId()] ?? ["ok" => false, "players" => 0, "max" => 0, "status" => "offline"];
		$selectedCount = match($definition->getPlayerCountMode()){
			"world" => $worldOnline,
			"query" => (string) $query["players"],
			"none" => "",
			default => $online
		};
		$lines = [];
		foreach($definition->getLines() as $line){
			$line = str_replace(
				["{npc}", "{world}", "{online}", "{players}", "{server_players}", "{world_players}", "{player_count}", "{query_players}", "{query_max}", "{query_status}"],
				[$definition->getId(), $definition->getWorld(), $online, $selectedCount, $online, $worldOnline, $selectedCount, (string) $query["players"], (string) $query["max"], $query["status"]],
				$line
			);
			$lines[] = TextFormat::colorize($line);
		}
		return implode("\n", $lines);
	}

	private function executeCommands(NpcDefinition $definition, Player $player, string $trigger = "shared") : void{
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

	/** @return list<string> */
	private function optionalListCall(object $target, string $method) : array{
		if(!is_callable([$target, $method])){
			return [];
		}
		$result = (\Closure::fromCallable([$target, $method]))();
		if(!is_array($result)){
			return [];
		}
		$values = [];
		foreach($result as $value){
			if(is_string($value) && trim($value) !== ""){
				$values[] = trim($value);
			}
		}
		return $values;
	}

	private function createParticle(string $type) : Particle{
		return match($type){
			"bubble" => new BubbleParticle(),
			"critical" => new CriticalParticle(),
			"entity_flame" => new EntityFlameParticle(),
			"explode" => new ExplodeParticle(),
			"happy_villager" => new HappyVillagerParticle(),
			"heart" => new HeartParticle(),
			"lava" => new LavaParticle(),
			"portal" => new PortalParticle(),
			"smoke" => new SmokeParticle(),
			"splash" => new SplashParticle(),
			"water" => new WaterParticle(),
			default => new FlameParticle()
		};
	}

	private function createSound(string $type) : Sound{
		return match($type){
			"door_bump" => new DoorBumpSound(),
			"explode" => new ExplodeSound(),
			"pop" => new PopSound(),
			"totem" => new TotemUseSound(),
			default => new ClickSound()
		};
	}

	private function loadSkin(NpcDefinition $definition) : Skin{
		if($definition->getSkinUrl() !== "" && $definition->getSkinFile() === ""){
			$this->fetchSkinUrl($definition);
		}
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

	private function fetchSkinUrl(NpcDefinition $definition) : void{
		if(isset($this->skinFetchInFlight[$definition->getId()]) || $definition->getSkinUrl() === ""){
			return;
		}
		if($this->plugin->getConfig()->getNested("skin-url.enabled", true) !== true){
			return;
		}
		$rawHosts = $this->plugin->getConfig()->getNested("skin-url.allowed-hosts", []);
		$allowedHosts = [];
		if(is_array($rawHosts)){
			foreach($rawHosts as $host){
				if(is_string($host) && trim($host) !== ""){
					$allowedHosts[] = strtolower(trim($host));
				}
			}
		}
		$maxBytes = $this->plugin->getConfig()->getNested("skin-url.max-bytes", 1048576);
		$timeout = $this->plugin->getConfig()->getNested("skin-url.timeout-seconds", 8);
		if(!is_int($maxBytes) || $maxBytes < 1 || !is_int($timeout) || $timeout < 1){
			return;
		}
		$this->skinFetchInFlight[$definition->getId()] = true;
		$this->plugin->getServer()->getAsyncPool()->submitTask(new SkinFetchTask(
			$this->plugin->getName(),
			$definition->getId(),
			$definition->getSkinUrl(),
			$allowedHosts,
			min($maxBytes, 8 * 1024 * 1024),
			min($timeout, 30)
		));
	}

	/** @param array<string, mixed> $result */
	public function completeSkinFetch(string $id, array $result) : void{
		unset($this->skinFetchInFlight[$id]);
		$definition = $this->definitions[$id] ?? null;
		if($definition === null || ($result["ok"] ?? false) !== true || !is_string($result["data"] ?? null)){
			if($definition !== null){
				$this->plugin->getLogger()->warning("NPC '$id' skin URL could not be loaded: " . (is_string($result["error"] ?? null) ? $result["error"] : "unknown error"));
			}
			return;
		}
		$data = $result["data"];
		if(!in_array(strlen($data), Skin::ACCEPTED_SKIN_SIZES, true)){
			return;
		}
		$directory = $this->plugin->getDataFolder() . "skins";
		if(!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)){
			return;
		}
		$file = $id . ".url.rgba";
		if(file_put_contents($directory . DIRECTORY_SEPARATOR . $file, $data, LOCK_EX) === false){
			return;
		}
		$definition->setSkinFile($file);
		$this->save();
		$this->respawn($id);
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

	private function parseBlock(string $blockId) : ?Block{
		$blockId = trim($blockId);
		if($blockId === ""){
			return null;
		}
		$item = StringToItemParser::getInstance()->parse($blockId);
		return $item?->getBlock();
	}

	private function canFly(string $entityType) : bool{
		return in_array($entityType, ["allay", "bat", "bee", "blaze", "ender_dragon", "ghast", "happy_ghast", "phantom", "vex"], true);
	}

	private function tickWalking(NpcDefinition $definition, NpcEntity|MobNpcEntity|BlockNpcEntity $entity) : void{
		$path = $definition->getWalkPath();
		if(count($path) < 1){
			return;
		}
		$index = $this->walkIndices[$definition->getId()] ?? 0;
		$index = min($index, count($path) - 1);
		$target = $path[$index];
		if($target["world"] !== $entity->getWorld()->getFolderName()){
			return;
		}
		$position = $entity->getPosition();
		$dx = $target["x"] - $position->x;
		$dy = $target["y"] - $position->y;
		$dz = $target["z"] - $position->z;
		$distance = sqrt(($dx * $dx) + ($dy * $dy) + ($dz * $dz));
		if($distance <= max(0.05, $definition->getWalkSpeed() * 2.0)){
			++$index;
			if($index >= count($path)){
				if(!$definition->isWalkLoop()){
					$index = count($path) - 1;
					$this->walkIndices[$definition->getId()] = $index;
					return;
				}
				$index = 0;
			}
			$this->walkIndices[$definition->getId()] = $index;
			return;
		}
		$step = min($definition->getWalkSpeed() * 2.0, $distance);
		$entity->teleport($position->add($dx / $distance * $step, $dy / $distance * $step, $dz / $distance * $step), rad2deg(atan2(-$dx, $dz)), $target["pitch"]);
	}

	private function tickRandomEmote(NpcDefinition $definition, NpcEntity|MobNpcEntity|BlockNpcEntity $entity) : void{
		if(!($entity instanceof NpcEntity)){
			return;
		}
		$now = microtime(true);
		$last = $this->lastRandomEmote[$definition->getId()] ?? 0.0;
		if($now - $last < $definition->getRandomEmoteCooldown()){
			return;
		}
		$pool = $definition->getEmotePool();
		if($pool === []){
			return;
		}
		$emote = $pool[array_rand($pool)];
		$uuid = EmoteCatalog::resolve($emote);
		if($uuid !== null){
			$entity->emote($uuid);
			$this->lastRandomEmote[$definition->getId()] = $now;
		}
	}

	private function queryPlayerCount(NpcDefinition $definition) : void{
		$key = strtolower($definition->getQueryHost() . ":" . $definition->getQueryPort());
		if(isset($this->queryInFlight[$key])){
			return;
		}
		$plugin = $this->plugin->getServer()->getPluginManager()->getPlugin("BedrockQuery");
		$api = $plugin !== null ? $this->optionalObjectCall($plugin, "getApi", []) : null;
		if($api === null || !is_callable([$api, "query"])){
			$this->queryResults[$definition->getId()] = ["ok" => false, "players" => 0, "max" => 0, "status" => "BedrockQuery unavailable"];
			return;
		}
		$this->queryInFlight[$key] = true;
		$callback = function(mixed $result) use ($definition, $key) : void{
			unset($this->queryInFlight[$key]);
			if(!is_array($result) || ($result["ok"] ?? false) !== true || !is_array($result["data"] ?? null)){
				$this->queryResults[$definition->getId()] = ["ok" => false, "players" => 0, "max" => 0, "status" => "offline"];
				return;
			}
			$data = $result["data"];
			$playersData = is_array($data["players"] ?? null) ? $data["players"] : [];
			$players = $playersData["online"] ?? $data["Players"] ?? $data["online"] ?? $data["players"] ?? 0;
			$max = $playersData["max"] ?? $data["MaxPlayers"] ?? $data["max_players"] ?? $data["max"] ?? 0;
			$this->queryResults[$definition->getId()] = [
				"ok" => true,
				"players" => is_int($players) || is_float($players) || (is_string($players) && is_numeric($players)) ? (int) $players : 0,
				"max" => is_int($max) || is_float($max) || (is_string($max) && is_numeric($max)) ? (int) $max : 0,
				"status" => is_string($result["source"] ?? null) ? $result["source"] : "online"
			];
		};
		try{
			(\Closure::fromCallable([$api, "query"]))($definition->getQueryHost(), $definition->getQueryPort(), $callback);
		}catch(\Throwable $error){
			unset($this->queryInFlight[$key]);
			$this->queryResults[$definition->getId()] = ["ok" => false, "players" => 0, "max" => 0, "status" => "query error"];
			$this->plugin->getLogger()->debug("BedrockQuery integration failed: " . $error->getMessage());
		}
	}

	private function rotateTowardsNearestPlayer(NpcEntity|MobNpcEntity|BlockNpcEntity $entity) : void{
		$nearest = null;
		$definition = $this->definitions[$entity->getNpcId()] ?? null;
		$nearestDistance = ($definition?->getRotationDistance() ?? 16.0) ** 2;
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

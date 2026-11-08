<?php

declare(strict_types=1);

namespace AxolotlBucketLabs\NPC\manager;

use AxolotlBucketLabs\NPC\entity\NpcEntity;
use AxolotlBucketLabs\NPC\entity\MobNpcEntity;
use AxolotlBucketLabs\NPC\entity\BlockNpcEntity;
use AxolotlBucketLabs\NPC\entity\ActorNpcEntity;
use AxolotlBucketLabs\NPC\asset\NpcAssetService;
use AxolotlBucketLabs\NPC\display\NpcDisplayService;
use AxolotlBucketLabs\NPC\display\NpcLookAtService;
use AxolotlBucketLabs\NPC\effect\NpcEffectsService;
use AxolotlBucketLabs\NPC\integration\NpcCommandService;
use AxolotlBucketLabs\NPC\model\NpcDefinition;
use AxolotlBucketLabs\NPC\model\VanillaMobCatalog;
use AxolotlBucketLabs\NPC\model\VanillaParticleCatalog;
use AxolotlBucketLabs\NPC\model\VanillaSoundCatalog;
use AxolotlBucketLabs\NPC\model\VanillaVariantCatalog;
use AxolotlBucketLabs\NPC\storage\NpcRepository;
use AxolotlBucketLabs\NPC\task\SkinFetchTask;
use pocketmine\entity\Skin;
use pocketmine\event\player\PlayerEntityInteractEvent;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\entity\Location;
use AxolotlBucketLabs\NPC\Main;
use function array_filter;
use function array_values;
use function is_finite;
use function is_int;
use function is_object;
use function mt_rand;
use function count;
use function ctype_digit;
use function floor;
use function round;
use function file_put_contents;
use function is_array;
use function is_dir;
use function in_array;
use function is_string;
use function mkdir;
use function microtime;
use function sprintf;
use function strlen;
use function str_replace;
use function strtolower;
use function trim;
use const DIRECTORY_SEPARATOR;

final class NpcManager{
	/** @var array<string, NpcDefinition> */
	private array $definitions = [];

	/** @var array<string, NpcEntity|MobNpcEntity|BlockNpcEntity|ActorNpcEntity> */
	private array $entities = [];

	/** @var array<string, array<string, float>> */
	private array $cooldowns = [];

	/** @var array<string, true> */
	private array $idSessions = [];

	/** @var array<string, true> */
	private array $removeSessions = [];

	/** @var array<string, array{ok: bool, players: int, max: int, status: string}> */
	private array $queryResults = [];

	/** @var array<string, true> */
	private array $queryInFlight = [];

	/** @var array<string, true> */
	private array $skinFetchInFlight = [];

	private NpcRepository $repository;

	private NpcAssetService $assets;

	private NpcDisplayService $display;

	private NpcLookAtService $lookAt;

	private NpcEffectsService $effects;

	private NpcCommandService $commands;

	public function __construct(private Main $plugin, ?NpcRepository $repository = null){
		$this->repository = $repository ?? new NpcRepository($plugin);
		$this->assets = new NpcAssetService($plugin);
		$this->display = new NpcDisplayService($plugin);
		$this->lookAt = new NpcLookAtService();
		$this->effects = new NpcEffectsService();
		$this->commands = new NpcCommandService($plugin);
	}

	public function load() : void{
		$this->despawnAll();
		$this->definitions = $this->repository->load();
	}

	public function save() : void{
		$this->repository->save($this->definitions);
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
		$this->effects->reset();
		$this->skinFetchInFlight = [];
	}

	public function tick() : void{
		$tick = $this->plugin->getServer()->getTick();
		foreach($this->definitions as $id => $definition){
			$profile = VanillaMobCatalog::profile($definition->getEntityType());
			$entity = $this->entities[$id] ?? null;
			if($entity === null || $entity->isClosed()){
				$this->spawn($definition);
				$entity = $this->entities[$id] ?? null;
			}
			if($entity === null){
				continue;
			}
			if($entity instanceof MobNpcEntity && $definition->getAnimationType() === "fly" && $profile->supports("flight")){
				$entity->tickFlight($tick);
			}
			if($definition->isRandomEmoteEnabled() && $definition->getEmotePool() !== [] && $tick % 20 === 0){
				$this->effects->tickRandomEmote($definition, $entity);
			}
			if($definition->getPlayerCountMode() === "query" && $definition->getQueryHost() !== "" && $tick % $definition->getQueryInterval() === 0){
				$this->queryPlayerCount($definition);
			}

			if($definition->getAnimationTrigger() === "timer" && $tick % $definition->getAnimationInterval() === 0){
				$this->effects->playAnimation($entity, $definition);
			}
			if($definition->isAnimationSoundEnabled() && $definition->getAnimationType() === "fly" && $profile->supports("flight") && $tick % 40 === 0){
				$this->effects->playAnimationSound($entity, $definition);
			}

			if($tick % 10 !== 0){
				continue;
			}
			$renderedLines = $this->display->renderLines($definition, $this->queryResults[$id] ?? null);
			$this->display->applyNametagVisibility($entity, $definition, $renderedLines);
			if($definition->isRotateEnabled() && $profile->supports("look_at")){
				$this->lookAt->rotateTowardsNearestPlayer($entity, $definition);
			}
			if($definition->isParticleEnabled()){
				$height = $entity->getSize()->getHeight();
				for($particle = 0; $particle < $definition->getParticleCount(); ++$particle){
					$spread = $definition->getParticleSpread();
					$offsetX = (mt_rand(-1000, 1000) / 1000) * $spread;
					$offsetY = mt_rand(0, 1000) / 1000 * max(0.25, $height);
					$offsetZ = (mt_rand(-1000, 1000) / 1000) * $spread;
					$entity->getWorld()->addParticle($entity->getPosition()->add($offsetX, $offsetY, $offsetZ), $this->effects->createParticle($definition->getParticleType()));
				}
			}
		}
	}

	public function getDefinition(string $id) : ?NpcDefinition{
		return $this->definitions[$id] ?? null;
	}

	/**
	 * Resolves the persistent NPC ID or a currently spawned runtime entity ID.
	 * Runtime IDs are deliberately accepted only for live NPC actors, so an old
	 * or unrelated entity ID cannot accidentally edit a definition.
	 */
	public function resolveId(string $identifier) : ?string{
		$identifier = trim($identifier);
		if($identifier === ""){
			return null;
		}
		if(isset($this->definitions[$identifier])){
			return $identifier;
		}
		if(!ctype_digit($identifier)){
			return null;
		}
		$runtimeId = (int) $identifier;
		foreach($this->entities as $id => $entity){
			if(!$entity->isClosed() && $entity->getId() === $runtimeId){
				return $id;
			}
		}
		return null;
	}

	/** @return array<string, NpcDefinition> */
	public function getDefinitions() : array{
		return $this->definitions;
	}

	/**
	 * Returns the first unused, human-readable ID for the create form.
	 * This value is only a suggestion. Callers may replace it with any valid ID.
	 */
	public function getNextSuggestedId() : string{
		$index = 0;
		do{
			$id = "NPC_" . $index;
			++$index;
		}while(isset($this->definitions[$id]));
		return $id;
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
		$definition->setRotate(true);
		$definition->setScale($player->getScale());
		$definition->setEquipment($this->assets->capturePlayerEquipment($player));
		$playerSkin = $player->getSkin();
		$storedSkin = $this->assets->persistSkin($playerSkin, $id);
		if($storedSkin !== null){
			$definition->setSkinFile($storedSkin["skin"]);
			$definition->setCapeFile($storedSkin["cape"]);
			$definition->setGeometry($storedSkin["geometry-name"], $storedSkin["geometry-data"]);
			$definition->setSkinDataFile($storedSkin["skin-data"]);
		}else{
			$this->plugin->getLogger()->warning("Player skin for NPC '$id' could not be persisted. The live NPC will use the current skin until the next restart.");
		}
		$this->remove($id);
		$this->definitions[$id] = $definition;
		$this->save();
		$this->spawn($definition, $playerSkin);
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

	/** @return NpcEntity|MobNpcEntity|BlockNpcEntity|ActorNpcEntity|null */
	public function spawn(NpcDefinition $definition, ?Skin $skinOverride = null) : NpcEntity|MobNpcEntity|BlockNpcEntity|ActorNpcEntity|null{
		$world = $this->plugin->getServer()->getWorldManager()->getWorldByName($definition->getWorld());
		if($world === null){
			$this->plugin->getLogger()->warning("World '" . $definition->getWorld() . "' for NPC '" . $definition->getId() . "' is not loaded");
			return null;
		}

		if(isset($this->entities[$definition->getId()]) && !$this->entities[$definition->getId()]->isClosed()){
			$this->entities[$definition->getId()]->close();
		}
		$entityType = VanillaMobCatalog::resolve($definition->getEntityType()) ?? "human";
		$profile = VanillaMobCatalog::profile($entityType);
		$yaw = $definition->getYaw();
		$pitch = $definition->getPitch();
		$x = $definition->getX();
		$y = $definition->getY();
		$z = $definition->getZ();
		if($profile->supports("block_anchor")){
			// Falling blocks and Shulkers use a block-grid origin. Keep the
			// server-side hitbox, nametag and attachment metadata aligned with the
			// model instead of spawning at the player's fractional coordinates.
			$x = floor($x) + 0.5;
			$y = floor($y);
			$z = floor($z) + 0.5;
		}
		if($profile->getRotationMode() === "cardinal"){
			$yaw = round($yaw / 90.0) * 90.0;
			$pitch = 0.0;
		}
		$location = new Location(
			$x,
			$y,
			$z,
			$world,
			$yaw,
			$pitch
		);
		if($entityType === "falling_block"){
			$blockId = $definition->getBlockId();
			if($blockId === ""){
				$definition->setBlockId("stone");
				$blockId = "stone";
				$this->save();
			}
			$block = $this->assets->parseBlock($blockId);
			if($block === null){
				$this->plugin->getLogger()->warning("NPC '" . $definition->getId() . "' has invalid block-id '" . $blockId . "'. Falling block NPCs use stone instead.");
				$definition->setBlockId("stone");
				$block = $this->assets->parseBlock("stone");
				$this->save();
			}
			if($block === null){
				$this->plugin->getLogger()->error("Unable to create falling_block NPC '" . $definition->getId() . "': the stone block could not be resolved");
				return null;
			}
			$entity = new BlockNpcEntity($location, $definition->getId(), $block, $profile->getSpawnOffsetY());
		}elseif(in_array($entityType, ["boat", "minecart", "primed_tnt"], true)){
			$networkId = VanillaMobCatalog::networkId($entityType);
			if($networkId === null){
				return null;
			}
			$entity = new ActorNpcEntity($location, $definition->getId(), $networkId, $profile->getWidth(), $profile->getHeight(), $profile->getEyeHeight(), $profile->getSpawnOffsetY());
		}elseif($entityType !== "human"){
			$networkId = VanillaMobCatalog::networkId($entityType);
			if($networkId === null){
				return null;
			}
			$variant = VanillaVariantCatalog::resolve($entityType, $definition->getVariant());
			if($variant === null && VanillaVariantCatalog::hasVariants($entityType)){
				$defaultVariant = VanillaVariantCatalog::default($entityType);
				$definition->setVariant($defaultVariant);
				$this->save();
				$variant = VanillaVariantCatalog::resolve($entityType, $defaultVariant);
			}
			$entity = new MobNpcEntity($location, $definition->getId(), $networkId, $profile->getWidth(), $profile->getHeight(), $profile->getEyeHeight(), $profile->supports("flight"), $variant);
		}else{
		$entity = new NpcEntity($location, $definition->getId(), $skinOverride ?? $this->loadSkin($definition), $this->assets->readNetworkSkinData($definition->getSkinDataFile()));
		}
		$entity->setScale($definition->getScale());
		if($entity instanceof NpcEntity){
			$this->assets->applyEquipment($entity, $definition);
		}
		$renderedLines = $this->display->renderLines($definition, $this->queryResults[$definition->getId()] ?? null);
		$this->display->applyNametagVisibility($entity, $definition, $renderedLines);
		$this->entities[$definition->getId()] = $entity;
		// The Entity constructor only registers the actor in the world. It does
		// not send a spawn packet to players whose chunks are already loaded.
		// This matters for /npc create and live edits, which happen after the
		// player has joined. Players joining later are still handled by PMMP's
		// normal chunk-entity spawning path.
		$entity->spawnToAll();
		if($definition->getAnimationTrigger() === "spawn"){
			$this->effects->playAnimation($entity, $definition);
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
				$type = VanillaParticleCatalog::resolve($type);
				if($type === null){
					return false;
				}
				$definition->setParticleType($type);
				break;
			case "sound":
				if(VanillaSoundCatalog::resolve($type) === null && !in_array($type, ["click", "door_bump", "explode", "pop", "totem"], true)){
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

	public function setParticleSettings(string $id, int $count, float $spread) : bool{
		$definition = $this->definitions[$id] ?? null;
		if($definition === null || !is_finite($spread)){
			return false;
		}
		$definition->setParticleCount($count);
		$definition->setParticleSpread($spread);
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
		$definition->setVariant(VanillaVariantCatalog::default($resolved));
		if($resolved === "falling_block" && $definition->getBlockId() === ""){
			$definition->setBlockId("stone");
		}
		$this->save();
		$this->respawn($id);
		return true;
	}

	public function setVariant(string $id, string $variant) : bool{
		$definition = $this->definitions[$id] ?? null;
		$entityType = $definition === null ? null : VanillaMobCatalog::resolve($definition->getEntityType());
		if($definition === null || $entityType === null){
			return false;
		}
		$variant = strtolower(trim($variant));
		$options = VanillaVariantCatalog::options($entityType);
		if(!isset($options[$variant])){
			return false;
		}
		$definition->setVariant($variant);
		$this->save();
		$this->respawn($id);
		return true;
	}

	public function setAnimation(string $id, string $type, string $animationId = "", int $interval = 100, string $trigger = "timer") : bool{
		$definition = $this->definitions[$id] ?? null;
		$type = strtolower(str_replace("-", "_", $type));
		if($definition === null || !in_array($type, ["none", "emote", "fly", "arm_swing", "hurt", "critical_hit", "magic_critical"], true)){
			return false;
		}
		$profile = VanillaMobCatalog::profile($definition->getEntityType());
		if(($type === "emote" && !$profile->supports("emote")) || ($type === "fly" && !$profile->supports("flight")) || (in_array($type, ["arm_swing", "hurt", "critical_hit", "magic_critical"], true) && !$profile->supports("actor_animation"))){
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

	/**
	 * Copies the creator's current player loadout into a Human NPC.
	 *
	 * A null slot list means all equipment slots. An empty list is accepted as
	 * a no-op so a form can let the operator select individual slots safely.
	 *
	 * @param list<string>|null $slots
	 */
	public function setEquipmentFromPlayer(Player $player, string $id, ?array $slots = null) : bool{
		$definition = $this->definitions[$id] ?? null;
		if($definition === null || !VanillaMobCatalog::isHuman($definition->getEntityType())){
			return false;
		}
		if($slots === []){
			return true;
		}
		$allowedSlots = ["hand", "offhand", "helmet", "chestplate", "leggings", "boots"];
		$selectedSlots = $slots ?? $allowedSlots;
		$playerEquipment = $this->assets->capturePlayerEquipment($player);
		$equipment = $definition->getEquipment();
		foreach($selectedSlots as $slot){
			if(!in_array($slot, $allowedSlots, true)){
				continue;
			}
			if(array_key_exists($slot, $playerEquipment)){
				$equipment[$slot] = $playerEquipment[$slot];
			}else{
				unset($equipment[$slot]);
			}
		}
		$definition->setEquipment($equipment);
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
		$definition->setSkinDataFile("");
		$this->save();
		$this->respawn($id);
		return true;
	}

	public function setSkinFromPlayer(Player $player, string $id) : bool{
		$definition = $this->definitions[$id] ?? null;
		if($definition === null || !VanillaMobCatalog::isHuman($definition->getEntityType())){
			return false;
		}
		$stored = $this->assets->persistSkin($player->getSkin(), $id);
		if($stored === null){
			$this->plugin->getLogger()->warning("Player skin for NPC '$id' has an unsupported size");
			return false;
		}
		$definition->setSkinFile($stored["skin"]);
		$definition->setCapeFile($stored["cape"]);
		$definition->setGeometry($stored["geometry-name"], $stored["geometry-data"]);
		$definition->setSkinDataFile($stored["skin-data"]);
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
			case "animation_sound": $definition->setAnimationSound($value); break;
			case "nametag": $definition->setNametagVisible($value); break;
			case "random_emote": $definition->setRandomEmoteEnabled($value); break;
			default: return false;
		}
		$this->save();
		if($field === "nametag"){
			$this->syncNametagVisibility($id);
		}
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
		return $this->setBoolean($id, "nametag", $visible);
	}

	public function setNametagVisibility(string $id, string $visibility) : bool{
		$definition = $this->definitions[$id] ?? null;
		if($definition === null || !in_array(strtolower($visibility), ["never", "hover", "always"], true)){
			return false;
		}
		$definition->setNametagVisibility($visibility);
		$this->save();
		$this->syncNametagVisibility($id);
		return true;
	}

	public function setSkinUrl(string $id, string $url) : bool{
		$definition = $this->definitions[$id] ?? null;
		if($definition === null || !VanillaMobCatalog::isHuman($definition->getEntityType())){
			return false;
		}
		$definition->setSkinUrl($url);
		$definition->setSkinDataFile("");
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
		$oldScale = $definition->getScale();
		$definition->setScale($scale);
		$this->save();
		if($definition->getScale() !== $oldScale){
			$this->respawn($id);
		}
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

	public function teleport(Player $player, string $id) : bool{
		$entity = $this->entities[$id] ?? null;
		if($entity === null || $entity->isClosed()){
			return false;
		}
		return $player->teleport($entity->getPosition());
	}

	public function handleInteract(PlayerEntityInteractEvent $event) : void{
		$entity = $event->getEntity();
		if(!($entity instanceof NpcEntity || $entity instanceof MobNpcEntity || $entity instanceof BlockNpcEntity || $entity instanceof ActorNpcEntity) || !isset($this->entities[$entity->getNpcId()])){
			return;
		}
		$event->cancel();
		$this->handleInteraction($entity, $event->getPlayer(), "right_click");
	}

	public function handleHit(NpcEntity|MobNpcEntity|BlockNpcEntity|ActorNpcEntity $entity, Player $player) : void{
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

	private function handleInteraction(NpcEntity|MobNpcEntity|BlockNpcEntity|ActorNpcEntity $entity, Player $player, string $trigger) : void{
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
			$this->effects->playAnimation($entity, $definition);
		}

		if($definition->isSoundEnabled()){
			$this->effects->playInteractionSound($entity, $definition, $player);
		}
		$this->commands->execute($definition, $player, $trigger);
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
				// PHP casts numeric string array keys to integers. NPC IDs are
				// persisted as strings, so normalize the runtime key before exposing
				// it through the string-only ScoreHud tag API.
				$nearestId = (string) $id;
				$nearestEntity = $entity;
				$nearestDistance = $distance;
			}
		}
		if($nearestId === null || $nearestEntity === null){
			return in_array($tag, ["npc.nearest_id", "npc.nearest_name", "npc.nearest_distance", "npc.nearest_entity_id", "npc.nearest_type", "npc.nearest_variant", "npc.nearest_world", "npc.nearest_nametag_visibility", "npc.nearest_animation", "npc.nearest_emote", "npc.nearest_motion"], true) ? "" : null;
		}
		$definition = $this->definitions[$nearestId] ?? null;
		$value = match($tag){
			"npc.nearest_id" => $nearestId,
			"npc.nearest_name" => $nearestEntity->getNameTag(),
			"npc.nearest_distance" => sprintf("%.1f", sqrt($nearestDistance)),
			"npc.nearest_entity_id" => (string) $nearestEntity->getId(),
			"npc.nearest_type" => $definition?->getEntityType() ?? "",
			"npc.nearest_variant" => $definition?->getVariant() ?? "",
			"npc.nearest_world" => $definition?->getWorld() ?? $world->getFolderName(),
			"npc.nearest_nametag_visibility" => $definition?->getNametagVisibility() ?? "",
			"npc.nearest_animation" => $definition !== null && in_array($definition->getAnimationType(), ["fly", "arm_swing", "hurt", "critical_hit", "magic_critical"], true) ? $definition->getAnimationType() : "none",
			"npc.nearest_emote" => $definition?->getAnimationType() === "emote" ? $definition->getAnimationId() : "",
			"npc.nearest_motion" => $definition?->getAnimationType() === "fly" ? "fly" : "none",
			default => null
		};
		return $value === null ? null : (string) $value;
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
				"%s: world=%s type=%s entity=%s runtime-id=%s lines=%d actions=%d equipment=%d skin=%s cooldown=%.2f interaction=%s nametag=%s rotate=%.1f count=%s",
				$id,
				$definition->getWorld(),
				$definition->getEntityType(),
				$entity !== null && !$entity->isClosed() ? "yes" : "no",
				$entity !== null && !$entity->isClosed() ? (string) $entity->getId() : "n/a",
				count($definition->getLines()),
				count($definition->getCommands()),
				count($definition->getEquipment()),
				$definition->getSkinFile() === "" ? "default" : $definition->getSkinFile(),
				$definition->getCooldown(),
				$definition->getInteractionMode(),
				$definition->getNametagVisibility(),
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
			$renderedLines = $this->display->renderLines($definition, $this->queryResults[$id] ?? null);
			$this->display->applyNametagVisibility($entity, $definition, $renderedLines);
		}
	}

	private function syncNametagVisibility(string $id) : void{
		$definition = $this->definitions[$id] ?? null;
		$entity = $this->entities[$id] ?? null;
		if($definition === null || $entity === null || $entity->isClosed()){
			return;
		}
		$renderedLines = $this->display->renderLines($definition, $this->queryResults[$id] ?? null);
		$this->display->syncNametagVisibility($entity, $definition, $renderedLines);
	}

	private function loadSkin(NpcDefinition $definition) : Skin{
		if($definition->getSkinUrl() !== "" && $definition->getSkinFile() === ""){
			$this->fetchSkinUrl($definition);
		}
		return $this->assets->loadSkin($definition);
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
		$definition->setSkinDataFile("");
		$this->save();
		$this->respawn($id);
	}

	private function queryPlayerCount(NpcDefinition $definition) : void{
		$key = strtolower($definition->getQueryHost() . ":" . $definition->getQueryPort());
		if(isset($this->queryInFlight[$key])){
			return;
		}
		$plugin = $this->plugin->getServer()->getPluginManager()->getPlugin("BedrockQuery");
		$api = null;
		if($plugin !== null && is_callable([$plugin, "getApi"])){
			$candidate = $plugin->getApi();
			$api = is_object($candidate) ? $candidate : null;
		}
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

}

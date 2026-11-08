<?php

declare(strict_types=1);

namespace AxolotlBucketLabs\NPC\entity;

use AxolotlBucketLabs\NPC\model\FlightPattern;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\NeverSavedWithChunkEntity;
use pocketmine\entity\animation\Animation;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\animation\CriticalHitAnimation;
use pocketmine\entity\animation\HurtAnimation;
use pocketmine\entity\animation\MagicHitAnimation;
use pocketmine\entity\Location;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\SyncActorPropertyPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\entity\Attribute as NetworkAttribute;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\player\Player;
use function array_map;
use function array_values;

/**
 * A lightweight visual mob actor. It deliberately has no mob AI or drops: NPC
 * definitions, not world entity saves, own its lifecycle and interaction data.
 *
 * The Bedrock client supplies the model and animation controller from the
 * network type identifier. Server-side behavior remains intentionally passive.
 */
final class MobNpcEntity extends Living implements NeverSavedWithChunkEntity, NpcNametagVisibility, NpcHeadRotation{
	use NpcNametagVisibilityTrait, NpcHeadRotationTrait;
	private bool $flightInitialized = false;
	private float $flightOriginX = 0.0;
	private float $flightOriginY = 0.0;
	private float $flightOriginZ = 0.0;
	private float $flightPhase;

	/**
	 * @param array{metadata?: array<int, int>, properties?: array<int, int>}|null $variantData
	 */
	public function __construct(Location $location, private string $npcId, private string $networkTypeId, private float $width = 0.6, private float $height = 1.8, private float $eyeHeight = 1.62, private bool $canFly = false, private ?array $variantData = null){
		parent::__construct($location);
		$this->setCanSaveWithChunk(false);
		$this->setNoClientPredictions(true);
		$this->setGravity(0.0);
		$this->flightPhase = FlightPattern::phaseFor($npcId);
	}

	public static function getNetworkTypeId() : string{
		return "minecraft:bee";
	}

	public function getNpcId() : string{
		return $this->npcId;
	}

	public function getNetworkNpcTypeId() : string{
		return $this->networkTypeId;
	}

	public function getName() : string{
		return "NPC Mob";
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo($this->height, $this->width, min($this->eyeHeight, $this->height));
	}

	public function canBeMovedByCurrents() : bool{
		return false;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::CAN_FLY, $this->canFly);
		if($this->networkTypeId === "minecraft:shulker"){
			// Shulkers are rendered as block-attached actors. Supplying their
			// attachment metadata prevents the client from falling back to an
			// arbitrary orientation when the actor is spawned without vanilla AI.
			$position = $this->getPosition();
			$properties->setByte(EntityMetadataProperties::SHULKER_ATTACH_FACE, 0);
			$properties->setByte(EntityMetadataProperties::SHULKER_ATTACHED, 1);
			$properties->setBlockPos(EntityMetadataProperties::SHULKER_ATTACH_POS, BlockPosition::fromVector3($position));
		}
		foreach($this->variantData["metadata"] ?? [] as $property => $value){
			if($property === EntityMetadataProperties::COLOR || $property === EntityMetadataProperties::COLOR_2){
				$properties->setByte($property, $value);
			}else{
				$properties->setInt($property, $value);
			}
		}
		$this->syncNpcNametagVisibility($properties);
	}

	/** Uses the definition's network ID instead of the static fallback required by Entity. */
	protected function sendSpawnPacket(Player $player) : void{
		if($this->networkTypeId === "minecraft:armadillo"){
			// Armadillo state is an actor property, not ordinary metadata. The
			// client must know the enum domain before it decodes PropertySyncData.
			$player->getNetworkSession()->sendDataPacket(self::createArmadilloPropertyPacket(), true);
		}
		$player->getNetworkSession()->sendDataPacket(AddActorPacket::create(
			$this->getId(),
			$this->getId(),
			$this->networkTypeId,
			$this->getOffsetPosition($this->getLocation()->asVector3()),
			$this->getMotion(),
			$this->getLocation()->pitch,
			$this->getLocation()->yaw,
			$this->getNpcHeadYaw(),
			$this->getLocation()->yaw,
			array_map(static fn(\pocketmine\entity\Attribute $attribute) : NetworkAttribute => new NetworkAttribute(
				$attribute->getId(),
				$attribute->getMinValue(),
				$attribute->getMaxValue(),
				$attribute->getValue()
			), array_values($this->attributeMap->getAll())),
			$this->getAllNetworkData(),
			new PropertySyncData($this->variantData["properties"] ?? [], []),
			[],
			null
		));
	}

	/**
	 * Bedrock runs the vanilla Armadillo state machine on the client after the
	 * actor is spawned. Reasserting the configured enum value keeps a visual NPC
	 * deterministic instead of allowing proximity logic to mutate its appearance.
	 */
	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if($this->networkTypeId === "minecraft:armadillo" && ($properties = $this->variantData["properties"] ?? []) !== [] && !$this->isClosed()){
			foreach($this->getViewers() as $viewer){
				$viewer->getNetworkSession()->sendDataPacket(SetActorDataPacket::create(
					$this->getId(),
					[],
					new PropertySyncData($properties, []),
					0
				));
			}
		}
		return $hasUpdate;
	}

	private static function createArmadilloPropertyPacket() : SyncActorPropertyPacket{
		return SyncActorPropertyPacket::create(new CacheableNbt(
			CompoundTag::create()
				->setTag("properties", new ListTag([
					CompoundTag::create()
						->setTag("enum", new ListTag([
							new StringTag("unrolled"),
							new StringTag("rolled_up"),
							new StringTag("rolled_up_peeking"),
							new StringTag("rolled_up_relaxing"),
							new StringTag("rolled_up_unrolling")
						], NBT::TAG_String))
						->setString("name", "minecraft:armadillo_state")
						->setInt("type", 3)
				], NBT::TAG_Compound))
				->setString("type", "minecraft:armadillo")
		));
	}

	/**
	 * Moves the Bee through a small passive hover/orbit around its spawn point.
	 * This is visual-only. It does not add mob AI, pollination, or persistence.
	 */
	public function tickFlight(int $tick) : void{
		$position = $this->getPosition();
		$offset = FlightPattern::offset($tick, $this->flightPhase);
		if(!$this->flightInitialized){
			$this->flightOriginX = $position->x - $offset["x"];
			$this->flightOriginY = $position->y - $offset["y"];
			$this->flightOriginZ = $position->z - $offset["z"];
			$this->flightInitialized = true;
			return;
		}

		$this->move(
			$this->flightOriginX + $offset["x"] - $position->x,
			$this->flightOriginY + $offset["y"] - $position->y,
			$this->flightOriginZ + $offset["z"] - $position->z
		);
	}

	public function playAnimation(string $type, string $id = "") : void{
		if($type === "emote" || $type === "fly"){
			return;
		}
		$animation = match($type){
			"arm_swing" => new ArmSwingAnimation($this),
			"hurt" => new HurtAnimation($this),
			"critical_hit" => new CriticalHitAnimation($this),
			"magic_critical" => new MagicHitAnimation($this),
			default => null
		};
		if($animation instanceof Animation){
			$this->broadcastAnimation($animation);
		}
	}
}

<?php

declare(strict_types=1);

namespace NhanAZ\NPC\entity;

use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\entity\NeverSavedWithChunkEntity;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\types\entity\Attribute as NetworkAttribute;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\player\Player;
use function array_map;
use function array_values;

/**
 * A passive visual actor for vanilla non-living entities.
 *
 * The server does not give these actors vehicle behaviour, TNT priming or
 * world persistence. They exist only as a client-side model owned by NPC.
 */
final class ActorNpcEntity extends Entity implements NeverSavedWithChunkEntity, NpcNametagVisibility, NpcHeadRotation{
	use NpcNametagVisibilityTrait, NpcHeadRotationTrait;
	public function __construct(
		Location $location,
		private string $npcId,
		private string $networkTypeId,
		private float $width,
		private float $height
	){
		parent::__construct($location);
		$this->setCanSaveWithChunk(false);
		$this->setNoClientPredictions(true);
		$this->setGravity(0.0);
	}

	/** Static fallback required by Entity; the actual ID is sent per actor. */
	public static function getNetworkTypeId() : string{
		return "minecraft:boat";
	}

	public function getNpcId() : string{
		return $this->npcId;
	}

	public function getNetworkNpcTypeId() : string{
		return $this->networkTypeId;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo($this->width, $this->height);
	}

	public function canBeMovedByCurrents() : bool{
		return false;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$this->syncNpcNametagVisibility($properties);
	}

	protected function getInitialDragMultiplier() : float{
		return 0.0;
	}

	protected function getInitialGravity() : float{
		return 0.0;
	}

	/** Uses the definition's network ID instead of the static fallback. */
	protected function sendSpawnPacket(Player $player) : void{
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
			new PropertySyncData([], []),
			[],
			null
		));
	}
}

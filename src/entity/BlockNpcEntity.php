<?php

declare(strict_types=1);

namespace NhanAZ\NPC\entity;

use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\NeverSavedWithChunkEntity;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;

/** Passive FallingSand-style actor. It never falls or mutates the world. */
final class BlockNpcEntity extends Entity implements NeverSavedWithChunkEntity, NpcNametagVisibility, NpcHeadRotation{
	use NpcNametagVisibilityTrait, NpcHeadRotationTrait;
	public function __construct(Location $location, private string $npcId, private Block $block){
		parent::__construct($location);
		$this->setCanSaveWithChunk(false);
		$this->setNoClientPredictions(true);
		$this->setGravity(0.0);
	}

	public static function getNetworkTypeId() : string{ return EntityIds::FALLING_BLOCK; }

	public function getNpcId() : string{ return $this->npcId; }

	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(0.98, 0.98); }

	public function canBeMovedByCurrents() : bool{ return false; }

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setInt(
			EntityMetadataProperties::VARIANT,
			TypeConverter::getInstance()->getBlockTranslator()->internalIdToNetworkId($this->block->getStateId())
		);
		$this->syncNpcNametagVisibility($properties);
	}

	protected function getInitialDragMultiplier() : float{ return 0.0; }

	protected function getInitialGravity() : float{ return 0.0; }

	public function getOffsetPosition(Vector3 $vector3) : Vector3{
		return $vector3->add(0, 0.49, 0);
	}

	public function getBlock() : Block{ return $this->block; }
}

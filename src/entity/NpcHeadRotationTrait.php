<?php

declare(strict_types=1);

namespace AxolotlBucketLabs\NPC\entity;

use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use function is_finite;

trait NpcHeadRotationTrait{
	private ?float $npcHeadYaw = null;

	public function setNpcHeadYaw(float $yaw) : void{
		if(!is_finite($yaw)){
			throw new \InvalidArgumentException("NPC head yaw must be finite");
		}
		if($this->npcHeadYaw === $yaw){
			return;
		}
		$this->npcHeadYaw = $yaw;
		$this->scheduleUpdate();
	}

	public function getNpcHeadYaw() : float{
		return $this->npcHeadYaw ?? $this->getLocation()->yaw;
	}

	/** Sends the current body and head rotation immediately to existing viewers. */
	public function syncNpcHeadYaw() : void{
		if(!$this->isClosed()){
			$this->updateMovement(true);
		}
	}

	/** Keeps Axolotl-PM's movement packet from replacing the NPC head yaw with body yaw. */
	protected function broadcastMovement(bool $teleport = false) : void{
		NetworkBroadcastUtils::broadcastPackets($this->getViewers(), [MoveActorAbsolutePacket::create(
			$this->getId(),
			$this->getOffsetPosition($this->getLocation()->asVector3()),
			$this->getLocation()->pitch,
			$this->getLocation()->yaw,
			$this->getNpcHeadYaw(),
			$this->isOnGround() ? MoveActorAbsolutePacket::FLAG_GROUND : 0
		)]);
	}
}

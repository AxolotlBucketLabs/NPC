<?php

declare(strict_types=1);

namespace NhanAZ\NPC\entity;

use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;

trait NpcNametagVisibilityTrait{
	private int $npcNametagVisibility = 1;
	private bool $npcNametagCanShow = true;

	public function setNpcNametagVisibility(string $visibility) : void{
		// Match Slapper's native semantics: the client decides when a visible,
		// non-always nametag is being looked at.
		$this->npcNametagCanShow = $visibility !== "never";
		$this->npcNametagVisibility = match($visibility){
			"never" => 0,
			"hover" => 0,
			default => 1
		};
		$this->setNameTagVisible($visibility !== "never");
		$this->setNameTagAlwaysVisible($visibility === "always");
	}

	protected function syncNpcNametagVisibility(EntityMetadataCollection $properties) : void{
		// This is the same metadata shape produced by the native Slapper-era
		// Entity setters: visible controls CAN_SHOW_NAMETAG, while byte 81
		// distinguishes hover (0) from always (1).
		$properties->setByte(EntityMetadataProperties::ALWAYS_SHOW_NAMETAG, $this->npcNametagVisibility, true);
		$properties->setGenericFlag(EntityMetadataFlags::CAN_SHOW_NAMETAG, $this->npcNametagCanShow);
	}
}

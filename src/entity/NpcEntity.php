<?php

declare(strict_types=1);

namespace NhanAZ\NPC\entity;

use pocketmine\entity\Human;
use pocketmine\entity\NeverSavedWithChunkEntity;
use pocketmine\entity\Skin;
use pocketmine\entity\Location;

final class NpcEntity extends Human implements NeverSavedWithChunkEntity{

	public function __construct(Location $location, private string $npcId){
		parent::__construct($location, self::defaultSkin());

		// NPC definitions are the source of truth. They must not be duplicated into
		// world chunks, which also keeps custom plugin entities out of world saves.
		$this->setCanSaveWithChunk(false);
		$this->setNoClientPredictions(true);
		$this->setGravity(0.0);
	}

	public function getNpcId() : string{
		return $this->npcId;
	}

	private static function defaultSkin() : Skin{
		// A valid opaque 64x64 fallback skin. Custom skin/geometry loading is kept
		// as a separate, validated phase so the core never fetches arbitrary URLs.
		return new Skin("NPC_Default", str_repeat("\xc8\xa0\x78\xff", 64 * 64));
	}
}

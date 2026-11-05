<?php

declare(strict_types=1);

namespace NhanAZ\NPC\entity;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\NeverSavedWithChunkEntity;
use pocketmine\entity\animation\Animation;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\animation\CriticalHitAnimation;
use pocketmine\entity\animation\HurtAnimation;
use pocketmine\entity\animation\MagicHitAnimation;
use pocketmine\entity\Location;

/**
 * A lightweight visual mob actor. It deliberately has no mob AI or drops: NPC
 * definitions, not world entity saves, own its lifecycle and interaction data.
 *
 * The Bedrock client supplies the model and animation controller from the
 * network type identifier. Server-side behavior remains intentionally passive.
 */
final class MobNpcEntity extends Living implements NeverSavedWithChunkEntity{

	public function __construct(Location $location, private string $npcId, private string $networkTypeId){
		parent::__construct($location);
		$this->setCanSaveWithChunk(false);
		$this->setNoClientPredictions(true);
		$this->setGravity(0.0);
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
		return new EntitySizeInfo(0.6, 0.7);
	}

	public function playAnimation(string $type, string $id = "") : void{
		if($type === "emote"){
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

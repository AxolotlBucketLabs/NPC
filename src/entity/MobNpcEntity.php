<?php

declare(strict_types=1);

namespace NhanAZ\NPC\entity;

use NhanAZ\NPC\model\FlightPattern;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\NeverSavedWithChunkEntity;
use pocketmine\entity\animation\Animation;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\animation\CriticalHitAnimation;
use pocketmine\entity\animation\HurtAnimation;
use pocketmine\entity\animation\MagicHitAnimation;
use pocketmine\entity\Location;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;

/**
 * A lightweight visual mob actor. It deliberately has no mob AI or drops: NPC
 * definitions, not world entity saves, own its lifecycle and interaction data.
 *
 * The Bedrock client supplies the model and animation controller from the
 * network type identifier. Server-side behavior remains intentionally passive.
 */
final class MobNpcEntity extends Living implements NeverSavedWithChunkEntity{
	private bool $flightInitialized = false;
	private float $flightOriginX = 0.0;
	private float $flightOriginY = 0.0;
	private float $flightOriginZ = 0.0;
	private float $flightPhase;

	public function __construct(Location $location, private string $npcId, private string $networkTypeId){
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
		return new EntitySizeInfo(0.6, 0.7);
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::CAN_FLY, true);
	}

	/**
	 * Moves the Bee through a small passive hover/orbit around its spawn point.
	 * This is deliberately visual-only; no mob AI, pollination or persistence is added.
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

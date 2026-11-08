<?php

declare(strict_types=1);

namespace AxolotlBucketLabs\NPC\display;

use AxolotlBucketLabs\NPC\entity\ActorNpcEntity;
use AxolotlBucketLabs\NPC\entity\BlockNpcEntity;
use AxolotlBucketLabs\NPC\entity\MobNpcEntity;
use AxolotlBucketLabs\NPC\entity\NpcEntity;
use AxolotlBucketLabs\NPC\model\NpcDefinition;
use AxolotlBucketLabs\NPC\model\VanillaMobCatalog;
use function atan2;
use function rad2deg;
use function round;
use function sqrt;

/** Keeps stationary NPC actors facing the nearest player in range. */
final class NpcLookAtService{
	public function rotateTowardsNearestPlayer(NpcEntity|MobNpcEntity|BlockNpcEntity|ActorNpcEntity $entity, NpcDefinition $definition) : void{
		$profile = VanillaMobCatalog::profile($definition->getEntityType());
		if(!$profile->supports("look_at")){
			return;
		}
		$nearest = null;
		$nearestDistance = $definition->getRotationDistance() ** 2;
		foreach($entity->getWorld()->getPlayers() as $player){
			if(!$player->isConnected() || !$player->isAlive() || $player->isSpectator()){
				continue;
			}
			$distance = $player->getPosition()->distanceSquared($entity->getPosition());
			if($distance < $nearestDistance){
				$nearest = $player;
				$nearestDistance = $distance;
			}
		}
		if($nearest === null){
			[$yaw, $pitch] = $this->normalizeRotation($definition->getYaw(), $definition->getPitch(), $profile->getRotationMode());
			if(abs($entity->getLocation()->yaw - $yaw) > 0.001 || abs($entity->getLocation()->pitch - $pitch) > 0.001 || abs($entity->getNpcHeadYaw() - $yaw) > 0.001){
				$entity->setRotation($yaw, $pitch);
				$entity->setNpcHeadYaw($yaw);
				$entity->syncNpcHeadYaw();
			}
			return;
		}

		$dx = $nearest->getPosition()->x - $entity->getPosition()->x;
		$dy = $nearest->getEyePos()->y - $entity->getEyePos()->y;
		$dz = $nearest->getPosition()->z - $entity->getPosition()->z;
		$horizontal = sqrt($dx ** 2 + $dz ** 2);
		$yaw = rad2deg(atan2(-$dx, $dz));
		$pitch = -rad2deg(atan2($dy, $horizontal));
		[$yaw, $pitch] = $this->normalizeRotation($yaw, $pitch, $profile->getRotationMode());
		$entity->setRotation($yaw, $pitch);
		$entity->setNpcHeadYaw($yaw);
		$entity->syncNpcHeadYaw();
	}

	/** @return array{float, float} */
	private function normalizeRotation(float $yaw, float $pitch, string $rotationMode) : array{
		if($rotationMode === "cardinal"){
			// Bedrock renders a Shulker as a block-attached actor. Its head yaw is
			// not a useful free-look channel, so keep its body on a cardinal side.
			return [round($yaw / 90.0) * 90.0, 0.0];
		}
		return [$yaw, $pitch];
	}
}

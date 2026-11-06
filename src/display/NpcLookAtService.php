<?php

declare(strict_types=1);

namespace NhanAZ\NPC\display;

use NhanAZ\NPC\entity\ActorNpcEntity;
use NhanAZ\NPC\entity\BlockNpcEntity;
use NhanAZ\NPC\entity\MobNpcEntity;
use NhanAZ\NPC\entity\NpcEntity;
use NhanAZ\NPC\model\NpcDefinition;
use function atan2;
use function rad2deg;
use function round;
use function sqrt;

/** Keeps stationary NPC actors facing the nearest player in range. */
final class NpcLookAtService{
	public function rotateTowardsNearestPlayer(NpcEntity|MobNpcEntity|BlockNpcEntity|ActorNpcEntity $entity, NpcDefinition $definition) : void{
		$nearest = null;
		$nearestDistance = $definition->getRotationDistance() ** 2;
		foreach($entity->getWorld()->getPlayers() as $player){
			$distance = $player->getPosition()->distanceSquared($entity->getPosition());
			if($distance < $nearestDistance){
				$nearest = $player;
				$nearestDistance = $distance;
			}
		}
		if($nearest === null){
			return;
		}

		$dx = $nearest->getPosition()->x - $entity->getPosition()->x;
		$dy = $nearest->getEyePos()->y - $entity->getEyePos()->y;
		$dz = $nearest->getPosition()->z - $entity->getPosition()->z;
		$horizontal = sqrt($dx ** 2 + $dz ** 2);
		$yaw = rad2deg(atan2(-$dx, $dz));
		$pitch = -rad2deg(atan2($dy, $horizontal));
		if($entity instanceof MobNpcEntity && $entity->getNetworkNpcTypeId() === "minecraft:shulker"){
			// Bedrock renders a Shulker as a block-attached actor. Its head yaw is
			// not a useful look-at channel, so keep its body on a cardinal side.
			$yaw = round($yaw / 90.0) * 90.0;
			$pitch = 0.0;
		}
		$entity->setRotation($yaw, $pitch);
		$entity->setNpcHeadYaw($yaw);
		$entity->syncNpcHeadYaw();
	}
}

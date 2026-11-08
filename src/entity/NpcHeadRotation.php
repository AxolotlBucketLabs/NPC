<?php

declare(strict_types=1);

namespace AxolotlBucketLabs\NPC\entity;

interface NpcHeadRotation{
	public function setNpcHeadYaw(float $yaw) : void;

	public function getNpcHeadYaw() : float;

	public function syncNpcHeadYaw() : void;
}

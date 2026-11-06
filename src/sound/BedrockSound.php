<?php

declare(strict_types=1);

namespace NhanAZ\NPC\sound;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\world\sound\Sound;

/** Sends a vanilla Bedrock level sound event, optionally for an actor type. */
final class BedrockSound implements Sound{
	public function __construct(private string $event, private string $entityType = ":"){
	}

	public function encode(Vector3 $pos) : array{
		return [LevelSoundEventPacket::create(
			$this->event,
			$pos,
			-1,
			$this->entityType,
			false,
			false,
			-1,
			null
		)];
	}
}

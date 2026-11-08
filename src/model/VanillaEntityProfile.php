<?php

declare(strict_types=1);

namespace AxolotlBucketLabs\NPC\model;

/**
 * Runtime and UI capabilities for one visual Bedrock actor type.
 *
 * NPC uses passive actors rather than server-side mob classes, so this is the
 * single profile source for hitbox, eye height, rotation and editor controls.
 */
final class VanillaEntityProfile{
	/**
	 * @param array<string, bool> $capabilities
	 */
	public function __construct(
		private readonly float $width,
		private readonly float $height,
		private readonly float $eyeHeight,
		private readonly string $rotationMode,
		private readonly array $capabilities,
		private readonly float $spawnOffsetY = 0.0
	){
	}

	public function getWidth() : float{ return $this->width; }

	public function getHeight() : float{ return $this->height; }

	public function getEyeHeight() : float{ return $this->eyeHeight; }

	/**
	 * Bedrock actor models use different visual origins. This offset keeps the
	 * model aligned with the NPC location used by the editor and persistence
	 * layer without changing the stored coordinates.
	 */
	public function getSpawnOffsetY() : float{ return $this->spawnOffsetY; }

	/**
	 * `body_head` follows a player freely, `cardinal` is used by block-attached
	 * actors such as Shulker, and `none` keeps the saved rotation.
	 */
	public function getRotationMode() : string{ return $this->rotationMode; }

	public function supports(string $capability) : bool{
		return $this->capabilities[$capability] ?? false;
	}
}

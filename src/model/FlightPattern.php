<?php

declare(strict_types=1);

namespace AxolotlBucketLabs\NPC\model;

use function cos;
use function crc32;
use function sin;

final class FlightPattern{
	private const RADIUS = 0.35;
	private const BOB_HEIGHT = 0.12;
	private const ANGULAR_SPEED = 0.08;

	private function __construct(){
		//NOOP
	}

	/**
	 * Gives each Bee NPC a stable phase so multiple NPCs do not move in lockstep.
	 */
	public static function phaseFor(string $npcId) : float{
		$seed = crc32($npcId);
		if($seed < 0){
			$seed += 4294967296;
		}
		return ($seed / 4294967296.0) * 6.283185307179586;
	}

	/**
	 * @return array{x: float, y: float, z: float}
	 */
	public static function offset(int $tick, float $phase) : array{
		$angle = $phase + ($tick * self::ANGULAR_SPEED);
		return [
			"x" => cos($angle) * self::RADIUS,
			"y" => sin($angle * 2.0) * self::BOB_HEIGHT,
			"z" => sin($angle) * self::RADIUS
		];
	}
}

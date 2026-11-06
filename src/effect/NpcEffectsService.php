<?php

declare(strict_types=1);

namespace NhanAZ\NPC\effect;

use NhanAZ\NPC\entity\ActorNpcEntity;
use NhanAZ\NPC\entity\BlockNpcEntity;
use NhanAZ\NPC\entity\MobNpcEntity;
use NhanAZ\NPC\entity\NpcEntity;
use NhanAZ\NPC\model\EmoteCatalog;
use NhanAZ\NPC\model\NpcDefinition;
use NhanAZ\NPC\model\VanillaMobCatalog;
use NhanAZ\NPC\model\VanillaSoundCatalog;
use NhanAZ\NPC\sound\BedrockSound;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\animation\CriticalHitAnimation;
use pocketmine\entity\animation\HurtAnimation;
use pocketmine\entity\animation\MagicHitAnimation;
use pocketmine\player\Player;
use pocketmine\world\particle\BubbleParticle;
use pocketmine\world\particle\CriticalParticle;
use pocketmine\world\particle\EntityFlameParticle;
use pocketmine\world\particle\ExplodeParticle;
use pocketmine\world\particle\FlameParticle;
use pocketmine\world\particle\HappyVillagerParticle;
use pocketmine\world\particle\HeartParticle;
use pocketmine\world\particle\LavaParticle;
use pocketmine\world\particle\Particle;
use pocketmine\world\particle\PortalParticle;
use pocketmine\world\particle\SmokeParticle;
use pocketmine\world\particle\SplashParticle;
use pocketmine\world\particle\WaterParticle;
use pocketmine\world\sound\ClickSound;
use pocketmine\world\sound\DoorBumpSound;
use pocketmine\world\sound\ExplodeSound;
use pocketmine\world\sound\PopSound;
use pocketmine\world\sound\Sound;
use pocketmine\world\sound\TotemUseSound;
use function array_filter;
use function array_rand;
use function array_values;
use function microtime;

/** Owns effects that are broadcast by an NPC without changing its state. */
final class NpcEffectsService{
	/** @var array<string, float> */
	private array $lastRandomEmote = [];

	public function reset() : void{
		$this->lastRandomEmote = [];
	}

	public function createParticle(string $type) : Particle{
		return match($type){
			"bubble" => new BubbleParticle(),
			"critical" => new CriticalParticle(),
			"entity_flame" => new EntityFlameParticle(),
			"explode" => new ExplodeParticle(),
			"happy_villager" => new HappyVillagerParticle(),
			"heart" => new HeartParticle(),
			"lava" => new LavaParticle(),
			"portal" => new PortalParticle(),
			"smoke" => new SmokeParticle(),
			"splash" => new SplashParticle(),
			"water" => new WaterParticle(),
			default => new FlameParticle()
		};
	}

	public function playInteractionSound(NpcEntity|MobNpcEntity|BlockNpcEntity|ActorNpcEntity $entity, NpcDefinition $definition, Player $player) : void{
		$entity->getWorld()->addSound($entity->getPosition(), $this->createSound($definition->getSoundType()), [$player]);
	}

	public function playAnimation(NpcEntity|MobNpcEntity|BlockNpcEntity|ActorNpcEntity $entity, NpcDefinition $definition) : void{
		if($entity instanceof MobNpcEntity){
			$entity->playAnimation($definition->getAnimationType(), $definition->getAnimationId());
			return;
		}
		if(!($entity instanceof NpcEntity)){
			return;
		}
		switch($definition->getAnimationType()){
			case "emote":
				$emoteId = EmoteCatalog::resolve($definition->getAnimationId());
				if($emoteId !== null){
					$entity->emote($emoteId);
				}
				break;
			case "arm_swing":
				$entity->broadcastAnimation(new ArmSwingAnimation($entity));
				break;
			case "hurt":
				$entity->broadcastAnimation(new HurtAnimation($entity));
				break;
			case "critical_hit":
				$entity->broadcastAnimation(new CriticalHitAnimation($entity));
				break;
			case "magic_critical":
				$entity->broadcastAnimation(new MagicHitAnimation($entity));
				break;
		}
	}

	public function playAnimationSound(NpcEntity|MobNpcEntity|BlockNpcEntity|ActorNpcEntity $entity, NpcDefinition $definition) : void{
		$recipients = array_values(array_filter(
			$entity->getWorld()->getPlayers(),
			static fn(Player $player) : bool => $player->getPosition()->distanceSquared($entity->getPosition()) <= 16.0 ** 2
		));
		if($recipients === []){
			return;
		}
		$entity->getWorld()->addSound(
			$entity->getPosition(),
			$this->createSoundForEntity("flap", $definition->getEntityType()),
			$recipients
		);
	}

	public function tickRandomEmote(NpcDefinition $definition, NpcEntity|MobNpcEntity|BlockNpcEntity|ActorNpcEntity $entity) : void{
		if(!($entity instanceof NpcEntity)){
			return;
		}
		$now = microtime(true);
		$last = $this->lastRandomEmote[$definition->getId()] ?? 0.0;
		if($now - $last < $definition->getRandomEmoteCooldown()){
			return;
		}
		$pool = $definition->getEmotePool();
		if($pool === []){
			return;
		}
		$emote = $pool[array_rand($pool)];
		$emoteId = EmoteCatalog::resolve($emote);
		if($emoteId !== null){
			$entity->emote($emoteId);
			$this->lastRandomEmote[$definition->getId()] = $now;
		}
	}

	private function createSound(string $type) : Sound{
		return $this->createSoundForEntity($type, null);
	}

	private function createSoundForEntity(string $type, ?string $entityType) : Sound{
		return match($type){
			"door_bump" => new DoorBumpSound(),
			"explode" => new ExplodeSound(),
			"pop" => new PopSound(),
			"totem" => new TotemUseSound(),
			"click" => new ClickSound(),
			default => new BedrockSound(
				VanillaSoundCatalog::resolve($type) ?? "ambient",
				$entityType === null ? ":" : (VanillaMobCatalog::networkId($entityType) ?? "minecraft:player")
			)
		};
	}
}

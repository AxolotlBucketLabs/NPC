<?php

declare(strict_types=1);

namespace AxolotlBucketLabs\NPC;

use AxolotlBucketLabs\NPC\command\NpcCommandHandler;
use AxolotlBucketLabs\NPC\entity\NpcEntity;
use AxolotlBucketLabs\NPC\entity\MobNpcEntity;
use AxolotlBucketLabs\NPC\entity\BlockNpcEntity;
use AxolotlBucketLabs\NPC\entity\ActorNpcEntity;
use AxolotlBucketLabs\NPC\integration\ScoreHudIntegration;
use AxolotlBucketLabs\NPC\manager\NpcManager;
use AxolotlBucketLabs\NPC\model\EmoteCatalog;
use AxolotlBucketLabs\NPC\model\VanillaMobCatalog;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerEntityInteractEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use function stream_get_contents;

final class Main extends PluginBase implements Listener{

	private NpcManager $npcManager;

	private NpcCommandHandler $commandHandler;

	protected function onEnable() : void{
		$this->saveResource("config.yml");
		$emoteResource = $this->getResource("emotes.jsonl");
		if($emoteResource !== null){
			EmoteCatalog::load((string) stream_get_contents($emoteResource));
			fclose($emoteResource);
		}

		$this->npcManager = new NpcManager($this);
		$this->commandHandler = new NpcCommandHandler($this->npcManager);
		$this->npcManager->load();
		$this->npcManager->spawnAll();
		ScoreHudIntegration::register($this, $this->npcManager);

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{
			$this->npcManager->tick();
			if($this->getServer()->getTick() % 10 === 0){
				ScoreHudIntegration::update($this, $this->npcManager);
			}
		}), 1);
	}

	protected function onDisable() : void{
		if(isset($this->npcManager)){
			$this->npcManager->save();
			$this->npcManager->despawnAll();
		}
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if($command->getName() === "rca"){
			return $this->commandHandler->handleRca($sender, array_values($args));
		}

		return $this->commandHandler->handleNpc($sender, array_values($args));
	}

	public function getNpcManager() : NpcManager{
		return $this->npcManager;
	}

	/** @param array<string, mixed> $result */
	public function completeSkinFetch(string $id, array $result) : void{
		$this->npcManager->completeSkinFetch($id, $result);
	}

	public static function registerEntityType(string $name, string $networkId) : void{
		VanillaMobCatalog::registerCustom($name, $networkId);
	}

	public function onPlayerEntityInteract(PlayerEntityInteractEvent $event) : void{
		$entity = $event->getEntity();
		if($entity instanceof NpcEntity || $entity instanceof MobNpcEntity || $entity instanceof BlockNpcEntity || $entity instanceof ActorNpcEntity){
			if($event->getPlayer()->isSneaking() && $event->getPlayer()->hasPermission("npc.admin")){
				$event->cancel();
				$this->commandHandler->openEditor($event->getPlayer(), $entity->getNpcId());
				return;
			}
			$this->npcManager->handleInteract($event);
		}
	}

	/**
	 * @handleCancelled
	 */
	public function onEntityDamage(EntityDamageEvent $event) : void{
		$entity = $event->getEntity();
		if($entity instanceof NpcEntity || $entity instanceof MobNpcEntity || $entity instanceof BlockNpcEntity || $entity instanceof ActorNpcEntity){
			$event->cancel();
			if($event instanceof EntityDamageByEntityEvent && $event->getDamager() instanceof Player){
				$this->npcManager->handleHit($entity, $event->getDamager());
			}
		}
	}
}

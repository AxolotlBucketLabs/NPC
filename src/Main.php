<?php

declare(strict_types=1);

namespace NhanAZ\NPC;

use NhanAZ\NPC\command\NpcCommandHandler;
use NhanAZ\NPC\entity\NpcEntity;
use NhanAZ\NPC\manager\NpcManager;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerEntityInteractEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;

final class Main extends PluginBase implements Listener{

	private NpcManager $npcManager;

	private NpcCommandHandler $commandHandler;

	protected function onEnable() : void{
		$this->saveResource("config.yml");

		$this->npcManager = new NpcManager($this);
		$this->commandHandler = new NpcCommandHandler($this->npcManager);
		$this->npcManager->load();
		$this->npcManager->spawnAll();

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{
			$this->npcManager->tick();
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

	public function onPlayerEntityInteract(PlayerEntityInteractEvent $event) : void{
		if($event->getEntity() instanceof NpcEntity){
			$this->npcManager->handleInteract($event);
		}
	}

	/**
	 * @handleCancelled
	 */
	public function onEntityDamage(EntityDamageEvent $event) : void{
		$entity = $event->getEntity();
		if($entity instanceof NpcEntity){
			$event->cancel();
			if($event instanceof EntityDamageByEntityEvent && $event->getDamager() instanceof Player){
				$this->npcManager->handleHit($entity, $event->getDamager());
			}
		}
	}
}

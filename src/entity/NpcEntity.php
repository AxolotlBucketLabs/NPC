<?php

declare(strict_types=1);

namespace NhanAZ\NPC\entity;

use pocketmine\entity\Human;
use pocketmine\entity\NeverSavedWithChunkEntity;
use pocketmine\entity\Skin;
use pocketmine\entity\Location;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\player\Player;

final class NpcEntity extends Human implements NeverSavedWithChunkEntity{

	public function __construct(Location $location, private string $npcId, ?Skin $skin = null, private string $menuName = ""){
		parent::__construct($location, $skin ?? self::defaultSkin());

		// NPC definitions are the source of truth. They must not be duplicated into
		// world chunks, which also keeps custom plugin entities out of world saves.
		$this->setCanSaveWithChunk(false);
		$this->setNoClientPredictions(true);
		$this->setGravity(0.0);
	}

	public function getNpcId() : string{
		return $this->npcId;
	}

	public function getMenuName() : string{
		return $this->menuName;
	}

	public function setMenuName(string $menuName) : void{
		$menuName = str_replace(["\r", "\n"], " ", trim($menuName));
		$menuName = substr($menuName, 0, 128);
		if($this->menuName === $menuName){
			return;
		}
		if($this->menuName !== "" && $menuName === ""){
			$this->sendMenuRemovalToViewers();
		}
		$this->menuName = $menuName;
		if(!$this->isClosed()){
			$this->respawnToAll();
		}
	}

	protected function sendSpawnPacket(Player $player) : void{
		parent::sendSpawnPacket($player);
		if($this->menuName !== ""){
			$session = $player->getNetworkSession();
			$session->sendDataPacket(PlayerListPacket::create([
				PlayerListEntry::createAdditionEntry(
					$this->getUniqueId(),
					$this->getId(),
					$this->menuName,
					$session->getTypeConverter()->getSkinAdapter()->toSkinData($this->getSkin())
				)
			]));
		}
	}

	public function despawnFrom(Player $player, bool $send = true) : void{
		parent::despawnFrom($player, $send);
		if($send && $this->menuName !== ""){
			$this->sendMenuRemoval($player);
		}
	}

	public function despawnFromAll() : void{
		if($this->menuName !== ""){
			$this->sendMenuRemovalToViewers();
		}
		parent::despawnFromAll();
	}

	private function sendMenuRemovalToViewers() : void{
		foreach($this->getViewers() as $player){
			$this->sendMenuRemoval($player);
		}
	}

	private function sendMenuRemoval(Player $player) : void{
		$player->getNetworkSession()->sendDataPacket(PlayerListPacket::create([
			PlayerListEntry::createRemovalEntry($this->getUniqueId())
		]));
	}

	public static function defaultSkin() : Skin{
		// A valid opaque 64x64 fallback skin. Custom skin/geometry loading is kept
		// as a separate, validated phase so the core never fetches arbitrary URLs.
		return new Skin("NPC_Default", str_repeat("\xc8\xa0\x78\xff", 64 * 64));
	}
}

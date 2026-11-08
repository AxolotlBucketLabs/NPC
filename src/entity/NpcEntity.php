<?php

declare(strict_types=1);

namespace AxolotlBucketLabs\NPC\entity;

use pocketmine\entity\Human;
use pocketmine\entity\NeverSavedWithChunkEntity;
use pocketmine\entity\Skin;
use pocketmine\entity\Location;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\types\AbilitiesData;
use pocketmine\network\mcpe\protocol\types\AbilitiesLayer;
use pocketmine\network\mcpe\protocol\types\DeviceOS;
use pocketmine\network\mcpe\protocol\types\command\CommandPermissions;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\network\mcpe\protocol\types\entity\StringMetadataProperty;
use pocketmine\network\mcpe\protocol\types\GameMode;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\network\mcpe\protocol\types\PlayerPermissions;
use pocketmine\network\mcpe\protocol\types\skin\SkinData;
use pocketmine\player\Player;
use function array_fill;

final class NpcEntity extends Human implements NeverSavedWithChunkEntity, NpcNametagVisibility, NpcHeadRotation{
	use NpcNametagVisibilityTrait, NpcHeadRotationTrait;

	public function __construct(Location $location, private string $npcId, ?Skin $skin = null, private ?SkinData $networkSkinData = null){
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

	public function setNetworkSkinData(?SkinData $networkSkinData) : void{
		$this->networkSkinData = $networkSkinData;
	}

	public function canBeMovedByCurrents() : bool{
		return false;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$this->syncNpcNametagVisibility($properties);
	}

	/**
	 * Sends the persisted Bedrock SkinData directly when one is available.
	 * This keeps persona skins working even when the optional PersonaSkin
	 * plugin is no longer installed after the NPC was created.
	 */
	protected function sendSpawnPacket(Player $player) : void{
		$networkSession = $player->getNetworkSession();
		$skinData = $this->networkSkinData ?? TypeConverter::getInstance()->getSkinAdapter()->toSkinData($this->skin);
		$networkSession->sendDataPacket(PlayerListPacket::create([PlayerListEntry::createAdditionEntry(
			$this->getUniqueId(),
			$this->getId(),
			$this->getName(),
			$skinData
		)]));

		$networkSession->sendDataPacket(AddPlayerPacket::create(
			$this->getUniqueId(),
			$this->getName(),
			$this->getId(),
			"",
			$this->getLocation()->asVector3(),
			$this->getMotion(),
			$this->getLocation()->pitch,
			$this->getLocation()->yaw,
			$this->getNpcHeadYaw(),
			ItemStackWrapper::legacy($networkSession->getTypeConverter()->coreItemStackToNet($this->getInventory()->getItemInHand())),
			GameMode::SURVIVAL,
			$this->getAllNetworkData(),
			new PropertySyncData([], []),
			new AbilitiesData(CommandPermissions::NORMAL, PlayerPermissions::VISITOR, $this->getId(), [
				new AbilitiesLayer(
					AbilitiesLayer::LAYER_BASE,
					array_fill(0, AbilitiesLayer::NUMBER_OF_ABILITIES, false),
					0.0,
					0.0,
					0.0
				)
			]),
			[],
			null,
			"",
			DeviceOS::UNKNOWN
		));

		$this->sendData([$player], [EntityMetadataProperties::NAMETAG => new StringMetadataProperty($this->getNameTag())]);
		$entityEventBroadcaster = $networkSession->getEntityEventBroadcaster();
		$entityEventBroadcaster->onMobArmorChange([$networkSession], $this);
		$entityEventBroadcaster->onMobOffHandItemChange([$networkSession], $this);
		$networkSession->sendDataPacket(PlayerListPacket::create([PlayerListEntry::createRemovalEntry($this->getUniqueId())]));
	}

	public static function defaultSkin() : Skin{
		// A valid opaque 64x64 fallback skin. Custom skin/geometry loading is kept
		// as a separate, validated phase so the core never fetches arbitrary URLs.
		return new Skin("NPC_Default", str_repeat("\xc8\xa0\x78\xff", 64 * 64));
	}
}

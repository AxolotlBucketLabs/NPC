<?php

declare(strict_types=1);

namespace AxolotlBucketLabs\NPC\asset;

use AxolotlBucketLabs\NPC\Main;
use AxolotlBucketLabs\NPC\entity\NpcEntity;
use AxolotlBucketLabs\NPC\model\NpcDefinition;
use pocketmine\block\Block;
use pocketmine\entity\Skin;
use pocketmine\item\Item;
use pocketmine\item\LegacyStringToItemParser;
use pocketmine\item\StringToItemParser;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\skin\SkinData;
use pocketmine\player\Player;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use function array_filter;
use function base64_decode;
use function file_get_contents;
use function file_put_contents;
use function filesize;
use function in_array;
use function is_dir;
use function is_file;
use function is_string;
use function json_decode;
use function mkdir;
use function preg_match;
use function realpath;
use function str_replace;
use function strlen;
use const DIRECTORY_SEPARATOR;

/** Owns NPC assets and PMMP-specific item/skin serialization. */
final class NpcAssetService{
	public function __construct(private Main $plugin){ }

	/** @return array{skin: string, cape: string, geometry-name: string, geometry-data: string, skin-data: string}|null */
	public function persistSkin(Skin $skin, string $baseName) : ?array{
		$skinData = $skin->getSkinData();
		if(!in_array(strlen($skinData), Skin::ACCEPTED_SKIN_SIZES, true)){
			return null;
		}
		$skinsDirectory = $this->plugin->getDataFolder() . "skins";
		if(!is_dir($skinsDirectory) && !mkdir($skinsDirectory, 0775, true) && !is_dir($skinsDirectory)){
			return null;
		}
		$skinFile = $baseName . ".rgba";
		if(file_put_contents($skinsDirectory . DIRECTORY_SEPARATOR . $skinFile, $skinData, LOCK_EX) === false){
			return null;
		}
		$capeFile = "";
		if(strlen($skin->getCapeData()) === 8192){
			$capeFile = $baseName . ".cape.rgba";
			if(file_put_contents($skinsDirectory . DIRECTORY_SEPARATOR . $capeFile, $skin->getCapeData(), LOCK_EX) === false){
				$capeFile = "";
			}
		}
		$skinDataFile = "";
		try{
			$networkSkin = TypeConverter::getInstance()->getSkinAdapter()->toSkinData($skin);
			if($networkSkin->isPersona() || $networkSkin->isPersonaCapeOnClassic()){
				$writer = new ByteBufferWriter("");
				CommonTypes::putSkin($writer, $networkSkin);
				$encoded = base64_encode($writer->getData());
				if(strlen($encoded) <= 16 * 1024 * 1024){
					$skinDataFile = $baseName . ".skin-data.b64";
					if(file_put_contents($skinsDirectory . DIRECTORY_SEPARATOR . $skinDataFile, $encoded, LOCK_EX) === false){
						$skinDataFile = "";
					}
				}
			}
		}catch(\Throwable $error){
			$this->plugin->getLogger()->warning("Bedrock SkinData for NPC '$baseName' could not be persisted: " . $error->getMessage());
		}
		return [
			"skin" => $skinFile,
			"cape" => $capeFile,
			"geometry-name" => $skin->getGeometryName(),
			"geometry-data" => $skin->getGeometryData(),
			"skin-data" => $skinDataFile
		];
	}

	public function loadSkin(NpcDefinition $definition) : Skin{
		$networkSkin = $this->readNetworkSkinData($definition->getSkinDataFile());
		if($networkSkin !== null && ($networkSkin->isPersona() || $networkSkin->isPersonaCapeOnClassic())){
			$skin = $this->createLegacySkinFromNetworkData($definition, $networkSkin);
			if($skin !== null){
				return $skin;
			}
		}
		$skinData = $this->readSkinAsset($definition->getSkinFile(), max(Skin::ACCEPTED_SKIN_SIZES));
		if($skinData === "" || !in_array(strlen($skinData), Skin::ACCEPTED_SKIN_SIZES, true)){
			if($definition->getSkinFile() !== ""){
			$this->plugin->getLogger()->warning("NPC '" . $definition->getId() . "' has an invalid or missing raw skin file. Using the fallback skin.");
			}
			return NpcEntity::defaultSkin();
		}

		$capeData = $this->readSkinAsset($definition->getCapeFile(), 8192);
		if($capeData !== "" && strlen($capeData) !== 8192){
			$capeData = "";
		}
		try{
			return new Skin(
				"NPC_" . $definition->getId(),
				$skinData,
				$capeData,
				$definition->getGeometryName(),
				$definition->getGeometryData()
			);
		}catch(\Throwable $error){
			$this->plugin->getLogger()->warning("NPC '" . $definition->getId() . "' skin rejected: " . $error->getMessage());
			return NpcEntity::defaultSkin();
		}
	}

	public function applyEquipment(NpcEntity $entity, NpcDefinition $definition) : void{
		$parser = StringToItemParser::getInstance();
		foreach($definition->getEquipment() as $slot => $itemName){
			$item = $parser->parse($itemName);
			if($item === null){
				$this->plugin->getLogger()->warning("NPC '" . $definition->getId() . "' has an unknown equipment item '" . $itemName . "'");
				continue;
			}
			switch($slot){
				case "hand": $entity->getInventory()->setItemInHand($item); break;
				case "offhand": $entity->getOffHandInventory()->setItem(0, $item); break;
				case "helmet": $entity->getArmorInventory()->setHelmet($item); break;
				case "chestplate": $entity->getArmorInventory()->setChestplate($item); break;
				case "leggings": $entity->getArmorInventory()->setLeggings($item); break;
				case "boots": $entity->getArmorInventory()->setBoots($item); break;
			}
		}
	}

	/** @return array<string, string> */
	public function capturePlayerEquipment(Player $player) : array{
		$armor = $player->getArmorInventory();
		$equipment = [
			"hand" => $this->itemParserName($player->getInventory()->getItemInHand()),
			"offhand" => $this->itemParserName($player->getOffHandInventory()->getItem(0)),
			"helmet" => $this->itemParserName($armor->getHelmet()),
			"chestplate" => $this->itemParserName($armor->getChestplate()),
			"leggings" => $this->itemParserName($armor->getLeggings()),
			"boots" => $this->itemParserName($armor->getBoots())
		];
		return array_filter(
			$equipment,
			static fn(?string $item) : bool => $item !== null
		);
	}

	public function parseBlock(string $blockId) : ?Block{
		$blockId = trim($blockId);
		if($blockId === ""){
			return null;
		}
		$item = StringToItemParser::getInstance()->parse($blockId);
		if($item !== null){
			return $item->getBlock();
		}
		if(preg_match('/^\\d+(?::\\d+)?$/D', $blockId) === 1){
			try{
				return LegacyStringToItemParser::getInstance()->parse($blockId)->getBlock();
			}catch(\Throwable $error){
				$this->plugin->getLogger()->warning("NPC block '$blockId' uses an unsupported legacy block ID. Use a modern block name such as stone. Parser message: " . $error->getMessage());
			}
		}
		return null;
	}

	private function readSkinAsset(string $relativePath, int $maxBytes) : string{
		if($relativePath === ""){
			return "";
		}
		$root = realpath($this->plugin->getDataFolder() . "skins");
		if($root === false || !is_dir($root)){
			return "";
		}
		$path = realpath($root . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $relativePath));
		if($path === false || !is_file($path) || !str_starts_with($path, $root . DIRECTORY_SEPARATOR)){
			return "";
		}
		$size = filesize($path);
		if($size === false || $size > $maxBytes){
			return "";
		}
		$data = file_get_contents($path);
		return is_string($data) ? $data : "";
	}

	public function readNetworkSkinData(string $relativePath) : ?SkinData{
		$encoded = $this->readSkinAsset($relativePath, 16 * 1024 * 1024);
		if($encoded === ""){
			return null;
		}
		$raw = base64_decode($encoded, true);
		if($raw === false || $raw === ""){
			return null;
		}
		try{
			$reader = new ByteBufferReader($raw);
			$skin = CommonTypes::getSkin($reader);
			if($reader->getUnreadLength() !== 0){
				return null;
			}
			return $skin;
		}catch(\Throwable){
			return null;
		}
	}

	private function createLegacySkinFromNetworkData(NpcDefinition $definition, SkinData $networkSkin) : ?Skin{
		$skinData = $networkSkin->getSkinImage()->getData();
		if(!in_array(strlen($skinData), Skin::ACCEPTED_SKIN_SIZES, true)){
			return null;
		}
		$capeData = $networkSkin->isPersonaCapeOnClassic() ? "" : $networkSkin->getCapeImage()->getData();
		if(strlen($capeData) !== 8192){
			$capeData = "";
		}
		$geometryName = $definition->getGeometryName();
		$resourcePatch = json_decode($networkSkin->getResourcePatch(), true);
		if(is_array($resourcePatch) && is_array($resourcePatch["geometry"] ?? null) && is_string($resourcePatch["geometry"]["default"] ?? null)){
			$geometryName = $resourcePatch["geometry"]["default"];
		}
		$geometryData = $networkSkin->getGeometryDataJson();
		if($geometryData === SkinData::GEOMETRY_DATA_NONE){
			$geometryData = $definition->getGeometryData();
		}
		try{
			return new Skin("NPC_" . $definition->getId(), $skinData, $capeData, $geometryName, $geometryData);
		}catch(\Throwable){
			return null;
		}
	}

	private function itemParserName(Item $item) : ?string{
		if($item->isNull()){
			return null;
		}
		$aliases = StringToItemParser::getInstance()->lookupAliases($item);
		return $aliases[0] ?? null;
	}
}

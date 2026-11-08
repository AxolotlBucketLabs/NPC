<?php

declare(strict_types=1);

namespace AxolotlBucketLabs\NPC\display;

use AxolotlBucketLabs\NPC\entity\ActorNpcEntity;
use AxolotlBucketLabs\NPC\entity\BlockNpcEntity;
use AxolotlBucketLabs\NPC\entity\MobNpcEntity;
use AxolotlBucketLabs\NPC\entity\NpcEntity;
use AxolotlBucketLabs\NPC\entity\NpcNametagVisibility;
use AxolotlBucketLabs\NPC\Main;
use AxolotlBucketLabs\NPC\model\NpcDefinition;
use AxolotlBucketLabs\NPC\model\VanillaMobCatalog;
use pocketmine\entity\Entity;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function array_filter;
use function count;
use function str_replace;

/** Owns nametag rendering and network visibility metadata. */
final class NpcDisplayService{
	public function __construct(private Main $plugin){}

	/** @param array{ok: bool, players: int, max: int, status: string}|null $query */
	public function renderLines(NpcDefinition $definition, ?array $query = null) : string{
		$online = (string) count($this->plugin->getServer()->getOnlinePlayers());
		$worldOnline = (string) count(array_filter(
			$this->plugin->getServer()->getOnlinePlayers(),
			static fn(Player $player) : bool => $player->getWorld()->getFolderName() === $definition->getWorld()
		));
		$query ??= ["ok" => false, "players" => 0, "max" => 0, "status" => "offline"];
		$selectedCount = match($definition->getPlayerCountMode()){
			"world" => $worldOnline,
			"query" => (string) $query["players"],
			"none" => "",
			default => $online
		};
		$entityType = VanillaMobCatalog::resolve($definition->getEntityType()) ?? $definition->getEntityType();

		$lines = [];
		foreach($definition->getLines() as $line){
			$line = str_replace(
				["{npc}", "{npc_id}", "{world}", "{online}", "{players}", "{server_players}", "{world_players}", "{player_count}", "{query_players}", "{query_max}", "{query_status}", "{entity_type}", "{variant}", "{color}"],
				[$definition->getId(), $definition->getId(), $definition->getWorld(), $online, $selectedCount, $online, $worldOnline, $selectedCount, (string) $query["players"], (string) $query["max"], $query["status"], $entityType, $definition->getVariant(), "§"],
				$line
			);
			$line = str_replace(
				["{line}", "{newLine}", "{newline}", "{newLines}", "{newlines}", "{breakLine}", "{breakline}", "\\r\\n", "\\n", "\\r", "\r\n", "\r"],
				"\n",
				$line
			);
			$lines[] = TextFormat::colorize($line);
		}
		return implode("\n", $lines);
	}

	public function applyNametagVisibility(Entity $entity, NpcDefinition $definition, string $renderedLines) : void{
		$visibility = $definition->getNametagVisibility();
		if($entity instanceof NpcNametagVisibility){
			$entity->setNpcNametagVisibility($visibility);
		}else{
			$entity->setNameTagVisible($visibility !== "never");
			$entity->setNameTagAlwaysVisible($visibility === "always");
		}
		if($visibility === "never"){
			// Some Bedrock clients keep rendering a cached Human nametag even
			// after CAN_SHOW_NAMETAG is cleared. Keep the configured lines in the
			// definition, but clear the network text in never mode.
			$entity->setNameTag("");
		}else{
			$entity->setNameTag($renderedLines);
		}
	}

	public function syncNametagVisibility(Entity $entity, NpcDefinition $definition, string $renderedLines) : void{
		$this->applyNametagVisibility($entity, $definition, $renderedLines);
		// Visibility is entity metadata. Updating it in place avoids re-sending
		// AddPlayer/PlayerList packets. That is important for PersonaSkin, whose
		// full Bedrock SkinData is not represented by a legacy Skin object.
		$entity->sendData(null);
	}
}

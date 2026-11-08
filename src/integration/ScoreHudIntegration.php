<?php

declare(strict_types=1);

namespace AxolotlBucketLabs\NPC\integration;

use Ifera\ScoreHud\event\PlayerTagUpdateEvent;
use Ifera\ScoreHud\event\TagsResolveEvent;
use Ifera\ScoreHud\scoreboard\ScoreTag;
use AxolotlBucketLabs\NPC\Main;
use AxolotlBucketLabs\NPC\manager\NpcManager;
use pocketmine\event\Event;
use pocketmine\event\EventPriority;
use pocketmine\player\Player;

/**
 * Optional event-driven ScoreHud integration. ScoreHud remains a soft
 * dependency: no ScoreHud class is referenced until the plugin is present.
 */
final class ScoreHudIntegration{
	/** @var list<string> */
	private const TAGS = [
		"npc.count",
		"npc.ids",
		"npc.server_players",
		"npc.world_players",
		"npc.nearest_id",
		"npc.nearest_name",
		"npc.nearest_distance",
		"npc.nearest_entity_id",
		"npc.nearest_type",
		"npc.nearest_variant",
		"npc.nearest_world",
		"npc.nearest_nametag_visibility",
		"npc.nearest_animation",
		"npc.nearest_emote",
		"npc.nearest_motion"
	];

	private static bool $registered = false;

	public static function register(Main $plugin, NpcManager $manager) : void{
		if(self::$registered){
			return;
		}
		if(!class_exists(TagsResolveEvent::class) || !is_a(TagsResolveEvent::class, Event::class, true)){
			return;
		}
		$plugin->getServer()->getPluginManager()->registerEvent(
			TagsResolveEvent::class,
			static function(Event $event) use ($manager) : void{
				if(!method_exists($event, "getTag") || !method_exists($event, "getPlayer")){
					return;
				}
				$player = $event->getPlayer();
				$tag = $event->getTag();
				if(!$player instanceof Player || !is_object($tag) || !method_exists($tag, "getName") || !method_exists($tag, "setValue")){
					return;
				}
				$tagName = $tag->getName();
				if(!is_string($tagName)){
					return;
				}
				$value = $manager->resolveScoreTag($player, $tagName);
				if($value !== null){
					$tag->setValue($value);
				}
			},
			EventPriority::NORMAL,
			$plugin
		);
		self::$registered = true;
	}

	public static function update(Main $plugin, NpcManager $manager) : void{
		if(!class_exists(PlayerTagUpdateEvent::class) || !class_exists(ScoreTag::class)){
			return;
		}
		/** @var \ReflectionClass<object> $scoreTagReflection */
		$scoreTagReflection = new \ReflectionClass(ScoreTag::class);
		/** @var \ReflectionClass<object> $updateReflection */
		$updateReflection = new \ReflectionClass(PlayerTagUpdateEvent::class);
		foreach($plugin->getServer()->getOnlinePlayers() as $player){
			foreach(self::TAGS as $tagName){
				$value = $manager->resolveScoreTag($player, $tagName);
				$tag = $scoreTagReflection->newInstance($tagName, $value ?? "");
				$event = $updateReflection->newInstance($player, $tag);
				$updateReflection->getMethod("call")->invoke($event);
			}
		}
	}
}

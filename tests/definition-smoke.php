<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/model/NpcDefinition.php';
require dirname(__DIR__) . '/src/model/FlightPattern.php';
require dirname(__DIR__) . '/src/model/EmoteCatalog.php';
require dirname(__DIR__) . '/src/model/VanillaMobCatalog.php';

use NhanAZ\NPC\model\NpcDefinition;
use NhanAZ\NPC\model\FlightPattern;
use NhanAZ\NPC\model\EmoteCatalog;
use NhanAZ\NPC\model\VanillaMobCatalog;

$definition = new NpcDefinition(
	"welcome",
	"world",
	0.5,
	65.0,
	0.5,
	0.0,
	0.0,
	["&aWelcome", "&7Online: {online}"],
	["console: say {player}"],
	2.0,
	true,
	false,
	true,
	1.0,
	["hand" => "iron_sword", "helmet" => "iron_helmet"]
);

$definition->setEntityType("bee");
$definition->setAnimation("emote", "wave", 20, "right-click");

if($definition->getId() !== "welcome" || count($definition->getLines()) !== 2 || $definition->getCooldown() !== 2.0 || $definition->getEntityType() !== "bee" || $definition->getAnimationType() !== "emote" || $definition->getAnimationId() !== "wave" || $definition->getAnimationInterval() !== 20 || $definition->getAnimationTrigger() !== "right_click"){
	throw new RuntimeException("NpcDefinition basic state is invalid");
}
if(count(VanillaMobCatalog::all()) < 90 || VanillaMobCatalog::resolve("minecraft:zombie") !== "zombie" || VanillaMobCatalog::networkId("allay") !== "minecraft:allay" || VanillaMobCatalog::resolve("falling_block") !== "falling_block"){
	throw new RuntimeException("Vanilla entity catalog is incomplete");
}
$definition->setInteractionMode("separate");
$definition->setHitCommands(["message:hit"]);
$definition->setInteractCommands(["message:interact"]);
$definition->setNametagVisible(false);
$definition->setRotationDistance(32.0);
$definition->setPlayerCountMode("world");
$definition->setWalkPath([["world" => "world", "x" => 1, "y" => 65, "z" => 2]]);
$definition->setEmotePool(["wave", "simple_clap"]);
if($definition->getInteractionMode() !== "separate" || $definition->getHitCommands() !== ["message:hit"] || $definition->isNametagVisible() || $definition->getRotationDistance() !== 32.0 || count($definition->getWalkPath()) !== 1 || count($definition->getEmotePool()) !== 2){
	throw new RuntimeException("NpcDefinition extended state is invalid");
}
if(EmoteCatalog::resolve("wave") !== "4c8ae710-df2e-47cd-814d-cc7bf21a3d67" || EmoteCatalog::resolve("4c8ae710-df2e-47cd-814d-cc7bf21a3d67") !== "4c8ae710-df2e-47cd-814d-cc7bf21a3d67" || EmoteCatalog::resolve("not-an-emote") !== null){
	throw new RuntimeException("Bedrock emote resolution failed");
}
if(EmoteCatalog::load("{\"title\":\"Bee Dance\",\"uuid\":\"4c8ae710-df2e-47cd-814d-cc7bf21a3d67\"}\n{\"title\":\"NPC Cheer\",\"uuid\":\"9a469a61-c83b-4ba9-b507-bdbe64430582\"}") !== 2 || EmoteCatalog::resolve("bee dance") !== "4c8ae710-df2e-47cd-814d-cc7bf21a3d67"){
	throw new RuntimeException("Emote catalog loading failed");
}
$emoteCatalog = file_get_contents(dirname(__DIR__) . '/resources/emotes.jsonl');
if($emoteCatalog === false || EmoteCatalog::load($emoteCatalog) < 1000 || EmoteCatalog::resolve("4th dimension") !== "5f2c9473-ff88-ed4f-674b-9943dd6c81f2" || EmoteCatalog::resolve("tp random") !== "f8721633-b6e8-b55c-fe38-984f52d756bc"){
	throw new RuntimeException("Bundled Marketplace emote catalog failed");
}
if(EmoteCatalog::imageUrl("e115b8af-b508-06c0-c4a9-fe540bf2fd5b") !== "https://raw.githubusercontent.com/TwistedAsylumMC/Bedrock-Emotes/main/images/e115b8af-b508-06c0-c4a9-fe540bf2fd5b.png"){
	throw new RuntimeException("Emote thumbnail URL generation failed");
}
$airGuitarVariants = EmoteCatalog::search("air guitar", 2, 1);
if(count($airGuitarVariants) !== 2 || $airGuitarVariants[0]["uuid"] !== "d1d6eaef-c80f-e289-7770-5c1df04cf14f"){
	throw new RuntimeException("Emote catalog pagination failed");
}

$roundTrip = NpcDefinition::fromArray("welcome", $definition->toArray());
if($roundTrip->getCommands() !== ["console: say {player}"] || !$roundTrip->isRotateEnabled() || $roundTrip->getEquipment()["hand"] !== "iron_sword" || $roundTrip->getEntityType() !== "bee" || $roundTrip->getAnimationType() !== "emote" || $roundTrip->getAnimationTrigger() !== "right_click"){
	throw new RuntimeException("NpcDefinition round trip failed");
}

$roundTrip->setScale(9.0);
$roundTrip->setLocation("lobby", 1.5, 70.0, -2.5, 90.0, 0.0);
$roundTrip->setParticleType("happy-villager");
$roundTrip->setSoundType("door-bump");
$roundTrip->setMenuName("Lobby NPC");
if($roundTrip->getScale() !== 4.0 || $roundTrip->getWorld() !== "lobby" || $roundTrip->getX() !== 1.5 || $roundTrip->getYaw() !== 90.0){
	throw new RuntimeException("NpcDefinition location and scale mutation failed");
}
if($roundTrip->getParticleType() !== "happy_villager" || $roundTrip->getSoundType() !== "door_bump"){
	throw new RuntimeException("NpcDefinition effect type normalization failed");
}
if($roundTrip->getMenuName() !== "Lobby NPC" || NpcDefinition::fromArray("welcome", $roundTrip->toArray())->getMenuName() !== "Lobby NPC"){
	throw new RuntimeException("NpcDefinition menu name round trip failed");
}

$roundTrip->setAnimation("fly", "", 1, "timer");
if($roundTrip->getAnimationType() !== "fly" || NpcDefinition::fromArray("welcome", $roundTrip->toArray())->getAnimationType() !== "fly"){
	throw new RuntimeException("Bee flight animation round trip failed");
}

$phase = FlightPattern::phaseFor("welcome");
if(FlightPattern::offset(0, $phase) === FlightPattern::offset(20, $phase)){
	throw new RuntimeException("Bee flight path did not change over time");
}

try{
	new NpcDefinition("unsafe id", "world", 0, 0, 0, 0, 0, ["bad"]);
	throw new RuntimeException("Unsafe NPC id was accepted");
}catch(InvalidArgumentException){
}

echo "NPC definition smoke: PASS\n";

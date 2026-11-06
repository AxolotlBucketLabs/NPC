<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/model/NpcDefinition.php';
require dirname(__DIR__) . '/src/model/FlightPattern.php';
require dirname(__DIR__) . '/src/model/EmoteCatalog.php';
require dirname(__DIR__) . '/src/model/VanillaMobCatalog.php';
require dirname(__DIR__) . '/src/model/VanillaSoundCatalog.php';
require dirname(__DIR__) . '/src/model/VanillaVariantCatalog.php';

use NhanAZ\NPC\model\NpcDefinition;
use NhanAZ\NPC\model\FlightPattern;
use NhanAZ\NPC\model\EmoteCatalog;
use NhanAZ\NPC\model\VanillaMobCatalog;
use NhanAZ\NPC\model\VanillaSoundCatalog;
use NhanAZ\NPC\model\VanillaVariantCatalog;

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

$defaultSound = NpcDefinition::fromArray("default-sound", ["lines" => ["NPC"]]);
if($defaultSound->isSoundEnabled()){
	throw new RuntimeException("New NPC sound must be disabled by default");
}
if(!NpcDefinition::fromArray("default-rotate", ["lines" => ["NPC"]])->isRotateEnabled() || VanillaSoundCatalog::resolve("ambient_in_water") !== "ambient.in.water" || VanillaSoundCatalog::resolve("flap") !== "flap"){
	throw new RuntimeException("NPC defaults or vanilla sound catalog failed");
}
$explicitSound = NpcDefinition::fromArray("explicit-sound", ["lines" => ["NPC"], "sound" => true]);
if(!$explicitSound->isSoundEnabled()){
	throw new RuntimeException("Explicit sound configuration must remain enabled");
}

$definition->setEntityType("bee");
$definition->setVariant("blue");
$definition->setAnimation("emote", "wave", 20, "right-click");

if($definition->getId() !== "welcome" || count($definition->getLines()) !== 2 || $definition->getCooldown() !== 2.0 || $definition->getEntityType() !== "bee" || $definition->getAnimationType() !== "emote" || $definition->getAnimationId() !== "wave" || $definition->getAnimationInterval() !== 20 || $definition->getAnimationTrigger() !== "right_click"){
	throw new RuntimeException("NpcDefinition basic state is invalid");
}
if(count(VanillaMobCatalog::all()) < 90 || VanillaMobCatalog::all()[0] !== "human" || VanillaMobCatalog::resolve("minecraft:zombie") !== "zombie" || VanillaMobCatalog::networkId("allay") !== "minecraft:allay" || VanillaMobCatalog::resolve("falling_block") !== "falling_block" || VanillaMobCatalog::networkId("boat") !== "minecraft:boat" || VanillaMobCatalog::networkId("minecart") !== "minecraft:minecart" || VanillaMobCatalog::networkId("tnt") !== "minecraft:tnt"){
	throw new RuntimeException("Vanilla entity catalog is incomplete");
}
if(VanillaVariantCatalog::default("armadillo") !== "unrolled" || VanillaVariantCatalog::resolve("armadillo", "unrolled")["properties"][0] !== 0 || VanillaVariantCatalog::resolve("armadillo", "rolled_up")["properties"][0] !== 1 || VanillaVariantCatalog::resolve("axolotl", "blue")["metadata"][2] !== 4 || count(VanillaVariantCatalog::options("sheep")) !== 16 || count(VanillaVariantCatalog::options("wolf")) !== 9){
	throw new RuntimeException("Vanilla entity variant catalog is incomplete");
}
if(VanillaMobCatalog::iconPath("bee") !== "textures/items/spawn_eggs/spawn_egg_bee" || VanillaMobCatalog::iconPath("evoker") !== "textures/items/spawn_eggs/spawn_egg_evoker" || VanillaMobCatalog::iconPath("falling_block") !== "textures/blocks/stone" || VanillaMobCatalog::iconPath("boat") !== "textures/items/boat_oak" || VanillaMobCatalog::iconPath("unknown-custom") !== "textures/items/egg"){
	throw new RuntimeException("Vanilla form icon paths are invalid");
}
$definition->setInteractionMode("separate");
$definition->setHitCommands(["message:hit"]);
$definition->setInteractCommands(["message:interact"]);
$definition->setNametagVisible(false);
$definition->setNametagVisibility("hover");
$definition->setRotationDistance(32.0);
$definition->setPlayerCountMode("world");
$definition->setEmotePool(["wave", "simple_clap"]);
if($definition->getInteractionMode() !== "separate" || $definition->getHitCommands() !== ["message:hit"] || !$definition->isNametagVisible() || $definition->getNametagVisibility() !== "hover" || $definition->getRotationDistance() !== 32.0 || count($definition->getEmotePool()) !== 2){
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
if($roundTrip->getCommands() !== ["console: say {player}"] || !$roundTrip->isRotateEnabled() || $roundTrip->getEquipment()["hand"] !== "iron_sword" || $roundTrip->getEntityType() !== "bee" || $roundTrip->getVariant() !== "blue" || $roundTrip->getAnimationType() !== "emote" || $roundTrip->getAnimationTrigger() !== "right_click" || $roundTrip->getNametagVisibility() !== "hover"){
	throw new RuntimeException("NpcDefinition round trip failed");
}

$personaSkinDefinition = NpcDefinition::fromArray("persona", [
	"lines" => ["persona"],
	"skin-data" => "skins/NPC_0.skin-data.b64"
]);
if($personaSkinDefinition->getSkinDataFile() !== "skins/NPC_0.skin-data.b64" || NpcDefinition::fromArray("persona", $personaSkinDefinition->toArray())->getSkinDataFile() !== "skins/NPC_0.skin-data.b64"){
	throw new RuntimeException("PersonaSkin snapshot path round trip failed");
}

$legacyVisibility = NpcDefinition::fromArray("legacy", ["lines" => ["legacy"], "nametag-visible" => false]);
if($legacyVisibility->getNametagVisibility() !== "never" || $legacyVisibility->isNametagVisible()){
	throw new RuntimeException("Legacy nametag visibility migration failed");
}

$roundTrip->setScale(9.0);
$roundTrip->setLocation("lobby", 1.5, 70.0, -2.5, 90.0, 0.0);
$roundTrip->setParticleType("happy-villager");
$roundTrip->setSoundType("door-bump");
if($roundTrip->getScale() !== 4.0 || $roundTrip->getWorld() !== "lobby" || $roundTrip->getX() !== 1.5 || $roundTrip->getYaw() !== 90.0){
	throw new RuntimeException("NpcDefinition location and scale mutation failed");
}
if($roundTrip->getParticleType() !== "happy_villager" || $roundTrip->getSoundType() !== "door_bump"){
	throw new RuntimeException("NpcDefinition effect type normalization failed");
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

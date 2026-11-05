<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/model/NpcDefinition.php';
require dirname(__DIR__) . '/src/model/FlightPattern.php';

use NhanAZ\NPC\model\NpcDefinition;
use NhanAZ\NPC\model\FlightPattern;

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

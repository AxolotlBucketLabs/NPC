<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/model/NpcDefinition.php';

use NhanAZ\NPC\model\NpcDefinition;

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
	1.0
);

if($definition->getId() !== "welcome" || count($definition->getLines()) !== 2 || $definition->getCooldown() !== 2.0){
	throw new RuntimeException("NpcDefinition basic state is invalid");
}

$roundTrip = NpcDefinition::fromArray("welcome", $definition->toArray());
if($roundTrip->getCommands() !== ["console: say {player}"] || !$roundTrip->isRotateEnabled()){
	throw new RuntimeException("NpcDefinition round trip failed");
}

try{
	new NpcDefinition("unsafe id", "world", 0, 0, 0, 0, 0, ["bad"]);
	throw new RuntimeException("Unsafe NPC id was accepted");
}catch(InvalidArgumentException){
}

echo "NPC definition smoke: PASS\n";

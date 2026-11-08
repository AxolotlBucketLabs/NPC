<?php

declare(strict_types=1);

$path = dirname(__DIR__) . "/resources/emotes.jsonl";
$contents = file_get_contents($path);
if($contents === false){
	throw new RuntimeException("Could not read the bundled emote catalog");
}

$lines = preg_split('/\R/u', $contents, -1, PREG_SPLIT_NO_EMPTY);
if($lines === false || $lines === []){
	throw new RuntimeException("The bundled emote catalog is empty");
}

$uuids = [];
foreach($lines as $index => $line){
	try{
		$entry = json_decode(trim($line), true, 512, JSON_THROW_ON_ERROR);
	}catch(JsonException $exception){
		throw new RuntimeException("Invalid JSON on emote catalog line " . ($index + 1), 0, $exception);
	}
	if(!is_array($entry) || !is_string($entry["title"] ?? null) || !is_string($entry["uuid"] ?? null) || trim($entry["title"]) === ""){
		throw new RuntimeException("Invalid emote catalog entry on line " . ($index + 1));
	}
	$uuid = strtolower(trim($entry["uuid"]));
	if(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $uuid) !== 1){
		throw new RuntimeException("Invalid emote UUID on line " . ($index + 1));
	}
	if(isset($uuids[$uuid])){
		throw new RuntimeException("Duplicate emote UUID {$uuid}");
	}
	$uuids[$uuid] = true;
}

echo "NPC emote catalog smoke: PASS (" . count($lines) . " entries)\n";

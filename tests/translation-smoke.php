<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$languages = [
	"bul", "chs", "deu", "eng", "fra", "ita", "jpn", "kor",
	"lav", "nld", "rus", "spa", "tur", "vie"
];

/** @return array<string, string> */
$readMessages = static function(string $path) : array{
	$contents = file_get_contents($path);
	if($contents === false){
		throw new RuntimeException("Could not read {$path}");
	}
	if(preg_match_all('/^([A-Za-z0-9_.]+):\s+"(.*)"$/m', $contents, $matches, PREG_SET_ORDER) === false){
		throw new RuntimeException("Could not parse {$path}");
	}
	$messages = [];
	foreach($matches as $match){
		$key = $match[1];
		if(isset($messages[$key])){
			throw new RuntimeException("Duplicate translation key {$key} in {$path}");
		}
		$messages[$key] = $match[2];
	}
	return $messages;
};

$english = $readMessages($root . "/resources/lang/messages-eng.yml");
if(!isset($english["language.name"]) || count($english) < 200){
	throw new RuntimeException("The English locale is incomplete");
}

$source = file_get_contents($root . "/src/command/NpcCommandHandler.php") . file_get_contents($root . "/src/ui/NpcUiManager.php");
if($source === false){
	throw new RuntimeException("Could not read localized source files");
}
preg_match_all('/(?:\$this->t|new Translator::translate)\("((?:cmd|ui)\.[A-Za-z0-9_.]+)"/', $source, $usedKeys);
foreach(array_unique($usedKeys[1]) as $key){
	if(!isset($english[$key])){
		throw new RuntimeException("Missing English translation key {$key}");
	}
}

foreach($languages as $language){
	$messages = $readMessages($root . "/resources/lang/messages-{$language}.yml");
	if(($messages["language.name"] ?? "") === ""){
		throw new RuntimeException("Locale {$language} has no language name");
	}
}

echo "NPC translation smoke: PASS\n";

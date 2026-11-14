<?php

declare(strict_types=1);

const SOURCE_API_URL = "https://api.github.com/repos/TwistedAsylumMC/Bedrock-Emotes/contents/emotes.json";
const TARGET_PATH = __DIR__ . "/../resources/emotes.jsonl";

$targetPath = TARGET_PATH;
foreach($argv as $argument){
	if(str_starts_with($argument, "--output=")){
		$targetPath = substr($argument, 9);
	}
}

/**
 * @throws RuntimeException
 */
function fetchJson(string $url, ?string $authorizationToken = null) : string{
	$headers = [
		"Accept: application/vnd.github+json",
		"User-Agent: Axolotl-NPC-emote-catalog"
	];
	if($authorizationToken !== null){
		$headers[] = "Authorization: Bearer " . $authorizationToken;
	}
	$context = stream_context_create([
		"http" => [
			"method" => "GET",
			"header" => implode("\r\n", $headers) . "\r\n",
			"timeout" => 30
		],
		"ssl" => [
			"verify_peer" => true,
			"verify_peer_name" => true
		]
	]);
	$contents = file_get_contents($url, false, $context);
	if($contents === false){
		throw new RuntimeException("Could not fetch {$url}");
	}
	return $contents;
}

try{
	$authorizationToken = getenv("GH_TOKEN") ?: null;
	$metadata = json_decode(fetchJson(SOURCE_API_URL, $authorizationToken), true, 512, JSON_THROW_ON_ERROR);
	if(!is_array($metadata) || !is_string($metadata["sha"] ?? null) || !is_string($metadata["download_url"] ?? null)){
		throw new RuntimeException("The upstream metadata response is missing its source revision");
	}

	$sourceSha = $metadata["sha"];
	$source = json_decode(fetchJson($metadata["download_url"]), true, 512, JSON_THROW_ON_ERROR);
	if(!is_array($source) || !array_is_list($source)){
		throw new RuntimeException("The upstream emote catalog is not a list");
	}

	$entries = [];
	$uuids = [];
	foreach($source as $index => $entry){
		if(!is_array($entry) || !is_string($entry["title"] ?? null) || !is_string($entry["uuid"] ?? null)){
			throw new RuntimeException("The upstream entry at index {$index} is missing title or UUID");
		}
		$title = trim($entry["title"]);
		$uuid = strtolower(trim($entry["uuid"]));
		if($title === ""){
			throw new RuntimeException("The upstream entry at index {$index} has an empty title");
		}
		if(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $uuid) !== 1){
			throw new RuntimeException("The upstream entry at index {$index} has an invalid UUID");
		}
		if(isset($uuids[$uuid])){
			throw new RuntimeException("The upstream catalog contains the duplicate UUID {$uuid}");
		}
		$uuids[$uuid] = true;
		$entries[] = ["title" => $title, "uuid" => $uuid];
	}

	if($entries === []){
		throw new RuntimeException("The upstream emote catalog is empty");
	}

	$lines = [];
	foreach($entries as $entry){
		$lines[] = json_encode($entry, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}
	$contents = implode("\n", $lines) . "\n";
	$current = is_file($targetPath) ? file_get_contents($targetPath) : null;
	if($current === false){
		throw new RuntimeException("Could not read " . $targetPath);
	}
	$changed = $current === null || str_replace(["\r\n", "\r"], "\n", $current) !== $contents;
	if($changed && file_put_contents($targetPath, $contents) === false){
		throw new RuntimeException("Could not write " . $targetPath);
	}

	echo "source_sha={$sourceSha}\n";
	echo "entries=" . count($entries) . "\n";
	echo "changed=" . ($changed ? "true" : "false") . "\n";
}catch(JsonException $exception){
	fwrite(STDERR, "Could not decode the upstream emote catalog: " . $exception->getMessage() . "\n");
	exit(1);
}catch(Throwable $exception){
	fwrite(STDERR, $exception->getMessage() . "\n");
	exit(1);
}

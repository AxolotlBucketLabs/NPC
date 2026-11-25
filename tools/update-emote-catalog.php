<?php

declare(strict_types=1);

const SOURCE_API_URL = "https://api.github.com/repos/AxolotlBucketLabs/BedrockCatalog/commits?path=data%2Fpersona-emotes.json&per_page=1";
const SOURCE_DATA_URL = "https://raw.githubusercontent.com/AxolotlBucketLabs/BedrockCatalog/";
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
	$commits = json_decode(fetchJson(SOURCE_API_URL, $authorizationToken), true, 512, JSON_THROW_ON_ERROR);
	if(!is_array($commits) || !array_is_list($commits) || !is_string($commits[0]["sha"] ?? null) || preg_match('/^[0-9a-f]{40}$/D', $commits[0]["sha"]) !== 1){
		throw new RuntimeException("BedrockCatalog did not return a valid source revision");
	}

	$sourceSha = $commits[0]["sha"];
	$source = json_decode(fetchJson(SOURCE_DATA_URL . $sourceSha . "/data/persona-emotes.json"), true, 512, JSON_THROW_ON_ERROR);
	if(!is_array($source) || ($source["schemaVersion"] ?? null) !== 1 || ($source["dataset"] ?? null) !== "persona_emotes" || !is_array($source["items"] ?? null) || !array_is_list($source["items"])){
		throw new RuntimeException("BedrockCatalog returned an unsupported persona-emote export");
	}

	$entries = [];
	$uuids = [];
	foreach($source["items"] as $index => $entry){
		if(!is_array($entry) || ($entry["dataset"] ?? null) !== "persona_emote" || ($entry["pieceType"] ?? null) !== "persona_emote" || ($entry["contentType"] ?? null) !== "PersonaDurable" || !is_string($entry["title"] ?? null) || !is_string($entry["uuid"] ?? null)){
			throw new RuntimeException("BedrockCatalog entry at index {$index} is not a confirmed persona emote with a title and UUID");
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

		$thumbnailUrl = null;
		$images = $entry["images"] ?? null;
		if(!is_array($images) || !array_is_list($images)){
			throw new RuntimeException("BedrockCatalog entry at index {$index} has an invalid image list");
		}
		foreach($images as $image){
			if(!is_array($image)){
				throw new RuntimeException("BedrockCatalog image at index {$index} is invalid");
			}
			if(($image["Tag"] ?? null) !== "Thumbnail"){
				continue;
			}
			if(!is_string($image["Url"] ?? null)){
				throw new RuntimeException("BedrockCatalog thumbnail at index {$index} has no URL");
			}
			$thumbnailUrl = $image["Url"];
			$urlParts = parse_url($thumbnailUrl);
			$host = is_array($urlParts) ? strtolower($urlParts["host"] ?? "") : "";
			if(!is_array($urlParts) || strtolower($urlParts["scheme"] ?? "") !== "https" || (!str_ends_with($host, ".xboxlive.com") && !str_ends_with($host, ".catalog.playfab.com"))){
				throw new RuntimeException("BedrockCatalog thumbnail at index {$index} has an unsupported URL");
			}
			break;
		}
		$entries[] = ["title" => $title, "uuid" => $uuid, "imageUrl" => $thumbnailUrl];
	}

	if($entries === [] || ($source["count"] ?? null) !== count($entries)){
		throw new RuntimeException("BedrockCatalog emote count does not match its exported entries");
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
	fwrite(STDERR, "Could not decode the BedrockCatalog export: " . $exception->getMessage() . "\n");
	exit(1);
}catch(Throwable $exception){
	fwrite(STDERR, $exception->getMessage() . "\n");
	exit(1);
}

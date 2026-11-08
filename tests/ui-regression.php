<?php

declare(strict_types=1);

$uiSource = file_get_contents(dirname(__DIR__) . '/src/ui/NpcUiManager.php');
$managerSource = file_get_contents(dirname(__DIR__) . '/src/manager/NpcManager.php');
$displaySource = file_get_contents(dirname(__DIR__) . '/src/display/NpcDisplayService.php');
$lookAtSource = file_get_contents(dirname(__DIR__) . '/src/display/NpcLookAtService.php');
$effectsSource = file_get_contents(dirname(__DIR__) . '/src/effect/NpcEffectsService.php');
$assetSource = file_get_contents(dirname(__DIR__) . '/src/asset/NpcAssetService.php');
$definitionSource = file_get_contents(dirname(__DIR__) . '/src/model/NpcDefinition.php');
$mobSource = file_get_contents(dirname(__DIR__) . '/src/entity/MobNpcEntity.php');
$actorSource = file_get_contents(dirname(__DIR__) . '/src/entity/ActorNpcEntity.php');
$blockSource = file_get_contents(dirname(__DIR__) . '/src/entity/BlockNpcEntity.php');
$profileSource = file_get_contents(dirname(__DIR__) . '/src/model/VanillaEntityProfile.php');
$commandServiceSource = file_get_contents(dirname(__DIR__) . '/src/integration/NpcCommandService.php');
$scoreHudSource = file_get_contents(dirname(__DIR__) . '/src/integration/ScoreHudIntegration.php');
if($uiSource === false || $managerSource === false || $displaySource === false || $lookAtSource === false || $effectsSource === false || $assetSource === false || $profileSource === false || $commandServiceSource === false || $scoreHudSource === false){
	throw new RuntimeException('Could not read NPC UI/manager/service sources');
}

// A one-argument closure is an onClose callback in this UI. It must not open
// another form: PMForms invokes it when the client clicks the X button.
if(preg_match('/function\s*\(Player\s+\$player\)\s+use\s*\(/', $uiSource) === 1){
	throw new RuntimeException('NPC form close callbacks must not navigate to another form');
}

if(preg_match('/public function setNametagVisibility\(.*?\r?\n\t}\r?\n/s', $managerSource, $match) !== 1 || str_contains($match[0], '$this->respawn(')){
	throw new RuntimeException('Nametag visibility must not respawn and reload the NPC skin');
}

if(preg_match('/public function applyNametagVisibility\(.*?\r?\n\t}\r?\n/s', $displaySource, $match) !== 1 || !str_contains($match[0], '$entity->setNameTag("")')){
	throw new RuntimeException('Never nametag visibility must clear the network nametag text');
}

if(preg_match('/public function syncNametagVisibility\(.*?\r?\n\t}\r?\n/s', $displaySource, $match) !== 1 || !str_contains($match[0], '$entity->sendData(null)') || str_contains($match[0], '$entity->respawnToAll()')){
	throw new RuntimeException('Live nametag mode changes must update metadata without respawning the actor or re-sending its skin');
}

if(!str_contains($uiSource, 'new Input("id", $this->t("ui.stable_id"), "NPC_0", $suggestedId)') || !str_contains($uiSource, '$this->manager->getNextSuggestedId()')){
	throw new RuntimeException('NPC create form must show an editable auto-incrementing ID suggestion');
}

$commandSource = file_get_contents(dirname(__DIR__) . '/src/command/NpcCommandHandler.php');
if($commandSource === false || !str_contains($commandSource, 'return $this->manager->getNextSuggestedId();') || str_contains($commandSource, 'return "npc_"')){
	throw new RuntimeException('Legacy NPC spawn commands must use the same NPC_0 ID suggestion as the UI');
}

if(str_contains($uiSource, 'new MenuOption("Browse Entity Types")') || str_contains($uiSource, 'new MenuOption("Browse Emotes")')){
	throw new RuntimeException('The main NPC menu must keep entity and emote browsers out of the primary actions');
}

if(!str_contains($uiSource, 'private function openEntityBrowser') || !str_contains($uiSource, 'new MenuOption($this->t("ui.search_entity_types"))') || !str_contains($uiSource, 'private function openEntitySearch') || !str_contains($uiSource, '$this->t("ui.special_actors") . "\\nBoat') || !str_contains($uiSource, 'new FormIcon(VanillaMobCatalog::iconPath("boat"), FormIcon::IMAGE_TYPE_PATH)') || !str_contains($uiSource, 'new FormIcon(VanillaMobCatalog::iconPath($type), FormIcon::IMAGE_TYPE_PATH)')){
	throw new RuntimeException('Entity type editing must open the type menu first and keep search inside that menu');
}

if(!str_contains($uiSource, 'private array $editReturnsToList') || !str_contains($uiSource, '$this->editReturnsToList[strtolower($player->getName())] = false') || !str_contains($uiSource, 'if($returnToList)')){
	throw new RuntimeException('Direct NPC editing must not show a list Back button');
}

if(preg_match('/private function openSkin\(.*?\r?\n\t}\r?\n/s', $uiSource, $match) !== 1 || !str_contains($match[0], 'new MenuForm') || !str_contains($match[0], 'new MenuOption($this->t("ui.use_local_skin"))') || !str_contains($match[0], 'new MenuOption($this->t("ui.use_https_skin"))') || !str_contains($match[0], 'new MenuOption($this->t("ui.player_skin"))') || !str_contains($uiSource, 'private function openPlayerSkin')){
	throw new RuntimeException('Skin source must expose local, HTTPS, and player-skin modes');
}

if(!str_contains($uiSource, 'new Toggle("sync_all"') || !str_contains($uiSource, '$this->manager->setEquipmentFromPlayer') || !str_contains($managerSource, 'public function setEquipmentFromPlayer')){
	throw new RuntimeException('Equipment editing must support copying the player loadout with toggles');
}

if(!str_contains($uiSource, 'private function openWorldList(Player $player, string $world)') || !str_contains($uiSource, '$definition->getWorld()')){
	throw new RuntimeException('NPC listing must be grouped by world before selecting an NPC');
}

if(preg_match('/private function openDelete\(.*?\r?\n\t}\r?\n/s', $uiSource, $match) !== 1 || str_contains($match[0], '$this->openMain(')){
	throw new RuntimeException('Deleting an NPC from the editor must not reopen the /npc form');
}

if(preg_match('/private function openCreate\(.*?\r?\n\t}\r?\n\r?\n\tprivate function openEntityBrowser/s', $uiSource, $match) !== 1 || str_contains($match[0], '$this->openEdit(')){
	throw new RuntimeException('Creating an NPC must close after submit instead of opening the editor');
}

if($definitionSource === false || !str_contains($definitionSource, 'private bool $rotate = true') || !str_contains($definitionSource, 'self::boolValue($data, "rotate", true)')){
	throw new RuntimeException('NPC rotation must be enabled by default');
}

if(!str_contains($managerSource, "has invalid block-id '") || str_contains($managerSource, 'using Human fallback') || !str_contains($managerSource, 'setBlockId("stone")')){
	throw new RuntimeException('Falling block NPCs must use a valid block actor fallback instead of Human');
}

if(!str_contains($uiSource, 'if($type === "falling_block")') || !str_contains($uiSource, '$this->openBlockVisual($player, $npcId)')){
	throw new RuntimeException('Selecting Falling Block must open its block selector immediately');
}

if(!str_contains($managerSource, '$playerSkin = $player->getSkin()') || !str_contains($managerSource, '$this->assets->persistSkin($playerSkin, $id)') || !str_contains($assetSource, 'getOffHandInventory()') || !str_contains($managerSource, 'getScale()') || !str_contains($managerSource, 'spawn($definition, $playerSkin)')){
	throw new RuntimeException('Human NPC creation must snapshot player appearance');
}

$nametagSource = file_get_contents(dirname(__DIR__) . '/src/entity/NpcNametagVisibilityTrait.php');
if($nametagSource === false || !str_contains($nametagSource, 'setByte(EntityMetadataProperties::ALWAYS_SHOW_NAMETAG, $this->npcNametagVisibility, true)') || !str_contains($nametagSource, 'EntityMetadataFlags::CAN_SHOW_NAMETAG') || str_contains($nametagSource, 'EntityMetadataFlags::ALWAYS_SHOW_NAMETAG, $alwaysVisible')){
	throw new RuntimeException('Nametag visibility must match the native Slapper metadata path');
}

$headSource = file_get_contents(dirname(__DIR__) . '/src/entity/NpcHeadRotationTrait.php');
if($headSource === false || !str_contains($headSource, 'MoveActorAbsolutePacket::create') || !str_contains($headSource, '$this->npcHeadYaw')){
	throw new RuntimeException('NPC movement packets must carry a separate head yaw');
}

foreach([$mobSource, $actorSource, $blockSource] as $entitySource){
	if($entitySource === false || !str_contains($entitySource, 'canBeMovedByCurrents() : bool') || !str_contains($entitySource, 'return false;')){
		throw new RuntimeException('NPC actors must ignore liquid currents');
	}
}

if(!str_contains($managerSource, 'VanillaMobCatalog::profile($entityType)') || !str_contains($managerSource, 'getParticleCount()') || !str_contains($lookAtSource, 'rotateTowardsNearestPlayer') || !str_contains($uiSource, 'private function openLookAt') || !str_contains($uiSource, 'ui.look_at_nearby_players')){
	throw new RuntimeException('NPC manager/UI is missing dimension-aware actors, particle bursts, or look-at settings');
}

if(!str_contains($profileSource, 'getEyeHeight') || !str_contains($profileSource, 'getRotationMode') || !str_contains($profileSource, 'supports') || !str_contains($mobSource, 'private float $eyeHeight') || !str_contains($actorSource, 'private float $eyeHeight') || !str_contains($managerSource, '$type === "fly" && !$profile->supports("flight")')){
	throw new RuntimeException('Entity profiles must define hitbox, eye height, rotation and form capabilities');
}

foreach([$uiSource, $managerSource, $definitionSource] as $source){
	if($source === false || str_contains($source, 'getWalk') || str_contains($source, 'setWalk') || str_contains($source, 'walk-enabled') || str_contains($source, 'WalkPathFinder')){
		throw new RuntimeException('Walking controls must be removed from the NPC core');
	}
}

if(!str_contains($uiSource, 'private function openVariant') || !str_contains($uiSource, 'VanillaVariantCatalog::hasVariants') || !str_contains($managerSource, 'public function setVariant') || !str_contains($managerSource, 'new MobNpcEntity($location, $definition->getId(), $networkId, $profile->getWidth(), $profile->getHeight(), $profile->getEyeHeight(), $profile->supports("flight"), $variant)')){
	throw new RuntimeException('NPC entity variants must be selectable in the UI and passed to the actor');
}

if(!str_contains($mobSource, 'EntityMetadataProperties::COLOR') || !str_contains($mobSource, 'setInt($property, $value)') || !str_contains($mobSource, 'new PropertySyncData($this->variantData["properties"] ?? [], [])') || !str_contains($definitionSource, 'private string $variant = ""') || !str_contains($definitionSource, '"variant" => $this->variant')){
	throw new RuntimeException('NPC variants must be rendered through Bedrock metadata and persisted in definitions');
}

if(!str_contains($mobSource, 'SyncActorPropertyPacket') || !str_contains($mobSource, 'minecraft:armadillo_state') || !str_contains($mobSource, 'rolled_up_unrolling') || !str_contains($mobSource, 'SetActorDataPacket::create')){
	throw new RuntimeException('Armadillo NPCs must register and keep their selected actor property state');
}

if(!str_contains($mobSource, 'EntityMetadataProperties::SHULKER_ATTACH_FACE') || !str_contains($mobSource, 'EntityMetadataProperties::SHULKER_ATTACH_POS') || !str_contains($lookAtSource, 'round($yaw / 90.0) * 90.0')){
	throw new RuntimeException('Shulker NPCs must use block attachment metadata and cardinal body rotation');
}

if(!str_contains($lookAtSource, '$dy = $nearest->getEyePos()->y - $entity->getEyePos()->y') || !str_contains($lookAtSource, 'rad2deg(atan2($dy, $horizontal))') || !str_contains($lookAtSource, '$definition->getYaw()') || !str_contains($lookAtSource, '$player->isSpectator()')){
	throw new RuntimeException('NPC look-at rotation must calculate vertical pitch from eye position');
}

if(str_contains($managerSource, 'syncHoverNametags') || str_contains($managerSource, 'isPlayerLookingAtNpcBody') || str_contains($nametagSource, 'sendNpcNametagTo')){
	throw new RuntimeException('NPC nametag visibility must use the simple native Slapper-style metadata path');
}

if(!str_contains($nametagSource, '$this->npcNametagCanShow = $visibility !== "never"') || !str_contains($nametagSource, '$this->setNameTagVisible($visibility !== "never")') || !str_contains($nametagSource, '$this->setNameTagAlwaysVisible($visibility === "always")')){
	throw new RuntimeException('NPC nametag visibility must match Slapper never/hover/always semantics');
}

foreach(['{line}', '{newLine}', '{newLines}', '{breakLine}', '\\\\n', '\\\\r\\\\n'] as $lineBreakToken){
	if(!str_contains($displaySource, $lineBreakToken)){
		throw new RuntimeException("NPC nametags must support line-break token $lineBreakToken");
	}
}

foreach(['{npc_id}', '{entity_type}', '{variant}', '{color}'] as $tag){
	if(!str_contains($displaySource, $tag)){
		throw new RuntimeException("NPC nametags must support $tag");
	}
}

foreach(['{player_uuid}', '{npc_id}', '{entity_type}', '{variant}', '{npc_x}', '{npc_y}', '{npc_z}', '{npc_yaw}', '{npc_pitch}'] as $tag){
	if(!str_contains($commandServiceSource, $tag)){
		throw new RuntimeException("NPC click actions must support $tag");
	}
}
if(!str_contains($commandServiceSource, 'getUniqueId()->toString()')){
	throw new RuntimeException('NPC click actions must expose the interacting player UUID');
}

foreach(['npc.nearest_variant', 'npc.nearest_world', 'npc.nearest_nametag_visibility'] as $tag){
	if(!str_contains($scoreHudSource, $tag) || !str_contains($managerSource, $tag)){
		throw new RuntimeException("ScoreHud integration must support $tag");
	}
}

if(!str_contains($uiSource, 'new MenuOption($this->t("ui.sound"))') || !str_contains($uiSource, 'new MenuOption($this->t("ui.particles"))') || !str_contains($uiSource, 'private function openSoundBrowser') || !str_contains($uiSource, 'private function openParticleBrowser') || !str_contains($uiSource, 'VanillaSoundCatalog::all()') || !str_contains($uiSource, 'VanillaParticleCatalog::all()') || !str_contains($effectsSource, 'new BedrockSound')){
	throw new RuntimeException('NPC effects must split sound and particle browser menus backed by vanilla catalogs');
}

if(!str_contains($uiSource, 'private function openActorAnimationSettings') || !str_contains($uiSource, '$this->openEmoteResults($player, $id, "", 0)') || !str_contains($uiSource, 'new MenuOption($this->t("ui.clear_animation"))') || str_contains($uiSource, 'private function openAnimationSettings')){
	throw new RuntimeException('Actor animations and player emotes must use separate, non-conflicting UI flows');
}

if(!str_contains($uiSource, 'private function openPlayerCountWorld') || !str_contains($uiSource, 'private function openPlayerCountQuery') || !str_contains($uiSource, '"world" => $this->t("ui.this_world")') || !str_contains($uiSource, '"query" => $this->t("ui.bedrock_query_host")')){
	throw new RuntimeException('Player count must use separate mode and configuration forms');
}

echo "NPC UI/nametag regression: PASS\n";

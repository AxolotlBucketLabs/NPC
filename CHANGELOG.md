# Changelog

All notable changes to NPC are documented here.

## [Unreleased]

The next release candidate is version `0.8.0`.

### Added

- Add capability-aware profiles for hitboxes, eye height, rotation, variants, and editor controls.
- Add vanilla entity variants, particle types, sound events, player-emote browsing, and ScoreHud tags.
- Add NPC nametag and click-action placeholders for IDs, entity types, variants, coordinates, and player UUIDs.
- Add live look-at rotation toward nearby players while keeping NPCs stationary.

### Changed

- Move the maintained plugin namespace and project identity to `AxolotlBucketLabs`.
- Use AxolotlBucketLabs DevTools `v3.3.0` for CI and release packaging.
- Resolve PMForms `2.3.1` from the AxolotlBucketLabs repository.
- Keep Human, mob, block, boat, minecart, and TNT NPCs as visual actors without mob AI or pathfinding.

### Fixed

- Preserve Human skin, geometry, equipment, and Bedrock `SkinData` snapshots across restarts.
- Update nametag visibility and appearance changes without unnecessarily respawning Human NPCs.
- Keep special actors aligned to their block anchors and ignore liquid currents.

### Compatibility

- Axolotl-PM or PocketMine-MP API `5.0.0` or newer within the API 5 boundary.
- PHP `8.4`.
- PMForms `2.3.1`, bundled into the built PHAR.

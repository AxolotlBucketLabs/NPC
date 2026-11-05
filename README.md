# NPC

`NPC` is an API 5 plugin for Axolotl-PM and PocketMine-MP. It provides a small, self-contained NPC core with a Slapper-style command surface while keeping NPC definitions in plugin data instead of world entity saves.

This repository is the source of truth for the plugin. It does not depend on the Ifera/ScoreHud wiki or on the source of the old Slapper add-ons.

## Current local milestone

The first deployable milestone includes:

- Human NPC entities with a valid fallback skin;
- persistent definitions in `plugin_data/NPC/npcs.yml`;
- `/npc create`, `remove`, `setname`, `setline`, `list`, `tp`, `reload` and `debug`;
- command actions, including trusted `console:` actions;
- per-player interaction cooldowns;
- optional look-at-nearest-player rotation;
- optional configurable particles and hit sounds (with flame/click defaults);
- local raw RGBA skin/cape files with path confinement and size validation;
- basic hand and armor equipment from PocketMine item aliases;
- `{npc}`, `{world}`, `{online}`, `{players}` and `{display_name}` placeholders;
- `{prefix}` and `{suffix}` when PureChat is installed;
- `{rank}` when PurePerms is installed;
- `{balance}`, `{money}` and `{coins}` when SimpleEconomy, EcoAPI, TownCoins, or TownEconomy exposes a compatible scalar getter;
- `/slapper` as a command alias and a guarded `/rca <player> <command>` compatibility command;
- a compatibility command surface for `/slapper spawn Human`, `/slapper edit`, `entities`, `version`, and `cancel`;
- hit-session compatibility for `/slapper id`, `/slapper remove`, `/slapper cancel`, and left-click command actions;
- edit aliases for names, commands, hand/armor equipment, scale, reload, and teleporting an NPC or player;
- DevTools PHAR packaging and PHPStan Level Max in CI.

NPC actions are configured by operators only. A player cannot write an action or a skin URL through the public command surface.

## Commands

```text
/npc help
/npc create <id> [name]
/npc remove <id>
/npc setname <id> <name>
/npc setline <id> <line> <text>
/npc addcommand <id> <command|console:command>
/npc clearcommands <id>
/npc equipment <id> <hand|helmet|chestplate|leggings|boots> <item|clear>
/npc skin <id> <relative-raw-skin-file|player|clear>
/npc cooldown <id> <seconds>
/npc scale <id> <size>
/npc particle-type <id> <type>
/npc sound-type <id> <type>
/npc rotate <id> <on|off>
/npc particle <id> <on|off>
/npc sound <id> <on|off>
/npc tp <id>
/npc list
/npc debug
/npc id
/npc cancel
/npc reload
/rca <player> <command>
```

Use `&a`, `&e`, or native `§` formatting in nametags. Example:

```yaml
version: 1
npcs:
  welcome:
    world: world
    x: 0.5
    y: 65.0
    z: 0.5
    yaw: 0.0
    pitch: 0.0
    lines:
      - "&aWelcome"
      - "&7Online: &f{online}"
    commands:
      - "console: say {player} clicked welcome"
      - "spawn"
    cooldown: 2
    rotate: true
    particle: false
    particle-type: flame
    sound: true
    sound-type: click
    scale: 1
    equipment:
      hand: iron_sword
      helmet: iron_helmet
```

Raw skins are deliberately local-only in this milestone. Place a 64x32, 64x64, or 128x128 RGBA file under `plugin_data/NPC/skins/`, then run `/npc skin <id> <relative-file>`. From in-game, `/npc skin <id> player` (or the old `/slapper edit <id> skin`) copies the executing player's validated skin, cape, and geometry into that local directory. Cape files must be exactly 8192 bytes and can be set in YAML with the `cape` field. The plugin does not download or follow skin URLs.

Player-specific integration placeholders are expanded for click actions. Nametags remain shared entity metadata, so `{online}` and `{players}` are suitable for persistent nametags while `{rank}` and `{balance}` are intended for action commands unless a future per-viewer nametag layer is enabled.

Supported particle types are `flame`, `entity_flame`, `heart`, `critical`, `smoke`, `explode`, `portal`, `happy_villager`, `bubble`, `splash`, `water`, and `lava`. Supported hit sounds are `click`, `pop`, `door_bump`, `explode`, and `totem`. Unknown values fall back safely to the default effect instead of dynamically loading a class.

## Slapper command compatibility

The `/slapper` command alias accepts the historical Human/Player workflow without loading the old Slapper plugin:

```text
/slapper spawn Human [name]
/slapper edit <id> name <name>
/slapper edit <id> addcommand <command>
/slapper edit <id> delcommand <command>
/slapper edit <id> listcommands
/slapper edit <id> helmet|chestplate|leggings|boots|hand <item|clear>
/slapper edit <id> scale <size>
/npc particle-type <id> <type>
/npc sound-type <id> <type>
/slapper edit <id> tphere
/slapper edit <id> tpto
```

The compatibility layer deliberately reports unsupported historical entity types instead of pretending they are equivalent to a Human. `/npc id` (or `/slapper id`) waits for a hit and reports both the stable NPC ID and the current network entity ID. `/npc remove` without an ID waits for a hit and removes only the selected NPC definition; `/npc cancel` clears the pending session. Right-click and left-click both run the configured actions, subject to the same cooldown.

When the old Slapper plugin is still installed and its entities are loaded, an operator can import the Human entities with:

```text
/npc migrate-slapper
```

The command copies location, nametag, commands, scale, hand/armor aliases, skin/cape/geometry, and creates persistent NPC definitions. It keeps the old entities by default so the result can be checked first. After verification, `/npc migrate-slapper remove` closes only the successfully imported old entities. If Slapper is absent or its entities were not loaded, the command safely reports zero imports; it does not attempt to parse arbitrary world files.

## Compatibility boundary

The core deliberately has no hard dependency on PureChat, PurePerms, ScoreHud, EcoAPI, SimpleEconomy, TownCoins, or TownEconomy. This lets it load when those plugins are absent and avoids coupling NPC persistence to another plugin's storage format.

The current milestone exposes generic placeholders plus soft adapters for the method shapes used by the current PureChat, PurePerms, SimpleEconomy, EcoAPI, TownCoins, and TownEconomy plugins. ScoreHud tag registration, forms, non-Human entity types, menu-name player-list entries, and full addon parity still require separate integration tests. The Slapper migration command intentionally requires the old plugin to have loaded the source entities; it is not a blind world-NBT converter. Until those tests exist, this README does not claim full replacement compatibility with every historical Slapper extension.

The old Slapper repositories and Poggit Archive pages supplied for this project are treated as feature references only. No source code has been copied into this implementation; license and API behavior will be reviewed separately for each future adapter.

## Local verification

The commands below use the current local Axolotl-PM checkout and the relocated PHP 8.4 binary:

```powershell
$php = 'C:\PHP-Binaries\PHP-8.4-Windows-x64-PM5-webrtc-0.3.1\bin\php\php.exe'
$devtools = 'C:\Users\NhanAZ\Documents\GitHub\DevTools'
$server = 'C:\Users\NhanAZ\Documents\GitHub\PocketMine-MP'

& $php "$devtools\bin\devtools.php" doctor --project="$PWD" --json
& $php -d phar.readonly=0 "$devtools\bin\devtools.php" build --project="$PWD" --out="$PWD\build" --overwrite --json
& $php "$devtools\bin\devtools.php" inspect --project="$PWD" --artifact="$PWD\build\NPC.phar" --json
```

PHPStan Level Max is generated against the selected server source by the DevTools build workflow. The isolated runtime smoke test must load the PHAR on the matching Axolotl-PM server before a release is published.

## License

The original code in this repository is released under the MIT License. PocketMine-MP/Axolotl-PM remains separately licensed by its respective project.

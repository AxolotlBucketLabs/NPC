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
- Slapper Human `menuname` compatibility using the current Axolotl-PM player-list packet API;
- edit aliases for names, commands, hand/armor equipment, scale, reload, and teleporting an NPC or player;
- `/npc ui`, a PMForms-backed operator GUI for creating, editing, importing and deleting NPCs;
- Human and lightweight Bee-display NPCs, with timer/spawn/right-click/left-click event triggers;
- player emotes such as `wave`, `simple_clap`, `over_there`, `over_here`, `bow`, `saluting`, and `thinking`, plus arbitrary valid Bedrock emote UUIDs;
- actor animations (`fly` for the Bee wing/flight state, plus `arm_swing`, `hurt`, `critical_hit`, and `magic_critical`) kept separate from player emotes;
- optional ScoreHud integration with live `npc.*` tags;
- DevTools PHAR packaging with the PMForms virion prepared from its pinned Composer source, plus PHPStan Level Max in CI.

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
/npc menuname <id> <name|remove>
/npc type <id> <human|bee>
/npc animation <id> <none|fly|arm_swing|hurt|critical_hit|magic_critical> [interval] [trigger]
/npc emote <id> <none|wave|simple_clap|over_there|over_here|bow|saluting|thinking|UUID> [interval] [trigger]
/npc ui
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

## Operator UI

`/npc ui` opens the operator menu built with the `pmforms` virion. The menu can create an NPC, edit its name/type/skin/effects/animation, add or remove actions, edit equipment, migrate loaded Slapper entities, and delete definitions. DevTools prepares PMForms from the pinned `NhanAZ-Libraries/pmforms` Composer source and packages it into `NPC.phar`; a separate forms plugin is not required at runtime.

The GUI is permission-gated by `npc.admin`. It is a control surface for trusted operators, not a player-facing NPC interaction menu.

## Entity types, emotes and animations

Human is the default and supports local skins, cape/geometry data, equipment, player-list menu names, and player emotes. `bee` is a lightweight visual mob actor using the Bedrock `minecraft:bee` network identifier. It is not a full Bee AI implementation: it does not pathfind, pollinate, or persist as a world entity. Skins and equipment are disabled for Bee definitions. With `animation-type: fly`, the plugin moves the actor through a small passive hover/orbit and advertises the Bedrock flying metadata flag; this is visual movement, not native Bee AI.

Player emotes, actor animations, and particles are separate controls:

- `/npc emote` sends a Bedrock player-emote packet. Friendly aliases resolve to the corresponding Bedrock UUID, and any valid Bedrock emote UUID is accepted so the catalog is not artificially limited to the aliases shipped with this plugin.
- `/npc animation` controls actor animation/state: `fly` enables the Bee wing/flight state and passive hover/orbit, while arm swing, hurt, critical hit, and magic critical send actor event animations. None of these are player emotes.
- `/npc particle` and `/npc particle-type` control particles independently of both emote and actor animation controls.

The legacy `animation-type` key is retained for configuration compatibility. New definitions can still use the same storage shape:

```yaml
entity-type: human
animation-type: emote
animation-id: wave
animation-interval: 100
animation-trigger: right_click
```

The old `animation-type: emote` and `animation-id: wave` form remains valid; `wave` is resolved to its Bedrock emote UUID before the packet is sent. For a Bee visual NPC, use `entity-type: bee` and `animation-type: fly`. The `animation-id` is ignored for `fly`; the interval is retained for compatibility but continuous flight is updated every server tick.

Use `timer` for a repeating emote or actor animation, `spawn` for one event when the NPC is spawned, or `right_click`/`left_click` for interaction-triggered events. Client support for a particular emote is controlled by the Bedrock client; the packet is sent only for the selected Human NPC. Bee motion is updated continuously and does not use an emote packet.

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
/slapper edit <id> menuname <name|remove>
```

The compatibility layer deliberately reports unsupported historical entity types instead of pretending they are equivalent to a Human. `/npc id` (or `/slapper id`) waits for a hit and reports both the stable NPC ID and the current network entity ID. `/npc remove` without an ID waits for a hit and removes only the selected NPC definition; `/npc cancel` clears the pending session. Right-click and left-click both run the configured actions, subject to the same cooldown. `/npc menuname <id> <name|remove>` keeps a Human-style NPC in the Bedrock player list with the requested name and removes the row cleanly when disabled.

When the old Slapper plugin is still installed and its entities are loaded, an operator can import the Human entities with:

```text
/npc migrate-slapper
```

The command copies location, nametag, commands, scale, hand/armor aliases, menu name, skin/cape/geometry, and creates persistent NPC definitions. It keeps the old entities by default so the result can be checked first. After verification, `/npc migrate-slapper remove` closes only the successfully imported old entities. If Slapper is absent or its entities were not loaded, the command safely reports zero imports; it does not attempt to parse arbitrary world files.

## Compatibility boundary

The core deliberately has no hard dependency on PureChat, PurePerms, ScoreHud, EcoAPI, SimpleEconomy, TownCoins, or TownEconomy. This lets it load when those plugins are absent and avoids coupling NPC persistence to another plugin's storage format.

The current milestone exposes generic placeholders plus soft adapters for the method shapes used by the current PureChat, PurePerms, SimpleEconomy, EcoAPI, TownCoins, and TownEconomy plugins. The Slapper migration command intentionally requires the old plugin to have loaded the source entities; it is not a blind world-NBT converter.

## ScoreHud integration

ScoreHud is optional and is detected at runtime. When present, NPC refreshes these tags every ten ticks:

```text
{npc.count}
{npc.ids}
{npc.nearest_id}
{npc.nearest_name}
{npc.nearest_distance}
{npc.nearest_entity_id}
{npc.nearest_type}
{npc.nearest_animation}
{npc.nearest_emote}
{npc.nearest_motion}
```

The tags are resolved in the viewer's current world. `npc.nearest_animation` contains the actor animation/state (`fly`, `arm_swing`, `hurt`, `critical_hit`, `magic_critical`) or `none`; `npc.nearest_emote` contains the configured friendly emote name/UUID or an empty string; and `npc.nearest_motion` remains a convenience value containing `fly` or `none`. `npc.nearest_*` values refer to the closest currently spawned NPC, while `npc.count` and `npc.ids` include only NPCs visible in that world. The integration uses ScoreHud's event API and does not require ScoreHud to be installed for NPC to load.

The old Slapper repositories and Poggit Archive pages supplied for this project are treated as feature references only. No source code has been copied into this implementation; license and API behavior will be reviewed separately for each future adapter.

## Dependency and local verification

PMForms is intentionally not copied into this repository. `composer.lock` pins the PMForms release and its Axolotl-PM platform package, while DevTools' `prepare-dependencies` step creates the ignored `.devtools-virions/` directory from that lockfile. The GitHub workflows use the same preparation path, so the source remains maintained in [NhanAZ-Libraries/pmforms](https://github.com/NhanAZ-Libraries/pmforms) and only the shaded library is present in the final PHAR.

The commands below use the current local Axolotl-PM checkout and the relocated PHP 8.4 binary:

```powershell
$php = 'C:\PHP-Binaries\PHP-8.4-Windows-x64-PM5-webrtc-0.3.1\bin\php\php.exe'
$devtools = 'C:\Users\NhanAZ\Documents\GitHub\DevTools'
$server = 'C:\Users\NhanAZ\Documents\GitHub\PocketMine-MP'

& composer install --no-dev --no-scripts --no-plugins --no-autoloader --no-interaction --no-progress
if (Test-Path "$PWD\.devtools-virions") { Remove-Item -LiteralPath "$PWD\.devtools-virions" -Recurse -Force }
& $php "$devtools\bin\devtools.php" prepare --project="$PWD" --virions="$PWD\.devtools-virions" --json
& $php "$devtools\bin\devtools.php" doctor --project="$PWD" --virions="$PWD\.devtools-virions" --json
& $php -d phar.readonly=0 "$devtools\bin\devtools.php" build --project="$PWD" --virions="$PWD\.devtools-virions" --out="$PWD\build" --overwrite --json
& $php "$devtools\bin\devtools.php" inspect --project="$PWD" --artifact="$PWD\build\NPC.phar" --json
```

PHPStan Level Max is generated against the selected server source by the DevTools build workflow. The isolated runtime smoke test must load the PHAR on the matching Axolotl-PM server before a release is published.

## License

The original code in this repository is released under the MIT License. PocketMine-MP/Axolotl-PM remains separately licensed by its respective project.

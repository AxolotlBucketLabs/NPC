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
- a paginated/searchable entity picker with Vanilla `IMAGE_TYPE_PATH` icons: mob spawn eggs, the NPC egg for Human, and a stone block icon for FallingBlock;
- Human and lightweight Bee-display NPCs, with timer/spawn/right-click/left-click event triggers;
- every current Vanilla Bedrock mob network type from Axolotl-PM's entity catalog, plus passive `falling_block`/FallingSand actors;
- a safe custom visual-entity registry through `Main::registerEntityType()` without arbitrary PHP class loading;
- shared interaction actions by default, with opt-in `separate` hit/interact actions and `message:` direct messages;
- optional walking paths (off by default), configurable nametag visibility, rotation distance, and asynchronous allowlisted HTTPS skin URLs;
- random player-emote pools (off by default) and a right-click sneak editor that opens the same PMForms editor as `/npc ui`;
- server-local and per-world player counts, plus optional remote Bedrock counts through the renamed `BedrockQuery` plugin;
- the bundled Bedrock Marketplace emote catalog (2,000+ title/UUID entries), including `wave`, `simple_clap`, `over_there`, `bow`, and many more, plus arbitrary valid Bedrock emote IDs;
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
/npc addcommand <id> <command|console:command|message:text>
/npc hitcommand <id> <action>
/npc interactcommand <id> <action>
/npc interaction-mode <id> <shared|separate>
/npc clearcommands <id>
/npc equipment <id> <hand|helmet|chestplate|leggings|boots> <item|clear>
/npc skin <id> <relative-raw-skin-file|player|clear>
/npc skin-url <id> <https-url|clear>
/npc cooldown <id> <seconds>
/npc scale <id> <size>
/npc particle-type <id> <type>
/npc sound-type <id> <type>
/npc menuname <id> <name|remove>
/npc type <id> <human|any-vanilla-mob|falling_block>
/npc animation <id> <none|fly|arm_swing|hurt|critical_hit|magic_critical> [interval] [trigger]
/npc emote <id> <none|wave|simple_clap|over_there|over_here|bow|saluting|thinking|UUID> [interval] [trigger]
/npc emotes [search]
/npc random-emote <id> <on|off> [seconds] [emote...]
/npc namevisible <id> <on|off>
/npc rotation-distance <id> <blocks>
/npc playercount <id> <none|server|world|query> [world|host] [port]
/npc block <id> <block-id|clear>
/npc walk <id> <on|off|add|clear> [speed]
/npc ui
/npc rotate <id> <on|off>
/npc particle <id> <on|off>
/npc sound <id> <on|off>
/npc tp <id>
/npc list
/npc entities
/npc updateall
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
      - "message:&aWelcome, {player}!"
    interaction-mode: shared
    hit-commands: []
    interact-commands: []
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
    nametag-visible: true
    rotation-distance: 16
    player-count-mode: server
    player-count-world: ""
    query-host: ""
    query-port: 19132
    query-interval: 200
    walk-enabled: false
    walk-speed: 0.06
    walk-loop: true
    walk-path: []
    random-emote-enabled: false
    random-emote-cooldown: 60
    emote-pool: [wave, simple_clap]
```

Raw skins remain local and are still the most deterministic option. Place a 64x32, 64x64, or 128x128 RGBA file under `plugin_data/NPC/skins/`, then run `/npc skin <id> <relative-file>`. From in-game, `/npc skin <id> player` copies the executing player's validated skin, cape, and geometry into that local directory. Direct PNG/RGBA skin URLs are supported through `/npc skin-url`, but only HTTPS hosts listed exactly under `skin-url.allowed-hosts` in `config.yml` are fetched. Fetching is asynchronous, redirects are disabled, private/reserved DNS addresses are rejected, and the response is size/time limited. Cape files must be exactly 8192 bytes and can be set in YAML with the `cape` field.

## Operator UI

`/npc ui` opens the operator menu built with the `pmforms` virion. The menu can create an NPC, choose its entity type from a searchable paginated Vanilla-icon picker, edit its name/type/skin/effects/animation, browse the emote catalog, select an emote from an image-backed paginated menu, configure player counts/walking/random emotes, add or remove actions, edit equipment, migrate loaded Slapper entities, and delete definitions. Sneak-right-clicking an NPC as an operator opens its editor directly. Entity buttons use Bedrock `FormIcon::IMAGE_TYPE_PATH`, so the client resolves Vanilla spawn-egg/block textures from its loaded resource pack without a CDN. Emote result buttons show the official 128x128 thumbnail from the Bedrock-Emotes image repository; only the URL is sent in the form, so the PNGs are not copied into `NPC.phar`. DevTools prepares PMForms from the pinned `NhanAZ-Libraries/pmforms` Composer source and packages it into `NPC.phar`; a separate forms plugin is not required at runtime.

The GUI is permission-gated by `npc.admin`. It is a control surface for trusted operators, not a player-facing NPC interaction menu.

## Entity types, emotes and animations

Human is the default and supports local/remote skins, cape/geometry data, equipment, player-list menu names, and player emotes. Every Vanilla mob in the current Bedrock entity ID table is available as a passive visual actor (`zombie`, `allay`, `camel`, `warden`, `happy_ghast`, and so on); `/npc entities` prints the exact catalog. These are visual actors, not native mob AI: they do not pathfind, attack, breed, drop loot, or persist as world entities. `falling_block` is a passive block actor and reads its visual from `block-id: stone` (or any valid PocketMine item/block alias). Skins/equipment/player-list menu names apply only to Human actors. `animation-type: fly` remains a visual hover/orbit state for the flying display types, not native AI.

The UI entity picker uses client-side Vanilla resource-pack paths rather than remote image URLs. Most current mobs use `textures/items/spawn_eggs/spawn_egg_<entity>`, while compatibility aliases use their Vanilla paths (`egg_npc`, `spawn_egg_evoker`, `spawn_egg_zombified_piglin`, and so on). Human uses the Vanilla NPC egg icon and `falling_block` uses `textures/blocks/stone`. The selected path is only an icon; it does not change the passive actor implementation or add mob AI. A custom registered actor receives the generic Vanilla egg fallback unless its type is added to the catalog mapping.

Player emotes, actor animations, and particles are separate controls:

- `/npc emote` sends a Bedrock player-emote packet. Names are normalized and resolved through the bundled 2,000+ entry title/UUID catalog; use `/npc emotes [search]` in-game to browse it. Any valid Bedrock emote ID is also accepted, so a new Marketplace entry can be used before the bundled snapshot is refreshed.
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

The bundled name catalog is generated from [TwistedAsylumMC/Bedrock-Emotes](https://github.com/TwistedAsylumMC/Bedrock-Emotes)' `emotes.json` snapshot. Only title and UUID pairs are included in the catalog; the form resolves thumbnails from the matching `images/<uuid>.png` URL in that repository. Marketplace offers and other metadata are not copied. Names work with spaces (`Air Guitar`) or underscores (`air_guitar`). Duplicate titles remain addressable by entering the UUID directly.

Use `timer` for a repeating emote or actor animation, `spawn` for one event when the NPC is spawned, or `right_click`/`left_click` for interaction-triggered events. Client support for a particular emote is controlled by the Bedrock client; the packet is sent only for the selected Human NPC. Bee motion is updated continuously and does not use an emote packet.

Player-specific integration placeholders are expanded for click actions. Nametags are shared entity metadata, so `{rank}` and `{balance}` are intended for action commands. Nametag player-count placeholders are definition-wide and safe for persistent display: `{online}`/`{server_players}` are server totals, `{world_players}` is the configured NPC world's local count, and `{player_count}` selects `server`, `world`, or remote `query`. Remote query values are also exposed as `{query_players}`, `{query_max}`, and `{query_status}`.

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

The compatibility layer now accepts all catalogued visual types instead of silently coercing them to Human. `/npc id` (or `/slapper id`) waits for a hit and reports both the stable NPC ID and the current network entity ID. `/npc remove` without an ID waits for a hit and removes only the selected NPC definition; `/npc cancel` clears the pending session. Right-click and left-click both run the shared action list by default, preserving one behavior for users who do not want to distinguish them. Set `interaction-mode: separate` to add `interact-commands` and `hit-commands`; the shared list still runs first. `/npc menuname <id> <name|remove>` keeps a Human-style NPC in the Bedrock player list with the requested name and removes the row cleanly when disabled.

When the old Slapper plugin is still installed and its entities are loaded, an operator can import the Human entities with:

```text
/npc migrate-slapper
```

The command copies location, nametag, commands, scale, hand/armor aliases, menu name, skin/cape/geometry, and creates persistent NPC definitions. It keeps the old entities by default so the result can be checked first. After verification, `/npc migrate-slapper remove` closes only the successfully imported old entities. If Slapper is absent or its entities were not loaded, the command safely reports zero imports; it does not attempt to parse arbitrary world files.

## Compatibility boundary

The core deliberately has no hard dependency on PureChat, PurePerms, ScoreHud, EcoAPI, SimpleEconomy, TownCoins, or TownEconomy. This lets it load when those plugins are absent and avoids coupling NPC persistence to another plugin's storage format.

The current milestone exposes generic placeholders plus soft adapters for the method shapes used by the current PureChat, PurePerms, SimpleEconomy, EcoAPI, TownCoins, and TownEconomy plugins. `BedrockQuery` is optional; install the renamed library from [NhanAZ-Libraries/BedrockQuery](https://github.com/NhanAZ-Libraries/BedrockQuery). When it is installed, `player-count-mode: query` calls its asynchronous API and does not make NPC load failure depend on that plugin. The Slapper migration command intentionally requires the old plugin to have loaded the source entities; it is not a blind world-NBT converter.

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
{npc.server_players}
{npc.world_players}
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

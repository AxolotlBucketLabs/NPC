# NPC

[![CI](https://github.com/NhanAZ-Plugins/NPC/actions/workflows/ci.yml/badge.svg)](https://github.com/NhanAZ-Plugins/NPC/actions/workflows/ci.yml)

NPC is an operator-focused visual NPC plugin for Axolotl-PM and PocketMine-MP API 5. It stores definitions in `plugin_data/NPC/npcs.yml` and renders stationary Human, Vanilla actor, block, boat, minecart, and TNT NPCs to Bedrock clients.

NPC actors are visual actors. They do not have mob AI, pathfinding, combat, drops, breeding, or world-entity persistence. The continuous behaviors are optional look-at rotation and the visual `fly` state for supported flying actors. The plugin keeps the Slapper-style command workflow while providing a PMForms editor for operators.

## Requirements

| Requirement | Version or scope |
| --- | --- |
| Axolotl-PM or PocketMine-MP | API `5.0.0` or newer within the supported API 5 boundary |
| PHP | `8.4` is the repository build baseline |
| PMForms | Bundled into the built PHAR by DevTools. A separate forms plugin is not required |
| BedrockQuery | Optional. Needed only for remote player-count mode |
| PureChat, PurePerms, ScoreHud | Optional runtime integrations |
| Economy plugin | Optional. The built-in adapters check SimpleEconomy, EcoAPI, TownCoins, and TownEconomy |

Development is pinned to `axolotl-pm/pocketmine-mp ~5.51.1` in `composer.json`. The plugin manifest declares API `5.0.0`. The repository does not claim that every Axolotl-PM development revision is compatible without testing that revision.

## Installation

1. Download an `NPC.phar` from a repository release or CI artifact.
2. Copy it to the server's `plugins/` directory.
3. Start or restart the server.
4. Run `/npc help` as an operator.

The built PHAR contains the PMForms virion. Do not copy the PMForms source into the server's plugin directory.

The operator UI requires `npc.admin`. The command itself uses `npc.command`, and the `/rca` compatibility command uses `npc.rca`.

## Quick start

The shortest in-game workflow is:

```text
/npc
Create NPC
Enter an ID and a nametag
Submit
```

The UI creates a Human NPC at the player's location. It snapshots the creator's current skin, geometry, scale, hand, off-hand, and armor loadout. The new NPC faces the creator's current direction and enables look-at rotation by default.

The same workflow is available from commands:

```text
/npc create NPC_0 Welcome
/npc type NPC_0 zombie
/npc namevisibility NPC_0 hover
```

IDs are stable strings. The UI suggests `NPC_0`, `NPC_1`, and so on, but an operator may enter any valid unused ID. IDs are not padded with leading zeroes.

## Operator UI

`/npc` and `/npc ui` open the PMForms editor. The main menu contains only creation and listing. Entity and emote browsers are opened from the relevant editor section instead of being placed in the main menu.

The editor is capability-aware:

- Human NPCs expose skin, equipment, player emotes, and random-emote controls.
- Mob NPCs expose supported actor variants and actor animations.
- `falling_block` exposes a block visual.
- Boat, minecart, and TNT actors do not show Human-only appearance controls.
- Variant controls are shown only for entity types that have registered variants.
- The NPC list is grouped by world.
- Closing a form does not reopen an unrelated menu. Direct sneak-right-click editing returns to the direct editor origin.

An operator with `npc.admin` can sneak and right-click an NPC to open its editor directly.

## Command reference

The tables below show `/npc` commands. The `/slapper` alias uses the same parser, and `/rca` remains a separate compatibility command.

### Management and location

| Command | Purpose |
| --- | --- |
| `/npc` | Open the operator UI when run by a player |
| `/npc ui` | Open the operator UI explicitly |
| `/npc help` | Show the command list |
| `/npc create <id> [name]` | Create a Human NPC at the player's location |
| `/npc spawn <entity-type> [name]` | Create a new NPC with a selected visual type |
| `/npc list` | List saved NPC IDs and their worlds |
| `/npc entities` | List entity types accepted by the current catalog |
| `/npc id` | Start a hit session that reports the stable ID and runtime entity ID |
| `/npc remove <id>` | Remove a saved NPC and its spawned actor |
| `/npc remove` | Start a hit session that removes the selected NPC |
| `/npc cancel` | Cancel a pending ID or remove hit session |
| `/npc tp <id>` | Teleport the executing player to an NPC |
| `/npc updateall` | Save the current definitions and respawn all NPCs |
| `/npc reload` | Reload definitions from `npcs.yml` |
| `/npc debug` | Print runtime and definition information |
| `/npc version` | Print the plugin version |
| `/npc rotate <id> <on or off>` | Enable or disable look-at rotation |
| `/npc rotation-distance <id> <blocks>` | Set the nearest-player look-at range |

### Identity, actions, and interaction

| Command | Purpose |
| --- | --- |
| `/npc setname <id> <name>` | Replace the first nametag line |
| `/npc setline <id> <line> <text>` | Set a nametag line from 1 to 16 |
| `/npc addcommand <id> <action>` | Add a shared action |
| `/npc clearcommands <id>` | Remove all shared actions |
| `/npc hitcommand <id> <action>` | Add an action for left-click hit events |
| `/npc interactcommand <id> <action>` | Add an action for right-click interact events |
| `/npc interaction-mode <id> shared` | Use the shared action list for both event types |
| `/npc interaction-mode <id> separate` | Run shared actions and the trigger-specific list |
| `/npc cooldown <id> <seconds>` | Set the per-player interaction cooldown |
| `/rca <player> <command>` | Run a command as an online player. This is a compatibility command |

Actions support three forms:

```text
message:&aWelcome, {player}!
console:say {player} clicked the NPC
spawnpoint
```

An action without a prefix runs as the interacting player. `console:` actions are trusted operator configuration and should not be exposed to untrusted users.

### Appearance

| Command | Purpose |
| --- | --- |
| `/npc type <id> <entity-type>` | Change the visual actor type |
| `/npc block <id> <block-name or clear>` | Set or clear the block used by `falling_block` |
| `/npc equipment <id> <slot> <item or clear>` | Set one Human equipment slot |
| `/npc skin <id> <relative-file or player or clear>` | Use a local raw skin, copy the executing player's skin, or clear it |
| `/npc skin-url <id> <https-url or clear>` | Fetch a remote Human skin through the configured allowlist |
| `/npc scale <id> <size>` | Set a Human or actor scale from `0.1` to `4.0` |

Equipment slots are `hand`, `offhand`, `helmet`, `chestplate`, `leggings`, and `boots`. PocketMine item aliases are accepted.

The UI provides an option to copy all or selected equipment slots from the executing player. This is available only for Human NPCs.

### Animation and effects

| Command | Purpose |
| --- | --- |
| `/npc animation <id> <type> [interval] [trigger]` | Configure `none`, `fly`, `arm_swing`, `hurt`, `critical_hit`, or `magic_critical` |
| `/npc emote <id> <name or UUID> [interval] [trigger]` | Configure a Bedrock player emote for a Human NPC |
| `/npc emotes [search]` | Search the bundled emote catalog |
| `/npc random-emote <id> <on or off> [seconds] [emote...]` | Enable a random player-emote pool |
| `/npc particle <id> <on\|off>` | Enable or disable particles |
| `/npc particle-type <id> <type>` | Select a particle type |
| `/npc sound <id> <on\|off>` | Enable or disable interaction sound |
| `/npc sound-type <id> <type>` | Select a vanilla sound event or legacy alias |

Animation triggers are `timer`, `spawn`, `right_click`, and `left_click`.

Player emotes are separate from actor animations. The bundled catalog is loaded from `resources/emotes.jsonl`. It contains the current checked-in title and UUID snapshot. The built-in aliases include `wave`, `simple_clap`, `over_there`, `over_here`, `bow`, `saluting`, and `thinking`. A valid Bedrock emote UUID can also be entered directly.

The current catalog includes visual variants for entities such as Armadillo, Axolotl, Cat, Fox, Frog, Llama, Mooshroom, Panda, Parrot, Rabbit, Sheep, Shulker, Wolf, and Tropical Fish. Run `/npc entities` for entity types and use the UI editor for the variant options available to the selected type.

`fly` is a visual animation state for supported flying actors such as Bee. It is not mob AI. The NPC does not pathfind or move through the world. `animation-sound: true` can send a nearby vanilla flap sound for flying actors.

## Nametags and look-at rotation

Nametag visibility uses the native Slapper-style metadata path:

| Mode | Client behavior |
| --- | --- |
| `always` | The nametag is visible and always shown |
| `hover` | The nametag is visible but not forced always-on. Bedrock decides when the player is looking at the actor |
| `never` | The nametag is hidden |

`hover` is client-defined. It is not a server-side raycast against the NPC's body or text. The legacy `nametag-visible: true|false` field and `/npc namevisible <id> <on|off>` command remain supported and map to `always` or `never`.

Look-at rotation is independent of nametag visibility. When enabled, the NPC updates its body and head orientation toward the nearest player within `rotation-distance`. The calculation uses the player's eye height. Shulkers use a cardinal body direction because their Bedrock actor is block-attached.

## Player-count displays

Set the source used by `{player_count}` with `/npc playercount`:

| Mode | Value |
| --- | --- |
| `none` | Empty value |
| `server` | Players online on this server |
| `world` | Players in the configured NPC world |
| `query` | Players returned by the optional BedrockQuery integration |

Example:

```text
/npc playercount NPC_0 world world
/npc playercount NPC_0 query lobby.example.com 19132
```

Remote query mode is asynchronous. If BedrockQuery is not installed or the target is unavailable, the NPC remains loaded and exposes an offline status instead of crashing the plugin.

## Nametag placeholders

The following placeholders are available in `lines` and are evaluated for the NPC definition:

```text
{npc}              Stable NPC ID
{world}            NPC world name
{online}           Server player count
{players}          Selected player-count value
{server_players}   Server player count
{world_players}    Player count in the NPC world
{player_count}     Selected player-count value
{query_players}    Remote query online count
{query_max}        Remote query maximum count
{query_status}     Remote query status
```

The following placeholders are available in click actions and are resolved for the interacting player:

```text
{player} {name} {display_name}
{npc} {world} {online} {players}
{prefix} {suffix} {rank}
{balance} {money} {coins}
```

`{prefix}` and `{suffix}` use PureChat when available. `{rank}` uses PurePerms when available. Economy placeholders try SimpleEconomy, EcoAPI, TownCoins, and TownEconomy in that order.

Nametag line breaks accept `{line}`, `{newLine}`, `{newline}`, `{newLines}`, `{newlines}`, `{breakLine}`, `{breakline}`, `\\n`, `\\r\\n`, `\\r`, and literal newline characters. Minecraft color codes use `&a` style or native `§` formatting.

## ScoreHud integration

ScoreHud is optional. NPC detects it at runtime and does not fail to load when ScoreHud is absent. The integration resolves and refreshes these tags:

```text
{npc.count}
{npc.ids}
{npc.server_players}
{npc.world_players}
{npc.nearest_id}
{npc.nearest_name}
{npc.nearest_distance}
{npc.nearest_entity_id}
{npc.nearest_type}
{npc.nearest_animation}
{npc.nearest_emote}
{npc.nearest_motion}
```

The count, ID list, and nearest-NPC values are scoped to the viewer's current world. `npc.nearest_animation` reports `fly`, `arm_swing`, `hurt`, `critical_hit`, `magic_critical`, or `none`. `npc.nearest_motion` reports `fly` or `none`.

## Configuration

The plugin creates `plugin_data/NPC/config.yml` on first start. The active skin URL settings are:

```yaml
skin-url:
  enabled: true
  allowed-hosts:
    - textures.minecraft.net
    - raw.githubusercontent.com
  max-bytes: 1048576
  timeout-seconds: 8
```

Remote skins must use HTTPS on port 443 and an exact host from `allowed-hosts`. Redirects are disabled. The asynchronous downloader validates DNS addresses, rejects private and reserved ranges, enforces the size and timeout limits, and accepts only 64x32, 64x64, or 128x128 skin images. Remote URLs are available only for Human NPCs.

## Persistent definitions

NPC definitions are stored in `plugin_data/NPC/npcs.yml`. The current file schema is version `4`. Prefer the UI or commands for edits. If the file must be edited manually, stop the server first and keep IDs unique.

```yaml
version: 4
npcs:
  NPC_0:
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
      - "message:&aWelcome, {player}!"
    interaction-mode: shared
    cooldown: 2
    rotate: true
    rotation-distance: 64
    entity-type: human
    nametag-visibility: always
    particle: false
    particle-type: flame
    particle-count: 8
    particle-spread: 0.35
    sound: false
    sound-type: click
    animation-type: none
    animation-id: ""
    animation-trigger: timer
    animation-interval: 100
    animation-sound: true
    player-count-mode: server
    player-count-world: ""
    query-host: ""
    query-port: 19132
    query-interval: 200
    scale: 1
    equipment: {}
```

Older definitions using `nametag-visible` continue to load. When `nametag-visibility` is absent, `true` maps to `always` and `false` maps to `never`. Walking and path fields are not part of the current NPC model.

Human NPC creation stores a local RGBA skin, cape, geometry, and when available a Bedrock `SkinData` snapshot under `plugin_data/NPC/skins/`. The snapshot allows a Persona-style skin to remain usable after the optional PersonaSkin plugin is removed. Existing NPCs cannot reconstruct Persona metadata that was never saved. Capture the player's skin again with `/npc skin <id> player` when needed.

## Compatibility behavior

The plugin keeps a small Slapper compatibility surface without installing or loading the old Slapper plugin:

```text
/slapper spawn Human [name]
/slapper edit <id> name <name>
/slapper edit <id> addcommand <action>
/slapper edit <id> delcommand <action>
/slapper edit <id> listcommands
/slapper edit <id> hand <item|clear>
/slapper edit <id> helmet|chestplate|leggings|boots <item|clear>
/slapper edit <id> scale <size>
/slapper edit <id> tphere
/slapper edit <id> tpto
```

Numeric runtime entity IDs returned by `/npc id` are accepted by ID-first edit and remove commands while the actor is spawned. The persistent NPC ID remains the source of truth.

## Optional integrations

| Plugin or library | Use |
| --- | --- |
| PureChat | `{prefix}` and `{suffix}` action placeholders |
| PurePerms | `{rank}` action placeholder |
| SimpleEconomy, EcoAPI, TownCoins, TownEconomy | `{balance}`, `{money}`, and `{coins}` action placeholders |
| BedrockQuery | Asynchronous remote player-count mode |
| ScoreHud | `npc.*` tags |
| PersonaSkin | Optional while capturing a Persona skin. NPC persists the captured data independently |
| PMForms | Build-time virion. It is shaded into the PHAR |

All integrations are capability-checked. NPC can start without any of these optional plugins.

## Build and verification

The repository uses Composer for the locked Axolotl-PM and PMForms sources and DevTools for virion preparation, PHPStan, and PHAR packaging. A local build requires PHP 8.4 and the Axolotl-PM extensions required by the selected server checkout.

```powershell
$php = 'C:\path\to\php.exe'
$devtools = 'C:\path\to\DevTools'

& composer install --no-dev --no-scripts --no-plugins --no-autoloader --no-interaction --no-progress
& $php "$devtools\bin\devtools.php" prepare --project="$PWD" --virions="$PWD\.devtools-virions" --json
& $php "$devtools\bin\devtools.php" doctor --project="$PWD" --virions="$PWD\.devtools-virions" --json
& $php -d phar.readonly=0 "$devtools\bin\devtools.php" build --project="$PWD" --virions="$PWD\.devtools-virions" --out="$PWD\build" --overwrite --json
& $php "$devtools\bin\devtools.php" inspect --project="$PWD" --artifact="$PWD\build\NPC.phar" --json
```

The CI workflow runs PHP lint, the model smoke test, DevTools preparation, PHPStan at max level, PHAR packaging, and a manifest check. Runtime smoke on a server checkout is a separate verification layer from source tests and artifact inspection.

## Internal architecture

`NpcManager` is the stable runtime facade. Focused services own the parts that do not need to be mixed with lifecycle orchestration:

- `storage/NpcRepository` handles `npcs.yml` loading, migration, validation, and saving.
- `asset/NpcAssetService` handles skin data, Persona snapshots, equipment, and block parsing.
- `display/NpcDisplayService` handles nametag rendering and visibility metadata.
- `display/NpcLookAtService` handles nearest-player body and head rotation.
- `effect/NpcEffectsService` handles particles, sound, actor animation, and player emotes.
- `integration/NpcCommandService` handles action commands and optional placeholder adapters.

The public commands, UI flow, persistence schema, and entity packet behavior remain exposed through `NpcManager` and `Main`.

## License

NPC is released under the MIT License. Axolotl-PM, PocketMine-MP, PMForms, BedrockQuery, ScoreHud, and other optional integrations remain licensed by their respective projects.

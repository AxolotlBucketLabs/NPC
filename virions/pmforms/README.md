# pmforms

Form API library for PocketMine-MP plugins

[![CI](https://github.com/NhanAZ-Libraries/pmforms/actions/workflows/ci.yml/badge.svg)](https://github.com/NhanAZ-Libraries/pmforms/actions/workflows/ci.yml)
[![License](https://img.shields.io/github/license/NhanAZ-Libraries/pmforms)](https://github.com/NhanAZ-Libraries/pmforms/blob/master/LICENSE)
[![Discord](https://img.shields.io/discord/986553214889517088?label=Discord&logo=discord&logoColor=white&color=5865F2)](https://discord.gg/j2X83ujT6c)


## Compatibility

- PHP 8.1 or newer
- PocketMine-MP API 5.x
- Axolotl-PM 5.49.1 for development analysis and DevTools integration

## Include in a plugin

With Poggit, add the virion to `.poggit.yml`:

```yaml
projects:
  YourPlugin:
    libs:
      - src: NhanAZ-Libraries/pmforms/pmforms
        version: ^2.3.0
```

## Demo plugin

[`examples/DevToolsConsumer/`](examples/DevToolsConsumer/) is a runnable demo plugin adapted from the upstream [pmforms-demo](https://github.com/dktapps-pm-pl/pmforms-demo). It demonstrates `MenuForm` with a URL image, `ModalForm`, `CustomForm`, form close callbacks, custom form elements, and reading typed values from `CustomFormResponse`.

Run one of these commands in game to open a form:

```text
/form menu
/form modal
/form custom
```

You can also target online players by name, for example `/form custom Steve`. The same example is built and analyzed with DevTools in CI, using the `devtools.yml` beside its `plugin.yml`.

## Develop with DevTools

The root `virion.yml` lets Axolotl-PM [DevTools](https://github.com/NhanAZ/DevTools) load this checkout as a shared development virion. Put the repository at `virions/pmforms/`, then declare it in the consuming plugin's `devtools.yml`:

```yaml
virions:
  - name: pmforms
    version: ^2.3.0
```

DevTools uses the shared source during development and shades the library into a standalone plugin PHAR when building. The production plugin does not need DevTools installed. The demo plugin is also the [DevTools consumer example](examples/DevToolsConsumer/), built and analyzed in CI.

## Development checks

```sh
composer install
composer validate --strict
composer analyse
```

PHPStan runs at `max` against Axolotl-PM 5.49.1.

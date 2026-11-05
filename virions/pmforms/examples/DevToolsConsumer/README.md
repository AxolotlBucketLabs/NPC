# pmforms demo plugin

This runnable PocketMine-MP / Axolotl-PM plugin demonstrates the pmforms API. It is adapted from [dktapps-pm-pl/pmforms-demo](https://github.com/dktapps-pm-pl/pmforms-demo) and also serves as the DevTools consumer fixture used by this repository's CI.

In game, run one of:

- `/form menu` to open a `MenuForm`, including an option with a URL image.
- `/form modal` to open a yes/no `ModalForm`.
- `/form custom` to open a `CustomForm` with a label, dropdown, input, slider, step slider, and toggle.

The form response and close callbacks show how to handle results. To send a form to another online player, append their name, for example `/form menu Steve`.

This demo is based on the upstream [pmforms-demo](https://github.com/dktapps-pm-pl/pmforms-demo), which is released under the Unlicense; see [LICENSE](LICENSE).

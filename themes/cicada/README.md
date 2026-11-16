# cicada

A dark onyx skin for the ISPConfig 3.3 control panel, after the Cicada theme for
OPNsense (`misc/theme-cicada`, rene@team-rebellion.net). It reads the same
brand-token contract as clarity and classic, so the Branding page drives its
accent, navigation band, login background, both logo variants and the tab icon.

Cicada started as a standalone theme,
[brightcolor/ispconfig-cicada](https://github.com/brightcolor/ispconfig-cicada).
This directory carries its v0.4.2 stylesheet and chart defaults. The standalone
repository remains the way to run Cicada on ISPConfig 3.2, which this extension
does not support.

## What ships

| File | Role |
|---|---|
| `assets/stylesheets/cicada.css` | the skin; every colour is a `--cic-*` custom property |
| `assets/javascripts/cicada-charts.js` | dark defaults for the dashboard's Chart.js charts |
| `brand.php` | reads the contract and reassigns the `--cic-*` properties |
| `title.php`, `favicon.php` | the panel name in the tab title, and the tab icon |
| `LICENSE` | BSD-2-Clause, for everything in this directory |

The two shell templates are generated at install time, like classic's, and are
never committed.

## The generated shell

`lib/shell.sh` builds `main.tpl.htm` and `main_login.tpl.htm` from the panel's
own `themes/default/templates/`. Cicada gets classic's four changes (asset paths
pinned to `themes/default/assets/`, the three brand endpoints linked, the tab
icons replaced by `favicon.php`, the footer credit split) and four of its own:

- the stock colour sheet `themes/default/theme.min.css` is dropped, because
  `cicada.css` replaces it wholesale;
- stock's red `#cc151c` (browser theme colour, tile colour, mask icon) becomes
  Cicada's `#dd630d`;
- `cicada.css` is linked after every stock sheet and directly before `brand.php`,
  so the brand overrides still win;
- `cicada-charts.js` loads right after Chart.js wherever the panel ships it.

Both asset links carry `?v=<cksum>`, so a browser fetches a new copy as soon as
the file changes. The generator refuses a stock shell in which it cannot find
the colour sheet exactly once: a renamed sheet would otherwise load under
Cicada and paint the panel light. Re-running `install.sh` after an ISPConfig
upgrade regenerates both templates from the new stock markup.

## How the Branding page drives Cicada

| Setting | Effect |
|---|---|
| Accent | `--cic-accent` takes the colour as entered. The text, line, deep and label values are derived from it: text reaches 4.5:1 and lines 3:1 on `#383838`, the lightest surface Cicada prints them on. Buttons, links, tabs, markers, focus rings, `.bg-primary` and the dashboard charts follow. |
| Navigation band | The module buttons and the mobile drawer, with labels, hover, active state and marker derived from the colour. |
| Navigation band, light mode | No effect: Cicada has one colour mode. |
| Login background | The page behind the login card. |
| Logos | See below. |
| Tab icon, panel name, version and Design-picker switches, footer credits | As on classic. |

With nothing set, `brand.php` emits an empty sheet and Cicada renders exactly as
it ships.

## Logos

Both places a logo appears sit on fixed dark grounds: the header strip on the
page colour `#1C1C1C`, and the login card header on `#202020`. No colour the
Branding page sets reaches either, so Cicada always asks for the
**dark-background** variant.

A light-background logo, the stock ISPConfig logo included, is drawn as a white
silhouette, which reads on the dark ground whatever its colours were. Upload a
dark-background variant on the Branding page and Cicada draws your logo as
supplied on both surfaces. The Branding page preview shows the silhouette
wherever the panel draws one.

## Custom properties

Modules built for Cicada colour themselves through the `--cic-*` properties,
with Cicada's orange as their fallback. The names stay stable; `brand.php` only
reassigns their values. `--cic-accent-line` is the accent drawn as a line or a
glyph and defaults to `var(--cic-accent)`.

## Licence

BSD-2-Clause, copyright bright color; see [LICENSE](LICENSE). The rest of the
repository is MIT.

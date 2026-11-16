# Cicada as a third design

**Date:** 2026-11-16 · **Status:** design approved, spec in review · **Branch:** `feat/cicada-design`

**Bases:** this fork at upstream `v3.6.0` (`0d74958`); Cicada from
[`brightcolor/ispconfig-cicada`](https://github.com/brightcolor/ispconfig-cicada)
`v0.4.2` (`f95f174`).

## 1. Summary

Cicada, a dark onyx skin for ISPConfig after the Cicada theme for OPNsense,
becomes the third design of this extension beside clarity and classic. It reads
the full brand-token contract, so the Branding page drives its accent, its
navigation band, the login background, both logo variants and the tab icon.
Like classic, its two shell templates are generated at install time from the
target panel's own stock templates, and the re-run after a panel upgrade
rebuilds them.

With nothing set on the Branding page, Cicada renders as the standalone v0.4.2
does, with one deliberate exception: the login card header (§6.6).

## 2. Goals and non-goals

**Goals**

1. `themes/cicada/` as a generated design, installed with `--design=cicada`.
2. Every key of the brand-token contract has a defined effect on Cicada, or a
   documented no-op.
3. The Branding page previews Cicada truthfully: the mark, its backdrop and its
   treatment in the preview are the ones the panel draws.
4. One registry per layer answers "which designs exist", so a fourth design is
   one entry per layer.
5. CI covers Cicada with the parity guarantees clarity and classic have, and
   proves byte for byte that classic's generated shell is unchanged.

**Non-goals**

- A light colour mode for Cicada. `rail_hex_light` is read and ignored, as on
  classic.
- ISPConfig 3.2. The fork supports what upstream supports. The standalone
  `ispconfig-cicada` repository stays the route for 3.2 panels.
- Browser chrome following `accent_hex`. `theme-color`,
  `msapplication-TileColor` and the mask-icon colour carry Cicada's orange as a
  static value written by the generator.
- Porting the standalone repository's browser tools (`check-contrast.js`,
  `check-dark.js`, `preview.html`). Their findings already live in the
  stylesheet; the fork's guarantees come from the probes in §8.
- A pull request to upstream in this round.

## 3. Sources and licensing

| Path in the fork | Source |
|---|---|
| `themes/cicada/assets/stylesheets/cicada.css` | standalone v0.4.2, plus the login rules of §6.5 and §6.6 |
| `themes/cicada/assets/javascripts/cicada-charts.js` | standalone v0.4.2, unchanged |
| `themes/cicada/brand.php`, `title.php`, `favicon.php` | new, modelled on classic's |
| `themes/cicada/README.md`, `themes/cicada/LICENSE` | new; LICENSE taken from the standalone repository |

The repository is MIT. The `themes/cicada/` directory is BSD-2-Clause as a
whole, copyright bright color, matching the standalone theme and its OPNsense
model; both licences are permissive and compatible. The theme README credits the
origin the stylesheet header already names: the Cicada theme for OPNsense
(`misc/theme-cicada`, rene@team-rebellion.net). The root README gains a licence
note for the directory.

## 4. Architecture

| Part | Where | Change |
|---|---|---|
| Design | `themes/cicada/` | stylesheet, chart defaults, the three brand endpoints |
| Shell generation | `lib/designs.sh` and `lib/shell.sh` (new), `install.sh` | one generator for every generated design, driven by one design table |
| Module | `interface/web/customizer/lib/preview.inc.php` | one design registry, Cicada's entry, a logo treatment per surface |
| Tests and CI | `tests/brand/`, `tests/shell/` (new), `.github/workflows/ci.yml` | Cicada in the cross-comparisons, generator fixtures |

## 5. Shell generation

### 5.1 Today

`generate_classic_templates()` in `install.sh` derives classic's `main.tpl.htm`
and `main_login.tpl.htm` from `themes/default/templates/` with three changes:
asset paths pinned to `themes/default/assets/`; `favicon.php`, `brand.php` and
`title.php` linked before `</head>`, the login shell's `brand.php` carrying
`?scene=login`; every tab-icon `<link>` replaced by the `favicon.php` link. It
also splits the footer credit into two addressable spans. It refuses to install
a result it cannot account for: the line count must equal stock plus what the
transform reported, no `current_theme` may remain, all three endpoints must be
present and exactly one tab-icon link may exist.

The standalone Cicada's `tools/make-templates.sh` changes the same two files in
five ways: the same asset pin; the stock colour sheet
`stylesheets/themes/default/theme.min.css` removed; stock red `#cc151c` replaced
by `#dd630d`; `cicada.css` linked last; `cicada-charts.js` inserted after
`chart.umd.js` where the panel ships Chart.js. Both URLs carry `?v=<cksum>`, so a
browser drops a stale copy when the content changes.

### 5.2 Target

One generator, `generate_shell_templates <design> <outdir>`, moves with its
checks into `lib/shell.sh`, which `install.sh` sources and CI runs against
fixtures without installing anything. Every generated design gets classic's
three changes and the credit split. What differs per design comes from one table
in `lib/designs.sh`:

| Field | clarity | classic | cicada |
|---|---|---|---|
| shell | shipped | generated | generated |
| credit label and link | (own template) | `Classic`, upstream repository | `Cicada`, this fork |
| drop the stock colour sheet | | no | yes |
| stock colour replaced by | | | `#cc151c` → `#dd630d` |
| design stylesheet | | | `assets/stylesheets/cicada.css` |
| chart defaults script | | | `assets/javascripts/cicada-charts.js` |

`ALL_DESIGNS` and the `--design=` parser read the same table.

Head order in a generated shell: tab-icon link, design stylesheet, `brand.php`,
`title.php`. `brand.php` is the last stylesheet, so its overrides win over the
design sheet.

### 5.3 Checks

Kept from classic and generalised: the line count equals stock plus the lines
each transform added minus the lines it removed, each term reported by the
transform itself; no `current_theme` remains; the three endpoints are present;
exactly one tab-icon link exists.

Added for the fields above: the design stylesheet appears once, directly before
`brand.php`; `brand.php` is the last stylesheet; the stock colour sheet is gone
when the design drops it; no `#cc151c` remains when the design replaces it; the
chart script appears once and after `chart.umd.js` when the stock shell loads
Chart.js, and is absent otherwise.

### 5.4 Regression proof

`tests/shell/` holds the stock `main.tpl.htm` and `main_login.tpl.htm` of
ISPConfig 3.3.1p1 as fixtures, with ISPConfig's BSD licence notice beside them,
and the two classic shells that upstream `v3.6.0`'s
`generate_classic_templates()` produces from them. CI generates classic with the
new generator and compares byte for byte, then generates Cicada and runs the
checks of §5.3. The expected classic output is produced once from upstream's
function and committed; it changes only in a deliberate commit that says why.

## 6. Brand reader: `themes/cicada/brand.php`

### 6.1 Shape

Classic's structure: a pre-authentication endpoint that reads the global
`sys_ini` row, parses `[branding]` and emits a short stylesheet, with
`?scene=login` selecting the login rules. With no contract key set it emits only
its header comment, which keeps an unbranded Cicada identical to the standalone.
The helper set (`brand_parse_config`, `brand_logo_variant`,
`brand_logo_variant_pref`, `brand_hex`, `brand_shade`, `brand_readable`,
`brand_contrast`, `brand_luminance`, `brand_rgba` and the HSL pair) is copied
from classic and joins the existing cross-comparisons (§8).

All steps and contrast targets of §6.2 and §6.3 are named once at the top of
`brand.php`; 4.5:1 is WCAG 2.x AA for text, 3:1 its minimum for user-interface
components.

`brand.php` reassigns the values of Cicada's custom properties and adds rules.
The `--cic-*` names stay as they are: third-party modules colour themselves
through them.

### 6.2 `accent_hex`

| Property | Value |
|---|---|
| `--cic-accent` | the stored colour |
| `--cic-accent-text` | `brand_readable()` from the stored colour until it reaches 4.5:1 on every surface on which `cicada.css` prints `--cic-accent-text`; that surface list is read from the stylesheet once and pinned in a test |
| `--cic-accent-deep` | the stored colour 11 HSL lightness points darker, the step between Cicada's own `#dd630d` and `#a84c0b` |
| `--cic-on-accent` | `#FFFFFF` or `#1C1C1C`, whichever measures the higher contrast on the stored colour |

Cicada's hand-tuned `#ef7d21` also shifts the hue by about two degrees, which a
hue-preserving walk cannot reproduce. For `#dd630d` the requirement is therefore
4.5:1 on the pinned surfaces and an HSL lightness within 4 points of
`#ef7d21`. An unset `accent_hex` keeps the hand-tuned values.

### 6.3 `rail_hex`

Cicada's navigation band is the row of module buttons, `#main-navigation a`, and
the off-canvas menu, `.pushy`. `rail_hex` fills both, with classic's derivation:
the ink is the higher-contrast one of `#FFFFFF` and `#1C1C1C` (Cicada's pair in
place of classic's stock ink); inactive labels use the ink at 82 % opacity;
hover, edge and active are the stored colour moved 7, 10 and 13 HSL lightness
points toward the ink. The active marker is the accent walked with
`brand_readable()` to 3:1 against the band, the WCAG 2.x minimum for
user-interface components; with no accent stored, Cicada's `#dd630d` is walked.
The header strip that holds
`#logo` lies outside the band and keeps the page ground.

`rail_hex_light` is read and has no effect: Cicada has one colour mode.

### 6.4 `login_bg`

As on classic, only the login scene emits it: `body { background: … }` behind
the login card. The card and everything on it keep Cicada's surfaces; the custom
login text sits inside the card body.

### 6.5 Logos

Resolution is the shared one: within a variant a reference beats an upload; a
surface asks for the variant that matches its backdrop; it falls back to the
other variant when that one is empty; an explicit `logo_variant_nav` or
`logo_variant_login` wins.

Both Cicada surfaces have fixed dark backdrops. `#logo` sits on the page ground
(`--cic-page`, `#1C1C1C`); the login mark sits in the card header (`--cic-head`,
`#202020`, after §6.6). Automatic therefore always asks for `on_dark`.

Treatment by variant:

- **Light-background mark** (an operator without a dark variant, the stock
  ISPConfig logo, or an explicit pin to `on_light`): drawn as a white
  silhouette, `filter: brightness(0) invert(1)` at opacity .82, the treatment
  Cicada already gives `#logo`. `cicada.css` now also applies it to the login
  `<img>`. This is the default, so it needs no output from `brand.php`.
- **Dark-background mark**: drawn as supplied. `brand.php` paints it (`#logo` in
  the app scene, `.panel-heading img` via `content: url(…)` in the login scene,
  classic's mechanism) and lifts the filter on that surface.

### 6.6 Login card header

Stock 3.3.1p1 paints the login card header inline: `<div class="panel-heading"
style="background: linear-gradient(to bottom, white, #eef0f2); …">`
(`main_login.tpl.htm` line 39). Cicada's `.panel-heading` rule sets
`background-color` with `!important`; the inline gradient is a
`background-image` and stays on top, so the standalone shows a light header with
a full-colour logo inside a dark card. The stock login `<body>` carries no class,
so Cicada's `body.login` selector has nothing to match there either.

`cicada.css` gains `background-image: none !important` for
`.panel-heading[style*="linear-gradient"]`, which matches the login card header
and leaves Cicada's `--cic-head` visible. The logo treatment of §6.5 applies
there.

### 6.7 Everything else

`favicon` and `favicon_url` through `favicon.php`; `company_name` through
`title.php`; `show_version`, `show_design_picker`, `show_ispconfig_credit` and
`show_theme_credit` through `brand.php` and the credit spans the generator
creates. Each behaves as on classic.

## 7. Module: one design registry

`customizer_logo_surfaces()` holds a `$chrome` table with one entry per
brand-aware design, and `customizer_installed_designs()` and
`customizer_login_notice_html()` each carry their own list of names. The fork
moves the table into `customizer_design_chrome()` and has all three read it.

Cicada's entry:

| Surface | default | backdrop setting | backdrop | light mark drawn as |
|---|---|---|---|---|
| nav | `on_dark` | none | `#1C1C1C` | silhouette |
| login | `on_dark` | none | `#202020` | silhouette |

The last column is new to the table; clarity and classic carry `as supplied`.
When the preview shows a light-background mark on a Cicada surface it applies the
same filter as the panel.

New UI strings go into all seven `*_customizer.lng` files; CI's
`lang_check.php` enforces this.

## 8. Tests and CI

- `tests/brand/probe_cicada.php` emits the resolver decision matrix, and
  `run.php` cross-compares it with clarity, classic and the module.
- `run.php` gains `render:cicada`.
- Accent probe over a grid of colours (greys from black to white, the primaries
  and secondaries, very light and very dark values, `#dd630d`):
  `--cic-accent-text` reaches 4.5:1 on every pinned surface; `--cic-on-accent`
  is the higher-contrast ink of the two.
- Logo probe: a light mark on either Cicada surface is drawn as a silhouette and
  a dark mark as supplied, in `brand.php` and in the preview alike.
- Registry parity: `lib/designs.sh`, the keys of `customizer_design_chrome()` and
  the `themes/*/brand.php` readers name the same set of designs.
- `tests/shell/run.sh` runs §5.4.
- The CI steps that walk `themes/*/brand.php` pick up Cicada as they are.
- GitHub Actions are enabled on the fork, so pull requests run CI.

Visual pass before the release, on a 3.3.1p1 panel: login, dashboard and a list
page in Cicada, unbranded and with a light and a dark accent; the login header
next to the standalone's.

## 9. Installer

- `--design=cicada` installs Cicada and `--design=all` includes it; the default
  stays clarity.
- Cicada is stamped with `ispconfig_version` and `ISPC_VERSION` like every
  design.
- Today `deploy()` deletes whatever sits at `themes/<design>`. A real directory
  there that holds no `brand.php` came from elsewhere, such as the standalone
  Cicada. The installer moves it to `<ISPCONFIG_ROOT>/theme-backups/<design>-<timestamp>/`,
  outside `themes/` so the Design picker never lists it, prints where it went
  and states that the other installer must stay unused from now on. The target
  directory is a flag, `--backup-dir=`, with that default.
- The closing output names the re-run command with the flags just used.

## 10. Documentation

- `README.md`: Cicada beside clarity and classic under "The interface",
  `--design=cicada` under "Install", the licence note.
- `themes/cicada/README.md`: origin, what the generator changes, the brand
  mapping of §6, the light-logo silhouette and how a dark variant avoids it, the
  pointer to the standalone repository for 3.2.
- `UPGRADING.md`: Cicada is regenerated by the same re-run as classic.
- `DESIGN.md` stays clarity's document.

## 11. Versioning

Releases of this fork are tagged `v<upstream>+cicada.<n>`, annotated, starting
at `v3.6.0+cicada.1`. The build-metadata suffix keeps the upstream base visible,
and the names stay distinct from every upstream tag, so
`git fetch upstream --tags` works without collisions.

## 12. Rollout to a panel

1. Back up the ISPConfig tree, the `dbispconfig` database, `/etc`, and each
   user's `app_theme`.
2. Clone the fork at the release tag and run
   `install.sh --copy --design=cicada`.
3. Check: `themes/cicada/ispconfig_version` equals `ISPC_APP_VERSION`; Cicada is
   offered under Tools → User Settings; administrators see the Branding page.
4. Re-run with the same flags after every ISPConfig update.

## 13. Risks

| Risk | Guard |
|---|---|
| The generator refactor changes classic's output | byte comparison with upstream's output (§5.4) |
| A copied helper drifts from the other designs | cross-comparison in `run.php` |
| An operator's accent produces unreadable text | accent probe (§8) |
| A third-party module styled through `--cic-*` breaks | names unchanged (§6.1) |
| The login header rule matches more than the login card | attribute selector on the inline gradient; visual pass |
| Replacing a foreign theme directory loses it | moved aside to the backup directory (§9) |

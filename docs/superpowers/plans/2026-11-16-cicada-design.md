# Cicada as a third design — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship Cicada as a generated, brand-aware design beside clarity and classic, then roll it out to one production panel.

**Architecture:** One shell generator moves from `install.sh` into `lib/shell.sh` and serves every generated design from one design table in `lib/designs.sh`; classic's output stays byte-identical. `themes/cicada/` brings Cicada's stylesheet and chart script plus three brand endpoints modelled on classic's. The module's preview reads one design registry, `customizer_design_chrome()`.

**Tech Stack:** Bash (installer, generator, shell tests), POSIX awk, PHP 8.2+ (endpoints, module, probes), plain JavaScript (chart defaults, Branding page), Node for one script test, GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-11-16-cicada-design.md`

## Global Constraints

- Bases: upstream `v3.6.0` (`0d74958`); Cicada `v0.4.2` (`f95f174`) from `https://github.com/brightcolor/ispconfig-cicada`.
- Everything inside the repository is English: comments, CLI output, commit messages, documentation.
- The repository is public: no host names, addresses, account names or other operator details in files or commits.
- ISPConfig 3.3, as upstream supports; stock fixtures are 3.3.1p1.
- `lib/` and `tests/shell/` are Bash like `install.sh`; awk programs stay mawk-compatible (no `gensub`, `IGNORECASE`, `length(array)`).
- Theme endpoints stay self-contained pre-auth files; they include no module code.
- `--cic-*` custom property names stay as they are; new names may be added.
- Contrast targets: 4.5:1 for text, 3:1 for lines and markers (WCAG 2.x); the lightest Cicada surface is `#383838`.
- An unbranded Cicada renders like standalone v0.4.2, except the login card header (spec §6.6).
- Tags: `v<upstream>+cicada.<n>`, annotated; the first is `v3.6.0+cicada.1`.
- New shell scripts get the executable bit in git: `git update-index --chmod=+x <file>`, verified with `git ls-files -s` showing `100755`.
- Every commit ends with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- One implementer builds serially; no subagents without the owner's explicit approval.

## Review Focus

1. **A light-only logo on Cicada** (no dark variant stored): the panel draws a white silhouette, and both preview paths draw the same. Tests in Task 6 (no filter rule is emitted for a light mark) and Task 8 (swatch style, `paintNavBrand` needle).
2. **A stock shell of another shape**: without Chart.js the shell carries no chart script; without the stock colour sheet line Cicada's generation refuses while classic's still succeeds. Tests in Task 3.
3. **`--backup-dir` inside `themes/`**: the installer refuses before it changes anything, since ISPConfig lists every directory there. Test in Task 9.
4. **Second run and foreign directory**: a re-run over this project's Cicada moves nothing aside and rewrites templates and stamps; a first run over a foreign `themes/cicada` moves it to the backup directory intact. Tests in Task 9.
5. **A panel name with quotes or backslashes** shown as the wordmark: the CSS string is escaped and the sheet stays balanced. Test in Task 6.

---

### Task 1: Shell fixtures and classic's reference output

**Files:**
- Create: `tests/shell/fixtures/3.3.1p1/main.tpl.htm`, `tests/shell/fixtures/3.3.1p1/main_login.tpl.htm`, `tests/shell/fixtures/3.3.1p1/LICENSE`
- Create: `tests/shell/make_expected.sh`, `tests/shell/expected/classic/main.tpl.htm`, `tests/shell/expected/classic/main_login.tpl.htm`
- Create: `tests/shell/lib.sh`, `tests/shell/run.sh`, `tests/shell/README.md`
- Modify: `.github/workflows/ci.yml` (new step after "Shell syntax")

**Interfaces:**
- Produces: `tests/shell/lib.sh` with `t_ok NAME CONDITION_EXIT_CODE [DETAIL]`, `t_eq NAME GOT WANT`, `t_done` (prints `# N passed, M failed`, exits 1 on any failure); `run.sh` prints `shell suite passed` last on success.

- [ ] **Step 1: Fetch the fixtures** from `https://git.ispconfig.org/ispconfig/ispconfig3/-/raw/3.3.1p1/` (`interface/web/themes/default/templates/main.tpl.htm`, `.../main_login.tpl.htm`, `LICENSE`) into `tests/shell/fixtures/3.3.1p1/`. Expected sizes: 8707, 4133, 1525 bytes.
- [ ] **Step 2: Write `make_expected.sh <tag>`**: `git show <tag>:install.sh`, cut out `generate_classic_templates() {` … the closing `}` with `sed -n '/^generate_classic_templates() {/,/^}/p'`, source it with `THEMES_DIR` pointing at a temp dir whose `default/templates` holds the fixtures, run it into `tests/shell/expected/classic/`.
- [ ] **Step 3: Run it**: `bash tests/shell/make_expected.sh v3.6.0`. Expected: two files; `grep -c "themes/classic/brand.php" tests/shell/expected/classic/*.htm` prints 1 per file.
- [ ] **Step 4: Write `run.sh`** with the test `classic shell is byte-identical to upstream v3.6.0`: source `lib/designs.sh` and `lib/shell.sh`, run `generate_shell_templates classic tests/shell/fixtures/3.3.1p1 themes/classic "$tmp"`, `cmp` both files against `expected/classic/`.
- [ ] **Step 5: Run** `bash tests/shell/run.sh`. Expected: FAIL, `lib/designs.sh: No such file or directory`.
- [ ] **Step 6: CI step** "Shell suite": `bash tests/shell/run.sh`. Extend "Shell syntax" to `bash -n` every existing file in `lib/` and `tests/shell/` (the loop skips a pattern that matches nothing, since `lib/` arrives in Task 2).
- [ ] **Step 7: Commit** fixtures, expected output, test files and CI (`--chmod=+x` on the three scripts): `Add shell fixtures and classic's reference output from v3.6.0`.

### Task 2: One generator in `lib/`, classic unchanged

**Files:**
- Create: `lib/designs.sh`, `lib/shell.sh`
- Modify: `install.sh` (design list and parser at 160-190, `generate_classic_templates` at 322-578 removed, theme loop at 590-650, `on_exit` at 117-145)
- Modify: `uninstall.sh` (design list and parser at 79-104, removal case at 255-258)

**Interfaces:**
- Produces, `lib/designs.sh`:
  - `ALL_DESIGNS="clarity classic"` (Task 3 appends `cicada`)
  - `design_known DESIGN` — exit 0 when DESIGN is in `ALL_DESIGNS`
  - `design_field DESIGN FIELD` — prints the value or nothing. Fields: `shell` (`shipped`|`generated`), `credit_label`, `credit_url`, `drop_sheet`, `stock_colour`, `design_colour`, `stylesheet`, `chart_script`. Values now: clarity `shell=shipped`; classic `shell=generated`, `credit_label=Classic`, `credit_url=https://github.com/wadejbeckett/ispconfig-theme-customizer`.
- Produces, `lib/shell.sh`: `generate_shell_templates DESIGN STOCK_DIR SRC_DIR OUT_DIR` — writes `OUT_DIR/main.tpl.htm` and `OUT_DIR/main_login.tpl.htm`; returns 1 with the reason on stderr when it refuses. `STOCK_DIR` holds the two stock templates, `SRC_DIR` is the design's directory in the clone.

- [ ] **Step 1: Move the function** into `lib/shell.sh` under the new name and signature. Replace `themes/classic` with `themes/$DESIGN`, the credit label and URL with `design_field`, `$THEMES_DIR/default/templates` with `STOCK_DIR`. Keep the awk program, the checks and their order.
- [ ] **Step 2: Wire `install.sh`**: source both files before argument parsing (`"$(cd "$(dirname "$0")" && pwd)/lib/…"`); the parser accepts `all` or any `design_known` name; the theme loop generates when `design_field "$THEME_NAME" shell` is `generated` and places the templates for that design; `CLASSIC_TPL_TMP` becomes `SHELL_TPL_TMP`.
- [ ] **Step 3: Wire `uninstall.sh`** the same way; the removal case passes `templates` for every `generated` design.
- [ ] **Step 4: Run** `bash tests/shell/run.sh`. Expected: `ok classic shell is byte-identical to upstream v3.6.0`, `shell suite passed`.
- [ ] **Step 5: Run** `bash -n install.sh uninstall.sh lib/*.sh` (no output) and `bash install.sh --design=nope; echo $?` (prints `ERROR: unknown design: nope (known: clarity classic, all)`, then `2`).
- [ ] **Step 6: Commit**: `Move the shell generator into lib/ behind one design table`.

### Task 3: Cicada's assets and generated shell

**Files:**
- Create: `themes/cicada/assets/stylesheets/cicada.css`, `themes/cicada/assets/javascripts/cicada-charts.js` (verbatim from `v0.4.2`), `themes/cicada/LICENSE` (the standalone `LICENSE`)
- Modify: `lib/designs.sh`, `lib/shell.sh`, `tests/shell/run.sh`, `.gitignore`, `.github/workflows/ci.yml`

**Interfaces:**
- Consumes: Task 2's table and generator.
- Produces: `ALL_DESIGNS="clarity classic cicada"`; cicada fields `shell=generated`, `credit_label=Cicada`, `credit_url=https://github.com/brightcolor/ispconfig-theme-customizer`, `drop_sheet=stylesheets/themes/default/theme.min.css`, `stock_colour=#cc151c`, `design_colour=#dd630d`, `stylesheet=assets/stylesheets/cicada.css`, `chart_script=assets/javascripts/cicada-charts.js`.

- [ ] **Step 1: Copy the assets and licence** from `git -C ../ispconfig-cicada show v0.4.2:<path>`.
- [ ] **Step 2: Write the failing tests** in `run.sh`, each over the fixtures:
  - `cicada: stylesheet once, directly before brand.php` — in both files the line after the `cicada.css?v=` link is the `brand.php` link.
  - `cicada: brand.php is the last stylesheet` — `grep -n "rel='stylesheet'" | tail -1` contains `themes/cicada/brand.php`.
  - `cicada: stock colour sheet gone` — no `theme.min.css`.
  - `cicada: no stock red left` — no `#cc151c`; `#dd630d` present.
  - `cicada: chart script once, after Chart.js` — in `main.tpl.htm` exactly one `cicada-charts.js?v=`, on the line after `chart.umd.js`; none in `main_login.tpl.htm`.
  - `cicada: cache busters are cksums` — the `?v=` values equal `cksum themes/cicada/<asset> | cut -d' ' -f1`.
  - `cicada: login shell scene and prefix` — `main_login.tpl.htm` links `../themes/cicada/brand.php?scene=login`.
  - Review Focus 2: `cicada: shell without Chart.js has no chart script` (fixture copy with the `chart.umd.js` line deleted; generation succeeds, zero `cicada-charts.js`), `cicada: refuses a shell without the colour sheet` (fixture copy with the `theme.min.css` line deleted; returns 1), `classic: still generates without the colour sheet`.
- [ ] **Step 3: Run** `bash tests/shell/run.sh`. Expected: the cicada tests FAIL, classic still passes.
- [ ] **Step 4: Extend the generator.** awk receives `drop_re`, `stock_colour`, `design_colour`, the design stylesheet line and the chart line; it deletes the drop line and counts it, replaces the stock colour with a literal index/substr loop like `lreplace`, prints head lines in the order favicon, design stylesheet, brand, title, and appends the chart line after the line containing `js/chartjs/chart.umd.js`, counting it. Cache buster: `?v=$(cksum "$SRC_DIR/<asset>" | cut -d' ' -f1)`. The line-count check becomes `stock + 3 - icons - dropped + (stylesheet ? 1 : 0) + charts (+1 credit split)`. A design with `drop_sheet` set refuses when `dropped` is not 1.
- [ ] **Step 5: Run** `bash tests/shell/run.sh`. Expected: all pass, `shell suite passed`.
- [ ] **Step 6: Ignore rules and CI.** `.gitignore`: `themes/cicada/ispconfig_version`, `themes/cicada/ISPC_VERSION`, `themes/cicada/templates/`. CI step "cicada ships its assets and endpoints only": `find themes/cicada -type f` minus `brand.php`, `title.php`, `favicon.php`, `README.md`, `LICENSE`, `assets/stylesheets/cicada.css`, `assets/javascripts/cicada-charts.js` must be empty.
- [ ] **Step 7: Commit**: `Add Cicada's assets and generate its shell from the panel's stock templates`.

### Task 4: Cicada stylesheet — line token and login rules

**Files:**
- Modify: `themes/cicada/assets/stylesheets/cicada.css` (`:root` at 20-70, lines 269, 299, 313, 763, 1014, 1655, 1757, 1867, section 15 at 1995)
- Create: `tests/brand/probe_cicada_css.php`
- Modify: `tests/brand/run.php` (`$checks`)

- [ ] **Step 1: Write the failing probe** `probe_cicada_css.php` (registered as `'cicada-css'` in `$checks`):
  - `:root defines --cic-accent-line as var(--cic-accent)`
  - `var(--cic-accent-line) is drawn 8 times` and `var(--cic-accent) remains 17 times` (16 fills plus the new definition)
  - each of `#main-navigation a.active`, `.modules .bi-icon`, `.modules .button.active`, `input[type="color"]:focus`, `.formbutton-default.active`, `.select2-container-multi.select2-container-active .select2-choices`, `.select2-dropdown-open.select2-drop-above .select2-choices`, `.datetimepicker table tr td.today:hover.active` uses `var(--cic-accent-line)`
  - `the login card header drops the inline gradient`: rule `.panel-heading[style*="linear-gradient"]` with `background-image: none !important`
  - `the login mark is a silhouette by default`: rule `.panel-heading[style*="linear-gradient"] > img` with `filter: brightness(0) invert(1)` and `opacity: .82`
  - `#logo keeps its silhouette`
- [ ] **Step 2: Run** `php tests/brand/run.php`. Expected: `== cicada-css ==` with FAIL lines, `BRAND SUITE FAILED`.
- [ ] **Step 3: Edit the stylesheet**: the token after `--cic-accent-deep` with a comment on why lines need their own token; the eight uses; both login rules in section 15 with a comment citing the inline style in stock `main_login.tpl.htm`.
- [ ] **Step 4: Run** `php tests/brand/run.php`. Expected: `brand suite passed`.
- [ ] **Step 5: Commit**: `Give Cicada a line token for accents and a dark login card header`.

### Task 5: Cicada brand reader — helpers and derivations

**Files:**
- Create: `themes/cicada/brand.php` (copy of `themes/classic/brand.php`, see Step 3)
- Create: `tests/brand/probe_cicada.php`
- Modify: `tests/brand/run.php` (`$probes`), `tests/brand/harness.php` (add `h_accents()`)

**Interfaces:**
- Produces, in the helper block of `themes/cicada/brand.php`:
  - `brand_cic_params(): array` — the one place for every step and target: `'overlay' => '#383838'`, `'surfaces' => array('#1C1C1C', '#202020', '#222222', '#292929', '#2D2D2D', '#383838')`, `'text_min' => 4.5`, `'line_min' => 3.0`, `'mark_min' => 3.0`, `'deep_step' => 11`, `'ink_light' => '#FFFFFF'`, `'ink_dark' => '#1C1C1C'`, `'rail_hover' => 7`, `'rail_edge' => 10`, `'rail_active' => 13`, `'rail_dim' => 0.82`, `'default_accent' => '#dd630d'`. It sits first in the helper block because the probes evaluate only that block.
  - `brand_cic_ink(string $hex): string` — `ink_light` or `ink_dark`, the higher contrast on `$hex`; a tie gives `ink_light`.
  - `brand_cic_accent_tokens(string $accent): array` — keys `accent`, `text`, `line`, `deep`, `on` per spec §6.2.
  - `brand_cic_rail_tokens(string $rail, string $accent): array` — keys `ink`, `dim` (`brand_rgba(ink, rail_dim)`), `hover`, `edge`, `active`, `mark` per spec §6.3; an empty `$accent` walks `default_accent`.
- Produces, in `harness.php`: `h_accents(): array` — `#000000 #333333 #777777 #BBBBBB #FFFFFF #FF0000 #00FF00 #0000FF #FFFF00 #00FFFF #FF00FF #FFF8E1 #0A0A0A #003366 #dd630d`.

- [ ] **Step 1: Write the failing probe** `probe_cicada.php`, modelled on `probe_classic.php`:
  - the classic checks, pointed at Cicada: keys in code, `$rail_light_noop` used twice, luminance agrees with `h_lum`, the `brand_hex()` cases, `brand_readable` reaches its ratio
  - `params are the documented values` — `brand_cic_params()` equals the array above
  - over `h_accents()`: `text reaches 4.5:1 on every surface` (`h_contrast` per surface); `text is the first readable step` (either the stored colour already reaches 4.5:1 on `#383838` and `text` equals `brand_shade(accent, L)`, or `brand_shade(accent, L(text) - 2)` misses 4.5:1 there); `line reaches 3:1 on #383838`; `deep is 11 points darker` (HSL L within 0.5 of `max(0, L - 11)`); `on is the better ink`
  - over `h_rails()`: `rail ink is the better ink`, `rail mark reaches 3:1 on the rail`; for `#01243D #000000 #FFFFFF #0065AB #F2F5F7`: `dim reaches 4.5:1 once flattened` (`h_flatten`)
  - `#dd630d keeps Cicada's dark label` — `on` is `#1C1C1C`
  - emits `MATRIX` from `h_variant_matrix()` like classic
- [ ] **Step 2: Register** `'cicada' => __DIR__ . '/probe_cicada.php'` in `$probes`; run `php tests/brand/run.php`. Expected: FAIL (`cannot read …/themes/cicada/brand.php`).
- [ ] **Step 3: Create `brand.php`**: copy classic's file; rewrite the header docblock for Cicada (contract consumed, surfaces, the silhouette rule); keep `$scene`, the config and `sys_ini` read and the ETag block verbatim; replace everything between `/* ---- resolve + validate the contract values ---- */` and `echo $css;` with the resolution of `$accent`, `$rail`, `$login_bg`, the `$rail_light_noop` read-and-unset, and `$css = "/* cicada brand overrides — generated by themes/cicada/brand.php */\n";`; in the helper block drop `brand_ink` and add the four functions above.
- [ ] **Step 4: Run** `php tests/brand/run.php`. Expected: `== cicada ==` all ok, `ok cicada decides identically to clarity on all 168 inputs`, `brand suite passed`.
- [ ] **Step 5: Commit**: `Add Cicada's brand reader helpers and colour derivations`.

### Task 6: Cicada brand reader — endpoint body, title and favicon

**Files:**
- Modify: `themes/cicada/brand.php` (body), `tests/brand/probe_render.php`, `tests/brand/run.php` (`$renders`)
- Create: `themes/cicada/title.php`, `themes/cicada/favicon.php` (copies of classic's)

**Interfaces:**
- Consumes: Task 5's helpers. Login mark selector everywhere: `.panel-heading[style*="linear-gradient"] > img`.

- [ ] **Step 1: Write the failing render tests** in `probe_render.php`, gated on `function_exists('brand_cic_accent_tokens')`; also turn the hard-coded `classic:` labels of the one-surface block into `$design:`:
  - `cicada: accent emits the five tokens` — for `accent_hex #0065AB`: `--cic-accent: #0065AB;` and `--cic-accent-text:`, `--cic-accent-line:`, `--cic-accent-deep:`, `--cic-on-accent:` inside one `:root {` block
  - `cicada: accent relabels .bg-primary` and `cicada: accent tints the tap highlight` (`-webkit-tap-highlight-color: rgba(0, 101, 171, 0.3)`)
  - `cicada: white rail gets readable ink` — `#main-navigation a {` carries `background: #FFFFFF`; its colour flattened on `#FFFFFF` reaches 4.5:1
  - `cicada: rail covers the off-canvas submenu` — `.pushy ul.subnavi li a:hover` present
  - `cicada: dark mark is painted and unfiltered` — `logo_on_dark` stored, app scene: `#logo { background-image: url("…png2…")` and `#logo { filter: none; opacity: 1; }`; login scene: the login selector with `content: url(` and `filter: none`
  - Review Focus 1: `cicada: a light mark leaves the silhouette alone` — only `custom_logo` stored: no `filter` in either scene
  - `cicada: login background only in the login scene` — `login_bg #F5F5F5`: `body { background: #F5F5F5; }` with `scene=login`, no `body {` without it
  - Review Focus 5: `cicada: wordmark is escaped` — no logo, company name `Acme "Hosting" \ Co`: the sheet holds `content: "Acme \"Hosting\" \\ Co"` and `css_balanced()`
- [ ] **Step 2: Register** `'render:cicada' => array(__DIR__ . '/probe_render.php', 'cicada')`; run `php tests/brand/run.php`. Expected: FAIL on the new cicada lines.
- [ ] **Step 3: Write the body** (spec §6.2–§6.7), in this order: `:root` tokens plus `.bg-primary` and `html, body` tap highlight when an accent is stored; rail rules for `#main-navigation a`, `:hover`, `.active`, the marker rule, `.pushy`, `.pushy a`, `.pushy a:hover, .pushy a.active, .pushy ul.subnavi li a:hover`; the logo block of classic with default `on_dark`, the login selector above, and `filter: none; opacity: 1;` for a painted dark mark or a wordmark (wordmark ink: `brand_readable(accent, L, '#1C1C1C', 4.5)`, `#FFFFFF` without accent); login scene `body` background; `show_version`, `show_design_picker` and the credit rules as classic's strings.
- [ ] **Step 4: Copy `title.php` and `favicon.php`** from classic; change the design name in comments only. Verify: `diff <(sed 's/classic/X/g;s/Classic/X/g' themes/classic/title.php) <(sed 's/cicada/X/g;s/Cicada/X/g' themes/cicada/title.php)` prints nothing; the same for `favicon.php`.
- [ ] **Step 5: Run** `php tests/brand/run.php` and `php -l` on the three endpoints. Expected: `brand suite passed`, `No syntax errors detected` ×3.
- [ ] **Step 6: Commit**: `Write Cicada's brand reader body and its title and favicon endpoints`.

### Task 7: Chart accent from the stylesheet

**Files:**
- Modify: `themes/cicada/assets/javascripts/cicada-charts.js` (constants at 22-33, `beforeInit` at 61-80)
- Create: `tests/js/cicada-charts.test.js`
- Modify: `.github/workflows/ci.yml` (new step "Chart script")

**Interfaces:**
- Produces: inside the IIFE, `readAccent(): string` (trimmed `--cic-accent-text` of `document.documentElement` when it matches `/^#[0-9a-f]{6}$/i`, otherwise `'#ef7d21'`) and `accentFill(hex): string` (`'rgba(239, 125, 33, .18)'` for the shipped colour, `rgba(r, g, b, .18)` otherwise). `beforeInit` calls both per chart.

- [ ] **Step 1: Write the failing test** with `node:assert` and `node:vm`: stub `globalThis.Chart` (`defaults` with `scale.grid`, `scale.ticks`, `plugins.legend`, `plugins.tooltip`; `register(p)` keeps `p`), `globalThis.document = { documentElement: {} }`, `globalThis.getComputedStyle`. Cases: `accent from the stylesheet` — property `' #123456'`, a dataset with `borderColor 'rgb(75, 192, 192)'` and `backgroundColor 'rgba(75, 192, 192, 0.2)'` becomes `'#123456'` and `'rgba(18, 52, 86, .18)'`; `shipped accent when the property is empty` — `'#ef7d21'` and `'rgba(239, 125, 33, .18)'`; `a chart with its own colour is left alone`. Last line `chart test passed`.
- [ ] **Step 2: Run** `node tests/js/cicada-charts.test.js`. Expected: FAIL on the first case (`'#ef7d21' !== '#123456'`).
- [ ] **Step 3: Implement** the two functions; keep the comment that explains why Chart.js needs plain colours, now saying the value is read once per chart from the computed style.
- [ ] **Step 4: Run** the test. Expected: `chart test passed`. Add the CI step `node tests/js/cicada-charts.test.js`.
- [ ] **Step 5: Commit**: `Let Cicada's charts follow the stored accent`.

### Task 8: Module — one design registry, Cicada's chrome, light-mark preview

**Files:**
- Modify: `interface/web/customizer/lib/preview.inc.php` (`customizer_logo_surfaces` at 857-985, `customizer_installed_designs` at 1041, `customizer_login_notice_html` at 1089, `customizer_logo_preview_html` at 1170)
- Modify: `interface/web/customizer/templates/customizer_edit.htm` (`paintNavBrand` at 1474; the frozen region from 2006 stays untouched)
- Modify: `tests/brand/probe_module.php`, `tests/brand/probe_page.php`
- Create: `tests/brand/probe_registry.php`; Modify: `tests/brand/run.php` (`$checks`)

**Interfaces:**
- Produces: `customizer_design_chrome(): array` — the `$chrome` table, clarity and classic entries moved verbatim, plus
  `'cicada' => array('nav' => array('default' => 'on_dark', 'bg_key' => '', 'bg' => '#1C1C1C', 'bg_key_light' => '', 'modes' => array(), 'light_mark' => 'silhouette'), 'login' => array('default' => 'on_dark', 'bg_key' => '', 'bg' => '#202020', 'bg_key_light' => '', 'modes' => array(), 'light_mark' => 'silhouette'))`.
- Surface entries from a slot with `light_mark` end with `'light_mark' => 'silhouette'`; other entries keep their shape.

- [ ] **Step 1: Write the failing tests.** `probe_module.php`: `registry lists three designs in order` (`array_keys(customizer_design_chrome()) === array('clarity', 'classic', 'cicada')`); `cicada surfaces on automatic` (exact two entries, nav `on_dark #1C1C1C`, login `on_dark #202020`, both ending in `light_mark`); `cicada obeys an explicit on_light`; `cicada suppresses the login notice` (`customizer_login_notice_html('cicada', …) === ''`); Review Focus 1: `a light mark on a cicada swatch is drawn as a silhouette` (`customizer_logo_preview_html` contains `filter:brightness(0) invert(1);opacity:.82`) and `classic swatches stay unfiltered`. `probe_page.php`: needle `light_mark` inside `paintNavBrand`. `probe_registry.php` (registered as `'registry'`): `ALL_DESIGNS` parsed from `lib/designs.sh`, the registry keys and the directories holding `themes/*/brand.php` are one set.
- [ ] **Step 2: Run** `php tests/brand/run.php`. Expected: FAIL (`Call to undefined function customizer_design_chrome()`).
- [ ] **Step 3: Implement**: the registry function; `customizer_logo_surfaces` reads it and appends `light_mark` to entries of such slots in all three entry branches; the two name lists become `array_keys(customizer_design_chrome())`; `customizer_logo_preview_html` keeps `light_mark` per box and adds `style="filter:brightness(0) invert(1);opacity:.82"` to the `<img>` of an `on_light` box that carries it; `paintNavBrand` sets `img.style.filter` and `img.style.opacity` to those values for an `on_light` entry with `light_mark === 'silhouette'` and clears both otherwise.
- [ ] **Step 4: Run** `php tests/brand/run.php` and `php tests/svg/run.php`. Expected: `brand suite passed`, the svg suite passes as before.
- [ ] **Step 5: Commit**: `Read every design from one registry and preview Cicada's light mark as drawn`.

### Task 9: Installer — foreign directories, backup directory, re-run line

**Files:**
- Modify: `install.sh` (usage header at 1-70, parser, theme loop, closing output at 790-819)
- Create: `tests/shell/install_test.sh`; Modify: `.github/workflows/ci.yml` (step "Install test")

**Interfaces:**
- Produces: flag `--backup-dir=PATH`, default `$ISPC_ROOT/theme-backups`; `move_aside DEST NAME` moves a real directory without `brand.php` to `$BACKUP_DIR/NAME-YYYYmmdd-HHMMSS` and says so, including that the other installer must stay unused on this panel.

- [ ] **Step 1: Write the failing test** `install_test.sh`: a fake root in `mktemp -d` with `interface/web/themes/default/templates/` (fixtures), `interface/lib/config.inc.php` and `server/lib/config.inc.php` (`define('ISPC_APP_VERSION', '3.3.1p1');`, `$conf['theme'] = 'default';`), and a foreign `themes/cicada/marker.txt`. Cases, all with `--theme --copy --design=cicada --no-assign`:
  - Review Focus 4: `first run moves the foreign directory aside` — exit 0; `theme-backups/cicada-*/marker.txt` exists; `themes/cicada/brand.php` exists; `themes/cicada/ispconfig_version` reads `3.3.1p1`; `themes/cicada/templates/main.tpl.htm` links `themes/cicada/brand.php`
  - Review Focus 4: `a re-run moves nothing` — exit 0; still one `theme-backups/cicada-*`
  - Review Focus 3: `a backup directory inside themes is refused` — `--backup-dir=<root>/interface/web/themes/old`: non-zero exit, stderr names `outside`, `themes/old` absent
  - `uninstall removes cicada with its templates` — `uninstall.sh --theme --design=cicada <root>`: `themes/cicada` absent
  - last line `install test passed`
- [ ] **Step 2: Run** `bash tests/shell/install_test.sh`. Expected: FAIL at the first case (no `theme-backups`).
- [ ] **Step 3: Implement**: flag parsing; default after parsing; refusal when the resolved backup path starts with `$THEMES_DIR/`; `move_aside` before `deploy()` in the theme loop; usage text lists `cicada` and `--backup-dir`; under the closing IMPORTANT note print `    $0 $ORIGINAL_ARGS`.
- [ ] **Step 4: Run** `bash tests/shell/install_test.sh` and `bash tests/shell/run.sh`. Expected: `install test passed`, `shell suite passed`. Add the CI step.
- [ ] **Step 5: Commit**: `Move a foreign design directory aside instead of deleting it`.

### Task 10: Documentation

**Files:**
- Create: `themes/cicada/README.md`
- Modify: `README.md` ("The interface", "Install", "After any ISPConfig upgrade", a licence note), `UPGRADING.md`, `contrib/webserver/README.md` (both deny patterns), `docs/superpowers/specs/2026-11-16-cicada-design.md` (status line)

- [ ] **Step 1: Theme README**: origin (Cicada for OPNsense, `misc/theme-cicada`, rene@team-rebellion.net; standalone repository), the generator's changes, the brand mapping of spec §6, the light-logo silhouette and the dark variant that avoids it, ISPConfig 3.2 pointer to the standalone repository, licence BSD-2-Clause.
- [ ] **Step 2: Root README and UPGRADING**: a `cicada` subsection beside clarity and classic; `--design=cicada` and `--backup-dir` under Install; Cicada regenerated by the same re-run as classic; the licence note for `themes/cicada/`.
- [ ] **Step 3: Deny rules**: add `LICENSE` to `(ispconfig_version|ISPC_VERSION|BUILT-AGAINST\.txt|README\.md)` in the nginx and Apache snippets and to the sentence that lists the denied files.
- [ ] **Step 4: Check** `php .github/scripts/lang_check.php` passes and every relative link in the changed Markdown files resolves (`grep -o '](\([^)]*\))'` against the tree). Set the spec status to `implemented`.
- [ ] **Step 5: Commit**: `Document Cicada`.

### Task 11: Visual pass

**Files:** none in the repository; work in the session scratchpad.

- [ ] **Step 1: Harness**: copy `../ispconfig-brightcolor/harness` into the scratchpad; in `lib.php` and `router.php` replace the theme name `brightcolor` with `cicada` and serve `/themes/cicada/` from the build; build `themes/cicada` from this branch into `build/themes/cicada`, its templates with `generate_shell_templates cicada <copy>/vendor/web/themes/default/templates themes/cicada <copy>/build/themes/cicada/templates` (`VENDOR` in the harness `lib.php`).
- [ ] **Step 2: Branded variants**: for the accent `#FFD400`, the accent `#003366` with rail `#003366`, and a stored dark logo, write the sheet `render()` from `probe_render.php` produces to `build/themes/cicada/brand.css` and point the built shell's `brand.php` link at it (build copy only).
- [ ] **Step 3: Screenshots** in the browser pane (desktop and mobile): login, dashboard, websites list; unbranded and the three variants; the unbranded login next to the standalone theme's.
- [ ] **Step 4: Judge**: login header dark with the silhouette; markers visible with `#003366`; labels readable on `#FFD400`; charts in the accent. A finding goes back to its task with a failing test first.

### Task 12: Release

- [ ] **Step 1: Full local run**: `php tests/brand/run.php`, `php tests/svg/run.php`, `python3 tests/bootstrap/run.py`, `bash tests/shell/run.sh`, `bash tests/shell/install_test.sh`, `node tests/js/cicada-charts.test.js`, `php .github/scripts/lang_check.php`. Expected: each reports success.
- [ ] **Step 2: Enable Actions on the fork**: `gh api -X PUT repos/brightcolor/ispconfig-theme-customizer/actions/permissions -F enabled=true -f allowed_actions=all`.
- [ ] **Step 3: Push and open the PR** `feat/cicada-design` → `main` of the fork; body summarises the four parts and the review focus; ends with the Claude Code line.
- [ ] **Step 4: CI green**, then merge with a merge commit (`gh pr merge --merge`), the project's style.
- [ ] **Step 5: Tag** `git tag -a v3.6.0+cicada.1 -m "Cicada as a third design"` on the merge commit; `git push origin v3.6.0+cicada.1`.
- [ ] **Step 6: Tag CI**: the tag push runs CI (`on: push`); wait for that run with one watcher in the background and continue only when it is green.

### Task 13: Rollout

The operator's server log names the host, the source directory and the backup location; none of that goes into this repository.

- [ ] **Step 1: Back up** per spec §12 into a timestamped pre-change directory: `dbispconfig` dump, the ISPConfig tree, `/etc`, and `SELECT username, app_theme FROM sys_user`; verify each file is non-empty and the dump ends with `-- Dump completed`. Open the server log entry with the time read from the server.
- [ ] **Step 2: Install**: clone the tag into the server's source directory and run `bash install.sh --copy --design=cicada` (theme and Branding page, admin assignment on). The closing output prints a `$conf['theme']` command; the panel's login design stays as it is, so that command stays unused.
- [ ] **Step 3: Verify**: `themes/cicada/ispconfig_version` equals `ISPC_APP_VERSION`; `themes/cicada/brand.php` answers with HTTP 200 and `text/css`; the previous `themes/cicada` sits in the backup directory; the admin's module list contains `customizer`; every user's `app_theme` matches the backup.
- [ ] **Step 4: Close the log entry** with result, checks and the way back (restore the moved directory and the tree from the backup); update the system profile: Cicada's new source, the re-run command after ISPConfig updates, and the standalone installer that must stay unused.

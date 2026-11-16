<?php
/* ============================================================
 * cicada — brand.php  (the brand READER for the Cicada design)
 * ------------------------------------------------------------
 * Cicada is a dark onyx skin after the Cicada theme for OPNsense. Its
 * stylesheet (assets/stylesheets/cicada.css) draws every colour through --cic-*
 * custom properties, so this file mostly REASSIGNS those properties from the
 * brand-token contract instead of overriding selectors. The property names stay
 * as they are: third-party modules colour themselves through them.
 *
 * Contract consumed (sys_ini, global row sysini_id = 1):
 *   THE LOGO — two variants, each named by the BACKGROUND it sits on:
 *     custom_logo  column              -> LIGHT-background mark, uploaded
 *     config [branding] logo_url       -> LIGHT-background mark, by reference
 *     config [branding] logo_on_dark   -> DARK-background mark, uploaded
 *     config [branding] logo_url_on_dark -> DARK-background mark, by reference
 *     config [branding] logo_variant_nav   -> ''|'on_light'|'on_dark': which
 *     config [branding] logo_variant_login    variant each SURFACE asks for.
 *                                         '' — and absent — mean AUTOMATIC.
 *   Resolution, identical to themes/clarity/brand.php, themes/classic/brand.php
 *   and the customizer's lib/preview.inc.php:
 *     1. within a variant, a valid reference beats an uploaded data URI;
 *     2. each SURFACE asks for the variant matching its own background and
 *        falls back to the other when the asked-for variant is empty.
 *   Both Cicada surfaces have FIXED dark backdrops: #logo sits on the page
 *   ground (#1C1C1C), the login mark in the card header (#202020). No colour
 *   the operator can set reaches either, so automatic asks for the
 *   dark-background mark on both.
 *
 *   How a mark is drawn: cicada.css flattens the logo to a white silhouette on
 *   both surfaces, because the stock ISPConfig logo and most uploads are made
 *   for a light ground. That default needs nothing from this file. When a
 *   surface resolves to the DARK-background mark, this file paints it and lifts
 *   the filter, so artwork made for a dark ground shows as designed.
 *
 *   config [branding] accent_hex  -> --cic-accent and the four values derived
 *                                    from it (text, line, deep, label), plus
 *                                    .bg-primary's label and the tap highlight
 *   config [branding] rail_hex    -> the main-navigation band (+ mobile menu)
 *   config [branding] rail_hex_light -> read, and deliberately a NO-OP here:
 *                                       Cicada has one colour mode. See the
 *                                       read below.
 *   config [branding] login_bg    -> the login page behind the card
 *   config [branding] show_version (0/1) -> 0 hides Help's version surfaces
 *   config [branding] show_design_picker (0/1) -> 0 hides the Design picker
 *                                                 under Tools > User Settings
 *   config [misc] company_name    -> text wordmark when no logo is set;
 *                                    tab title / alt text via title.php
 *
 *   config [branding] show_ispconfig_credit (0/1) -> footer courtesy line
 *   config [branding] show_theme_credit     (0/1) -> footer courtesy line
 *
 * The footer toggles rely on the shell generator (lib/shell.sh), which splits
 * stock's credit line into .nzc-credit-ispconfig and appends .nzc-credit-theme
 * while building this design's shell, exactly as it does for classic. Both ship
 * ON, and neither touches a licence notice: these are courtesy lines.
 *
 * ---- how a colour is derived ---------------------------------------------
 * Fills take the operator's colour EXACTLY as entered: --cic-accent and the
 * navigation band are the brand. Whatever is printed on Cicada's dark surfaces
 * is walked from that colour with brand_readable() until it clears its WCAG 2.x
 * ratio on the lightest surface it can sit on, --cic-overlay (#383838): 4.5:1
 * for text, 3:1 for lines and markers. Every step and target lives in
 * brand_cic_params(), at the head of the helper block.
 *
 * ---- design constraints (shared with clarity and classic) ----------------
 *   - Pre-auth safe: the login screen links this file, so it must work with no
 *     session. A single, side-effect-free, read-only query of one sys_ini row —
 *     no ISPConfig app bootstrap, no maintenance-mode redirect, no session.
 *   - Always HTTP 200 with valid CSS. With nothing set it emits an empty sheet
 *     and Cicada renders exactly as it ships.
 *   - Injection-safe: every value is validated for its output context before it
 *     reaches the page (anchored hex regex / data-URI regex / url allowlist
 *     regex with /D / 0|1 / control-char strip + CSS-string escape + length cap).
 *   - company_name is normalised identically here and in title.php so the CSS
 *     wordmark and the tab title never disagree about the panel's own name.
 *     Change one, change the other.
 *
 * ---- ?scene=login --------------------------------------------------------
 * Cicada's shell is generated from the panel's stock templates, like classic's,
 * and stock's login <body> carries no class, no id and nothing stable to hang a
 * selector on. The generator therefore links THIS endpoint as
 * brand.php?scene=login from the login template only, and the login-only rules
 * key off the URL instead of the DOM. Everything else is emitted in both scenes.
 * ============================================================ */

header('Content-Type: text/css; charset=utf-8');

// Only ever compared, never emitted. The login shell is the only template
// install.sh gives this parameter to.
$scene = (isset($_GET['scene']) && $_GET['scene'] === 'login') ? 'login' : 'app';

/* ---- read the contract (direct, minimal, side-effect-free) ---- */
$branding     = array();
$custom_logo  = '';
$company_name = '';
$read_ok      = false; // true only when the sys_ini read actually succeeded

// Locate interface/lib/config.inc.php WITHOUT trusting __DIR__ alone.
// install.sh's DEFAULT mode is a SYMLINK, and PHP resolves __DIR__ through
// symlinks — so on a symlinked design __DIR__ is the git clone, where
// ../../../lib/config.inc.php does not exist. This endpoint would then fail to
// find the panel, take its DB-failure path, and emit its empty/no-op response:
// branding silently doing nothing on the documented default install, with no
// error anywhere. SCRIPT_FILENAME is the path the web server actually
// requested and is NOT symlink-resolved, so try that first; __DIR__ still
// covers CLI use and copy installs, and DOCUMENT_ROOT is a last resort.
$config_inc = '';
$_cfg_candidates = array();
if (!empty($_SERVER['SCRIPT_FILENAME'])) {
    $_cfg_candidates[] = dirname($_SERVER['SCRIPT_FILENAME']) . '/../../../lib/config.inc.php';
}
$_cfg_candidates[] = __DIR__ . '/../../../lib/config.inc.php';
if (!empty($_SERVER['DOCUMENT_ROOT'])) {
    $_cfg_candidates[] = $_SERVER['DOCUMENT_ROOT'] . '/../lib/config.inc.php';
}
foreach ($_cfg_candidates as $_cand) {
    if (is_readable($_cand)) { $config_inc = $_cand; break; }
}
unset($_cfg_candidates, $_cand);
if (is_readable($config_inc)) {
    // config.inc.php only defines $conf + constants — but on a web request it also
    // emits `Content-Type: text/html`, which we re-assert away from below.
    require $config_inc;
    if (isset($conf) && is_array($conf) && !empty($conf['db_host'])) {
        // PHP 8.1+ makes mysqli throw by default; keep the graceful errno idiom working.
        if (function_exists('mysqli_report')) {
            mysqli_report(MYSQLI_REPORT_OFF);
        }
        try {
            $port   = isset($conf['db_port']) ? (int)$conf['db_port'] : 3306;
            $mysqli = @new mysqli($conf['db_host'], $conf['db_user'], $conf['db_password'], $conf['db_database'], $port);
            if ($mysqli && !$mysqli->connect_errno) {
                @$mysqli->set_charset('utf8mb4');
                if ($res = @$mysqli->query('SELECT config, custom_logo FROM sys_ini WHERE sysini_id = 1')) {
                    $read_ok = true;
                    if ($row = $res->fetch_assoc()) {
                        $parsed   = brand_parse_config((string)$row['config']);
                        $branding = (isset($parsed['branding']) && is_array($parsed['branding'])) ? $parsed['branding'] : array();
                        if (isset($parsed['misc']['company_name']) && is_string($parsed['misc']['company_name'])) {
                            // Normalise EXACTLY as title.php does, so the two brand
                            // endpoints can never derive different strings from the
                            // same sys_ini row (they co-render on the login page: the
                            // CSS wordmark here, the tab title / <img alt> there).
                            // Only control characters are removed, and only because
                            // they cannot be represented: a raw CR/LF terminates a CSS
                            // string, after which the parser's error recovery would
                            // read the remainder of the name as further declarations.
                            // Everything printable survives and is escaped at the
                            // emit site instead — see the wordmark block below.
                            // The class is applied byte-wise and deliberately WITHOUT
                            // /u: with /u a single malformed UTF-8 byte makes
                            // preg_replace return NULL and the brand silently vanishes,
                            // while 0x00-0x1F and 0x7F never occur inside a valid UTF-8
                            // sequence (continuation bytes are 0x80-0xBF), so a byte
                            // filter cannot damage a multibyte character.
                            $company_name = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $parsed['misc']['company_name']));
                            // multibyte-safe cap: byte-substr would cut a UTF-8
                            // codepoint in half and corrupt the rendered wordmark.
                            // The cap is for the nowrap header slot only — title.php
                            // keeps document.title and alt text uncapped on purpose.
                            if (function_exists('mb_substr')) {
                                if (mb_strlen($company_name, 'UTF-8') > 40) $company_name = mb_substr($company_name, 0, 40, 'UTF-8');
                            } elseif (strlen($company_name) > 40) {
                                $company_name = substr($company_name, 0, 40);
                            }
                        }
                        $custom_logo = (string)$row['custom_logo'];
                    }
                }
                if ($mysqli instanceof mysqli) {
                    $mysqli->close();
                }
            }
        } catch (\Throwable $e) {
            // any DB fault -> emit empty CSS; never leak an error on this pre-auth route
            $branding     = array();
            $custom_logo  = '';
            $company_name = '';
            $read_ok      = false;
        }
    }
}

// Re-assert the stylesheet MIME: config.inc.php sends text/html on web requests,
// and browsers reject a standards-mode stylesheet whose type isn't text/css.
header('Content-Type: text/css; charset=utf-8');

/* ---- caching ----------------------------------------------------------------
 * On a good read, cache briefly (with an ETag) so this isn't a blocking round-
 * trip on every full page load; the branding is global, so a saved change
 * appears within max-age or on a hard refresh. 'private' keeps it out of shared
 * / reverse-proxy caches. On a DB fault we still emit an (empty) sheet but must
 * NOT cache it — otherwise a transient outage would blank the host's branding
 * for the whole max-age window even after the DB recovers.
 * The scene is part of the validator: the two scenes are different URLs, but an
 * ETag that ignored it would let a stale revalidation cross them. It carries a
 * second job since the logo variant became per-surface — the two scenes now
 * resolve their logos independently, so their sheets can differ by more than
 * the login-only rules.
 * The two logo_variant_* keys need no term of their own here: the contract
 * stores them in [branding], serialize($branding) already hashes them, and
 * flipping either one therefore moves this validator. That is the ONLY thing
 * keeping them cached correctly — move either key out of the [branding]
 * section and this line has to name it by hand, or a saved change would sit
 * behind a stale 304 for the whole max-age window. */
if ($read_ok) {
    $etag = '"' . md5($scene . '|' . serialize($branding) . '|' . md5($custom_logo) . '|' . $company_name) . '"';
    header('ETag: ' . $etag);
    header('Cache-Control: private, max-age=30');
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
        http_response_code(304);
        exit;
    }
} else {
    header('Cache-Control: no-store');
}

/* ---- resolve + validate the contract values ---- */
$accent   = brand_hex($branding, 'accent_hex');
$rail     = brand_hex($branding, 'rail_hex');
$login_bg = brand_hex($branding, 'login_bg');

//* rail_hex_light is read here and used for nothing, on purpose.
//*
//* It is on CI's brand-token contract list, which every design under themes/
//* must satisfy, and the check greps this file, so a comment alone would satisfy
//* it while the design said nothing about the key at all. Reading it in CODE is
//* the honest answer: Cicada has ONE colour mode, and there is no light scope
//* for a second rail colour to live in.
//*
//* tests/brand/probe_cicada.php holds both halves: that the key appears in code
//* rather than in prose, and that the value never reaches a rule.
$rail_light_noop = brand_hex($branding, 'rail_hex_light');
unset($rail_light_noop);

// Which surface this response paints, and the operator's choice of mark for
// it. The login shell asks with ?scene=login, the app frame without, so one
// request resolves one preference, exactly as classic does.
$surface     = ($scene === 'login') ? 'login' : 'nav';
$stored_pref = ($surface === 'login')
    ? (isset($branding['logo_variant_login']) ? $branding['logo_variant_login'] : '')
    : (isset($branding['logo_variant_nav'])   ? $branding['logo_variant_nav']   : '');

$css = "/* cicada brand overrides — generated by themes/cicada/brand.php */\n";

// The login mark's selector, written once. cicada.css flattens the mark with
// the same selector, and a weaker one here would lose to it.
$login_mark = '.panel-heading[style*="linear-gradient"] > img';

$params = brand_cic_params();

/* =========================================================================
 * accent — Cicada's accent properties, and its two accent uses outside them
 * =======================================================================*/
// cicada.css draws every accent through these five properties, so assigning
// them recolours buttons, links, tabs, markers and focus rings at once. The fill
// takes the stored colour exactly; whatever is printed on the dark surfaces is
// walked from it until it reads (brand_cic_accent_tokens()).
$tok = ($accent !== '') ? brand_cic_accent_tokens($accent) : array();
if ($accent !== '') {
    $css .= ":root {\n"
          . "  --cic-accent: {$tok['accent']};\n"
          . "  --cic-accent-text: {$tok['text']};\n"
          . "  --cic-accent-line: {$tok['line']};\n"
          . "  --cic-accent-deep: {$tok['deep']};\n"
          . "  --cic-on-accent: {$tok['on']};\n"
          . "}\n";
    // Two uses the stylesheet writes as literals: .bg-primary prints white on
    // the fill whatever the fill is, and the tap highlight is Cicada's orange as
    // an rgba. Both follow the stored accent.
    $css .= ".bg-primary { color: {$tok['on']}; }\n";
    $css .= "html, body { -webkit-tap-highlight-color: " . brand_rgba($accent, $params['tap_alpha']) . "; }\n";
}

/* =========================================================================
 * rail — the main-navigation band and the off-canvas menu
 * =======================================================================*/
// Cicada's band is the row of module buttons and the mobile drawer. Both sit on
// --cic-surface, which cards and fields share, so the band is painted by
// selector; reassigning the property would repaint every card with it. Labels,
// states and the active marker come from brand_cic_rail_tokens(). The header
// strip that holds #logo lies outside the band and keeps the page ground.
if ($rail !== '') {
    $band = brand_cic_rail_tokens($rail, $accent);
    $css .= "#main-navigation a { background: {$rail}; border-color: {$band['edge']}; "
          . "border-bottom-color: {$band['edge']}; color: {$band['dim']}; }\n";
    $css .= "#main-navigation a:hover { background: {$band['hover']}; color: {$band['ink']}; }\n";
    $css .= "#main-navigation a.active { background: {$band['active']}; color: {$band['ink']}; }\n";
    $css .= "#main-navigation a:hover, #main-navigation a.active { border-bottom-color: {$band['mark']}; }\n";
    $css .= ".pushy { background: {$rail}; }\n";
    $css .= ".pushy a { color: {$band['dim']}; }\n";
    $css .= ".pushy a:hover, .pushy a.active, .pushy ul.subnavi li a:hover "
          . "{ background: {$band['hover']}; color: {$band['ink']}; }\n";
}

/* =========================================================================
 * logo + wordmark
 * -------------------------------------------------------------------------
 * The two-variant model, resolved per SURFACE as on classic: ?scene=login
 * reaches the login shell alone, so one request paints one surface.
 *
 * ---- WHAT AUTOMATIC MEASURES ON CICADA: nothing, on either surface --------
 * #logo sits in the header strip on the page ground (cicada.css: body
 * { background: var(--cic-page) }, #1C1C1C); rail_hex paints #main-navigation
 * and .pushy only. The login mark sits in the card header, which cicada.css
 * gives --cic-head (#202020) once it has dropped stock's inline gradient, and
 * login_bg paints the page BEHIND the card. Neither colour reaches either mark,
 * so both backgrounds are passed as '' and automatic falls through to this
 * design's default, the dark-background mark. An explicit logo_variant_nav or
 * logo_variant_login still wins outright.
 *
 * ---- HOW A MARK IS DRAWN ---------------------------------------------------
 * cicada.css turns both marks into a white silhouette: the right answer for the
 * stock ISPConfig logo and any artwork made for a light ground, and the default
 * this file leaves alone. A dark-background mark is painted here with the filter
 * lifted, so it shows as designed. The panel name becomes the wordmark only when
 * there is no logo of either variant.
 * =======================================================================*/
$core_logo = (preg_match('#^data:image/[a-z0-9.+-]+;base64,[A-Za-z0-9+/=]+$#iD', $custom_logo)) ? $custom_logo : '';

$logo_on_light = brand_logo_variant(
    isset($branding['logo_url']) ? $branding['logo_url'] : '',
    $custom_logo
);
$logo_on_dark = brand_logo_variant(
    isset($branding['logo_url_on_dark']) ? $branding['logo_url_on_dark'] : '',
    isset($branding['logo_on_dark'])     ? $branding['logo_on_dark']     : ''
);

$has_logo = ($logo_on_light !== '' || $logo_on_dark !== '');

$logo_pref  = brand_logo_variant_pref($stored_pref, '', 'on_dark');
$logo_want  = ($logo_pref === 'on_dark') ? $logo_on_dark  : $logo_on_light;
$logo_other = ($logo_pref === 'on_dark') ? $logo_on_light : $logo_on_dark;
$logo_src   = ($logo_want !== '') ? $logo_want : $logo_other;
$mark_sel   = ($scene === 'login') ? $login_mark : '#logo';

// Core already renders custom_logo on both surfaces; any other mark is painted.
if ($logo_src !== '' && $logo_src !== $core_logo) {
    if ($scene === 'login') {
        $css .= "{$login_mark} { content: url(\"{$logo_src}\"); height: auto; width: auto; "
              . "max-height: 64px; max-width: 100%; }\n";
    } else {
        $css .= "#logo { background-image: url(\"{$logo_src}\") !important; background-repeat: no-repeat !important; "
              . "background-position: left center !important; background-size: contain !important; }\n";
    }
}

if ($logo_src !== '' && $logo_src === $logo_on_dark) {
    $css .= "{$mark_sel} { filter: none; opacity: 1; }\n";
} elseif (!$has_logo && $company_name !== '') {
    $wordmark = str_replace(array('\\', '"'), array('\\\\', '\\"'), $company_name);
    // Printed on a dark surface: the accent's text value, or white without one.
    $ink = ($accent !== '') ? $tok['text'] : $params['ink_light'];
    if ($scene === 'login') {
        $css .= "{$login_mark} { content: \"{$wordmark}\"; font-size: 20px; font-weight: 700; "
              . "letter-spacing: 0.01em; color: {$ink}; filter: none; opacity: 1; }\n";
    } else {
        $css .= "#logo { background-image: none !important; width: auto !important; filter: none; opacity: 1; }\n";
        $css .= "#logo a { display: flex; align-items: center; width: auto; height: 100%; text-decoration: none; }\n";
        $css .= "#logo a::after { content: \"{$wordmark}\"; font-size: 20px; font-weight: 700; "
              . "letter-spacing: 0.01em; white-space: nowrap; color: {$ink}; }\n";
    }
}

/* =========================================================================
 * login scene
 * =======================================================================*/
// login_bg paints the page behind the card. The card and everything on it keep
// Cicada's surfaces, so nothing printed on the card needs a new ink.
if ($scene === 'login' && $login_bg !== '') {
    $css .= "body { background: {$login_bg}; }\n";
}

/* =========================================================================
 * software version visibility (demo / white-label mode)
 * =======================================================================*/
// [branding] show_version = 0 hides Help's version surfaces for EVERY user,
// including the operator (CSS cannot see roles): the sidebar "About ISPConfig"
// section with its Version item, and the version line itself — p.frmTextHead is
// used by help/version.php alone across the whole 3.3.1p1 interface. Both
// surfaces are rendered from CORE templates (sidenav.tpl.htm, help/version.php),
// which is why the selectors are identical to Clarity's.
if (isset($branding['show_version']) && $branding['show_version'] === '0') {
    $css .= "#sidebar li#help_version, #sidebar ul:has(> li#help_version), "
          . "#sidebar header:has(+ ul > li#help_version) { display: none; }\n";
    $css .= "#pageContent p.frmTextHead { display: none; }\n";
}

/* =========================================================================
 * design picker visibility
 * =======================================================================*/
// [branding] show_design_picker = 0 hides the Design drop-down that core
// renders in interface/web/tools/templates/user_settings.htm — the per-user
// picker under Tools > User Settings. That page is what the selector targets,
// and it is identified by its own Save button (data-form-action) because
// #app_theme on its own would also match any OTHER page that renders a Design
// control inside a .form-group: tools/tpl_default.php ("Default Theme
// settings") declares an app_theme SELECT in form/tpl_default.tform.php, and
// hiding the operator's default-setting control was never the intent.
// Hiding is cosmetic and applies to EVERY role including the operator (CSS
// cannot see roles), so it must be switched back on from the Branding page
// before anyone can change design; a crafted request can still write the
// stored theme, which makes this cosmetic and not enforcement.
if (isset($branding['show_design_picker']) && $branding['show_design_picker'] === '0') {
	$css .= "#pageContent:has([data-form-action='tools/user_settings.php']) .form-group:has(#app_theme) { display: none; }\n";
}

/* =========================================================================
 * footer courtesy lines
 * =======================================================================*/
// Stock renders the whole footer as bare text inside <footer id='footer'>, so
// CSS alone could only hide all of it or none of it. The shell generator
// (lib/shell.sh) therefore splits it while building this design's shell: core's
// own line goes into
// .nzc-credit-ispconfig and ours is appended as .nzc-credit-theme, giving each
// toggle a target. If a future ISPConfig moves that markup the spans are absent,
// these rules match nothing, and the credits simply stay visible — the failure
// mode is "attribution shown", which is the right way round.
//
// Both default to ON: only an explicit '0' hides anything. These are courtesy
// lines, NOT licence notices — no toggle here touches a licence, and the licence
// texts ship regardless of what is switched off.
$hide_ispc  = isset($branding['show_ispconfig_credit']) && $branding['show_ispconfig_credit'] === '0';
$hide_theme = isset($branding['show_theme_credit'])     && $branding['show_theme_credit']     === '0';

// The separator lives INSIDE .nzc-credit-theme (the generator nests it there), so
// hiding ours takes the separator with it by containment — no rule needed. The
// case that actually dangles is the opposite one: hide core's line, keep ours,
// and the footer opens with a stray "·". So the separator is hidden alongside
// .nzc-credit-ispconfig, which is also what clarity's reader does.
if ($hide_ispc)  { $css .= ".nzc-credit-ispconfig, .nzc-credit-sep { display: none; }\n"; }
if ($hide_theme) { $css .= ".nzc-credit-theme { display: none; }\n"; }
// With both gone the bar is an empty strip of padding, so remove it entirely
// rather than leave a gap the operator has to wonder about.
if ($hide_ispc && $hide_theme) { $css .= "#footer { display: none; }\n"; }

echo $css;

/* ============================================================
 * Helpers — pure, dependency-free.
 * The shared colour maths and the logo resolver are copies of
 * themes/classic/brand.php's (same names, same bodies), so the designs cannot
 * drift: tests/brand/run.php cross-compares the resolver across every copy.
 * The brand_cic_* functions are Cicada's own — the one place for its steps and
 * targets, then the derivations of the accent and the navigation band. Designs
 * are never loaded in the same request (one active theme, one endpoint), so the
 * shared names cannot collide.
 * ============================================================ */

/**
 * Every step and contrast target of Cicada's derivations, in one place.
 *
 * overlay         --cic-overlay, the lightest surface Cicada prints accent text
 *                 or an accent line on; clearing a ratio there clears it on all
 * surfaces        every surface Cicada paints under text, darkest first
 * text_min        WCAG 2.x AA for text
 * line_min        WCAG 2.x minimum for lines, markers and other UI graphics
 * mark_min        the same minimum, for the active marker on the navigation band
 * deep_step       HSL lightness between Cicada's own #dd630d and #a84c0b
 * ink_light/dark  the two label colours: white, and Cicada's page ground
 * rail_hover/edge/active  HSL lightness steps of the band's states, toward the ink
 * rail_dim        opacity of an inactive label on the band
 * default_accent  Cicada's own orange, used for the marker when no accent is set
 * tap_alpha       opacity of the accent as the mobile tap highlight, as cicada.css has it
 */
function brand_cic_params()
{
    return array(
        'overlay'        => '#383838',
        'surfaces'       => array('#1C1C1C', '#202020', '#222222', '#292929', '#2D2D2D', '#383838'),
        'text_min'       => 4.5,
        'line_min'       => 3.0,
        'mark_min'       => 3.0,
        'deep_step'      => 11,
        'ink_light'      => '#FFFFFF',
        'ink_dark'       => '#1C1C1C',
        'rail_hover'     => 7,
        'rail_edge'      => 10,
        'rail_active'    => 13,
        'rail_dim'       => 0.82,
        'default_accent' => '#dd630d',
        'tap_alpha'      => 0.3,
    );
}

/**
 * The label colour to print on $hex: white or Cicada's page ground, whichever
 * measures the higher contrast. A tie goes to white.
 */
function brand_cic_ink($hex)
{
    $p = brand_cic_params();
    return (brand_contrast($hex, $p['ink_light']) >= brand_contrast($hex, $p['ink_dark']))
        ? $p['ink_light'] : $p['ink_dark'];
}

/**
 * The values Cicada's accent properties take for a stored accent.
 *
 * accent  the stored colour, unchanged: it is the fill, and the fill is the brand
 * text    walked from it to text_min on the overlay (--cic-accent-text)
 * line    walked from it to line_min on the overlay (--cic-accent-line)
 * deep    deep_step lightness points darker (--cic-accent-deep)
 * on      the label to print on the fill (--cic-on-accent)
 */
function brand_cic_accent_tokens($accent)
{
    $p = brand_cic_params();
    list(, , $l) = brand_hex_to_hsl($accent);
    return array(
        'accent' => $accent,
        'text'   => brand_readable($accent, $l, $p['overlay'], $p['text_min']),
        'line'   => brand_readable($accent, $l, $p['overlay'], $p['line_min']),
        'deep'   => brand_shade($accent, $l - $p['deep_step']),
        'on'     => brand_cic_ink($accent),
    );
}

/**
 * The navigation band's values for a stored rail colour.
 *
 * ink     the label on the band
 * dim     an inactive label: the ink at rail_dim opacity
 * hover / edge / active
 *         the band moved rail_hover, rail_edge and rail_active lightness points
 *         toward the ink, the same geometry classic uses
 * mark    the active marker: the accent (Cicada's own when none is stored)
 *         walked to mark_min against the band
 */
function brand_cic_rail_tokens($rail, $accent)
{
    $p = brand_cic_params();
    $ink = brand_cic_ink($rail);
    list(, , $rail_l) = brand_hex_to_hsl($rail);
    $toward = ($ink === $p['ink_light']) ? 1 : -1;
    $from = ($accent !== '') ? $accent : $p['default_accent'];
    list(, , $from_l) = brand_hex_to_hsl($from);
    return array(
        'ink'    => $ink,
        'dim'    => brand_rgba($ink, $p['rail_dim']),
        'hover'  => brand_shade($rail, $rail_l + $toward * $p['rail_hover']),
        'edge'   => brand_shade($rail, $rail_l + $toward * $p['rail_edge']),
        'active' => brand_shade($rail, $rail_l + $toward * $p['rail_active']),
        'mark'   => brand_readable($from, $from_l, $rail, $p['mark_min']),
    );
}

/**
 * Parse the whole sys_ini config blob with ISPConfig's own INI reader, so this
 * reader can never drift from the customizer's writer (which serialises with the
 * same framework class). ini_parser.inc.php is a pure, dependency-free class —
 * safe to require on this pre-auth path. Falls back to '' if it's ever missing.
 *
 * stripslashes() before parsing is mandatory, not a nicety: the write path
 * escapes the field on its way in (tform_base::_encode() runs db->quote() over
 * every value before the blob is serialised) and ini_parser unescapes nothing,
 * so core ALWAYS unquotes on read — app.inc.php:108, getconf.inc.php:54,
 * server_config_edit.php:190. Using getconf::get_global_config() instead would
 * be worse than doing it here: it applies the same stripslashes with no
 * counterpart on write, and it drags the app bootstrap onto a pre-auth route.
 */
function brand_parse_config($config)
{
    // Same reasoning as the config path resolved at top level: a bare __DIR__
    // walk lands in the git clone on a symlink install, so prefer the path the
    // web server actually requested. $config_inc is NOT usable here — this is a
    // function, and PHP has no implicit access to the enclosing file's scope;
    // referencing it emitted "Undefined variable" warnings straight into this
    // endpoint's CSS response, which corrupted the stylesheet.
    $parser_file = '';
    $_pf = array();
    if (!empty($_SERVER['SCRIPT_FILENAME'])) {
        $_pf[] = dirname($_SERVER['SCRIPT_FILENAME']) . '/../../../lib/classes/ini_parser.inc.php';
    }
    $_pf[] = __DIR__ . '/../../../lib/classes/ini_parser.inc.php';
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $_pf[] = $_SERVER['DOCUMENT_ROOT'] . '/../lib/classes/ini_parser.inc.php';
    }
    foreach ($_pf as $_c) {
        if (is_readable($_c)) { $parser_file = $_c; break; }
    }
    if (is_readable($parser_file)) {
        require_once $parser_file;
        if (class_exists('ini_parser')) {
            $p   = new ini_parser();
            $out = $p->parse_ini_string(stripslashes($config));
            return is_array($out) ? $out : array();
        }
    }
    return array();
}

/**
 * Resolve ONE logo variant to the value this sheet may emit, or '' when the
 * variant is unset or holds something we will not print.
 *
 * $ref  the by-reference slot   (logo_url / logo_url_on_dark)
 * $data the uploaded data URI   (sys_ini.custom_logo / [branding] logo_on_dark)
 *
 * The reference wins, which is the precedence logo_url has always had over
 * custom_logo — unchanged, now simply applied to each variant in turn.
 *
 * Byte-identical to themes/clarity/brand.php's copy, and to the two patterns in
 * the customizer's lib/preview.inc.php. The duplication is deliberate: this is a
 * pre-authentication endpoint that must keep working with the customizer
 * uninstalled, so it cannot include the module's code, and the two designs are
 * never loaded in the same request. All copies must be changed together.
 *
 * (?!/) rejects protocol-relative "//host/..." — a REMOTE url in disguise, which
 * would defeat the local-path privacy contract; writer-side validation matches.
 *
 * The D modifier is load-bearing, not decoration: without it PCRE's `$` also
 * matches BEFORE a final newline, so "/img/logo.png\n" would validate and the
 * raw LF would be emitted inside url("...") — a literal newline terminates a
 * double-quoted CSS string, breaking this sheet for every visitor including
 * pre-auth on the login screen. The writer-side validator in the module's tform
 * carries /D for the same reason.
 */
function brand_logo_variant($ref, $data)
{
    if (is_string($ref) && $ref !== ''
        && preg_match('#^(https://[^\s"\'<>()\\\\]+|/(?!/)[^\s"\'<>()\\\\]+)$#D', $ref)) {
        return $ref;
    }
    if (is_string($data) && $data !== ''
        && preg_match('#^data:image/[a-z0-9.+-]+;base64,[A-Za-z0-9+/=]+$#iD', $data)) {
        return $data;
    }
    return '';
}

/**
 * Which logo VARIANT one surface should ask for: 'on_light' (the mark drawn FOR
 * light backgrounds) or 'on_dark' (the mark drawn FOR dark ones).
 *
 * $stored   the operator's [branding] logo_variant_nav / logo_variant_login
 *           value, already read by the caller.
 * $bg_hex   the colour that slot's background actually ends up, or '' when the
 *           design cannot know it. Caller-supplied on purpose: which colour a
 *           logo sits on is a property of the DESIGN, not of this feature.
 *           Clarity passes rail_hex / login_bg; classic passes '' for both.
 * $default  the variant to use when nothing else decides.
 *
 * The operator's explicit choice is checked FIRST and is absolute. Automatic is
 * a convenience, not a policy: someone who has looked at their own panel and
 * disagreed with the guess has to win, or the feature is just a second
 * hardcoded assumption wearing a select box.
 *
 * Anything that is not one of the two literals means automatic, which folds
 * three cases into one test: the key absent (never saved), the key present and
 * EMPTY (saved once while on automatic — the writer emits the line
 * unconditionally, so ini_parser::parse_ini_string returns '' rather than
 * nothing, and absent/empty must behave alike), and a value hand-edited into
 * the blob that this build does not recognise.
 *
 * The result is only ever compared with ===; it is never interpolated into CSS,
 * which is what keeps this field out of the escaping contract that governs
 * every colour and URL in this file.
 *
 * Byte-identical to themes/clarity/brand.php's copy and to the customizer's
 * lib/preview.inc.php, for the same reason brand_logo_variant() is: a pre-auth
 * reader cannot include the module's code, so the copies are duplicated and
 * must be changed together. tests/brand/run.php diffs the three copies'
 * decisions over a shared grid on every push, so that claim is executed rather
 * than left to an eye.
 *
 * It has not always been true. This copy took ($surface, $branding, …) and
 * composed the key itself while the docblock above said it was byte-identical to
 * clarity's ($stored, …) — so a maintainer who believed the instruction and
 * pasted clarity's body in would have handed $stored the string 'nav', which
 * never matches either literal, and every explicit preference on this design
 * would have fallen through to the default in silence. Taking the value rather
 * than the key is also what puts both key names LITERALLY at the call site,
 * which is what CI's brand-token contract check needs in order to catch a
 * rename: a composed lookup is invisible to grep.
 */
function brand_logo_variant_pref($stored, $bg_hex, $default)
{
    if ($stored === 'on_light' || $stored === 'on_dark') {
        return $stored;
    }
    if (is_string($bg_hex) && preg_match('/^#[0-9A-Fa-f]{6}$/D', $bg_hex) === 1) {
        return brand_is_dark($bg_hex) ? 'on_dark' : 'on_light';
    }
    return $default;
}

/**
 * Is $hex a dark background — i.e. does the mark drawn for DARK backgrounds
 * belong on it?
 *
 * WCAG 2.x relative luminance, not HSL lightness. Lightness is a hue-blind
 * midpoint: #0000FF and #FFFF00 both sit at lightness 50 while the yellow is
 * about thirteen times as bright, so choosing artwork by lightness would put
 * the white mark on the yellow. Each channel is normalised to 0..1,
 * gamma-expanded out of sRGB, then weighted 0.2126 / 0.7152 / 0.0722.
 *
 * The 0.5 threshold is deliberately NOT the 0.184 pivot brand_readable() uses.
 * That one picks INK for a surface and has to respect a contrast ratio; this
 * one picks between two finished artworks whose internal contrast is the
 * operator's business, so the question is the plain one — is this background
 * more dark than light.
 *
 * A malformed or empty hex returns FALSE (treat it as light) instead of
 * warning. This is a pre-auth endpoint that emits text/css: a PHP notice would
 * be printed INTO the stylesheet and corrupt every rule after it. Callers
 * validate before calling, so the guard is defence in depth, and "light" is the
 * safer guess — the light-background mark is the one every panel has, because
 * it is the variant core itself stores in sys_ini.custom_logo.
 *
 * Byte-identical to themes/clarity/brand.php's copy and to the customizer's
 * lib/preview.inc.php; all copies change together, and tests/brand/run.php
 * diffs their decisions on every push.
 *
 * The sRGB arithmetic is called, not repeated. This function used to carry its
 * own copy of the transfer function — the same loop, the same weights, the same
 * knee — eighty lines from brand_luminance(), which already had it and which
 * brand_readable() already used. Two copies in ONE file is the drift that has
 * nothing to catch it: a contrast fix applied to the gamma curve in one leaves
 * the other behind, and the two then disagree about the same colour on the same
 * page. Cross-FILE parity never required that; it requires the guard, the
 * delegation and the 0.5 constant to match, which they do.
 */
function brand_is_dark($hex)
{
    if (!is_string($hex) || preg_match('/^#[0-9A-Fa-f]{6}$/D', $hex) !== 1) {
        return false;
    }
    return (brand_luminance($hex) < 0.5);
}

/**
 * A validated #rrggbb from the contract, or ''.
 *
 * /D is not decoration and is not optional. tform_base appends only "s" to a
 * validator, and PCRE's `$` matches immediately before a final newline as well
 * as at the true end of the subject — so without /D a stored "#FFFFFF\n" passes
 * here and the raw LF is emitted into a text/css response, corrupting the
 * declaration it lands in, on an endpoint that answers with no session.
 * themes/clarity/brand.php's copy of this function has always carried it, so
 * this one was the only place in the project where two readers disagreed about
 * the same stored value. Every anchored pattern in this project carries /D;
 * tests/brand/probe_classic.php and probe_clarity.php now hold both copies to it.
 */
function brand_hex($branding, $key)
{
    if (isset($branding[$key]) && preg_match('/^#[0-9A-Fa-f]{6}$/D', $branding[$key])) {
        return $branding[$key];
    }
    return '';
}

/** Keep a hex colour's hue + saturation, set its lightness to $l (0-100). Returns #rrggbb. */
function brand_shade($hex, $l)
{
    list($h, $s, ) = brand_hex_to_hsl($hex);
    return brand_hsl_to_hex($h, $s, max(0.0, min(100.0, (float)$l)));
}

/**
 * A shade of $hex, starting at lightness $l and walking AWAY from $bg until the
 * pair clears $min contrast (WCAG 2.x ratio: 4.5 for body text, 3.0 for a
 * non-text indicator such as a border or an underline).
 *
 * This is what makes an arbitrary brand colour safe on stock's light surfaces.
 * Stock's lightness ladder was chosen for a blue at hue ~208; the same rung in
 * yellow or lime is far brighter, and hue-swapping alone would produce text
 * nobody can read. Walking in 2-point steps keeps the result as close to the
 * operator's chosen colour as the ratio allows. Black on white is ~21:1, so the
 * walk always terminates well before the clamp.
 */
function brand_readable($hex, $l, $bg, $min)
{
    //* Walk toward whichever end of the ramp actually reads better on $bg,
    //* measured rather than pivoted: the crossover is at luminance 0.1791, and a
    //* rounded 0.184 pivot sends colours in between — #767676 is one — walking
    //* the wrong way, away from contrast instead of toward it.
    $dir  = (brand_contrast('#FFFFFF', $bg) > brand_contrast('#000000', $bg)) ? 1 : -1;
    $last = brand_shade($hex, $l);
    for ($i = 0; $i <= 50; $i++) {
        $try_l = $l + ($dir * 2 * $i);
        $last  = brand_shade($hex, $try_l);
        if (brand_contrast($last, $bg) >= $min) {
            return $last;
        }
        //* Stop at the end of the ramp IN THE DIRECTION OF TRAVEL. Testing both
        //* ends would end the walk on its first step whenever $l starts at a
        //* boundary, returning the colour it was asked to walk away from.
        if (($dir < 0 && $try_l <= 0) || ($dir > 0 && $try_l >= 100)) {
            break;
        }
    }
    return $last;
}

/** WCAG 2.x contrast ratio between two hex colours (1.0 .. 21.0). */
function brand_contrast($a, $b)
{
    $la = brand_luminance($a);
    $lb = brand_luminance($b);
    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

/** WCAG relative luminance of a hex colour (0.0 .. 1.0). */
function brand_luminance($hex)
{
    $c = ltrim($hex, '#');
    $w = array(0.2126, 0.7152, 0.0722);
    $y = 0.0;
    for ($i = 0; $i < 3; $i++) {
        $v = hexdec(substr($c, $i * 2, 2)) / 255;
        $v = ($v <= 0.03928) ? ($v / 12.92) : pow((($v + 0.055) / 1.055), 2.4);
        $y += $w[$i] * $v;
    }
    return $y;
}

/** "rgba(r, g, b, a)" from a hex colour. */
function brand_rgba($hex, $alpha)
{
    $c = ltrim($hex, '#');
    $r = hexdec(substr($c, 0, 2));
    $g = hexdec(substr($c, 2, 2));
    $b = hexdec(substr($c, 4, 2));
    return sprintf('rgba(%d, %d, %d, %s)', $r, $g, $b, rtrim(rtrim(sprintf('%.3f', $alpha), '0'), '.'));
}

/** #rrggbb -> array(h[0-360], s[0-100], l[0-100]). */
function brand_hex_to_hsl($hex)
{
    $c = ltrim($hex, '#');
    $r = hexdec(substr($c, 0, 2)) / 255;
    $g = hexdec(substr($c, 2, 2)) / 255;
    $b = hexdec(substr($c, 4, 2)) / 255;
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $l = ($max + $min) / 2;
    $d = $max - $min;
    if ($d == 0) {
        return array(0, 0, $l * 100);
    }
    $s = $d / (1 - abs(2 * $l - 1));
    if ($max == $r) {
        $h = 60 * fmod((($g - $b) / $d), 6);
    } elseif ($max == $g) {
        $h = 60 * ((($b - $r) / $d) + 2);
    } else {
        $h = 60 * ((($r - $g) / $d) + 4);
    }
    if ($h < 0) {
        $h += 360;
    }
    return array($h, $s * 100, $l * 100);
}

/** h[0-360], s[0-100], l[0-100] -> #rrggbb. */
function brand_hsl_to_hex($h, $s, $l)
{
    $s /= 100;
    $l /= 100;
    $c = (1 - abs(2 * $l - 1)) * $s;
    $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
    $m = $l - $c / 2;
    if ($h < 60)       { $rp = $c; $gp = $x; $bp = 0; }
    elseif ($h < 120)  { $rp = $x; $gp = $c; $bp = 0; }
    elseif ($h < 180)  { $rp = 0; $gp = $c; $bp = $x; }
    elseif ($h < 240)  { $rp = 0; $gp = $x; $bp = $c; }
    elseif ($h < 300)  { $rp = $x; $gp = 0; $bp = $c; }
    else               { $rp = $c; $gp = 0; $bp = $x; }
    return sprintf('#%02X%02X%02X',
        (int)round(($rp + $m) * 255),
        (int)round(($gp + $m) * 255),
        (int)round(($bp + $m) * 255));
}

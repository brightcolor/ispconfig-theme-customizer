<?php
/**
 * themes/cicada/brand.php — the pure helpers and Cicada's colour derivations.
 *
 * The shared helpers are copies of classic's, so the checks probe_classic.php
 * runs against classic's copy run here against Cicada's. The derivations are
 * Cicada's own: an operator's accent and rail become the values Cicada's custom
 * properties and navigation band take, and each has to stay readable on the
 * dark surfaces Cicada paints under it, whatever colour was stored.
 *
 * Contrast is measured with the harness's own WCAG maths (h_contrast), never
 * with the reader's, so a fault in the reader's maths cannot pass its own test.
 *
 * Run through tests/brand/run.php, which gives this file a process of its own.
 */

require_once __DIR__ . '/harness.php';
$cicada_src = __DIR__ . '/../../themes/cicada/brand.php';
load_helpers($cicada_src);

/* ---- the contract keys appear in code, not only in prose ----------------- */
$code = '';
foreach (token_get_all(file_get_contents($cicada_src)) as $tok) {
    if (is_array($tok)) {
        if ($tok[0] === T_COMMENT || $tok[0] === T_DOC_COMMENT) continue;
        $code .= $tok[1];
        continue;
    }
    $code .= $tok;
}
foreach (array('logo_variant_nav', 'logo_variant_login', 'rail_hex_light') as $key) {
    t_ok("cicada reads '$key' in code, not only in a comment", strpos($code, $key) !== false);
}

/* ---- rail_hex_light is read and goes nowhere ----------------------------- */
$uses = 0;
foreach (token_get_all(file_get_contents($cicada_src)) as $tok) {
    if (is_array($tok) && $tok[0] === T_VARIABLE && $tok[1] === '$rail_light_noop') $uses++;
}
t_eq('the light-rail read is assigned and discarded, and used nowhere else', $uses, 2);

/* ---- the shared helpers behave as classic's do --------------------------- */
foreach (h_rails() as $hex) {
    t_ok("luminance agrees with the spec for $hex", abs(brand_luminance($hex) - h_lum($hex)) < 1e-9);
    t_eq("brand_is_dark is brand_luminance < 0.5 for $hex", brand_is_dark($hex), brand_luminance($hex) < 0.5);
}
t_eq('a valid hex is returned as stored', brand_hex(array('accent_hex' => '#DD630D'), 'accent_hex'), '#DD630D');
t_eq('lower case is returned as stored', brand_hex(array('accent_hex' => '#dd630d'), 'accent_hex'), '#dd630d');
t_eq('an absent key is empty', brand_hex(array(), 'accent_hex'), '');
t_eq('a missing hash is refused', brand_hex(array('accent_hex' => 'dd630d'), 'accent_hex'), '');
t_eq('a short value is refused', brand_hex(array('accent_hex' => '#dd630'), 'accent_hex'), '');
t_eq('a named colour is refused', brand_hex(array('accent_hex' => 'orange'), 'accent_hex'), '');
t_eq('a TRAILING newline is refused', brand_hex(array('accent_hex' => "#dd630d\n"), 'accent_hex'), '');
t_ok('black on white is ~21:1', abs(brand_contrast('#000000', '#FFFFFF') - 21.0) < 0.05);
t_ok('brand_readable walks to the ratio it was asked for',
    h_contrast(brand_readable('#FFD700', 50, '#FFFFFF', 4.5), '#FFFFFF') >= 4.5);

/* ---- the one place for every step and target ----------------------------- */
t_eq('params are the documented values', brand_cic_params(), array(
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
));

/* ---- accent: text, line, deep and label for any stored colour ------------ */
$params = brand_cic_params();
foreach (h_accents() as $hex) {
    $t = brand_cic_accent_tokens($hex);
    t_eq("$hex: the accent itself is the stored colour", $t['accent'], $hex);

    $worst = 99.0;
    foreach ($params['surfaces'] as $bg) {
        $worst = min($worst, h_contrast($t['text'], $bg));
    }
    t_ok(sprintf('%s: text reaches 4.5:1 on every surface (worst %.2f)', $hex, $worst), $worst >= 4.5, $t['text']);

    list(, , $l) = brand_hex_to_hsl($hex);
    $start = brand_shade($hex, $l);
    if (h_contrast($start, '#383838') >= 4.5) {
        t_eq("$hex: an accent that already reads is kept as text", $t['text'], $start);
    } else {
        list(, , $lt) = brand_hex_to_hsl($t['text']);
        $prev = h_contrast(brand_shade($hex, $lt - 2), '#383838');
        t_ok(sprintf('%s: text is the first readable step (one step darker: %.2f)', $hex, $prev), $prev < 4.5, $t['text']);
    }

    $c = h_contrast($t['line'], '#383838');
    t_ok(sprintf('%s: line reaches 3:1 on #383838 (%.2f)', $hex, $c), $c >= 3.0, $t['line']);

    list(, , $ld) = brand_hex_to_hsl($t['deep']);
    t_ok(sprintf('%s: deep is 11 points darker (L %.1f from %.1f)', $hex, $ld, $l),
        abs($ld - max(0.0, $l - 11)) <= 0.5, $t['deep']);

    $other = ($t['on'] === '#FFFFFF') ? '#1C1C1C' : '#FFFFFF';
    t_ok("$hex: the label is the better of the two inks",
        in_array($t['on'], array('#FFFFFF', '#1C1C1C'), true) && h_contrast($t['on'], $hex) >= h_contrast($other, $hex),
        $t['on']);
}
$own = brand_cic_accent_tokens('#dd630d');
t_eq("#dd630d keeps Cicada's dark label", $own['on'], '#1C1C1C');

/* ---- rail: ink, its dimmed step and the active marker -------------------- */
foreach (h_rails() as $rail) {
    foreach (array('' => 'no accent', '#0065AB' => 'accent #0065AB') as $accent => $what) {
        $r = brand_cic_rail_tokens($rail, $accent);
        $other = ($r['ink'] === '#FFFFFF') ? '#1C1C1C' : '#FFFFFF';
        t_ok("rail $rail, $what: the ink is the better of the two",
            h_contrast($r['ink'], $rail) >= h_contrast($other, $rail), $r['ink']);
        $c = h_contrast($r['mark'], $rail);
        t_ok(sprintf('rail %s, %s: the marker reaches 3:1 on the rail (%.2f)', $rail, $what, $c), $c >= 3.0, $r['mark']);
    }
}
foreach (array('#01243D', '#000000', '#FFFFFF', '#0065AB', '#F2F5F7') as $rail) {
    $r = brand_cic_rail_tokens($rail, '');
    $c = h_contrast(h_flatten($r['dim'], $rail), $rail);
    t_ok(sprintf('rail %s: the dimmed label reaches 4.5:1 once flattened (%.2f)', $rail, $c), $c >= 4.5, $r['dim']);
}

/* ---- the resolver decides like every other copy --------------------------- */
$matrix = array();
foreach (h_variant_matrix() as $row) {
    $matrix[] = brand_logo_variant_pref($row[0], $row[1], $row[2]);
}
echo 'MATRIX ' . json_encode($matrix) . "\n";

t_done();

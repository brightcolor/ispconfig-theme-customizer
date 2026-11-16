<?php
/**
 * themes/cicada/assets/stylesheets/cicada.css — what the brand reader relies on.
 *
 * brand.php recolours Cicada by reassigning its custom properties, so the
 * stylesheet has to draw each accent through the right one: a fill through
 * --cic-accent, a line or glyph through --cic-accent-line. A border left on
 * --cic-accent would vanish on the dark ground the moment an operator stores a
 * dark brand colour, and nothing else would notice.
 *
 * The login rules are here for the same reason: brand.php lifts the silhouette
 * filter from a dark-background mark with the very selector these rules use.
 *
 * Parsed as text. Run through tests/brand/run.php.
 */

require_once __DIR__ . '/harness.php';

$path = __DIR__ . '/../../themes/cicada/assets/stylesheets/cicada.css';
$raw  = file_get_contents($path);
if ($raw === false) {
    fwrite(STDERR, "cannot read $path\n");
    exit(2);
}

// Comments name properties in prose; only declarations count.
$css = preg_replace('#/\*.*?\*/#s', '', $raw);

/**
 * selector => concatenated declarations of every rule whose selector list
 * names it. Whitespace inside a selector is collapsed so a line break in a
 * grouped list does not hide it.
 */
function css_rules($css) {
    $rules = array();
    foreach (explode('}', $css) as $chunk) {
        $at = strpos($chunk, '{');
        if ($at === false) continue;
        $decls = substr($chunk, $at + 1);
        foreach (explode(',', substr($chunk, 0, $at)) as $sel) {
            $sel = trim(preg_replace('/\s+/', ' ', $sel));
            if ($sel === '') continue;
            $rules[$sel] = (isset($rules[$sel]) ? $rules[$sel] : '') . $decls;
        }
    }
    return $rules;
}

$rules = css_rules($css);

function rule_has($rules, $sel, $needle) {
    return isset($rules[$sel]) && strpos(preg_replace('/\s+/', ' ', $rules[$sel]), $needle) !== false;
}

t_ok(':root defines --cic-accent-line as var(--cic-accent)',
    preg_match('/--cic-accent-line:\s*var\(--cic-accent\);/', isset($rules[':root']) ? $rules[':root'] : '') === 1);

t_eq('var(--cic-accent-line) is drawn 8 times', substr_count($css, 'var(--cic-accent-line)'), 8);
t_eq('var(--cic-accent) remains 17 times', substr_count($css, 'var(--cic-accent)'), 17);

$lines = array(
    '#main-navigation a.active'                                          => 'border-bottom-color: var(--cic-accent-line)',
    '.modules .bi-icon'                                                  => 'color: var(--cic-accent-line)',
    '.modules .button.active'                                            => 'border-bottom-color: var(--cic-accent-line)',
    'input[type="color"]:focus'                                          => 'border-color: var(--cic-accent-line)',
    '.formbutton-default.active'                                         => 'border-bottom-color: var(--cic-accent-line)',
    '.select2-container-multi.select2-container-active .select2-choices' => 'border-color: var(--cic-accent-line)',
    '.select2-dropdown-open.select2-drop-above .select2-choices'         => 'border-color: var(--cic-accent-line)',
    '.datetimepicker table tr td.today:hover.active'                    => 'border-color: var(--cic-accent-line)',
);
foreach ($lines as $sel => $decl) {
    t_ok("$sel draws its accent through --cic-accent-line", rule_has($rules, $sel, $decl),
        isset($rules[$sel]) ? trim(preg_replace('/\s+/', ' ', $rules[$sel])) : 'no such rule');
}

$head = '.panel-heading[style*="linear-gradient"]';
t_ok('the login card header drops the inline gradient',
    rule_has($rules, $head, 'background-image: none !important'));
t_ok('the login mark is a silhouette by default',
    rule_has($rules, $head . ' > img', 'filter: brightness(0) invert(1)')
    && rule_has($rules, $head . ' > img', 'opacity: .82'));
t_ok('#logo keeps its silhouette', rule_has($rules, '#logo', 'filter: brightness(0) invert(1)'));


/* ---- every label sits on the fill its token is derived for -----------------
 * brand.php derives --cic-on-accent from the stored accent. A rule that prints
 * that label on another fill (the lighter hover fill, a status colour) inherits
 * a label chosen for a different background: white on a light hover fill under
 * a dark accent. Each fill family therefore has its own label token.
 */
function root_value($rules, $name) {
    return preg_match('/' . preg_quote($name, '/') . ':\s*([^;]+);/', isset($rules[':root']) ? $rules[':root'] : '', $m)
        ? trim($m[1]) : '';
}
t_eq(':root defines --cic-on-accent-text', root_value($rules, '--cic-on-accent-text'), '#1c1c1c');
t_eq(':root defines --cic-on-status', root_value($rules, '--cic-on-status'), '#1c1c1c');

$fills = array(
    'var(--cic-accent)'      => 'var(--cic-on-accent)',
    'var(--cic-accent-text)' => 'var(--cic-on-accent-text)',
    'var(--cic-ok)'          => 'var(--cic-on-status)',
    'var(--cic-ok-text)'     => 'var(--cic-on-status)',
    'var(--cic-warn)'        => 'var(--cic-on-status)',
    'var(--cic-warn-lift)'   => 'var(--cic-on-status)',
);
foreach (explode('}', $css) as $chunk) {
    $at = strpos($chunk, '{');
    if ($at === false) continue;
    $decl = preg_replace('/\s+/', ' ', substr($chunk, $at + 1));
    if (!preg_match('/color: (var\(--cic-on-[a-z-]+\))/', $decl, $label)) continue;
    if (!preg_match('/background(?:-color)?: ([^;]+);/', $decl, $fill)) continue;
    $fill = trim($fill[1]);
    if (!isset($fills[$fill])) continue;
    $sel = trim(preg_replace('/\s+/', ' ', substr($chunk, 0, $at)));
    t_eq("$sel labels its $fill fill with " . $fills[$fill], $label[1], $fills[$fill]);
}

//* The status fills are fixed, so their dark label is checked once here.
foreach (array('--cic-ok', '--cic-ok-text', '--cic-warn', '--cic-warn-lift') as $fill) {
    $hex = root_value($rules, $fill);
    $c = preg_match('/^#[0-9a-fA-F]{6}$/', $hex) ? h_contrast($hex, '#1C1C1C') : 0;
    t_ok(sprintf('the status label reads on %s (%.2f:1)', $fill, $c), $c >= 4.5, $hex);
}

t_done();

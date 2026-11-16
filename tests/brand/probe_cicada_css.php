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

t_done();

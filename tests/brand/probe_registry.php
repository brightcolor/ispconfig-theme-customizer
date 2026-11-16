<?php
/**
 * One set of designs, named in three places that have to agree: the installer's
 * table (lib/designs.sh), the module's registry (customizer_design_chrome()) and
 * the brand readers under themes/. A design missing from the registry installs
 * fine and is then never previewed; one missing from the table is previewed and
 * cannot be installed. Neither fails anywhere else.
 *
 * Run through tests/brand/run.php.
 */

require_once __DIR__ . '/harness.php';
require_once __DIR__ . '/../../interface/web/customizer/lib/preview.inc.php';

$sh    = (string)@file_get_contents(__DIR__ . '/../../lib/designs.sh');
$table = preg_match('/^ALL_DESIGNS="([^"]*)"$/m', $sh, $m) ? preg_split('/\s+/', trim($m[1])) : array();
t_ok('lib/designs.sh names its designs', count($table) > 0);

t_ok('customizer_design_chrome() exists', function_exists('customizer_design_chrome'));
$registry = function_exists('customizer_design_chrome') ? array_keys(customizer_design_chrome()) : array();

$readers = array();
foreach ((array)glob(__DIR__ . '/../../themes/*/brand.php') as $file) {
    $readers[] = basename(dirname($file));
}

function h_sorted($list) {
    sort($list);
    return $list;
}

t_eq("the module's registry names the installer's designs", h_sorted($registry), h_sorted($table));
t_eq('every design the installer ships has a brand reader', h_sorted($readers), h_sorted($table));

/* ---- bin/reset_app_theme.php: the fourth list, read from the table --------
 * A bare manual run resets "every design this project ships". It reads that
 * from lib/designs.sh, and it leaves out a design whose directory another
 * installer put under themes/ (no brand.php): those users are not this
 * project's to reset. The helper names the designs before it touches the
 * database, which is what lets this check run without one.
 */
function h_reset_designs($root) {
    $php = PHP_BINARY !== '' ? PHP_BINARY : 'php';
    $out = array();
    exec(escapeshellarg($php) . ' ' . escapeshellarg(__DIR__ . '/../../bin/reset_app_theme.php') . ' '
        . escapeshellarg($root . '/interface/lib/config.inc.php') . ' 2>&1', $out);
    foreach ($out as $line) {
        if (preg_match('/^\s*designs: (.*)$/', $line, $m)) return trim($m[1]);
    }
    return 'no designs line in: ' . implode(' | ', $out);
}
$panel = sys_get_temp_dir() . '/resetprobe_' . getmypid();
@mkdir($panel . '/interface/lib', 0700, true);
@mkdir($panel . '/interface/web/themes', 0700, true);
//* A database nobody listens on: the helper names its designs, then stops.
file_put_contents($panel . '/interface/lib/config.inc.php',
    "<?php\n\$conf = array('db_host' => '127.0.0.1', 'db_port' => 1, 'db_user' => 'x', 'db_password' => 'x', 'db_database' => 'x');\n");
t_eq('a bare reset names every design in lib/designs.sh', h_reset_designs($panel), implode(' ', $table));
@mkdir($panel . '/interface/web/themes/cicada', 0700, true);
t_eq("a bare reset leaves out a design another installer put there",
    h_reset_designs($panel), implode(' ', array_values(array_diff($table, array('cicada')))));
@touch($panel . '/interface/web/themes/cicada/brand.php');
t_eq("this project's own design is reset again", h_reset_designs($panel), implode(' ', $table));
@unlink($panel . '/interface/web/themes/cicada/brand.php');
@rmdir($panel . '/interface/web/themes/cicada');
@unlink($panel . '/interface/lib/config.inc.php');
@rmdir($panel . '/interface/web/themes');
@rmdir($panel . '/interface/web');
@rmdir($panel . '/interface/lib');
@rmdir($panel . '/interface');
@rmdir($panel);

t_done();

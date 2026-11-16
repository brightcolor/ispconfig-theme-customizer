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

t_done();

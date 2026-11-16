<?php
/**
 * ispconfig-theme-customizer — reset users' theme choice back to 'default'.
 * Copyright (c) 2026 Wade Beckett. MIT License — see ../LICENSE.
 *
 * After a design directory is removed, ISPConfig does NOT heal the
 * sys_user.app_theme column: affected users get a "chosen theme is not
 * compatible" error banner at EVERY login (core only falls back at session
 * level). This flips every row pointing at one of the named designs back to
 * 'default'. Idempotent; run by uninstall.sh; safe to re-run.
 *
 * Usage: php reset_app_theme.php [config.inc.php] [design ...]
 *
 *   config.inc.php  defaults to /usr/local/ispconfig/interface/lib/config.inc.php
 *   design ...      defaults to EVERY design this project ships, as listed in
 *                   lib/designs.sh, minus any design whose directory another
 *                   installer put on the panel. uninstall.sh always passes the
 *                   ones it actually removed; a bare manual run means "get users
 *                   off this project's designs", which is the only thing a bare
 *                   run can sensibly mean.
 *
 * The designs it resets are named before the database is touched.
 */

/**
 * The designs a bare run resets: every name in lib/designs.sh, the table the
 * installer reads, except a design whose directory under the panel's themes/
 * holds no brand.php. Every design this project installs carries brand.php; a
 * directory without one came from another installer (for cicada, typically
 * the standalone ispconfig-cicada), and its users are not this project's to
 * reset. Returns null when the table cannot be read.
 */
function reset_default_designs($designs_sh, $themes_dir) {
    $src = @file_get_contents($designs_sh);
    if($src === false || !preg_match('/^ALL_DESIGNS="([^"]*)"$/m', $src, $m)) return null;
    $out = array();
    foreach(preg_split('/\s+/', trim($m[1])) as $design) {
        if($design === '') continue;
        $dir = $themes_dir . '/' . $design;
        if(is_dir($dir) && !is_file($dir . '/brand.php')) continue;
        $out[] = $design;
    }
    return $out;
}

$args      = array_slice($argv, 1);
$conf_path = (isset($args[0]) && $args[0] !== '') ? $args[0] : '/usr/local/ispconfig/interface/lib/config.inc.php';
$themes    = array_slice($args, 1);
if(!$themes) {
    $designs_sh = __DIR__ . '/../lib/designs.sh';
    $themes = reset_default_designs($designs_sh, dirname(dirname($conf_path)) . '/web/themes');
    if($themes === null) {
        fwrite(STDERR, "ERROR: cannot read the design list in $designs_sh.\n"
            . "       Name the designs to reset after the config path, e.g.\n"
            . "       php reset_app_theme.php $conf_path cicada\n");
        exit(1);
    }
    if(!$themes) {
        echo "  no design of this project is on the panel, so no user is reset\n";
        exit(0);
    }
}

//* A theme name reaches a query, so it is validated as a name and not merely
//* escaped — and 'default' is refused outright: accepting it would let a typo
//* rewrite every row in the table to the value it already has, masking the
//* mistake instead of reporting it.
foreach($themes as $t) {
    if(!preg_match('/^[a-z0-9_-]+$/', $t)) {
        fwrite(STDERR, "ERROR: not a valid theme name: $t\n");
        exit(1);
    }
    if($t === 'default') {
        fwrite(STDERR, "ERROR: refusing to 'reset' the default theme\n");
        exit(1);
    }
}

echo "  designs: " . implode(' ', $themes) . "\n";

if(!is_readable($conf_path)) {
    fwrite(STDERR, "ERROR: ISPConfig config not readable: $conf_path\n");
    exit(1);
}
require $conf_path;
if(!isset($conf) || !is_array($conf) || empty($conf['db_host'])) {
    fwrite(STDERR, "ERROR: no database configuration found in $conf_path\n");
    exit(1);
}

if(function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

$port = isset($conf['db_port']) ? (int)$conf['db_port'] : 3306;
try {
    $m = @new mysqli($conf['db_host'], $conf['db_user'], $conf['db_password'], $conf['db_database'], $port);
} catch(\Throwable $e) {
    $m = false;
}
if(!$m || $m->connect_errno) {
    fwrite(STDERR, "ERROR: database connection failed" . ($m ? ": " . $m->connect_error : "") . "\n");
    exit(1);
}

//* MYSQLI_REPORT_OFF means prepare() returns false instead of throwing —
//* guard it or the next line fatals mid-uninstall with no useful message
$stmt = $m->prepare("UPDATE sys_user SET app_theme = 'default' WHERE app_theme = ?");
if(!$stmt) {
    fwrite(STDERR, "ERROR: prepare failed: " . $m->error . "\n");
    exit(1);
}

//* One execute per design rather than one IN(...) list: the statement is
//* prepared once, the per-design counts are what the operator needs to read,
//* and a failure names the design it happened on.
$total = 0;
foreach($themes as $t) {
    $stmt->bind_param('s', $t);
    if(!$stmt->execute()) {
        fwrite(STDERR, "ERROR: update failed for '$t': " . $stmt->error . "\n");
        $stmt->close();
        $m->close();
        exit(1);
    }
    $n = $stmt->affected_rows;
    $total += $n;
    if($n > 0) {
        echo "  - reset app_theme to 'default' for $n user(s) on '$t'\n";
    }
}
$stmt->close();

if($total === 0) {
    echo "  no user had app_theme set to " . implode(' or ', array_map(function($t) { return "'$t'"; }, $themes)) . "\n";
}
$m->close();

#!/usr/bin/env bash
#
# install.sh and uninstall.sh against a scratch panel: the stock fixtures as
# themes/default/templates and the two config files. No root and no database;
# --no-assign skips the admin module assignment, which needs both.
#
#   bash tests/shell/install_test.sh
set -uo pipefail

here="$(cd "$(dirname "$0")" && pwd)"
repo="$(cd "$here/../.." && pwd)"
. "$here/lib.sh"

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
root="$work/ispconfig"
themes="$root/interface/web/themes"

mkdir -p "$themes/default/templates" "$root/interface/lib" "$root/server/lib"
cp "$here/fixtures/3.3.1p1/main.tpl.htm" "$here/fixtures/3.3.1p1/main_login.tpl.htm" "$themes/default/templates/"
for conf in "$root/interface/lib/config.inc.php" "$root/server/lib/config.inc.php"; do
  printf "<?php\ndefine('ISPC_APP_VERSION', '3.3.1p1');\n\$conf['theme'] = 'default';\n" > "$conf"
done

# Another installer's Cicada: a real directory that holds no brand.php.
mkdir -p "$themes/cicada"
echo "standalone" > "$themes/cicada/marker.txt"

flags="--theme --copy --design=cicada --no-assign"

# ---- the first run meets the foreign directory -------------------------------
bash "$repo/install.sh" $flags "$root" > "$work/run1.txt" 2>&1
t_ok "first run exits 0" $? "$(tail -n 5 "$work/run1.txt")"
moved="$(ls -d "$root"/theme-backups/cicada-* 2>/dev/null | head -n 1)"
[ -n "$moved" ] && [ -f "$moved/marker.txt" ]
t_ok "first run moves the foreign directory aside" $? "$(ls -la "$root" 2>&1)"
[ -n "$moved" ] && grep -q -F "$moved" "$work/run1.txt"
t_ok "first run says where it went" $?
[ -f "$themes/cicada/brand.php" ]
t_ok "first run installs this project's Cicada" $?
t_eq "first run stamps the panel version" "$(cat "$themes/cicada/ispconfig_version" 2>/dev/null)" "3.3.1p1"
grep -q -F "themes/cicada/brand.php" "$themes/cicada/templates/main.tpl.htm" 2>/dev/null
t_ok "first run generates Cicada's shell" $?
grep -q -F -- "$repo/install.sh $flags $root" "$work/run1.txt"
t_ok "the closing note gives the re-run command with the same flags" $?

# ---- a re-run finds its own Cicada ------------------------------------------
bash "$repo/install.sh" $flags "$root" > "$work/run2.txt" 2>&1
t_ok "a re-run exits 0" $? "$(tail -n 5 "$work/run2.txt")"
t_eq "a re-run moves nothing" "$(ls -d "$root"/theme-backups/cicada-* 2>/dev/null | wc -l | tr -d ' ')" "1"

# ---- a backup directory the Design picker would list ------------------------
bash "$repo/install.sh" $flags "--backup-dir=$themes/old" "$root" > "$work/run3.txt" 2>&1
[ $? -ne 0 ]
t_ok "a backup directory inside themes is refused" $? "$(tail -n 5 "$work/run3.txt")"
grep -q "outside" "$work/run3.txt"
t_ok "the refusal says it must lie outside themes" $?
[ ! -e "$themes/old" ]
t_ok "the refused directory is never created" $?

# ---- uninstall --------------------------------------------------------------
bash "$repo/uninstall.sh" --theme --design=cicada "$root" > "$work/un.txt" 2>&1
[ ! -e "$themes/cicada" ]
t_ok "uninstall removes cicada with its templates" $? "$(tail -n 5 "$work/un.txt")"

t_done
echo "install test passed"

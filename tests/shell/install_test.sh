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
rerun="$(printf '%q ' "$repo/install.sh" $flags "$root")"
grep -q -F -- "${rerun% }" "$work/run1.txt"
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

# Anywhere else under interface/web/ the web server would hand the moved
# design's files to anyone who asks for them, so that is refused as well,
# however the path is spelled.
for bad in "$root/interface/web/old-themes" "$themes/../old-themes"; do
  bash "$repo/install.sh" $flags "--backup-dir=$bad" "$root" > "$work/run4.txt" 2>&1
  [ $? -ne 0 ]
  t_ok "a backup directory under the web root is refused ($bad)" $? "$(tail -n 5 "$work/run4.txt")"
  grep -q -F "outside the panel's web directory" "$work/run4.txt"
  t_ok "the refusal names the web directory ($bad)" $?
done

# ---- the re-run command can be pasted back as it stands ----------------------
# Started by a relative path with an argument that holds a space: the closing
# note names the script by its absolute path and quotes every argument, so the
# line runs the same install from any directory.
spaced="$work/old themes"
( cd "$repo" && bash ./install.sh $flags "--backup-dir=$spaced" "$root" ) > "$work/run5.txt" 2>&1
t_ok "a run by a relative path with a spaced argument exits 0" $? "$(tail -n 5 "$work/run5.txt")"
hint="$(grep -A1 "Re-run it with the same flags:" "$work/run5.txt" | tail -n 1)"
eval "set -- $hint"
t_eq "the re-run command names the script by its absolute path" "${1:-}" "$repo/install.sh"
t_eq "the re-run command gives back every argument" "$#" "7"
t_eq "the re-run command keeps an argument with a space whole" "${6:-}" "--backup-dir=$spaced"
# The note at an interrupted install prints the same command.
t_eq "install.sh spells the re-run command in one place" \
  "$(grep -c 'rerun_command' "$repo/install.sh")" "3"

# ---- uninstall --------------------------------------------------------------
bash "$repo/uninstall.sh" --theme --design=cicada "$root" > "$work/un.txt" 2>&1
[ ! -e "$themes/cicada" ]
t_ok "uninstall removes cicada with its templates" $? "$(tail -n 5 "$work/un.txt")"

# ---- uninstall leaves another installer's design alone ----------------------
# A real themes/cicada without brand.php is not this project's: the plain
# uninstall (every design by default) must keep it, and a user reset must not
# fall back to resetting designs nobody asked to remove.
root2="$work/ispconfig2"
mkdir -p "$root2/interface/web/themes/cicada" "$root2/interface/lib" "$root2/server/lib"
cp "$root/interface/lib/config.inc.php" "$root2/interface/lib/"
cp "$root/server/lib/config.inc.php" "$root2/server/lib/"
echo "standalone" > "$root2/interface/web/themes/cicada/marker.txt"
bash "$repo/uninstall.sh" --theme "$root2" > "$work/un2.txt" 2>&1
[ -f "$root2/interface/web/themes/cicada/marker.txt" ]
t_ok "a plain uninstall keeps another installer's cicada" $? "$(tail -n 5 "$work/un2.txt")"
grep -q "keeping $root2/interface/web/themes/cicada" "$work/un2.txt"
t_ok "the uninstall says it kept it" $?
bash "$repo/uninstall.sh" --theme --design=cicada --reset-users "$root2" > "$work/un3.txt" 2>&1
! grep -q "resetting users' theme choice" "$work/un3.txt"
t_ok "a reset with nothing left to remove resets nobody" $? "$(tail -n 5 "$work/un3.txt")"

t_done
echo "install test passed"

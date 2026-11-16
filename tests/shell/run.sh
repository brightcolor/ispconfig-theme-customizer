#!/usr/bin/env bash
#
# The shell suite: the shared template generator against the stock templates of
# ISPConfig 3.3.1p1 (tests/shell/fixtures/). No panel and no root needed.
#
#   bash tests/shell/run.sh
set -uo pipefail

here="$(cd "$(dirname "$0")" && pwd)"
repo="$(cd "$here/../.." && pwd)"
fixtures="$here/fixtures/3.3.1p1"

. "$here/lib.sh"
. "$repo/lib/designs.sh" || { echo "FAIL cannot load lib/designs.sh"; exit 1; }
. "$repo/lib/shell.sh"   || { echo "FAIL cannot load lib/shell.sh"; exit 1; }

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

# ---- classic: moving the generator must not move a byte -------------------
# expected/classic/ is what upstream v3.6.0's generate_classic_templates()
# produced from these fixtures (tests/shell/make_expected.sh).
mkdir -p "$work/classic"
generate_shell_templates classic "$fixtures" "$repo/themes/classic" "$work/classic"
t_ok "classic shell generates" $?
for f in main.tpl.htm main_login.tpl.htm; do
  cmp -s "$work/classic/$f" "$here/expected/classic/$f"
  t_ok "classic shell is byte-identical to upstream v3.6.0 ($f)" $?
done

# ---- cicada: classic's changes plus Cicada's own ---------------------------
cic_src="$repo/themes/cicada"
mkdir -p "$work/cicada"
generate_shell_templates cicada "$fixtures" "$cic_src" "$work/cicada"
t_ok "cicada shell generates" $?
main="$work/cicada/main.tpl.htm"
login="$work/cicada/main_login.tpl.htm"
css_sum="$(cksum "$cic_src/assets/stylesheets/cicada.css" | cut -d' ' -f1)"
js_sum="$(cksum "$cic_src/assets/javascripts/cicada-charts.js" | cut -d' ' -f1)"

for f in "$main" "$login"; do
  n="$(basename "$f")"
  t_eq "cicada: stylesheet once ($n)" "$(grep -c "themes/cicada/assets/stylesheets/cicada.css?v=" "$f")" "1"
  next="$(grep -A1 "cicada.css?v=" "$f" | tail -n 1)"
  case "$next" in *"themes/cicada/brand.php"*) r=0 ;; *) r=1 ;; esac
  t_ok "cicada: stylesheet directly before brand.php ($n)" "$r" "$next"
  last="$(grep "rel='stylesheet'" "$f" | tail -n 1)"
  case "$last" in *"themes/cicada/brand.php"*) r=0 ;; *) r=1 ;; esac
  t_ok "cicada: brand.php is the last stylesheet ($n)" "$r" "$last"
  t_eq "cicada: stock colour sheet gone ($n)" "$(grep -c "theme.min.css" "$f")" "0"
  t_eq "cicada: no stock red left ($n)" "$(grep -c "#cc151c" "$f")" "0"
  grep -q "#dd630d" "$f"
  t_ok "cicada: Cicada's orange in its place ($n)" $?
  t_eq "cicada: stylesheet cache buster is its cksum ($n)" \
    "$(grep -o "cicada.css?v=[0-9]*" "$f" | cut -d= -f2)" "$css_sum"
done

t_eq "cicada: chart script once in the app shell" "$(grep -c "cicada-charts.js?v=" "$main")" "1"
after="$(grep -A1 "js/chartjs/chart.umd.js" "$main" | tail -n 1)"
case "$after" in *"themes/cicada/assets/javascripts/cicada-charts.js?v=$js_sum'"*) r=0 ;; *) r=1 ;; esac
t_ok "cicada: chart script right after Chart.js, its cksum as cache buster" "$r" "$after"
t_eq "cicada: no chart script in the login shell" "$(grep -c "cicada-charts.js" "$login")" "0"
grep -q "href='../themes/cicada/brand.php?scene=login'" "$login"
t_ok "cicada: login shell links ../themes/cicada/brand.php?scene=login" $?

# ---- stock shells of another shape ------------------------------------------
# Without Chart.js (3.2, or a later release that drops it): no chart script.
mkdir -p "$work/nochart-stock" "$work/nochart"
grep -v "js/chartjs/chart.umd.js" "$fixtures/main.tpl.htm" > "$work/nochart-stock/main.tpl.htm"
cp "$fixtures/main_login.tpl.htm" "$work/nochart-stock/"
generate_shell_templates cicada "$work/nochart-stock" "$cic_src" "$work/nochart"
t_ok "cicada: a shell without Chart.js still generates" $?
t_eq "cicada: a shell without Chart.js gets no chart script" \
  "$(grep -c "cicada-charts.js" "$work/nochart/main.tpl.htm")" "0"

# Without the stock colour sheet: Cicada cannot know what replaced it and must
# refuse; classic never touches that sheet and still generates.
mkdir -p "$work/nosheet-stock" "$work/nosheet-cicada" "$work/nosheet-classic"
for f in main.tpl.htm main_login.tpl.htm; do
  grep -v "theme.min.css" "$fixtures/$f" > "$work/nosheet-stock/$f"
done
generate_shell_templates cicada "$work/nosheet-stock" "$cic_src" "$work/nosheet-cicada" 2>/dev/null
[ $? -ne 0 ]
t_ok "cicada: refuses a shell without the colour sheet" $?
generate_shell_templates classic "$work/nosheet-stock" "$repo/themes/classic" "$work/nosheet-classic"
t_ok "classic: still generates without the colour sheet" $?

t_done
echo "shell suite passed"

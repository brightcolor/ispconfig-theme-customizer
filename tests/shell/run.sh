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

t_done
echo "shell suite passed"

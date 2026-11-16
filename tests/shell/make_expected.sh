#!/usr/bin/env bash
#
# Regenerate tests/shell/expected/classic/ with an upstream tag's own
# generate_classic_templates(), run against the stock fixtures.
#
#   bash tests/shell/make_expected.sh v3.6.0
#
# The expected files are committed, and tests/shell/run.sh holds the shared
# generator to them byte for byte. Regenerating them is a deliberate act: do it
# only to adopt a reviewed upstream change to classic's shell, and say in the
# commit message what moved and why.
set -euo pipefail

tag="${1:?usage: make_expected.sh <upstream tag>}"
here="$(cd "$(dirname "$0")" && pwd)"
repo="$(cd "$here/../.." && pwd)"
fixtures="$here/fixtures/3.3.1p1"
out="$here/expected/classic"

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

mkdir -p "$work/themes/default/templates" "$work/out" "$out"
cp "$fixtures/main.tpl.htm" "$fixtures/main_login.tpl.htm" "$work/themes/default/templates/"

# Only the function, cut from that tag's installer: the rest of install.sh is a
# script that would start installing the moment it was sourced.
git -C "$repo" show "$tag:install.sh" \
  | sed -n '/^generate_classic_templates() {/,/^}/p' > "$work/generator.sh"
if [ ! -s "$work/generator.sh" ]; then
  echo "ERROR: $tag:install.sh holds no generate_classic_templates()." >&2
  echo "       Name a tag from before the generator moved into lib/shell.sh." >&2
  exit 1
fi

# The function reads the stock templates from $THEMES_DIR/default/templates.
THEMES_DIR="$work/themes"
# shellcheck source=/dev/null
. "$work/generator.sh"
generate_classic_templates "$work/out"

cp "$work/out/main.tpl.htm" "$work/out/main_login.tpl.htm" "$out/"
echo "wrote expected/classic/main.tpl.htm and main_login.tpl.htm from $tag"

#!/usr/bin/env bash
#
# The shared shell generator, for every design whose table entry in
# lib/designs.sh says shell=generated. install.sh and tests/shell/run.sh source
# this file after lib/designs.sh, whose design_field() it reads.
#
# Moved here from install.sh's generate_classic_templates(). tests/shell/run.sh
# holds classic's output to what that function produced in v3.6.0, byte for byte.

# Generate a design's two shell templates from the TARGET PANEL's stock ones.
#
#   generate_shell_templates DESIGN STOCK_DIR SRC_DIR OUT_DIR
#
# DESIGN    a design whose table entry says shell=generated (lib/designs.sh)
# STOCK_DIR the panel's themes/default/templates (tests pass their fixtures)
# SRC_DIR   the design's directory in this clone
# OUT_DIR   a scratch directory to write main.tpl.htm and main_login.tpl.htm
#           into; the caller places them
#
# Returns 1, with the reason on stderr, when it refuses.
#
# The whole transform is four mechanical changes, and nothing else may differ:
#
#   1. themes/<tmpl_var name='current_theme'>/assets/  ->  themes/default/assets/
#      Template fallback does NOT extend to assets. Under a generated design
#      the stock markup would ask for themes/<design>/assets/…, where none of
#      stock's files exist, and every stylesheet, script and icon on the page
#      would 404. Pinning them to the default theme is what lets classic ship
#      no assets at all, and lets a design with assets of its own ship only those.
#
#   2. brand.php, title.php and favicon.php linked immediately before </head>,
#      so the design can read the brand contract. Nothing else reads it on a
#      stock shell.
#
#   3. every <link rel='icon'> / rel='shortcut icon'> is REPLACED by the
#      favicon.php link from change 2. Change 1 alone would leave them
#      pinned to themes/default/assets/favicon/ — i.e. serving the ISPConfig
#      mark on every tab of a panel whose whole point is to carry someone
#      else's brand. The endpoint falls back to those very files when nothing is
#      stored, so an unbranded panel is unchanged.
#      Stock declares the icon three times (two sized PNGs and the legacy
#      shortcut .ico) and the replacement is ONE unsized, untyped link: what the
#      endpoint returns depends on what the operator stored, and a browser skips
#      a link whose declared type it cannot render. That makes this the one
#      change that alters the output's LINE COUNT, so the count check below is
#      derived from how many icon links were actually removed rather than being
#      a constant — see it for the arithmetic.
#      The other icon references (apple-touch-icon, mask-icon, manifest, tile
#      config) are deliberately left pointing at stock's assets: they are
#      platform install artefacts with their own size and format contracts, not
#      the tab icon, and each would need its own endpoint to be brandable.
#
# Deliberately NOT added: a "generated file, do not edit" banner. It would ship
# in the HTML of a pre-authentication page and announce, to anyone who can reach
# the login screen, which extension this panel runs — the exact disclosure a
# white-label install is for. The warning lives in each design's README.md and
# in .gitignore instead.
generate_shell_templates() {
  local design="$1" stock="$2" srcdir="$3" outdir="$4"
  local find_str="themes/<tmpl_var name='current_theme'>/assets/"
  local tpl src out prefix scene ins1 ins2 ins3 cred2 expected src_n out_n rc icons out_icons
  local credit_label credit_url drop stock_colour design_colour stylesheet chart_script
  local ins_css chart_line counts dropped charts asset
  credit_label="$(design_field "$design" credit_label)"
  credit_url="$(design_field "$design" credit_url)"
  drop="$(design_field "$design" drop_sheet)"
  stock_colour="$(design_field "$design" stock_colour)"
  design_colour="$(design_field "$design" design_colour)"
  stylesheet="$(design_field "$design" stylesheet)"
  chart_script="$(design_field "$design" chart_script)"

  # A design's own assets are linked with ?v=<cksum> below. A missing one would
  # ship a link that 404s on every page, so it stops the build here.
  for asset in $stylesheet $chart_script; do
    if [ ! -f "$srcdir/$asset" ]; then
      echo "ERROR: $design's $asset is missing from $srcdir." >&2
      return 1
    fi
  done

  # ONE definition of "this line is a tab-icon <link>", used by the awk transform
  # (which deletes them) and by the shell check afterwards (which proves exactly
  # one survives and that it is ours). POSIX character classes rather than \t so
  # awk and grep -E read the pattern identically. Anchored at the start of the
  # line: a <link> that does not begin its line is not a shape this generator
  # claims to understand, and it is refused loudly below rather than mangled.
  local icon_re="^[[:space:]]*<link[^>]*rel=['\"](shortcut icon|icon)['\"]"
  local cnt_file="$outdir/.nzc_icon_count"

  for tpl in main.tpl.htm main_login.tpl.htm; do
    src="$stock/$tpl"
    out="$outdir/$tpl"
    if [ ! -f "$src" ]; then
      echo "ERROR: $src not found — $design is generated from the panel's own stock" >&2
      echo "       templates and cannot be built without them." >&2
      return 1
    fi

    # How deep the page using this template is served from. The app frame is
    # rendered at the web root and links its assets as 'themes/…'; the login
    # shell is rendered from /login/ and links '../themes/…'. Root-relative
    # '/themes/…' would work on a panel mounted at the docroot and break on one
    # behind a sub-path, so take whichever prefix the stock markup itself uses
    # and stay exactly as portable as core is.
    if grep -q -F "href='../${find_str}stylesheets/" "$src"; then
      prefix='../'
    elif grep -q -F "href='${find_str}stylesheets/" "$src"; then
      prefix=''
    else
      echo "ERROR: cannot tell how $tpl links its stylesheets." >&2
      echo "       This ISPConfig's stock shell is not the shape $design knows how to" >&2
      echo "       transform. Install clarity instead (--design=clarity) and open an" >&2
      echo "       issue with your ISPConfig version." >&2
      return 1
    fi

    # Only the login shell gets ?scene=login. brand.php has a few rules that must
    # apply to the login screen alone (its background, its one primary button),
    # and stock's login <body> carries no class, no id and nothing stable to hang
    # a selector on — clarity adds one in its own template, a generated design may not touch
    # the markup. So the scope travels in the URL instead of the DOM.
    scene=''
    [ "$tpl" = "main_login.tpl.htm" ] && scene='?scene=login'

    ins1="  <link rel='stylesheet' href='${prefix}themes/${design}/brand.php${scene}' />"
    ins2="  <script src='${prefix}themes/${design}/title.php'></script>"
    # No type/sizes attributes, and the same ${prefix} as the endpoints above so
    # a panel served from a sub-path keeps working (stock links its favicons
    # root-relative, but its stylesheets tell us what this panel can rely on).
    ins3="  <link rel='icon' href='${prefix}themes/${design}/favicon.php'>"

    # The footer credits have to become individually addressable, or the Branding
    # page ends up with two toggles that do nothing on this design. Stock renders
    # the whole line as bare text inside <footer id='footer'>, so CSS can only
    # hide all of it or none of it. Wrapping core's own line in one span and
    # appending ours in a second gives show_ispconfig_credit and
    # show_theme_credit a target each — the same shape clarity's template uses.
    #
    # Both ship ON. Nothing is hidden until an administrator chooses to hide it,
    # and licence notices are never touched by either toggle — these are courtesy
    # lines, which is a different thing.
    cred2="<span class='nzc-credit-theme'><span class='nzc-credit-sep'> &middot; </span><a href='${credit_url}' target='_blank' rel='noopener'>${credit_label}</a></span>"

    # A design's own stylesheet goes in after every stock sheet and directly
    # before brand.php, so the brand overrides still win over it. Its chart
    # defaults follow Chart.js, which stock loads in the body. Both carry
    # ?v=<cksum>: the file names never change and ISPConfig sends no cache
    # headers, so the URL has to move when the content does.
    ins_css=''
    if [ -n "$stylesheet" ]; then
      ins_css="  <link rel='stylesheet' href='${prefix}themes/${design}/${stylesheet}?v=$(cksum "$srcdir/$stylesheet" | cut -d' ' -f1)' />"
    fi
    chart_line=''
    if [ -n "$chart_script" ]; then
      chart_line="  <script src='${prefix}themes/${design}/${chart_script}?v=$(cksum "$srcdir/$chart_script" | cut -d' ' -f1)'></script>"
    fi

    # index()/substr rather than gsub(): the search string is a literal full of
    # regex metacharacters, and a literal search cannot be defeated by quoting.
    #
    # Exit status is a three-way answer, not a boolean: 0 transformed, 1 no
    # </head> (nowhere to link anything), 2 an icon <link> that shares its line
    # with other markup — a shape this generator does not know how to rewrite,
    # which must stop the install rather than silently drop whatever else was on
    # that line. The END rule's own exit would override the earlier one, hence
    # the `aborted` guard.
    rc=0
    awk -v find="$find_str" -v repl="themes/default/assets/" \
        -v ins1="$ins1" -v ins2="$ins2" -v ins3="$ins3" -v cred2="$cred2" \
        -v icon_re="$icon_re" -v cnt_file="$cnt_file" \
        -v drop="$drop" -v stock_colour="$stock_colour" -v design_colour="$design_colour" \
        -v ins_css="$ins_css" -v chart_line="$chart_line" '
        function lreplace(s,   acc, p) {
            acc = ""
            while ((p = index(s, find)) > 0) {
                acc = acc substr(s, 1, p - 1) repl
                s   = substr(s, p + length(find))
            }
            return acc s
        }
        # The stock colour the design replaces, by the same literal search.
        function creplace(s,   acc, p) {
            if (stock_colour == "") return s
            acc = ""
            while ((p = index(s, stock_colour)) > 0) {
                acc = acc substr(s, 1, p - 1) design_colour
                s   = substr(s, p + length(stock_colour))
            }
            return acc s
        }
        # how many tags start on this line — one is the shape we handle
        function tag_count(s,   parts) { return split(s, parts, "<") - 1 }
        { line = creplace(lreplace($0)) }
        line ~ icon_re {
            if (tag_count(line) != 1) { aborted = 1; exit 2 }
            icons++
            next
        }
        # The stock sheet a design replaces wholesale, counted so the shell can
        # refuse a panel where it is missing or doubled.
        drop != "" && index(line, drop) > 0 { dropped++; next }
        !done && line ~ /<\/head>/ {
            print ins3
            if (ins_css != "") print ins_css
            print ins1
            print ins2
            done = 1
        }
        # Split the footer credit into two addressable spans, preserving stock
        # indentation. Matching on both "powered by" and app_link keeps this off
        # any other line that happens to contain one of them.
        !fdone && line ~ /powered by/ && line ~ /app_link/ {
            match(line, /^[ \t]*/)
            ind  = substr(line, 1, RLENGTH)
            body = substr(line, RLENGTH + 1)
            printf "%s<span class=\047nzc-credit-ispconfig\047>%s</span>\n", ind, body
            printf "%s%s\n", ind, cred2
            fdone = 1
            next
        }
        { print line }
        chart_line != "" && index(line, "js/chartjs/chart.umd.js") > 0 { print chart_line; charts++ }
        END {
            # One string first: print followed by a parenthesised expression is
            # parsed differently across awks, and panels run mawk.
            tally = (icons + 0) " " (dropped + 0) " " (charts + 0)
            print tally > cnt_file
            if (!aborted && !done) exit 1
        }
    ' "$src" > "$out" || rc=$?

    if [ "$rc" -eq 2 ]; then
      echo "ERROR: an icon <link> shares its line with other markup in $src:" >&2
      grep -nE "$icon_re" "$src" >&2 || true
      echo "       $design replaces those links with one pointing at its favicon" >&2
      echo "       endpoint, and it will not delete a line carrying anything else." >&2
      echo "       Install clarity instead (--design=clarity) and open an issue with" >&2
      echo "       your ISPConfig version." >&2
      return 1
    elif [ "$rc" -ne 0 ]; then
      echo "ERROR: no </head> in $src — nowhere to link the brand endpoints." >&2
      return 1
    fi

    # What awk changed: icon links removed, stock sheets dropped, chart scripts
    # added. Read from the file awk wrote rather than counted again here: one
    # definition of each pattern, one engine applying it, so the counts and the
    # changes can never disagree.
    counts="$(cat "$cnt_file" 2>/dev/null || true)"
    icons="${counts%% *}"
    dropped="${counts#* }"; dropped="${dropped%% *}"
    charts="${counts##* }"
    case "$icons:$dropped:$charts" in
      *[!0-9:]*|:*|*::*|*:)
        echo "ERROR: the template transform did not report what it changed in $tpl" >&2
        echo "       (icon links, stock sheets, chart scripts), so its output cannot be" >&2
        echo "       verified." >&2
        return 1
        ;;
    esac

    # A design that drops a stock sheet must find exactly one. None means the
    # stock shell changed shape and the sheet may now load under another name,
    # over the design; more than one is a shape this generator does not know.
    if [ -n "$drop" ] && [ "$dropped" -ne 1 ]; then
      echo "ERROR: $design replaces the stock stylesheet $drop, and $src links it" >&2
      echo "       $dropped times. This ISPConfig's stock shell is not the shape $design" >&2
      echo "       knows how to transform. Install classic or clarity instead and open an" >&2
      echo "       issue with your ISPConfig version." >&2
      return 1
    fi

    # --- verify the output is the stock file plus exactly those changes ------
    # A silent miss here is the worst outcome this script has: a generated shell
    # that still points at themes/<design>/assets/ serves a panel with every
    # stylesheet and script 404ing, and nothing logs it.
    src_n="$(awk 'END{print NR}' "$src")"
    out_n="$(awk 'END{print NR}' "$out")"
    # +3 for the three <head> links (favicon, brand, title), MINUS the icon links
    # that were replaced by the first of them — stock declares the tab icon three
    # times, so this is normally a net zero on the app frame and the login shell
    # alike. Deriving it from the count awk reported, rather than hardcoding
    # "minus 3", is what lets this check keep working on an ISPConfig that ships
    # a different number of icon links: the check moves with the transform
    # instead of having to be relaxed.
    # +1 more if the footer credit was split in two. That split is expected in
    # the app frame and absent from the login shell, which has no footer — so it
    # too is derived from the output rather than assumed, and a future ISPConfig
    # that moves the footer degrades to a warning instead of failing an install
    # over a courtesy line.
    # And for a design with assets of its own: +1 for its stylesheet, MINUS the
    # stock sheet it dropped, +1 per chart script, each as awk reported it.
    expected=$(( src_n + 3 - icons - dropped + charts ))
    if [ -n "$ins_css" ]; then expected=$(( expected + 1 )); fi
    if grep -q "nzc-credit-ispconfig" "$out"; then expected=$(( expected + 1 )); fi
    if [ "$out_n" -ne "$expected" ]; then
      echo "ERROR: generated $tpl is $out_n lines, expected $expected" >&2
      echo "       (stock $src_n + 3 head links - $icons icon links replaced" >&2
      echo "        - $dropped stock sheets dropped + $charts chart scripts" >&2
      echo "        + the design's own stylesheet, if it has one)." >&2
      return 1
    fi
    if [ "$tpl" = "main.tpl.htm" ] && ! grep -q "nzc-credit-ispconfig" "$out"; then
      echo "WARNING: could not find the footer credit line in $src, so the" >&2
      echo "         'hide footer credits' toggles will have no effect on $design." >&2
      echo "         Everything else works; please report your ISPConfig version." >&2
    fi
    # After the rewrite the template must not reference the active theme AT ALL.
    # If a future ISPConfig uses current_theme for something other than an asset
    # path, that path would resolve into the design's directory too —
    # so this check failing means "look at it", not "loosen the check".
    if grep -q "current_theme" "$out"; then
      echo "ERROR: $tpl still references current_theme after the rewrite:" >&2
      grep -n "current_theme" "$out" >&2
      echo "       Under $design those paths resolve into themes/$design/, which holds" >&2
      echo "       no assets. Refusing to install a shell that would 404 everything." >&2
      return 1
    fi
    if ! grep -q -F "themes/${design}/brand.php" "$out" || ! grep -q -F "themes/${design}/title.php" "$out" \
       || ! grep -q -F "themes/${design}/favicon.php" "$out"; then
      echo "ERROR: the brand endpoints are missing from the generated $tpl." >&2
      return 1
    fi

    # A design's own sheet: once, directly before brand.php, and brand.php the
    # last stylesheet of the document, or something loaded later would paint
    # over the brand. The replaced stock colour must be gone, and the chart
    # script must sit once, right after Chart.js, wherever the stock shell
    # loads Chart.js, and nowhere else.
    if [ -n "$ins_css" ]; then
      if [ "$(grep -c -F "$ins_css" "$out" || true)" != "1" ] \
         || [ "$(grep -A1 -F "$ins_css" "$out" | tail -n 1)" != "$ins1" ]; then
        echo "ERROR: $design's stylesheet is not linked once, directly before brand.php," >&2
        echo "       in the generated $tpl." >&2
        return 1
      fi
      if [ "$(grep "rel='stylesheet'" "$out" | tail -n 1)" != "$ins1" ]; then
        echo "ERROR: brand.php is not the last stylesheet in the generated $tpl, so a" >&2
        echo "       sheet loaded after it would paint over the brand." >&2
        return 1
      fi
    fi
    if [ -n "$stock_colour" ] && grep -q -F "$stock_colour" "$out"; then
      echo "ERROR: the generated $tpl still carries the stock colour $stock_colour." >&2
      return 1
    fi
    if [ -n "$chart_line" ]; then
      if grep -q "js/chartjs/chart.umd.js" "$src"; then
        if [ "$charts" != "1" ] \
           || [ "$(grep -A1 "js/chartjs/chart.umd.js" "$out" | tail -n 1)" != "$chart_line" ]; then
          echo "ERROR: $design's chart script is not loaded once, right after Chart.js," >&2
          echo "       in the generated $tpl." >&2
          return 1
        fi
      elif [ "$charts" != "0" ]; then
        echo "ERROR: the generated $tpl loads $design's chart script without Chart.js." >&2
        return 1
      fi
    fi

    # Exactly one tab-icon link, and it is ours. This is the check that catches
    # an icon <link> written in a shape the pattern above did not recognise: the
    # stock one would survive alongside ours, the browser would be free to pick
    # either, and a panel would keep flying the ISPConfig flag on some tabs and
    # the operator's on others — the kind of half-applied white-label that gets
    # noticed by a customer rather than by a check.
    out_icons="$(grep -cE "$icon_re" "$out" || true)"
    if [ "$out_icons" != "1" ]; then
      echo "ERROR: generated $tpl carries $out_icons tab-icon <link> tags, expected 1:" >&2
      grep -nE "$icon_re" "$out" >&2 || true
      return 1
    fi
  done

  # The scratch dir is copied from by name, not wholesale, so this only tidies —
  # but a leftover counter file in a directory the caller may one day copy
  # entirely is exactly the sort of thing that ships by accident.
  rm -f "$cnt_file"
}

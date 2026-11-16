#!/usr/bin/env bash
#
# The designs this repository ships and what the installer needs to know about
# each one. install.sh and uninstall.sh source this file, tests/shell/run.sh
# reads it, and tests/brand/probe_registry.php holds it to the module's design
# registry and to the themes/*/brand.php readers. One table, so a new design is
# one entry here.

# Every design this repository ships, in install order: the Design picker lists
# them in the order the documentation introduces them.
ALL_DESIGNS="clarity classic cicada"

# design_known DESIGN — exit 0 when DESIGN is one of ALL_DESIGNS.
design_known() {
  case " $ALL_DESIGNS " in
    *" $1 "*) return 0 ;;
  esac
  return 1
}

# design_field DESIGN FIELD — prints the value, or nothing when the design does
# not set the field.
#
#   shell          shipped    the design commits its own shell templates
#                  generated  lib/shell.sh builds them at install time from the
#                             panel's own stock templates
#   credit_label   link text of the design's footer credit (generated shells)
#   credit_url     link target of that credit
#   drop_sheet     path fragment of a stock stylesheet the shell must not load
#   stock_colour   a stock colour literal the shell replaces ...
#   design_colour  ... with this one
#   stylesheet     the design's own stylesheet, relative to themes/<design>/
#   chart_script   a script loaded right after Chart.js, relative to themes/<design>/
design_field() {
  case "$1:$2" in
    clarity:shell)        echo "shipped" ;;
    classic:shell)        echo "generated" ;;
    classic:credit_label) echo "Classic" ;;
    classic:credit_url)   echo "https://github.com/wadejbeckett/ispconfig-theme-customizer" ;;
    cicada:shell)         echo "generated" ;;
    cicada:credit_label)  echo "Cicada" ;;
    cicada:credit_url)    echo "https://github.com/brightcolor/ispconfig-theme-customizer" ;;
    cicada:drop_sheet)    echo "stylesheets/themes/default/theme.min.css" ;;
    cicada:stock_colour)  echo "#cc151c" ;;
    cicada:design_colour) echo "#dd630d" ;;
    cicada:stylesheet)    echo "assets/stylesheets/cicada.css" ;;
    cicada:chart_script)  echo "assets/javascripts/cicada-charts.js" ;;
    *)                    ;;
  esac
}

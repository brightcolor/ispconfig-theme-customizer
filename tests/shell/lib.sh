#!/usr/bin/env bash
# Shared helpers for the shell suites (run.sh, install_test.sh). Sourced, not run.

T_PASS=0
T_FAIL=0

# t_ok NAME STATUS [DETAIL] — STATUS is an exit code, 0 passes.
t_ok() {
  if [ "$2" -eq 0 ]; then
    T_PASS=$((T_PASS + 1))
    echo "ok   $1"
  else
    T_FAIL=$((T_FAIL + 1))
    echo "FAIL $1${3:+  -- $3}"
  fi
}

# t_eq NAME GOT WANT
t_eq() {
  if [ "$2" = "$3" ]; then
    t_ok "$1" 0
  else
    t_ok "$1" 1 "got '$2', want '$3'"
  fi
}

# Prints the tally; ends the suite with status 1 when anything failed.
t_done() {
  echo "# $T_PASS passed, $T_FAIL failed"
  [ "$T_FAIL" -eq 0 ] || exit 1
}

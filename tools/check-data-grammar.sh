#!/usr/bin/env bash
# Refuses a data file that is anything but the literal array the importer
# writes: the opening tag, the importer's one-line banner, `return [`, one
# `'<code>'=>[...],` row per line built only from single-quoted strings, string
# lists, `=>` and commas, and `];`. Run by update-data.yml's open-pr job, which
# runs no PHP, on the files it is about to commit: a dependency that ran in the
# import job must not be able to slip code into a generated file.
# Usage: tools/check-data-grammar.sh <file>...
set -euo pipefail
export LC_ALL=C
str="'([^'\\\\]|\\\\['\\\\])*'"
value="(${str}|\\[(${str}(,${str})*)?\\])"
pair="'[a-z_]+'=>${value}"
row="^'[0-9]+'=>\\[${pair}(,${pair})*\\],\$"
banner='^// Imported by tools/(import-snapshot|generate)\.php from snapshot [0-9]{4}-[0-9]{2}-[0-9]{2}(\.[1-9][0-9]*)? \(see data/sources\.json\)\. Do not edit\.$'
status=0
for file in "$@"; do
  fail() { echo "$file: $1" >&2; status=1; }
  [ -s "$file" ] || { fail "empty or missing"; continue; }
  [ -z "$(tail -c 1 "$file")" ] || { fail "does not end with a newline"; continue; }
  lines=$(wc -l < "$file")
  [ "$lines" -ge 4 ] || { fail "too short"; continue; }
  [ "$(sed -n 1p "$file")" = '<?php' ] || fail "line 1 is not <?php"
  sed -n 2p "$file" | grep -Eq "$banner" || fail "line 2 is not the importer's banner"
  [ "$(sed -n 3p "$file")" = 'return [' ] || fail "line 3 is not 'return ['"
  [ "$(sed -n "${lines}p" "$file")" = '];' ] || fail "the last line is not '];'"
  bad=$(sed -n "4,$((lines - 1))p" "$file" | grep -Evn "$row" | head -n 3 || true)
  [ -z "$bad" ] || fail "rows outside the generated grammar (line numbers from 4): ${bad:0:200}"
done
exit $status

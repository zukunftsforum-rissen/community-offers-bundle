#!/bin/bash
# Group Symfony log entries by correlation id (cid)
# Usage: ./tools/log/group-by-cid.sh var/log/dev.log

if [ -z "$1" ]; then
  echo "Usage: $0 <logfile>" >&2
  exit 1
fi

logfile="$1"

awk '
  BEGIN { RS=""; ORS="\n\n" }
  /cid: "[^"]+"/ {
    match($0, /cid: "([^"]+)"/, arr)
    cid = arr[1]
    if (cid != "") {
      entries[cid] = entries[cid] ? entries[cid] "\n\n" $0 : $0
      order[cid] = ++count[cid] == 1 ? ++cid_order : order[cid]
    }
  }
  END {
    n = asorti(order, sorted)
    for (i = 1; i <= n; i++) {
      cid = sorted[i]
      print "CID: " cid "\n"
      split(entries[cid], lines, "\n")
      for (j in lines) {
        if (lines[j] ~ /cid: "/) {
          # Print timestamp and event name from the line
          match(lines[j], /^\[([^]]+)\] ([^:]+): ([^ ]+)/, m)
          if (m[1] && m[3]) {
            printf("[%s] %s\n", m[1], m[3])
          }
          # Print all key: value pairs indented
          while (match(lines[j], /([a-zA-Z0-9_]+): ([^ ]+)/, kv)) {
            printf("    %s: %s\n", kv[1], kv[2])
            lines[j] = substr(lines[j], RSTART + RLENGTH)
          }
        }
      }
    }
  }
' "$logfile"

# To make executable:
# chmod +x tools/log/group-by-cid.sh

#!/usr/bin/env python3
"""Check the starter-commentary packs in data/commentary/*.json.

The importer finds a pack's imported entries by the pack id, so every entry key must start with
"<pack id>:" and be unique; otherwise a later sync cannot update or remove the entries.
Usage: python3 tools/check_packs.py
"""
import glob, json, os, sys

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
problems = 0
for path in sorted(glob.glob(os.path.join(ROOT, "data", "commentary", "*.json"))):
    pack = json.load(open(path, encoding="utf8"))
    pid, seen = pack.get("id", ""), set()
    for e in pack.get("entries", []):
        key = e.get("key", "")
        if not key.startswith(pid + ":"):
            print("%s: key %r does not start with the pack id %r" % (os.path.basename(path), key, pid)); problems += 1
        if key in seen:
            print("%s: duplicate key %r" % (os.path.basename(path), key)); problems += 1
        seen.add(key)
        for field in ("title", "passage", "content", "themes", "section"):
            if not e.get(field):
                print("%s: %s has no %s" % (os.path.basename(path), key, field)); problems += 1
    print("%s: %d entries" % (os.path.basename(path), len(seen)))
sys.exit(1 if problems else 0)

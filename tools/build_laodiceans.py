#!/usr/bin/env python3
"""Build the Epistle to the Laodiceans (data/further/laodiceans.json) from M. R. James's translation.

Source: M. R. James, The Apocryphal New Testament (Oxford: Clarendon Press, 1924), translated from the Latin,
supplied by the site owner; the copy is kept in tools/.cache/laodiceans/laodiceans-james.txt (git-ignored). James's
introduction is kept as the chapter's heading; the twenty verses follow. Not reworded.

Usage:  python3 tools/build_laodiceans.py
"""
import json, os, re, sys

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
SRC = os.path.join(ROOT, "tools", ".cache", "laodiceans", "laodiceans-james.txt")
OUT = os.path.join(ROOT, "data", "further", "laodiceans.json")


def main():
    lines = [l.strip() for l in open(SRC, encoding="utf-8") if l.strip()][1:]  # the title line goes
    intro, verses = [], []
    for line in lines:
        m = re.match(r"^(\d+)\s+(.*)$", line)
        if m:
            verses.append({"n": int(m.group(1)), "t": m.group(2)})
        elif verses:
            verses[-1]["t"] += " " + line
        else:
            intro.append(line)
    data = {
        "slug": "laodiceans",
        "name": "Epistle to the Laodiceans",
        "language": "Latin",
        "translation": "M. R. James, 1924",
        "source": {
            "title": "M. R. James, The Apocryphal New Testament (1924)",
            "url": "https://archive.org/details/JAMESApocryphalNewTestament1924",
            "credit": "The translation from Latin by M. R. James, The Apocryphal New Testament (Oxford: Clarendon Press, 1924)",
            "short": "M. R. James, 1924",
            "note": "The text above the verses is James's introduction.",
            "licence": "Public domain",
            "licence_url": "",
            "translator": {"type": "Person", "name": "M. R. James", "url": "https://en.wikipedia.org/wiki/M._R._James"},
        },
        "chapters": [{"n": 1, "heading": " ".join(intro), "verses": verses}],
    }
    with open(OUT, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=1)
        f.write("\n")
    print("Laodiceans: %d verses" % len(verses))
    return 0


if __name__ == "__main__":
    sys.exit(main())

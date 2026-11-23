#!/usr/bin/env python3
"""Write data/jps-versification.json: where the English (ESV) verse numbers differ from the Hebrew numbering the JPS
1917 text follows, for every book with JPS chapters in data/jps/.

Found from the chapter lengths: where a JPS chapter is longer than the English by d verses and the next shorter by
d, the English starts the next chapter d verses earlier (Exodus 8:1 is the Hebrew 7:26), so English verses 1 to d
of the next chapter come from the end of this one and the rest are shifted back by d; and the other way round
(Numbers 16:36 is the Hebrew 17:1). The Ten Commandments, where the Hebrew makes one verse of "Thou shalt not
murder" to "false witness", are written out by hand: the English verses with no Hebrew verse of their own map to
"" (their words are in the verse before). The rules are read by TBE_Bible::jps().

Usage:  python3 tools/build_jps_versification.py
"""
import json, os, sys

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
OUT = os.path.join(ROOT, "data", "jps-versification.json")
# Within one chapter: the Hebrew joins English verses first..last into one; after them it runs (last - first) behind.
JOINED = {("exodus", 20): (13, 16), ("deuteronomy", 5): (17, 20)}


def length(kind, slug, c):
    path = os.path.join(ROOT, "data", kind, slug, "%d.json" % c)
    return len(json.load(open(path, encoding="utf-8"))["verses"]) if os.path.exists(path) else None


def main():
    books = json.load(open(os.path.join(ROOT, "data", "books.json"), encoding="utf-8"))
    old = json.load(open(OUT, encoding="utf-8")) if os.path.exists(OUT) else {}
    out = {"_note": old.get("_note", "")}
    for book in books:
        slug, n = book["slug"], book["chapters"]
        if not os.path.isdir(os.path.join(ROOT, "data", "jps", slug)):
            continue
        rules = {}
        for c in range(1, n + 1):
            if (slug, c) in JOINED:
                first, last = JOINED[(slug, c)]
                gap = last - first
                r = rules.setdefault(str(c), {})
                for v in range(first + 1, last + 1):
                    r[str(v)] = ""
                for v in range(last + 1, length("esv", slug, c) + 1):
                    r[str(v)] = "%d:%d" % (c, v - gap)
                continue
            e, j = length("esv", slug, c), length("jps", slug, c)
            e2, j2 = length("esv", slug, c + 1), length("jps", slug, c + 1)
            if None in (e, j, e2, j2) or e == j:
                continue
            d = j - e
            if d > 0 and e2 - j2 == d:  # English starts the next chapter d verses early
                r = rules.setdefault(str(c + 1), {})
                r["shift"] = -d
                for k in range(1, d + 1):
                    r[str(k)] = "%d:%d" % (c, e + k)
            elif d < 0 and j2 - e2 == -d:  # the Hebrew starts the next chapter -d verses early
                r = rules.setdefault(str(c), {})
                for k in range(1, -d + 1):
                    r[str(j + k)] = "%d:%d" % (c + 1, k)
                rules.setdefault(str(c + 1), {})["shift"] = -d
        if rules:
            out[slug] = dict(sorted(rules.items(), key=lambda kv: int(kv[0])))
    with open(OUT, "w", encoding="utf-8") as f:
        json.dump(out, f, ensure_ascii=False, indent=1)
        f.write("\n")
    for slug, rules in out.items():
        if slug != "_note":
            print(slug, sorted(rules, key=int))
    return 0


if __name__ == "__main__":
    sys.exit(main())

#!/usr/bin/env python3
"""Gather the places where the Hebrew text is written one way and read another (data/manuscripts/hebrew.json).

Source: the site's own interlinear (data/interlinear/, from STEPBible's TAHOT, CC BY 4.0), whose words carry a
note where the manuscripts or the scribes' reading tradition differ:
  Q  written (ketiv) and read (qere) differ: the printed word is the qere, the note gives the ketiv
  R  a word the Leningrad Codex lacks, restored from other manuscripts
  X  a word only some manuscripts or editions have
  -  (no code) a different reading in another Hebrew edition
For each, the book, chapter, verse, the printed word with its gloss, and the other form with its gloss, when the
note gives them. A place counts as a difference of meaning when the two English glosses differ beyond spelling.

Usage:  python3 tools/build_hebrew_variants.py
"""
import glob, json, os, re, sys

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
OUT = os.path.join(ROOT, "data", "manuscripts", "hebrew.json")


def norm(gloss):
    return re.sub(r"[^a-z ]", "", re.sub(r"[/‹›\[\]]", " ", gloss.lower())).split()


def main():
    books = json.load(open(os.path.join(ROOT, "data", "books.json"), encoding="utf-8"))
    order = [b["slug"] for b in (books if isinstance(books, list) else books.get("books", []))]
    items = []
    for f in glob.glob(os.path.join(ROOT, "data", "interlinear", "*", "*.json")):
        d = json.load(open(f, encoding="utf-8"))
        if d.get("lang") != "he":
            continue
        slug, ch = f.split(os.sep)[-2], int(os.path.basename(f)[:-5])
        for v, words in d["v"].items():
            for i, w in enumerate(words):
                if len(w) < 7 or not isinstance(w[6], dict):
                    continue
                note = w[6]
                other = re.match(r'^\w=\s*(\S+)\s*\(([^)]*)\)\s*"([^"]*)"', note.get("v", "") or "")
                item = {
                    "b": slug, "c": ch, "v": int(v), "i": i, "k": note.get("t") or "E",
                    "he": w[0], "g": w[2].strip(),
                    "ohe": other.group(2).replace("/", "").replace("\\", "") if other else "",
                    "og": re.sub(r"\s+", " ", other.group(3).replace("/", " ")).strip() if other else "",
                }
                item["m"] = 1 if item["og"] and norm(item["g"]) != norm(item["og"]) else 0
                items.append(item)
    items.sort(key=lambda x: (order.index(x["b"]) if x["b"] in order else 999, x["c"], x["v"], x["i"]))
    with open(OUT, "w", encoding="utf-8") as f:
        json.dump({"source": "STEPBible TAHOT, CC BY 4.0, via data/interlinear", "items": items}, f, ensure_ascii=False, separators=(",", ":"))
    counts = {}
    for it in items:
        counts[it["k"]] = counts.get(it["k"], 0) + 1
    print("Hebrew variants:", len(items), counts, "meaning:", sum(it["m"] for it in items))
    return 0


if __name__ == "__main__":
    sys.exit(main())

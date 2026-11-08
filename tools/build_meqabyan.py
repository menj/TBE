#!/usr/bin/env python3
"""Build the three books of Meqabyan (data/further/{1,2,3}-meqabyan.json) from the Wikisource translation.

Source: "Translation:1 Meqabyan", "Translation:2 Meqabyan" and "Translation:3 Meqabyan" on English Wikisource,
translated from Ge'ez by Wikisource contributors, CC BY-SA 4.0. The site owner supplied the text as copied from
those pages; the copies are kept in tools/.cache/meqabyan/{slug}.txt (git-ignored) and are NOT stored in this
repository. The script takes the text from the first "Chapter 1" after the page header to the end, splits it
into chapters and verses, and reports any verse number that is missing or out of order. Nothing is reworded.

Verses are numbered "12 text" or "12. text" (1 Meqabyan) or "[12] text" (2 and 3 Meqabyan). Where the page runs two verses
together ("...idols.17 And he..."), they are split at the next expected number. A line without a number before
verse 1 is the chapter's heading; a line without a number after it continues the verse before.

Usage:  python3 tools/build_meqabyan.py
"""
import json, os, re, sys

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
CACHE = os.path.join(ROOT, "tools", ".cache", "meqabyan")
OUT = os.path.join(ROOT, "data", "further")
BOOKS = {
    "1-meqabyan": "1 Meqabyan",
    "2-meqabyan": "2 Meqabyan",
    "3-meqabyan": "3 Meqabyan",
}
VERSE = re.compile(r"^(?:\[(\d+)\]|(\d+)\.?)\s+(\S.*)$")


def split_inline(n, text):
    """Split '...idols.17 And he...' at the next expected verse number: [(n, text), (n + 1, text), ...]."""
    out = []
    while True:
        m = re.search(r"(?<=[.!?;:\"'”’…—)])\s*\[?(%d)\]?\s*(?=[A-Z“\"‘'(])" % (n + 1), text)
        if not m:
            out.append((n, text.strip()))
            return out
        out.append((n, text[: m.start()].strip()))
        text = text[m.end():]
        n += 1


def parse(raw):
    lines = [l.strip() for l in raw.splitlines()]
    # The body starts at the "Chapter 1" that follows the page header (the "←" navigation arrow).
    start = next(i for i, l in enumerate(lines) if l == "←")
    start = next(i for i in range(start, len(lines)) if lines[i] == "Chapter 1")
    chapters, problems = [], []
    for line in lines[start:]:
        if not line:
            continue
        m = re.match(r"^Chapter (\d+)$", line)
        if m:
            chapters.append({"n": int(m.group(1)), "heading": "", "verses": []})
            continue
        ch = chapters[-1]
        m = VERSE.match(line)
        if m:
            for n, t in split_inline(int(m.group(1) or m.group(2)), m.group(3)):
                ch["verses"].append({"n": n, "t": t})
        elif ch["verses"]:
            ch["verses"][-1]["t"] += " " + line
        else:
            ch["heading"] = (ch["heading"] + " " + line).strip()
    for i, ch in enumerate(chapters, 1):
        if ch["n"] != i:
            problems.append("chapter %d is numbered %d" % (i, ch["n"]))
        want = 1
        for v in ch["verses"]:
            if v["n"] != want:
                problems.append("chapter %d: verse %d follows %d" % (ch["n"], v["n"], want - 1))
            want = v["n"] + 1
    return chapters, problems


def main():
    os.makedirs(OUT, exist_ok=True)
    for slug, name in BOOKS.items():
        path = os.path.join(CACHE, slug + ".txt")
        if not os.path.exists(path):
            print("missing", path, file=sys.stderr)
            return 1
        chapters, problems = parse(open(path, encoding="utf-8").read())
        title = name.replace(" ", "_")
        data = {
            "slug": slug,
            "name": name,
            "language": "Ge'ez",
            "translation": "Wikisource",
            "source": {
                "title": "Translation:" + name,
                "url": "https://en.wikisource.org/wiki/Translation:" + title,
                "credit": "A working English translation from Ge’ez by Wikisource contributors",
                "short": "Wikisource",
                "church": "It is not a translation authorised by the Ethiopian Orthodox Tewahedo Church.",
                "note": "Verse numbers follow the Wikisource page; where it skips a number, so does this page.",
                "translator": {"type": "Organization", "name": "Wikisource contributors", "url": "https://en.wikisource.org/"},
                "licence": "CC BY-SA 4.0",
                "licence_url": "https://creativecommons.org/licenses/by-sa/4.0/",
            },
            "chapters": chapters,
        }
        with open(os.path.join(OUT, slug + ".json"), "w", encoding="utf-8") as f:
            json.dump(data, f, ensure_ascii=False, indent=1)
            f.write("\n")
        verses = sum(len(c["verses"]) for c in chapters)
        print("%s: %d chapters, %d verses" % (name, len(chapters), verses))
        for p in problems:
            print("   ", p)
    return 0


if __name__ == "__main__":
    sys.exit(main())

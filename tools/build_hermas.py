#!/usr/bin/env python3
"""Build the Shepherd of Hermas (data/further/hermas.json) from J. B. Lightfoot's translation.

Source: The Shepherd of Hermas, translated by J. B. Lightfoot (in The Apostolic Fathers, 1891), public domain.
The site owner supplied the text; the copy is kept in tools/.cache/hermas/hermas-lightfoot.txt (git-ignored).

The book is in three parts, Visions 1 to 5, Mandates 1 to 12 and Parables 1 to 10; each of the 27 is a chapter
here, in that order. Each paragraph keeps its printed reference ("3[11]:2": chapter 3 of the part, chapter 11
counted through the whole book, verse 2) as its label; the paragraphs are numbered 1, 2, 3 ... within the part
for the page's anchors. The text is not reworded. Some printed references repeat or skip (the copy has, for
example, two runs of "5[13]" in Vision 3, and five of "27[104]" in Parable 9): a run that starts again at verse 1
under the same reference is the next chapter, so it is numbered on from the chapter before, and the script
lists every reference it changed. A suffix "a" ("27[104]a:4") continues the chapter before.

Usage:  python3 tools/build_hermas.py
"""
import json, os, re, sys

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
SRC = os.path.join(ROOT, "tools", ".cache", "hermas", "hermas-lightfoot.txt")
OUT = os.path.join(ROOT, "data", "further", "hermas.json")
PART = re.compile(r"^(Vision|Mandate|Parable) (\d+)$")
VERSE = re.compile(r"^((\d+)(?:\[(\d+)a?\])?a?:(\d+))\s+(.*)$")


def main():
    if not os.path.exists(SRC):
        print("missing", SRC, file=sys.stderr)
        return 1
    chapters, problems = [], []
    for line in open(SRC, encoding="utf-8"):
        line = line.strip()
        if not line:
            continue
        m = PART.match(line)
        if m:
            chapters.append({"n": len(chapters) + 1, "title": line, "heading": "", "verses": []})
            continue
        if not chapters:
            continue  # the title and translator lines
        m = VERSE.match(line)
        ch = chapters[-1]
        if m:
            c, b, v = int(m.group(2)), int(m.group(3) or 0), int(m.group(4))
            if (c, b) == ch.get("_printed") and v <= ch["_v"]:
                ch["_actual"] = (ch["_actual"][0] + 1, ch["_actual"][1] + 1 if ch["_actual"][1] else 0)
            elif (c, b) != ch.get("_printed"):
                ch["_actual"] = (c, b)
            ch["_printed"], ch["_v"] = (c, b), v
            ac, ab = ch["_actual"]
            label = "%d[%d]:%d" % (ac, ab, v) if ab else "%d:%d" % (ac, v)
            if label != m.group(1).replace("a", ""):
                problems.append("%s: %s numbered %s" % (ch["title"], m.group(1), label))
            ch["verses"].append({"n": len(ch["verses"]) + 1, "l": label, "t": m.group(5).strip()})
        elif line == "Parables Which He Spake With Me":
            continue
        elif ch["verses"]:
            ch["verses"][-1]["t"] += " " + line
        else:
            ch["heading"] = (ch["heading"] + " " + line).strip()
    for ch in chapters:
        for k in ("_printed", "_v", "_actual"):
            ch.pop(k, None)
    data = {
        "slug": "hermas",
        "name": "Shepherd of Hermas",
        "language": "Greek",
        "translation": "J. B. Lightfoot",
        "source": {
            "title": "The Shepherd of Hermas, translated by J. B. Lightfoot",
            "url": "https://archive.org/details/apostolicfathers00ligh",
            "credit": "The translation from Greek by J. B. Lightfoot, in The Apostolic Fathers (London: Macmillan, 1891)",
            "short": "J. B. Lightfoot",
            "note": "Each paragraph keeps its printed reference: chapter of the part, with the chapter counted through the whole book in brackets, and verse.",
            "licence": "Public domain",
            "licence_url": "",
            "translator": {"type": "Person", "name": "J. B. Lightfoot", "url": "https://en.wikipedia.org/wiki/Joseph_Lightfoot"},
        },
        "chapters": chapters,
    }
    with open(OUT, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=1)
        f.write("\n")
    print("Shepherd of Hermas: %d parts, %d paragraphs" % (len(chapters), sum(len(c["verses"]) for c in chapters)))
    for p in problems:
        print("   ", p)
    return 0


if __name__ == "__main__":
    sys.exit(main())

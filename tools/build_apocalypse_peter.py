#!/usr/bin/env python3
"""Build the Apocalypse of Peter (data/further/apocalypse-of-peter.json) from Andrew Rutherfurd's translation.

Source: "The Apocalypse of Peter", translated by Andrew Rutherfurd, in The Ante-Nicene Fathers, Additional
Volume (Edinburgh: T. and T. Clark, 1896), public domain. The site owner supplied the text; the copy is kept in
tools/.cache/apocalypse-peter/apocalypse-peter.txt (git-ignored).

Chapter 1 is the Greek fragment found at Akhmim in 1886-87, verses 1 to 33 as the translation numbers them
(it marks some with a comma, "7,", and leaves 2 and 5 unmarked; their words stay with the verse before).
Chapter 2 is the five passages quoted by Clement of Alexandria, Macarius Magnes and Methodius. Not reworded.

Usage:  python3 tools/build_apocalypse_peter.py
"""
import json, os, re, sys

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
SRC = os.path.join(ROOT, "tools", ".cache", "apocalypse-peter", "apocalypse-peter.txt")
OUT = os.path.join(ROOT, "data", "further", "apocalypse-of-peter.json")


def split(text, step=3):
    """Split at verse numbers that rise by one to three: [{"n", "t"}], verse 1 at the start."""
    marks, last = [], 1
    for m in re.finditer(r"(?<![\w.])(\d{1,2})(?:\.\s+(?=[A-Z“\"])|,\s+(?=[a-z]))", text):
        n = int(m.group(1))
        if last < n <= last + step:
            marks.append((n, m.start(), m.end()))
            last = n
    starts = [(1, 0, 0)] + marks
    out = []
    for i, (n, s, e) in enumerate(starts):
        end = starts[i + 1][1] if i + 1 < len(starts) else len(text)
        out.append({"n": n, "t": re.sub(r"\s+", " ", text[e:end]).strip()})
    return out


def main():
    raw = open(SRC, encoding="utf-8").read()
    body, frags = raw.split("FRAGMENTS OF THE APOCALYPSE OF PETER.")
    body = re.sub(r"^\s*1?\s*", "", body)
    chapters = [
        {"n": 1, "title": "The Akhmim fragment", "heading": "", "verses": split(body.strip())},
        {"n": 2, "title": "Passages quoted by the Fathers", "heading": "", "verses": [{"n": i + 1, "t": t.strip()} for i, t in enumerate(re.split(r"\s*\n\s*\d\.\s+", "\n" + frags.strip())[1:])]},
    ]
    data = {
        "slug": "apocalypse-of-peter",
        "name": "Apocalypse of Peter",
        "language": "Greek",
        "translation": "Andrew Rutherfurd, 1896",
        "source": {
            "title": "The Apocalypse of Peter, translated by Andrew Rutherfurd (1896)",
            "url": "https://www.ccel.org/ccel/schaff/anf09.vii.ii.html",
            "credit": "The translation from Greek by Andrew Rutherfurd, in The Ante-Nicene Fathers, Additional Volume (Edinburgh: T. and T. Clark, 1896)",
            "short": "Andrew Rutherfurd, 1896",
            "note": "The text breaks off where the Akhmim manuscript does; the second chapter gives the passages the Fathers quote from the book.",
            "licence": "Public domain",
            "licence_url": "",
            "translator": {"type": "Person", "name": "Andrew Rutherfurd", "url": ""},
        },
        "chapters": chapters,
    }
    with open(OUT, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=1)
        f.write("\n")
    for c in chapters:
        print(c["title"], [v["n"] for v in c["verses"]])
    return 0


if __name__ == "__main__":
    sys.exit(main())

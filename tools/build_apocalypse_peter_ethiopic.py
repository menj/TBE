#!/usr/bin/env python3
"""Build the Ethiopic Apocalypse of Peter into data/further/apocalypse-of-peter.json, as its first chapters.

Source: the translation of the Ethiopic text by J. K. Elliott (The Apocryphal New Testament, Oxford: Clarendon
Press, 1993, 600-609), as printed in Bart D. Ehrman, Lost Scriptures (Oxford University Press, 2003), 280-289.
The site owner holds the rights to its use (owner decision, 9 November 2026, in .claude/CLAUDE.md). The PDF the
owner supplied is kept in tools/.cache/apocalypse-peter/ehrman.pdf (git-ignored).

The script reads the PDF's word positions (pdftotext -bbox-layout), keeps the two text columns of each page in
reading order, drops running heads, page numbers and footnotes, starts a chapter at each bold chapter number set
alone at the head of a paragraph (1 to 17), and starts a paragraph at each indented first line. Paragraphs are
numbered 1, 2, 3 ... within the chapter. Hyphens at line ends are joined. Not reworded.

Then it runs tools/build_apocalypse_peter.py's text (the Akhmim fragment and the quoted passages, Rutherfurd
1896) after it, as chapters 18 and 19.

Usage:  python3 tools/build_apocalypse_peter_ethiopic.py
"""
import html, json, os, re, subprocess, sys

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
PDF = os.path.join(ROOT, "tools", ".cache", "apocalypse-peter", "ehrman.pdf")


def lines():
    out = subprocess.run(["pdftotext", "-bbox-layout", PDF, "-"], capture_output=True, text=True).stdout
    for page in re.findall(r"<page.*?</page>", out, re.S)[3:]:
        cols = []
        for blk in re.findall(r"<block (.*?)>(.*?)</block>", page, re.S):
            for ln in re.findall(r"<line (.*?)>(.*?)</line>", blk[1], re.S):
                x = float(re.search(r'xMin="([\d.]+)"', ln[0]).group(1))
                y = float(re.search(r'yMin="([\d.]+)"', ln[0]).group(1))
                text = html.unescape(" ".join(re.findall(r"<word[^>]*>(.*?)</word>", ln[1])))
                cols.append((x, y, text))
        yield cols


def main():
    rows = []
    for cols in lines():
        body = [c for c in cols if 60 < c[1] < 600 and not re.match(r"^(THE APOCALYPSE OF PETER|\d{3})$", c[2])]
        left = [c for c in body if c[0] < 205]
        right = [c for c in body if c[0] >= 205]
        for col in (left, right):
            if not col:
                continue
            base = min(c[0] for c in col if not re.match(r"^\d{1,2}$", c[2]))
            for x, y, t in sorted(col, key=lambda c: c[1]):
                rows.append((x - base, t))
    chapters, cur = [], None
    started = glued = new = False
    for indent, t in rows:
        if re.match(r"^\d{1,2}$", t) and int(t) == len(chapters) + 1:
            cur = {"n": len(chapters) + 1, "title": "Chapter %s" % t, "heading": "", "verses": []}
            chapters.append(cur)
            started = True
            new = True
            glued = True
            continue
        if not started:
            continue
        if glued and cur["verses"]:
            glued = False  # the line after the chapter number's line continues it
        elif indent > 6 or new or not cur["verses"]:
            cur["verses"].append({"n": len(cur["verses"]) + 1, "t": t})
            new = False
            continue
        prev = cur["verses"][-1]
        prev["t"] = prev["t"][:-1] + t if prev["t"].endswith(("-", "­")) and t[:1].islower() else prev["t"] + " " + t
    ref = re.compile(r"^(\d{1,2}\s*)?((\d )?[A-Z][a-z]+\.? \d+:[\d,–\- ]+(; )?)*$")
    for c in chapters:
        for v in c["verses"]:
            v["t"] = re.sub(r"\s+", " ", v["t"].replace("\u00ad ", "").replace("\u00ad", "")).strip()
        c["verses"] = [v for v in c["verses"] if not ref.match(v["t"])]  # the footnotes themselves
        for v in c["verses"]:
            # A footnote the PDF set on the same line as the text ("against 4 Matt 17:4; Mark 9:5; Luke 9: you").
            v["t"] = re.sub(r"\s\d{1,2}(?: (?:\d )?[A-Z][a-z]+\.? \d+:[\d,–\-]*;?)+(?=\s)", "", v["t"])
            v["t"] = re.sub(r"(?<=[.”’,;:?!])\s?\d{1,2}(?=\s|$)", "", v["t"]).strip()  # footnote marks
        for i, v in enumerate(c["verses"]):
            v["n"] = i + 1
    return chapters


def build():
    eth = main()
    out = os.path.join(ROOT, "data", "further", "apocalypse-of-peter.json")
    subprocess.run([sys.executable, os.path.join(ROOT, "tools", "build_apocalypse_peter.py")], check=True, capture_output=True)
    data = json.load(open(out, encoding="utf-8"))
    greek = data["chapters"]
    for i, c in enumerate(greek):
        c["n"] = len(eth) + i + 1
        c["title"] = {"The Akhmim fragment": "Greek: the Akhmim fragment", "Passages quoted by the Fathers": "Greek: passages quoted by the Fathers"}.get(c["title"], c["title"])
    for c in eth:
        c["title"] = "Ethiopic, chapter %d" % c["n"]
    data["chapters"] = eth + greek
    data["translation"] = "J. K. Elliott (Ethiopic), Andrew Rutherfurd (Greek)"
    data["source"] = {
        "title": "Bart D. Ehrman, Lost Scriptures (2003), with J. K. Elliott's translation",
        "url": "https://global.oup.com/academic/product/lost-scriptures-9780195141825",
        "credit": "Chapters 1 to 17: the Ethiopic text in the translation by J. K. Elliott, The Apocryphal New Testament (Oxford: Clarendon Press, 1993), as printed in Bart D. Ehrman, Lost Scriptures (Oxford University Press, 2003)",
        "short": "J. K. Elliott and Andrew Rutherfurd",
        "note": "Used with the site owner's permission. The last two chapters give the Greek text, the Akhmim fragment and the passages the Fathers quote, in Andrew Rutherfurd's public-domain translation (The Ante-Nicene Fathers, Additional Volume, 1896). Paragraphs are numbered within each chapter.",
        "licence": "Used by permission",
        "licence_url": "",
        "translator": {"type": "Person", "name": "J. K. Elliott", "url": ""},
    }
    with open(out, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=1)
        f.write("\n")
    print("Apocalypse of Peter: %d Ethiopic chapters (%d paragraphs), then the Greek" % (len(eth), sum(len(c["verses"]) for c in eth)))


if __name__ == "__main__":
    sys.exit(build())

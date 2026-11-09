#!/usr/bin/env python3
"""Build the deuterocanonical books and Ezra Sutu'el from the King James Version Apocrypha (data/further/).

Source: the King James Version with the Apocrypha (1769 Oxford text), public domain, in the USFM files of
eBible.org (https://ebible.org/Scriptures/eng-kjv_usfm.zip). The script downloads them once into
tools/.cache/kjva/ (git-ignored).

Each book of the prepared sections of data/canons.json that the KJV Apocrypha has is written as
data/further/{slug}.json. Two are put together from parts: the Additions to Daniel (the Song of the Three, Susanna,
Bel and the Dragon, each a titled chapter) and Ezra Sutu'el, which is chapters 3 to 14 of 2 Esdras (4 Ezra), the
book the Ethiopian church reads; its chapters keep their 2 Esdras numbers as titles. Footnotes and the KJV's
italics marks are left out; the words are the KJV's.

Usage:  python3 tools/build_kjva.py
"""
import json, os, re, sys, urllib.request, zipfile

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
CACHE = os.path.join(ROOT, "tools", ".cache", "kjva")
OUT = os.path.join(ROOT, "data", "further")
URL = "https://ebible.org/Scriptures/eng-kjv_usfm.zip"
UA = "thebible.exposed-importer/1.0 (https://thebible.exposed/)"
# slug: (name, [(USFM code, title or None, first chapter, last chapter)])
BOOKS = {
    "tobit": ("Tobit", [("TOB", None, 1, 99)]),
    "judith": ("Judith", [("JDT", None, 1, 99)]),
    "wisdom": ("Wisdom of Solomon", [("WIS", None, 1, 99)]),
    "sirach": ("Sirach (Ecclesiasticus)", [("SIR", None, 1, 99)]),
    "baruch": ("Baruch, with the Letter of Jeremiah", [("BAR", None, 1, 99)]),
    "1-maccabees": ("1 Maccabees", [("1MA", None, 1, 99)]),
    "2-maccabees": ("2 Maccabees", [("2MA", None, 1, 99)]),
    "esther-additions": ("Additions to Esther", [("ESG", "chapters", 1, 99)]),
    "daniel-additions": ("Additions to Daniel", [("S3Y", "The Song of the Three Holy Children", 1, 99), ("SUS", "Susanna", 1, 99), ("BEL", "Bel and the Dragon", 1, 99)]),
    "1-esdras": ("1 Esdras", [("1ES", None, 1, 99)]),
    "prayer-of-manasseh": ("Prayer of Manasseh", [("MAN", None, 1, 99)]),
    "4-ezra": ("Ezra Sutu'el (4 Ezra)", [("2ES", "2 Esdras", 3, 14)]),
}


def usfm():
    path = os.path.join(CACHE, "eng-kjv_usfm.zip")
    if not os.path.exists(path):
        os.makedirs(CACHE, exist_ok=True)
        with urllib.request.urlopen(urllib.request.Request(URL, headers={"User-Agent": UA}), timeout=300) as r:
            open(path, "wb").write(r.read())
    z = zipfile.ZipFile(path)
    files = {}
    for name in z.namelist():
        m = re.match(r"\d+-([0-9A-Z]{3})eng-kjv\.usfm$", os.path.basename(name))
        if m:
            files[m.group(1)] = z.read(name).decode("utf-8-sig")
    return files


def clean(text):
    text = re.sub(r"\\f .*?\\f\*", "", text)  # footnotes
    text = re.sub(r"\\x .*?\\x\*", "", text)  # cross references
    text = re.sub(r"\\w ([^|\\]*)\|[^\\]*\\w\*", r"\1", text)
    text = re.sub(r"\\\+?[a-z]+\d?\*?", "", text)  # character markers (\add, \nd, \wj ...), opening and closing
    return re.sub(r"\s+", " ", text).strip()


def chapters_of(src):
    """{chapter: [(verse, text)]} from a USFM book."""
    out, ch = {}, 0
    for line in src.splitlines():
        m = re.match(r"\\c (\d+)", line)
        if m:
            ch = int(m.group(1))
            out[ch] = []
            continue
        if not ch:
            continue
        for part in re.split(r"(?=\\v \d+)", line):
            m = re.match(r"\\v (\d+)\S* ?(.*)", part)
            if m:
                out[ch].append([int(m.group(1)), m.group(2)])
            elif out[ch] and not re.match(r"\\(c|s\d?|ms|mt\d?|h|toc\d|id|ide|d|r)\b", part):
                out[ch][-1][1] += " " + re.sub(r"^\\(p|q\d?|m|pi\d?|nb|b|li\d?)\b", "", part)
    return {c: [(v, clean(t)) for v, t in vs if clean(t)] for c, vs in out.items()}


def main():
    files = usfm()
    for slug, (name, parts) in BOOKS.items():
        chapters = []
        for code, title, lo, hi in parts:
            src = chapters_of(files[code])
            for c in sorted(src):
                if lo <= c <= hi:
                    ch = {"n": len(chapters) + 1, "heading": "", "verses": [{"n": v, "t": t} for v, t in src[c]]}
                    if title == "2 Esdras":
                        ch["title"] = "2 Esdras %d" % c
                    elif title == "chapters":
                        ch["title"] = "Chapter %d" % c
                    elif title:
                        ch["title"] = title + ("" if len(src) == 1 else " %d" % c)
                    chapters.append(ch)
        if any("title" in c for c in chapters):
            for c in chapters:
                c.setdefault("title", "Chapter %d" % c["n"])
        note = "The King James Version of 1611 in the Oxford text of 1769, from eBible.org."
        if slug == "4-ezra":
            note = "Ezra Sutu'el is chapters 3 to 14 of 2 Esdras (4 Ezra); the KJV translates the Latin, while the Ethiopian church reads the Ge'ez. " + note
        data = {
            "slug": slug,
            "name": name,
            "language": "Greek" if slug not in ("4-ezra",) else "Latin (translated); Ge'ez in the Ethiopian church",
            "translation": "King James Version",
            "source": {
                "title": "King James Version with the Apocrypha (eBible.org)",
                "url": "https://ebible.org/kjv/",
                "credit": "The King James Version",
                "short": "King James Version",
                "note": note,
                "licence": "Public domain",
                "licence_url": "",
                "translator": {"type": "Organization", "name": "The King James Version translators", "url": ""},
            },
            "chapters": chapters,
        }
        with open(os.path.join(OUT, slug + ".json"), "w", encoding="utf-8") as f:
            json.dump(data, f, ensure_ascii=False, indent=1)
            f.write("\n")
        print("%s: %d chapters, %d verses" % (name, len(chapters), sum(len(c["verses"]) for c in chapters)))
    return 0


if __name__ == "__main__":
    sys.exit(main())

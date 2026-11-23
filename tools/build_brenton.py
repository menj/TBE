#!/usr/bin/env python3
"""Build 3 Maccabees, 4 Maccabees and Psalm 151 (data/further/) from Brenton's English Septuagint.

Source: Sir Lancelot C. L. Brenton, The Septuagint Version of the Old Testament, translated into English (London:
Samuel Bagster and Sons, 1844), public domain, in the USFM files of eBible.org
(https://ebible.org/Scriptures/eng-Brenton_usfm.zip), downloaded once into tools/.cache/brenton/ (git-ignored).
These are the three books of the prepared sections that the King James Apocrypha does not have. The USFM is read
with tools/build_kjva.py's parser; footnotes and italics marks are left out.

Usage:  python3 tools/build_brenton.py
"""
import json, os, re, sys, urllib.request, zipfile

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
sys.path.insert(0, os.path.join(ROOT, "tools"))
from build_kjva import chapters_of  # noqa: E402

CACHE = os.path.join(ROOT, "tools", ".cache", "brenton")
OUT = os.path.join(ROOT, "data", "further")
URL = "https://ebible.org/Scriptures/eng-Brenton_usfm.zip"
UA = "thebible.exposed-importer/1.0 (https://thebible.exposed/)"
# slug: (name, USFM code, chapters to take, title of each chapter or None)
BOOKS = {
    "3-maccabees": ("3 Maccabees", "3MA", None, None),
    "4-maccabees": ("4 Maccabees", "4MA", None, None),
    "psalm-151": ("Psalm 151", "PSA", [151], "Psalm 151"),
}


def usfm():
    path = os.path.join(CACHE, "eng-Brenton_usfm.zip")
    if not os.path.exists(path):
        os.makedirs(CACHE, exist_ok=True)
        with urllib.request.urlopen(urllib.request.Request(URL, headers={"User-Agent": UA}), timeout=300) as r:
            open(path, "wb").write(r.read())
    z = zipfile.ZipFile(path)
    files = {}
    for name in z.namelist():
        m = re.match(r"\d+-([0-9A-Z]{3})eng-Brenton\.usfm$", os.path.basename(name))
        if m:
            files[m.group(1)] = z.read(name).decode("utf-8-sig")
    return files


def main():
    files = usfm()
    for slug, (name, code, only, title) in BOOKS.items():
        src = chapters_of(files[code])
        chapters = []
        for c in sorted(src):
            if only and c not in only:
                continue
            ch = {"n": len(chapters) + 1, "heading": "", "verses": [{"n": v, "t": t} for v, t in src[c]]}
            if title:
                ch["title"] = title
            chapters.append(ch)
        data = {
            "slug": slug,
            "name": name,
            "language": "Greek",
            "translation": "Brenton, 1844",
            "source": {
                "title": "Brenton's English Septuagint (eBible.org)",
                "url": "https://ebible.org/eng-Brenton/",
                "credit": "The translation from the Greek Septuagint by Sir Lancelot C. L. Brenton (London: Samuel Bagster and Sons, 1844)",
                "short": "Brenton, 1844",
                "note": "",
                "licence": "Public domain",
                "licence_url": "",
                "translator": {"type": "Person", "name": "Lancelot C. L. Brenton", "url": "https://en.wikipedia.org/wiki/Lancelot_Charles_Lee_Brenton"},
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

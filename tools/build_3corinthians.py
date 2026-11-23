#!/usr/bin/env python3
"""Build 3 Corinthians (data/further/3-corinthians.json) from M. R. James's translation.

Source: M. R. James, The Apocryphal New Testament (Oxford: Clarendon Press, 1924), the Acts of Paul, part VII,
public domain, as transcribed on earlychristianwritings.com (actspaul.html), fetched once into tools/.cache/misc/
(git-ignored). The exchange has three parts, each a chapter here: the Corinthians' letter to Paul (I), the
deacons bringing it (II) and Paul's reply (III). James marks additions of single manuscripts in capitals and in
brackets; they are kept as he printed them. The text ends before his note "[Laon adds ...".

Usage:  python3 tools/build_3corinthians.py
"""
import html, json, os, re, sys, urllib.request

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
CACHE = os.path.join(ROOT, "tools", ".cache", "misc", "actspaul.html")
URL = "https://www.earlychristianwritings.com/text/actspaul.html"
UA = "thebible.exposed-importer/1.0 (https://thebible.exposed/)"
TITLES = {"I": "The Corinthians to Paul", "II": "The letter brought to Paul", "III": "Paul to the Corinthians"}


def main():
    if not os.path.exists(CACHE):
        os.makedirs(os.path.dirname(CACHE), exist_ok=True)
        with urllib.request.urlopen(urllib.request.Request(URL, headers={"User-Agent": UA}), timeout=60) as r:
            open(CACHE, "wb").write(r.read())
    raw = open(CACHE, encoding="latin-1").read()
    t = html.unescape(re.sub(r"<[^>]+>", "\n", re.sub(r"(?is)<(script|style).*?</\1>", "", raw)))
    start = t.index("I. 1 Stephanus")
    end = t.index("[Laon adds", start)
    body = re.sub(r"\s+", " ", t[start:end]).strip()
    body = re.sub(r"(\w)- (?=[a-z])", r"\1", body)  # a word broken at a line end ("trans- gressed")
    parts = re.split(r"(?:^| )(I|II|III)\.\s?(?=1 )", body)
    chapters = []
    for roman, text in zip(parts[1::2], parts[2::2]):
        marks, want = [], 1
        for m in re.finditer(r"(?<![\w(])(\d{1,2}) (?=\S)", text):
            n = int(m.group(1))
            if want <= n <= want + 1:  # a number the transcription lost is skipped; its words stay with the verse before
                marks.append((n, m.start(), m.end()))
                want = n + 1
        verses = []
        for i, (v, s, e) in enumerate(marks):
            stop = marks[i + 1][1] if i + 1 < len(marks) else len(text)
            verses.append({"n": v, "t": text[e:stop].strip()})
        chapters.append({"n": len(chapters) + 1, "title": TITLES[roman], "heading": "", "verses": verses})
    data = {
        "slug": "3-corinthians",
        "name": "3 Corinthians (from the Acts of Paul)",
        "language": "Greek, in Latin, Coptic and Armenian copies",
        "translation": "M. R. James, 1924",
        "source": {
            "title": "M. R. James, The Apocryphal New Testament (1924), the Acts of Paul",
            "url": URL,
            "credit": "The translation by M. R. James, The Apocryphal New Testament (Oxford: Clarendon Press, 1924), as transcribed on earlychristianwritings.com",
            "short": "M. R. James, 1924",
            "note": "Words in capitals and in brackets are James's marks for what only some manuscripts have.",
            "licence": "Public domain",
            "licence_url": "",
            "translator": {"type": "Person", "name": "M. R. James", "url": "https://en.wikipedia.org/wiki/M._R._James"},
        },
        "chapters": chapters,
    }
    with open(os.path.join(ROOT, "data", "further", "3-corinthians.json"), "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=1)
        f.write("\n")
    print("3 Corinthians:", [(c["title"], len(c["verses"])) for c in chapters])
    return 0


if __name__ == "__main__":
    sys.exit(main())

#!/usr/bin/env python3
"""Build 1 Clement, 2 Clement, the Didache and Barnabas (data/further/) from J. B. Lightfoot's translations.

Source: J. B. Lightfoot and J. R. Harmer, The Apostolic Fathers (London: Macmillan, 1891), public domain, in the
transcriptions on earlychristianwritings.com (Peter Kirby). The script fetches each page once, a second apart, into
tools/.cache/apostolic/ (git-ignored) and reads it from there. The pages mark each verse "1Clem 1:1" (or "1:1" in
the Didache); the script splits at the markers and keeps the words. 1 Clement's opening greeting (its "prologue")
is the heading of chapter 1. The Didache marks Lightfoot's scripture quotations with braces; they are dropped.

Usage:  python3 tools/build_apostolic.py
"""
import html, json, os, re, sys, time, urllib.request

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
CACHE = os.path.join(ROOT, "tools", ".cache", "apostolic")
OUT = os.path.join(ROOT, "data", "further")
BASE = "https://www.earlychristianwritings.com/text/"
UA = "thebible.exposed-importer/1.0 (https://thebible.exposed/)"
BOOKS = {
    "1-clement": ("1 Clement", "1clement", r"1Clem"),
    "2-clement": ("2 Clement", "2clement", r"2Clem"),
    "didache": ("Didache (Teaching of the Twelve Apostles)", "didache", r""),
    "barnabas": ("Epistle of Barnabas", "barnabas", r"Barnabas"),
}


def page(stem):
    path = os.path.join(CACHE, stem + ".html")
    if not os.path.exists(path):
        os.makedirs(CACHE, exist_ok=True)
        with urllib.request.urlopen(urllib.request.Request(BASE + stem + "-lightfoot.html", headers={"User-Agent": UA}), timeout=60) as r:
            open(path, "wb").write(r.read())
        time.sleep(1)
    return open(path, encoding="latin-1").read()


def text_of(raw):
    raw = re.sub(r"(?is)<(script|style).*?</\1>", "", raw)
    t = html.unescape(re.sub(r"<[^>]+>", " ", raw))
    return re.sub(r"\s+", " ", t)


def main():
    for slug, (name, stem, prefix) in BOOKS.items():
        t = text_of(page(stem))
        mark = re.compile((prefix + r"\s+" if prefix else r"(?<![\w:])") + r"(prologue|\d+):(\d+)\b")
        hits = list(mark.finditer(t))
        if not prefix:  # the Didache: from its first verse
            first = next(i for i, m in enumerate(hits) if m.group(1) == "1" and m.group(2) == "1")
            hits = hits[first:]
        chapters, heading = {}, ""
        for i, m in enumerate(hits):
            end = hits[i + 1].start() if i + 1 < len(hits) else len(t)
            words = t[m.end():end].strip()
            if i + 1 == len(hits):  # the last verse runs into the page's footer
                words = re.split(r"\s(?:Go to the Chronological|Home|Previous|Next|Copyright|Kirby|Early Christian Writings)\b", words)[0].strip()
            words = re.sub(r"[{}]", "", words).strip()
            words = re.sub(r"\s+([,.;:?!)])", r"\1", words)
            if m.group(1) == "prologue":
                heading = words
                continue
            chapters.setdefault(int(m.group(1)), []).append({"n": int(m.group(2)), "t": words})
        out = [{"n": c, "heading": heading if c == 1 else "", "verses": chapters[c]} for c in sorted(chapters)]
        problems = [c["n"] for i, c in enumerate(out, 1) if c["n"] != i]
        data = {
            "slug": slug,
            "name": name,
            "language": "Greek",
            "translation": "J. B. Lightfoot, 1891",
            "source": {
                "title": "J. B. Lightfoot and J. R. Harmer, The Apostolic Fathers (1891)",
                "url": BASE + stem + "-lightfoot.html",
                "credit": "The translation from Greek by J. B. Lightfoot, in The Apostolic Fathers (London: Macmillan, 1891), as transcribed on earlychristianwritings.com",
                "short": "J. B. Lightfoot, 1891",
                "note": "",
                "licence": "Public domain",
                "licence_url": "",
                "translator": {"type": "Person", "name": "J. B. Lightfoot", "url": "https://en.wikipedia.org/wiki/Joseph_Lightfoot"},
            },
            "chapters": out,
        }
        with open(os.path.join(OUT, slug + ".json"), "w", encoding="utf-8") as f:
            json.dump(data, f, ensure_ascii=False, indent=1)
            f.write("\n")
        print("%s: %d chapters, %d verses%s" % (name, len(out), sum(len(c["verses"]) for c in out), "; chapters out of order: %s" % problems if problems else ""))
    return 0


if __name__ == "__main__":
    sys.exit(main())

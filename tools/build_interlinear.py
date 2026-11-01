#!/usr/bin/env python3
"""Build the word-by-word interlinear data (data/interlinear/{book}/{chapter}.json).

Source: STEPBible-Data (https://github.com/STEPBible/STEPBible-Data), CC BY 4.0,
data created by STEPBible.org based on work at Tyndale House Cambridge.
  TAHOT  Translators Amalgamated Hebrew OT  (Leningrad Codex, tagged, ETCBC morphology)

The raw files are NOT stored in this repository (the licence asks that they are not
redistributed). This script downloads them from GitHub into tools/.cache/ (git-ignored),
politely and only if missing, then writes a compact, reformatted copy of the data.

Usage:  python3 tools/build_interlinear.py            # download if needed, then build
        python3 tools/build_interlinear.py --src DIR  # use files already in DIR
"""
import argparse, collections, json, os, re, sys, time, urllib.parse, urllib.request

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
OUT = os.path.join(ROOT, "data", "interlinear")
BASE = "https://raw.githubusercontent.com/STEPBible/STEPBible-Data/master/Translators%20Amalgamated%20OT%2BNT/"
FILES = {
    "tahot1.txt": "TAHOT Gen-Deu - Translators Amalgamated Hebrew OT - STEPBible.org CC BY.txt",
    "tahot2.txt": "TAHOT Jos-Est - Translators Amalgamated Hebrew OT - STEPBible.org CC BY.txt",
    "tahot3.txt": "TAHOT Job-Sng - Translators Amalgamated Hebrew OT - STEPBible.org CC BY.txt",
    "tahot4.txt": "TAHOT Isa-Mal - Translators Amalgamated Hebrew OT - STEPBible.org CC BY.txt",
}
# STEPBible's three-letter book codes for the Old Testament, in canonical order (same order as data/books.json).
OT = "Gen Exo Lev Num Deu Jos Jdg Rut 1Sa 2Sa 1Ki 2Ki 1Ch 2Ch Ezr Neh Est Job Psa Pro Ecc Sng Isa Jer Lam Ezk Dan Hos Jol Amo Oba Jon Mic Nam Hab Zep Hag Zec Mal".split()


def slug_map():
    books = json.load(open(os.path.join(ROOT, "data", "books.json"), encoding="utf8"))
    ot = [b["slug"] for b in books if b["testament"] == "OT"]
    assert len(ot) == len(OT)
    return dict(zip(OT, ot))


def fetch_sources(dest):
    os.makedirs(dest, exist_ok=True)
    for local, remote in FILES.items():
        path = os.path.join(dest, local)
        if os.path.exists(path) and os.path.getsize(path) > 1000:
            continue
        url = BASE + urllib.parse.quote(remote)
        print("downloading", remote, flush=True)
        req = urllib.request.Request(url, headers={"User-Agent": "thebible.exposed-importer/1.0"})
        with urllib.request.urlopen(req, timeout=120) as r, open(path, "wb") as f:
            f.write(r.read())
        time.sleep(2)


def clean_heb(s):
    return s.replace("/", "").replace("\\", "")


def clean_gloss(s):
    s = s.replace("/ ", " ").replace("/", "").replace("\\", "")
    return s.replace("<", "‹").replace(">", "›")


def strong_base(code):
    """H7225G -> H7225, G2316G -> G2316"""
    m = re.match(r"([HG]\d{1,4})", code or "")
    return m.group(1) if m else ""


def parse_hebrew(path, rows):
    pat = re.compile(r"^([1-3A-Za-z]{3})\.(\d+)\.(\d+)(?:\([^)]*\))?#(\d+)=([^\t]*)\t")
    for line in open(path, encoding="utf8"):
        m = pat.match(line)
        if not m:
            continue
        cols = line.rstrip("\n").split("\t")
        if len(cols) < 6:
            continue
        book, ch, vs, _, typ = m.group(1), int(m.group(2)), int(m.group(3)), m.group(4), m.group(5)
        dstr = cols[4]
        root = re.search(r"\{([HG]\d{1,4})", dstr)
        strongs = root.group(1) if root else strong_base(dstr)
        lemma = ""
        if len(cols) > 11:
            lm = re.search(r"\{[HG]\d+[A-Za-z]?=([^=}]+)=", cols[11])
            lemma = lm.group(1) if lm else ""
        word = [clean_heb(cols[1]), clean_heb(cols[2]), clean_gloss(cols[3]), strongs, cols[5].replace("\\", ""), lemma]
        extra = {}
        if typ[:1] in "QRX":
            extra["t"] = typ[:1]
        if len(cols) > 6 and cols[6].strip():
            extra["v"] = re.sub(r"<[^>]+>", "", cols[6]).strip()
        if extra:
            word.append(extra)
        rows[(book, ch)][vs].append(word)


def write_chapters(rows, slugs, lang):
    count = 0
    for (book, ch), verses in rows.items():
        slug = slugs.get(book)
        if not slug:
            continue
        folder = os.path.join(OUT, slug)
        os.makedirs(folder, exist_ok=True)
        data = {"lang": lang, "v": {str(v): verses[v] for v in sorted(verses)}}
        with open(os.path.join(folder, "%d.json" % ch), "w", encoding="utf8") as f:
            json.dump(data, f, ensure_ascii=False, separators=(",", ":"))
        count += 1
    return count


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--src", default=os.path.join(ROOT, "tools", ".cache"), help="folder holding (or to receive) the source files")
    args = ap.parse_args()
    fetch_sources(args.src)
    ot = slug_map()

    heb = collections.defaultdict(lambda: collections.defaultdict(list))
    for n in ("tahot1.txt", "tahot2.txt", "tahot3.txt", "tahot4.txt"):
        parse_hebrew(os.path.join(args.src, n), heb)

    chapters = write_chapters(heb, ot, "he")
    words = sum(len(w) for vs in heb.values() for w in vs.values())
    print("chapters: %d; words: %d" % (chapters, words))


if __name__ == "__main__":
    main()

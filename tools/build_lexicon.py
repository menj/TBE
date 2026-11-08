#!/usr/bin/env python3
"""Build the Strong's lexicon (data/lexicon/) from STEPBible's brief lexicons and the interlinear data.

Source: STEPBible-Data (https://github.com/STEPBible/STEPBible-Data), CC BY 4.0, by STEPBible.org:
  TBESG  Translators Brief lexicon of Extended Strongs for Greek (definitions after Abbott-Smith and others)
  TBESH  Translators Brief lexicon of Extended Strongs for Hebrew (definitions after the abridged BDB)
As with the interlinear, the raw files are not stored in this repository: this script downloads them into
tools/.cache/lexicon/ (git-ignored) when missing, and writes a compact, reformatted copy.

Writes:
  data/lexicon/{g|h}/{n}.json   the entries for Strong's numbers n*100 to n*100+99, keyed "G3056":
                                {"s": [senses], "n": occurrences in the interlinear, "r": up to 120 references}
                                A sense is {"e": extended number, "w": word, "t": transliteration, "p": part of
                                speech, "g": gloss, "d": definition}. In "d", <b>, <i> and line breaks stay,
                                other markup goes, and a scripture reference becomes [[slug c v|label]].
  data/lexicon/{g|h}-index.json  every number with its word, gloss and count, for the lists by letter.

Usage:  python3 tools/build_lexicon.py
"""
import collections, html, json, os, re, sys, unicodedata, urllib.parse, urllib.request

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
CACHE = os.path.join(ROOT, "tools", ".cache", "lexicon")
OUT = os.path.join(ROOT, "data", "lexicon")
BASE = "https://raw.githubusercontent.com/STEPBible/STEPBible-Data/master/Lexicons/"
FILES = {
    "G": "TBESG - Translators Brief lexicon of Extended Strongs for Greek - STEPBible.org CC BY.txt",
    "H": "TBESH - Translators Brief lexicon of Extended Strongs for Hebrew - STEPBible.org CC BY.txt",
}
REFS = 120

# STEPBible's book codes in canonical order, the same order as data/books.json.
CODES = ("Gen Exo Lev Num Deu Jos Jdg Rut 1Sa 2Sa 1Ki 2Ki 1Ch 2Ch Ezr Neh Est Job Psa Pro Ecc Sng Isa Jer Lam "
         "Ezk Dan Hos Jol Amo Oba Jon Mic Nam Hab Zep Hag Zec Mal Mat Mrk Luk Jhn Act Rom 1Co 2Co Gal Eph Php "
         "Col 1Th 2Th 1Ti 2Ti Tit Phm Heb Jas 1Pe 2Pe 1Jn 2Jn 3Jn Jud Rev").split()


def source(kind):
    path = os.path.join(CACHE, "tbes%s.txt" % kind)
    if not os.path.exists(path):
        os.makedirs(CACHE, exist_ok=True)
        url = BASE + urllib.parse.quote(FILES[kind])
        print("downloading", FILES[kind])
        req = urllib.request.Request(url, headers={"User-Agent": "thebible.exposed-importer/1.0"})
        with urllib.request.urlopen(req, timeout=120) as r, open(path, "wb") as f:
            f.write(r.read())
    return path


def slugs():
    books = json.load(open(os.path.join(ROOT, "data", "books.json"), encoding="utf8"))
    return {code: b["slug"] for code, b in zip(CODES, books)}


def definition(raw, books):
    """Keep <b>, <i> and line breaks; turn <ref='Mat.8.8'>..</ref> into [[matthew 8 8|label]]; drop the rest."""
    def ref(m):
        target, label = m.group(1), html.unescape(re.sub(r"<[^>]+>", "", m.group(2)))
        hit = re.match(r"^(\w+)\.(\d+)\.(\d+)", target)
        if not hit or hit.group(1) not in books:
            return label
        return "[[%s %s %s|%s]]" % (books[hit.group(1)], hit.group(2), hit.group(3), label.replace("|", "/").replace("]", ")"))

    text = re.sub(r"<ref='([^']*)'>(.*?)</ref>", ref, raw or "", flags=re.S)
    text = re.sub(r"<br\s*/?>", "\n", text, flags=re.I)
    text = re.sub(r"<(/?)(b|i)>", lambda m: "\x01%s%s\x02" % (m.group(1), m.group(2).lower()), text, flags=re.I)
    text = html.unescape(re.sub(r"<[^>]+>", "", text))
    text = text.replace("<", "&lt;").replace(">", "&gt;").replace("\x01", "<").replace("\x02", ">")
    text = re.sub(r"[ \t]+", " ", text)
    text = re.sub(r"\s*\n\s*", "\n", text).strip()
    return re.sub(r"__", "", text)


def fold(word):
    """A word without accents or breathings, for sorting and for matching Metzger's list."""
    word = unicodedata.normalize("NFD", word)
    return "".join(c for c in word if not unicodedata.combining(c)).lower().replace("ς", "σ")


def occurrences():
    """Strong's number -> (count, [[slug, chapter, verse], ...]) from the interlinear, in canonical order."""
    books = [b["slug"] for b in json.load(open(os.path.join(ROOT, "data", "books.json"), encoding="utf8"))]
    count = collections.Counter()
    refs = collections.defaultdict(list)
    for slug in books:
        folder = os.path.join(ROOT, "data", "interlinear", slug)
        if not os.path.isdir(folder):
            continue
        for ch in sorted(int(f[:-5]) for f in os.listdir(folder) if f.endswith(".json")):
            data = json.load(open(os.path.join(folder, "%d.json" % ch), encoding="utf8"))
            for v in sorted(data["v"], key=int):
                seen = set()
                for w in data["v"][v]:
                    for num in re.findall(r"[GH]\d{4}", w[3]):
                        count[num] += 1
                        if num not in seen and len(refs[num]) < REFS:
                            refs[num].append([slug, ch, int(v)])
                        seen.add(num)
    return count, refs


def main():
    books = slugs()
    count, refs = occurrences()
    for kind in "GH":
        entries = collections.OrderedDict()
        for line in open(source(kind), encoding="utf-8-sig"):
            row = unicodedata.normalize("NFC", line).rstrip("\n").split("\t")  # one code point per accented letter
            # Hebrew senses can sit under "H0122a", "H0122b": they belong to H0122.
            if len(row) < 8 or not re.match(r"^[GH]\d{4}[a-z]?$", row[0]):
                continue
            num = row[0][:5]
            # Column 2 holds the sense's own extended number ("H0430G = a Name of"); column 3 can name another entry.
            sense = {"e": row[1].split("=")[0].strip(), "w": row[3].strip(), "t": row[4].strip(), "p": row[5].strip(),
                     "g": row[6].strip(), "d": definition(row[7], books)}
            if not sense["d"]:
                del sense["d"]
            entries.setdefault(num, []).append(sense)
        shards = collections.defaultdict(dict)
        index = []
        for num, senses in entries.items():
            shards[int(num[1:]) // 100][num] = {"s": senses, "n": count.get(num, 0), "r": refs.get(num, [])}
            index.append([num, senses[0]["w"], senses[0]["g"], count.get(num, 0)])
        folder = os.path.join(OUT, kind.lower())
        os.makedirs(folder, exist_ok=True)
        for n, shard in shards.items():
            with open(os.path.join(folder, "%d.json" % n), "w", encoding="utf8") as f:
                json.dump(shard, f, ensure_ascii=False, separators=(",", ":"))
        index.sort(key=lambda r: (fold(r[1]), r[0]))
        with open(os.path.join(OUT, "%s-index.json" % kind.lower()), "w", encoding="utf8") as f:
            json.dump(index, f, ensure_ascii=False, separators=(",", ":"))
        used = sum(1 for num in entries if count.get(num))
        print("%s: %d numbers in %d files, %d of them in the interlinear" % (kind, len(entries), len(shards), used))
        missing = [n for n in count if n[0] == kind and n not in entries]
        if missing:
            print("  in the interlinear but not the lexicon:", len(missing), sorted(missing)[:10])


if __name__ == "__main__":
    sys.exit(main())

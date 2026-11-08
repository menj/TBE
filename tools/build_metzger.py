#!/usr/bin/env python3
"""Build data/lexicon/metzger.json from Bruce M. Metzger, Lexical Aids for Students of New Testament Greek.

The owner holds the rights to the book and serves the scan at https://thebible.exposed/pdf/lexical-aids.pdf
(owner decision of 7 November 2026, in .claude/CLAUDE.md and SSOT.md). The scan is page images only, two book
pages to an image, turned on their side. This script:
  1. downloads the PDF into tools/.cache/metzger/ (or takes --pdf PATH);
  2. extracts the page images (pdfimages), turns them upright and reads them with Tesseract (Greek and English),
     caching the text of each image, so a second run reads nothing again;
  3. parses Part I, "Words classified according to their frequency": each "WORDS OCCURRING ... TIMES" heading
     starts a group, and each line that begins with a Greek word is an entry, its wrapped lines joined to it;
  4. matches each word to its Strong's number through data/lexicon/g-index.json: accents and breathings
     ignored, each half of "X or Y", a deponent verb under its active form as STEP files it; failing that, a
     close spelling (an OCR slip) whose count in the interlinear fits the group; among several, the one whose
     count falls nearest the group's;
  5. writes {"groups": [{"title", "min", "max", "words": [{"w", "g", "n"}]}], "words": {"G3056": {"w", "g",
     "group"}}}. An entry the script cannot match keeps "n": "" and is listed on the page without a link.

Needs: pdfimages (poppler-utils), tesseract with the "grc" and "eng" languages, Pillow.
Usage:  python3 tools/build_metzger.py [--pdf PATH]
"""
import argparse, glob, json, os, re, subprocess, sys, unicodedata, urllib.request

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
CACHE = os.path.join(ROOT, "tools", ".cache", "metzger")
URL = "https://thebible.exposed/pdf/lexical-aids.pdf"
OUT = os.path.join(ROOT, "data", "lexicon", "metzger.json")
PART_I = range(9, 26)  # the scan's images that hold Part I
# Meanings the OCR mangled past mending by rule, set by hand from the page image: Strong's number -> meaning.
CORRECTIONS = {"G2362": "a throne (throne)"}

GREEK = re.compile(r"^[Ͱ-Ͽἀ-῿]")
HEAD = re.compile(r"WORDS\s+OC[CG][UV]RR?ING\s+(.*?)\s+TIMES", re.I)
LOOKALIKE = str.maketrans("ΤΟΡΙΜΕΑΝ", "TOPIMEAN")  # Greek capitals the OCR reads in English headings


def ocr(pdf):
    """The text of every page image, upright, cached."""
    from PIL import Image

    os.makedirs(CACHE, exist_ok=True)
    if not glob.glob(os.path.join(CACHE, "pg-*.png")):
        subprocess.run(["pdfimages", "-png", pdf, os.path.join(CACHE, "pg")], check=True)
    texts = {}
    for path in sorted(glob.glob(os.path.join(CACHE, "pg-*.png"))):
        n = int(re.search(r"pg-(\d+)", path).group(1))
        txt = os.path.join(CACHE, "up-%03d.txt" % n)
        if not os.path.exists(txt):
            up = os.path.join(CACHE, "up-%03d.png" % n)
            Image.open(path).rotate(-90, expand=True).save(up)
            subprocess.run(["tesseract", up, txt[:-4], "-l", "grc+eng", "--psm", "3"], check=True,
                           stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
            os.remove(up)
        texts[n] = open(txt, encoding="utf8").read()
    return texts


def number(s):
    """OCR's reading of a number ("I01", "IO", "QI", "15!") as an int."""
    s = s.translate(str.maketrans("IlO!oQSB", "11011958"))
    return int(s) if s.isdigit() else None


def bounds(text):
    """(min, max, title) from a heading's middle part: "201 TO 500", "32 OR 33", "25", "MORE THAN 500"."""
    t = re.sub(r"\s+", " ", text.strip()).upper()
    if t.startswith("MORE"):
        n = number(t.split()[-1])
        return (n + 1 if n else 501), 10 ** 5, "Words used more than %d times" % (n or 500)
    nums = [number(x) for x in re.split(r" (?:TO|OR) ", t)]
    nums = [x for x in nums if x]
    if not nums:
        return None
    lo, hi = min(nums), max(nums)
    title = "Words used %d times" % lo if lo == hi else "Words used %d to %d times" % (lo, hi)
    return lo, hi, title


def fold(word):
    word = unicodedata.normalize("NFD", word)
    word = "".join(c for c in word if not unicodedata.combining(c)).lower().replace("ς", "σ")
    return re.sub(r"[^α-ω]", "", word)


def clean(s):
    s = re.sub(r"\s+", " ", s).strip()
    return re.sub(r"\s+([,;.)])", r"\1", s)


def gloss(text):
    """Metzger's English meaning and derivatives, without the Greek forms and article the OCR garbles
    ("-ov, 6, a man (anthropology)" -> "a man (anthropology)"), and with its common slips mended."""
    parts = [p.strip() for p in text.split(",")]
    form = re.compile(r"^([-~“\"'‘]\S*|\S{1,2}|τό|τά|[\u0370-\u03FF\u1F00-\u1FFF]+)$")  # endings, article, Greek forms
    while len(parts) > 1 and (form.match(parts[0]) or not re.search(r"[A-Za-z]{2,}|^I\b", parts[0])):
        parts.pop(0)
    out = re.sub(r"(\w)- (\w)", r"\1\2", ", ".join(parts))  # words split across lines
    for bad, good in ((r"(^|, )6,", r"\1ὁ,"), (r"^Lam\b", "I am"), (r"^\[ ", "I "), (r"^1 ", "I "), (r"^l ", "I "), (r" α ", " a "),
                      (r"^α ", "a "), (r"\s+", " ")):
        out = re.sub(bad, good, out)
    return out.strip()


def balanced(text):
    """Whether every bracket the entry opened is closed: a wrapped line almost always falls inside one."""
    return text.count("(") <= text.count(")") and text.count("[") <= text.count("]")


def parse(texts):
    groups, group = [], None
    for n in PART_I:
        for line in texts.get(n, "").splitlines():
            line = line.strip()
            if not line:
                continue
            m = HEAD.search(line.translate(LOOKALIKE))
            if m:
                b = bounds(m.group(1))
                if b:
                    group = {"title": b[2], "min": b[0], "max": b[1], "words": []}
                    groups.append(group)
                continue
            if group is None or re.fullmatch(r"[\divxlcIVXLC\s.]+", line):
                continue  # page numbers and running heads
            if GREEK.match(line) and "," in line:
                word, rest = line.split(",", 1)
                group["words"].append({"w": clean(word), "g": clean(rest), "p": n + 1})
            elif group["words"] and balanced(group["words"][-1]["g"]) and (
                    re.match(r"^[^\s,]+,\s", line) or re.search(r",\s*(ὁ|ἡ|τό|6)\s*,", line)
                    or re.match(r"^\S{1,4}\s+[1Il]\s+[a-z]", line)):
                # An entry whose headword the OCR read as Latin letters ("vids, -οῦ" for υἱός): kept apart, so it
                # does not run into the entry before it, and dropped later as unreadable.
                group["words"].append({"w": "?", "g": "", "p": n + 1})
            elif group["words"]:
                group["words"][-1]["g"] = clean(group["words"][-1]["g"] + " " + line)
    return [g for g in groups if g["words"]]


def spellings(entry):
    """The ways STEP may spell Metzger's headword: each half of "X or Y", the active form of a deponent verb
    (ἀποκρίνομαι -> ἀποκρίνω), -υω for -υμι, and -ω for an aorist in -ον (εἶδον -> εἴδω)."""
    out = []
    for part in re.split(r"\s+or\s+|\(|=", entry, flags=re.I):
        f = fold(part.split()[0]) if part.split() else ""
        if not f:
            continue
        out.append(f)
        for end, new in (("ομαι", "ω"), ("υμι", "υω"), ("ον", "ω"), ("μαι", "ω")):
            if f.endswith(end):
                out.append(f[: -len(end)] + new)
    return out


def match(groups):
    """Each word's Strong's number: by spelling, else by a close spelling whose count fits the group."""
    import difflib

    index = json.load(open(os.path.join(ROOT, "data", "lexicon", "g-index.json"), encoding="utf8"))
    by_word = {}
    for num, word, gloss, count in index:
        for part in re.split(r"[,\s]+", word):
            if fold(part):
                by_word.setdefault(fold(part), []).append((num, count))
    keys = list(by_word)
    found = 0
    words = {}
    for g in groups:
        lo, hi = g["min"], min(g["max"], 30000)
        mid = (lo + hi) / 2
        for w in g["words"]:
            w["n"] = ""
            for f in spellings(w["w"]):
                cands = by_word.get(f)
                if not cands:
                    near = difflib.get_close_matches(f, keys, n=5, cutoff=0.8)
                    cands = [c for k in near for c in by_word[k] if lo * 0.6 <= c[1] <= hi * 1.6]
                if cands:
                    w["n"] = min(cands, key=lambda c: abs(c[1] - mid))[0]
                    break
            if w["n"]:
                found += 1
                w["w"] = next(r[1] for r in index if r[0] == w["n"])  # the lexicon's spelling, not the OCR's
                w["g"] = CORRECTIONS.get(w["n"], w["g"])
                words.setdefault(w["n"], {"w": w["w"], "g": w["g"], "group": g["title"], "p": w["p"]})
    return words, found


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--pdf", help="a local copy of lexical-aids.pdf")
    args = ap.parse_args()
    pdf = args.pdf
    if not pdf:
        pdf = os.path.join(CACHE, "lexical-aids.pdf")
        if not os.path.exists(pdf):
            os.makedirs(CACHE, exist_ok=True)
            req = urllib.request.Request(URL, headers={"User-Agent": "thebible.exposed-importer/1.0"})
            with urllib.request.urlopen(req, timeout=300) as r, open(pdf, "wb") as f:
                f.write(r.read())
    groups = parse(ocr(pdf))
    for g in groups:
        g["words"] = [w for w in g["words"] if not re.search(r"[A-Za-z\]\[?]", w["w"])]  # OCR fragments
        for w in g["words"]:
            w["g"] = gloss(w["g"])
    words, found = match(groups)
    total = sum(len(g["words"]) for g in groups)
    os.makedirs(os.path.dirname(OUT), exist_ok=True)
    data = {"source": "Bruce M. Metzger, Lexical Aids for Students of New Testament Greek (new edition, 1969), Part I.",
            "groups": groups, "words": words}
    with open(OUT, "w", encoding="utf8") as f:
        json.dump(data, f, ensure_ascii=False, separators=(",", ":"))
    for g in groups:
        print("%-36s %4d words, %4d matched" % (g["title"], len(g["words"]), sum(1 for w in g["words"] if w["n"])))
    print("%d groups, %d words, %d matched to a Strong's number" % (len(groups), total, found))


if __name__ == "__main__":
    sys.exit(main())

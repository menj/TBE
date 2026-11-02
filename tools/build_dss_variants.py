#!/usr/bin/env python3
"""Compare a Dead Sea Scroll with the Masoretic Hebrew of the interlinear and write the differences.

Source: the CACCHT / ETCBC Dead Sea Scrolls dataset (https://github.com/ETCBC/dss), built on Martin Abegg's data,
CC BY-NC 4.0 (Zenodo release v2.0, https://doi.org/10.5281/zenodo.23731063). The data is NOT stored in this
repository; run this against a local copy of its tf/ folder. Needs Text-Fabric (pip install text-fabric).

Usage:  python3 tools/build_dss_variants.py --tf PATH/TO/tf/2.0 --book isaiah --code Is --scroll 1Qisaa --chapters 1-5

Writes data/dss/{book}/{chapter}.json. Each difference is a "meaning" difference (a different word, a word the
scroll adds or leaves out, a different form) or a "spelling" difference (the same word written with different
vowel letters, a lengthened pronoun, a different word division). The page shows meaning differences by default
and spelling differences on request.
"""
import argparse, difflib, json, os, re, sys

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
POINTS = re.compile(r"[֑-ׇ]")
FINAL = str.maketrans("ךםןףץ", "כמנפצ")
HEB = re.compile(r"[^א-ת]")


def skeleton(text):
    """Consonants only, final forms folded."""
    return HEB.sub("", POINTS.sub("", text).translate(FINAL))


PROTECTED = {"הוא": "הוא", "הואה": "הוא", "היא": "היא"}   # he / she must never fall together
MARKERS = {"ס", "פ", "ר"}                                  # paragraph marks (setuma, petuha) written as words in the Hebrew text


def spelling_form(word):
    """The word reduced to what the two spellings share, to tell spelling from meaning: after the first letter,
    the vowel letters vav, yod and alef are taken out; a final he is taken out; the long pronoun endings the
    scrolls like (-מה, -כה) are shortened. He and she (הוא, היא) are never merged."""
    if word in PROTECTED:
        return PROTECTED[word]
    w = word[:1] + re.sub("[ויא]", "", word[1:])
    w = re.sub("(?<=.)מה$", "מ", w)
    w = re.sub("(?<=.)כה$", "כ", w)
    if len(w) > 2:
        w = re.sub("ה$", "", w)
    return w


def mt_words(path, verse):
    data = json.load(open(path, encoding="utf8"))["v"]
    out = []
    for w in data.get(str(verse), []):
        for part in re.split(r"[־\s]+", w[0]):  # a maqaf joins two words in print and a paragraph mark rides on one; the scroll writes them apart
            sk = skeleton(part)
            if sk and sk not in MARKERS:
                out.append((part, sk))
    return out


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--tf", required=True)
    ap.add_argument("--book", required=True, help="our slug, e.g. isaiah")
    ap.add_argument("--code", required=True, help="the dataset's book code, e.g. Is")
    ap.add_argument("--scroll", required=True, help="the dataset's scroll name, e.g. 1Qisaa")
    ap.add_argument("--label", default="Great Isaiah Scroll (1QIsa-a)")
    ap.add_argument("--chapters", required=True, help="e.g. 1-5")
    args = ap.parse_args()
    lo, hi = [int(x) for x in args.chapters.split("-")] if "-" in args.chapters else (int(args.chapters),) * 2

    from tf.fabric import Fabric
    api = Fabric(locations=args.tf, silent="deep").loadAll(silent="deep")
    F, L = api.F, api.L

    scroll = next(s for s in F.otype.s("scroll") if F.scroll.v(s) == args.scroll)

    def mark(word, feature):
        """Whether any sign of the word carries a text-critical feature (the features live on the signs)."""
        return any(getattr(F, feature).v(sg) for sg in L.d(word, otype="sign"))

    verses = {}
    for w in L.d(scroll, otype="word"):
        if F.book.v(w) != args.code or F.type.v(w) != "glyph" or mark(w, "rem"):
            continue                          # other books, numerals and punctuation, and words the scribe struck out
        letters = skeleton(F.glyph.v(w) or "")
        chapter, verse = F.chapter.v(w) or "", F.verse.v(w) or ""
        if not letters or not chapter.isdigit() or not verse.isdigit():
            continue
        pieces = verses.setdefault((int(chapter), int(verse)), [])
        rec, unc = (1 if mark(w, "rec") else 0), (1 if mark(w, "unc") else 0)
        if pieces and not pieces[-1]["open"]:   # no space after the previous piece: the same written word
            pieces[-1]["t"] += letters
            pieces[-1]["rec"] |= rec
            pieces[-1]["unc"] |= unc
        else:
            pieces.append({"t": letters, "rec": rec, "unc": unc, "open": False})
        pieces[-1]["open"] = bool(F.after.v(w))

    for chapter in range(lo, hi + 1):
        src = os.path.join(ROOT, "data", "interlinear", args.book, "%d.json" % chapter)
        mt_verses = json.load(open(src, encoding="utf8"))["v"]
        out = {"book": args.book, "chapter": chapter, "scroll": args.label, "v": {}}
        for vs in sorted(int(v) for v in mt_verses):
            mt = mt_words(src, vs)
            ds = verses.get((chapter, vs), [])
            if not ds:
                continue                      # the scroll has nothing here (lost or not preserved)
            a = [spelling_form(t[1]) for t in mt]
            b = [spelling_form(d["t"]) for d in ds]
            found = []
            for tag, i1, i2, j1, j2 in difflib.SequenceMatcher(None, a, b, autojunk=False).get_opcodes():
                if tag == "equal":
                    for k in range(i2 - i1):
                        if mt[i1 + k][1] != ds[j1 + k]["t"]:
                            found.append({"k": "s", "t": "r", "mt": [i1 + k, i1 + k + 1], "ds": ds[j1 + k]["t"], "r": ds[j1 + k]["rec"], "u": ds[j1 + k]["unc"]})
                    continue
                reading = " ".join(d["t"] for d in ds[j1:j2])
                rec = max([d["rec"] for d in ds[j1:j2]] or [0]); unc = max([d["unc"] for d in ds[j1:j2]] or [0])
                if tag == "replace" and "".join(a[i1:i2]) == "".join(b[j1:j2]):
                    found.append({"k": "s", "t": "r", "mt": [i1, i2], "ds": reading, "r": rec, "u": unc})   # same word, divided differently
                elif tag == "replace":
                    found.append({"k": "m", "t": "r", "mt": [i1, i2], "ds": reading, "r": rec, "u": unc})
                elif tag == "delete":
                    found.append({"k": "m", "t": "o", "mt": [i1, i2], "ds": "", "r": 0, "u": 0})
                else:
                    found.append({"k": "m", "t": "a", "mt": [i1, i1], "ds": reading, "r": rec, "u": unc})
            if found:
                out["v"][str(vs)] = found
        os.makedirs(os.path.join(ROOT, "data", "dss", args.book), exist_ok=True)
        with open(os.path.join(ROOT, "data", "dss", args.book, "%d.json" % chapter), "w", encoding="utf8") as f:
            json.dump(out, f, ensure_ascii=False, separators=(",", ":"))
        m = sum(1 for v in out["v"].values() for x in v if x["k"] == "m"); s = sum(1 for v in out["v"].values() for x in v if x["k"] == "s")
        print("%s %d: %d meaning, %d spelling" % (args.book, chapter, m, s))


if __name__ == "__main__":
    main()

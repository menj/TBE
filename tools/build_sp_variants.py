#!/usr/bin/env python3
"""Compare the Samaritan Pentateuch with the Masoretic Hebrew of the interlinear and write the differences.

Source: the CACCHT Text-Fabric dataset of the Samaritan Pentateuch (https://github.com/DT-UCPH/sp; Christian Canu
Hoejgaard, Martijn Naaijer and Stefan Schorch, https://doi.org/10.5281/zenodo.7734632), CC BY-NC 4.0. Its text is
the Samaritanus project's transcription of MS Dublin, Chester Beatty Library 751 (Genesis 1 to Deuteronomy 32:36)
and MS Garizim 1 (Deuteronomy 32:36 to 34), from Stefan Schorch's critical edition (de Gruyter, 2018-). The data is
NOT stored in this repository: the script clones it into tools/.cache/sp/ (git-ignored) when missing, and reads the
Text-Fabric files directly, without the Text-Fabric library.

Writes data/sp/{book}/{chapter}.json for Genesis to Deuteronomy, in the format of data/dss/ (see
tools/build_dss_variants.py, whose comparison it uses): each difference is of meaning or of spelling, and the
interlinear marks them in the same way.

Usage:  python3 tools/build_sp_variants.py [--tf PATH/TO/tf/VERSION]
"""
import argparse, difflib, glob, json, os, subprocess, sys, unicodedata

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
sys.path.insert(0, os.path.join(ROOT, "tools"))
from build_dss_variants import skeleton, spelling_form, mt_words  # noqa: E402

CACHE = os.path.join(ROOT, "tools", ".cache", "sp")
BOOKS = {"Genesis": "genesis", "Exodus": "exodus", "Leviticus": "leviticus", "Numbers": "numbers", "Deuteronomy": "deuteronomy"}
LABEL = "Samaritan Pentateuch"


def read_feature(path):
    """A Text-Fabric node feature or edge as {node: value}; ranges and lists of nodes are expanded."""
    out, node = {}, 0
    for line in open(path, encoding="utf8"):
        line = line.rstrip("\n")
        if line.startswith("@") or (not line and node == 0 and not out):
            continue
        if "\t" in line:
            spec, value = line.split("\t", 1)
            nodes = []
            for part in spec.split(","):
                a, _, b = part.partition("-")
                nodes += list(range(int(a), int(b or a) + 1))
        else:
            nodes, value = [node + 1], line
        for n in nodes:
            out[n] = value
        node = nodes[-1]
    return out


def slots(spec):
    out = []
    for part in spec.split(","):
        a, _, b = part.partition("-")
        out += list(range(int(a), int(b or a) + 1))
    return out


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--tf")
    args = ap.parse_args()
    tfdir = args.tf
    if not tfdir:
        if not os.path.isdir(os.path.join(CACHE, "sp")):
            os.makedirs(CACHE, exist_ok=True)
            subprocess.run(["git", "clone", "--depth", "1", "https://github.com/DT-UCPH/sp.git", os.path.join(CACHE, "sp")], check=True)
        tfdir = sorted(glob.glob(os.path.join(CACHE, "sp", "tf", "*")), key=lambda p: [int(x) for x in os.path.basename(p).split(".") if x.isdigit()])[-1]
    otype_ranges = {}
    for line in open(os.path.join(tfdir, "otype.tf"), encoding="utf8"):
        if "\t" in line and not line.startswith("@"):
            spec, kind = line.rstrip("\n").split("\t")
            a, _, b = spec.partition("-")
            otype_ranges[kind] = (int(a), int(b or a))
    book, chapter, verse = (read_feature(os.path.join(tfdir, f + ".tf")) for f in ("book", "chapter", "verse"))
    oslots = read_feature(os.path.join(tfdir, "oslots.tf"))
    cons = read_feature(os.path.join(tfdir, "g_cons_utf8.tf"))
    trailer = read_feature(os.path.join(tfdir, "trailer.tf"))
    # Which verse each sign belongs to.
    where = {}
    for v in range(otype_ranges["verse"][0], otype_ranges["verse"][1] + 1):
        for s in slots(oslots[v]):
            where[s] = v
    book_of_slot = []
    for b in range(otype_ranges["book"][0], otype_ranges["book"][1] + 1):
        sl = slots(oslots[b])
        book_of_slot.append((sl[0], sl[-1], book[b]))
    chap_of_slot = []
    for c in range(otype_ranges["chapter"][0], otype_ranges["chapter"][1] + 1):
        sl = slots(oslots[c])
        chap_of_slot.append((sl[0], sl[-1], int(chapter[c])))
    def find(table, s):
        for a, b, val in table:
            if a <= s <= b:
                return val
    verses = {}
    lo, hi = otype_ranges["word"]
    open_word = False
    for w in range(lo, hi + 1):
        letters = skeleton(unicodedata.normalize("NFD", cons.get(w, "")))  # the data writes shin as one sign, U+FB2A
        first = slots(oslots[w])[0]
        if not letters:
            continue
        key = (BOOKS[find(book_of_slot, first)], find(chap_of_slot, first), int(verse[where[first]]))
        pieces = verses.setdefault(key, [])
        if pieces and not open_word:
            pieces[-1]["t"] += letters  # no space after the previous word: a prefix written with it
        else:
            pieces.append({"t": letters})
        open_word = trailer.get(w, "") != ""  # a space or a stop after the word ends it; nothing joins it to the next
    total = {"m": 0, "s": 0}
    for slug in BOOKS.values():
        files = sorted(glob.glob(os.path.join(ROOT, "data", "interlinear", slug, "*.json")), key=lambda p: int(os.path.basename(p)[:-5]))
        for src in files:
            ch = int(os.path.basename(src)[:-5])
            mt_verses = json.load(open(src, encoding="utf8"))["v"]
            out = {"book": slug, "chapter": ch, "scroll": LABEL, "source": "sp", "v": {}}
            for vs in sorted(int(v) for v in mt_verses):
                mt = mt_words(src, vs)
                sp = verses.get((slug, ch, vs), [])
                if not sp:
                    continue
                a = [spelling_form(t[1]) for t in mt]
                b = [spelling_form(d["t"]) for d in sp]
                found = []
                for tag, i1, i2, j1, j2 in difflib.SequenceMatcher(None, a, b, autojunk=False).get_opcodes():
                    if tag == "equal":
                        for k in range(i2 - i1):
                            if mt[i1 + k][1] != sp[j1 + k]["t"]:
                                found.append({"k": "s", "t": "r", "mt": [i1 + k, i1 + k + 1], "ds": sp[j1 + k]["t"], "r": 0, "u": 0})
                        continue
                    reading = " ".join(d["t"] for d in sp[j1:j2])
                    if tag == "replace" and "".join(a[i1:i2]) == "".join(b[j1:j2]):
                        found.append({"k": "s", "t": "r", "mt": [i1, i2], "ds": reading, "r": 0, "u": 0})
                    elif tag == "replace":
                        found.append({"k": "m", "t": "r", "mt": [i1, i2], "ds": reading, "r": 0, "u": 0})
                    elif tag == "delete":
                        found.append({"k": "m", "t": "o", "mt": [i1, i2], "ds": "", "r": 0, "u": 0})
                    else:
                        found.append({"k": "m", "t": "a", "mt": [i1, i1], "ds": reading, "r": 0, "u": 0})
                if found:
                    out["v"][str(vs)] = found
                    for x in found:
                        total[x["k"]] += 1
            os.makedirs(os.path.join(ROOT, "data", "sp", slug), exist_ok=True)
            with open(os.path.join(ROOT, "data", "sp", slug, "%d.json" % ch), "w", encoding="utf8") as f:
                json.dump(out, f, ensure_ascii=False, separators=(",", ":"))
        print(slug, len(files), "chapters", flush=True)
    print("meaning %(m)d, spelling %(s)d" % total)
    return 0


if __name__ == "__main__":
    sys.exit(main())

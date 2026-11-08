#!/usr/bin/env python3
"""Build the manuscripts section (data/manuscripts/passages.json) from the CNTR transcriptions.

Source: Center for New Testament Restoration (CNTR), Electronic Transcriptions, by Alan Bunning,
https://github.com/Center-for-New-Testament-Restoration/transcriptions, CC BY-SA 4.0. The same data the
manuscript pages on https://greekcntr.org show. The raw files are NOT stored in this repository; this script
clones them into tools/.cache/cntr (git-ignored) when missing, or reads a copy given with --src.

For each passage in PASSAGES the script reads every early manuscript (CNTR "class 1": papyri and parchment
manuscripts up to about AD 400) and the printed editions, and writes what each one has there:
  p  the passage is written         a  the page survives and the passage is not on it
  l  the page is lost or too damaged to tell
A "verses" passage is judged verse by verse from CNTR's own marks ("-" verse absent, "-~" or an empty line:
the page is lost). A "words" passage (a few words inside a verse) is judged by looking for its key words in the
first scribe's text; a damaged verse that lacks them counts as lost, never as absent.

Usage:  python3 tools/build_manuscripts.py             # clone CNTR if needed, then build
        python3 tools/build_manuscripts.py --src DIR   # use a local copy of the transcriptions repository
"""
import argparse, html, json, os, re, subprocess, sys, time, unicodedata, urllib.error, urllib.request

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
CACHE = os.path.join(ROOT, "tools", ".cache", "cntr")
OUT = os.path.join(ROOT, "data", "manuscripts", "passages.json")
REPO = "https://github.com/Center-for-New-Testament-Restoration/transcriptions"

# Book numbers in CNTR's verse ids (40 = Matthew ... 66 = Revelation) and our slugs.
BOOKS = {40: "matthew", 41: "mark", 42: "luke", 43: "john", 44: "acts", 45: "romans", 54: "1-timothy", 62: "1-john"}

# The passages. "verses": whole verses, judged from CNTR's own marks. "words": key words inside one verse.
PASSAGES = [
    {"id": "mark-16-9-20", "kind": "verses", "ref": [41, 16, 9, 16, 20]},
    {"id": "john-7-53-8-11", "kind": "verses", "ref": [43, 7, 53, 8, 11]},
    {"id": "luke-22-43-44", "kind": "verses", "ref": [42, 22, 43, 22, 44]},
    {"id": "luke-23-34", "kind": "words", "ref": [42, 23, 34, 23, 34], "words": "πατερ αφεσ αυτοισ"},
    {"id": "matthew-6-13", "kind": "words", "ref": [40, 6, 13, 6, 13], "words": "οτι σου εστιν η βασιλεια"},
    # "words" may list several spellings, separated by "|": a match on any one counts. They match whole words.
    {"id": "luke-3-22", "kind": "words", "ref": [42, 3, 22, 3, 22], "words": "σημερον γεγεννηκα σε"},
    {"id": "1-timothy-3-16", "kind": "words", "ref": [54, 3, 16, 3, 16], "words": "θεος εφανερωθη|θσ εφανερωθη"},
    {"id": "1-john-5-7-8", "kind": "words", "ref": [62, 5, 7, 5, 7], "words": "εν τω ουρανω"},
    {"id": "john-5-4", "kind": "verses", "ref": [43, 5, 4, 5, 4]},
    {"id": "acts-8-37", "kind": "verses", "ref": [44, 8, 37, 8, 37]},
    {"id": "matthew-17-21", "kind": "verses", "ref": [40, 17, 21, 17, 21]},
    {"id": "matthew-18-11", "kind": "verses", "ref": [40, 18, 11, 18, 11]},
    {"id": "matthew-23-14", "kind": "verses", "ref": [40, 23, 14, 23, 14]},
    {"id": "mark-7-16", "kind": "verses", "ref": [41, 7, 16, 7, 16]},
    {"id": "mark-9-44-46", "kind": "verses", "ref": [41, 9, 44, 9, 46], "only": [44, 46]},
    {"id": "mark-11-26", "kind": "verses", "ref": [41, 11, 26, 11, 26]},
    {"id": "mark-15-28", "kind": "verses", "ref": [41, 15, 28, 15, 28]},
    {"id": "luke-17-36", "kind": "verses", "ref": [42, 17, 36, 17, 36]},
    {"id": "luke-23-17", "kind": "verses", "ref": [42, 23, 17, 23, 17]},
    {"id": "acts-15-34", "kind": "verses", "ref": [44, 15, 34, 15, 34]},
    {"id": "acts-28-29", "kind": "verses", "ref": [44, 28, 29, 28, 29]},
    {"id": "romans-16-24", "kind": "verses", "ref": [45, 16, 24, 16, 24]},
]

# English translations from CNTR's translation pages (https://greekcntr.org/translation/), public domain only:
# KJV 1769 (Pure Cambridge Edition), World English Bible 2020, Berean Standard Bible 2023 (CC0). The NET Bible
# shown there is used by CNTR with its publisher's permission, which does not pass on, so it is left out.
TRANSLATIONS = ["KJV", "WEB", "BSB"]
TR_URL = "https://greekcntr.org/translation/data/%s.html"
TR_CACHE = os.path.join(ROOT, "tools", ".cache", "cntr-translation")

# The printed editions in CNTR's "critical texts" folder that the pages compare with.
EDITIONS = ["WH", "RP", "KJTR", "SR"]


def nt_slugs():
    """CNTR book number (40 to 66) to our slug."""
    books = json.load(open(os.path.join(ROOT, "data", "books.json"), encoding="utf8"))
    return {40 + i: b["slug"] for i, b in enumerate(b for b in books if b["testament"] == "NT")}


def translation(vid):
    """{KJV: text, WEB: text, BSB: text} for one verse, from CNTR's translation page (cached; one request a second)."""
    os.makedirs(TR_CACHE, exist_ok=True)
    path = os.path.join(TR_CACHE, vid + ".html")
    if not os.path.exists(path):
        req = urllib.request.Request(TR_URL % vid, headers={"User-Agent": "thebible.exposed-importer/1.0"})
        try:
            with urllib.request.urlopen(req, timeout=40) as r:
                body = r.read().decode("utf8")
        except urllib.error.HTTPError as e:
            print("  no translation page for", vid, "(%d)" % e.code)
            return {}
        with open(path, "w", encoding="utf8") as f:
            f.write(body)
        time.sleep(1)
    body = open(path, encoding="utf8").read()
    out = {}
    for name, text in re.findall(r"<tr><td>([A-Z]+)</td><td>\d+</td><td>(.*?)</td></tr>", body, re.S):
        if name in TRANSLATIONS:
            text = html.unescape(re.sub(r"<[^>]+>", "", text)).replace("¶", "").strip()
            out[name] = "" if text == "-" else text
    return out


def source(path):
    if path:
        return path
    if not os.path.isdir(os.path.join(CACHE, "class 1")):
        os.makedirs(os.path.dirname(CACHE), exist_ok=True)
        print("cloning", REPO)
        subprocess.run(["git", "clone", "--depth", "1", REPO, CACHE], check=True)
    return CACHE


def read(path):
    """{verse id: raw text} for one transcription file."""
    out = {}
    with open(path, encoding="utf8") as f:
        for line in f:
            line = line.rstrip("\n")
            if re.match(r"^\d{8}( |$)", line):
                out[line[:8]] = line[9:]
    return out


def first_hand(raw):
    """The first scribe's text: x{...} kept, every later correction {..} a{..} b{..} dropped."""
    text = re.sub(r"x\{([^}]*)\}", r"\1", raw)
    return re.sub(r"[ab]?\{[^}]*\}", "", text)


def marks(text):
    """The transcription marks a reader does not need: damage (%), editors' restorations (^), nomina sacra (=),
    numbers ($), supplied words (~ +), word division (_), brackets, the alternative verse mark and a private-use
    character. An illegible letter (U+FFFD) becomes a dot, as in printed editions; text lost in a gap becomes an
    ellipsis."""
    text = text.replace("\ufffd", "·").replace("\ue001", "")
    text = re.sub(r"[*&]+", " … ", text)
    return re.sub(r"[=$%^~+_\[\]⋄]", "", text)


def clean(raw):
    """Plain letters for comparing: page, column and line marks become spaces, other marks go."""
    text = first_hand(raw).replace("¯", "ν")
    text = re.sub(r"[\\|/]\d*", " ", text)
    return re.sub(r"\s+", " ", marks(text)).strip()


def lines(raw):
    """The text as the manuscript sets it out, line by line (for display)."""
    text = first_hand(raw).replace("¯", "ν")
    text = re.sub(r"[\\|/]\d*", "/", text)
    out = [re.sub(r"\s+", " ", part).strip() for part in marks(text).split("/")]
    out = [line for line in out if line]
    # A run of gap marks reads as one.
    return [line for i, line in enumerate(out) if not (line == "…" and i and out[i - 1] == "…")]


def plain(text):
    """Accents, breathings, punctuation and case removed, final sigma folded, for comparing words."""
    text = unicodedata.normalize("NFD", text)
    text = "".join(c for c in text if not unicodedata.combining(c)).lower().replace("ς", "σ")
    return re.sub(r"\s+", " ", re.sub(r"[^α-ω ]", " ", text)).strip()


def has_words(words, text):
    """Whether any of the "|"-separated spellings stands in the text as whole words."""
    hay = " %s " % plain(text)
    return any(" %s " % plain(w) in hay for w in words.split("|"))


def verse_state(raw):
    """p present, a absent, l lost, for one verse line."""
    body = raw.strip()
    if body == "-":
        return "a"
    if body in ("", "-~", "+~") or re.fullmatch(r"[*&~\s]*", body):
        return "l"
    return "p"


def damaged(raw):
    """Whether part of the verse is lost (* &), unreadable (U+FFFD) or supplied whole by the editors (~ +). A
    letter the editors restored inside a word that survives (^) leaves the reading clear, so it does not count."""
    return bool(re.search(r"[*&~+\ufffd]", raw))


def verse_ids(ref, only=None):
    book, c1, v1, c2, v2 = ref
    ids = []
    for c in range(c1, c2 + 1):
        start = v1 if c == c1 else 1
        end = v2 if c == c2 else 99
        for v in range(start, end + 1):
            vid = "%02d%03d%03d" % (book, c, v)
            if (only and v not in only) or (KNOWN and vid not in KNOWN):
                continue
            ids.append("%02d%03d%03d" % (book, c, v))
    return ids


def neighbour(text, vid, step):
    """The verse id before (step -1) or after (step 1) the passage that this manuscript has."""
    keys = sorted(text)
    if vid not in keys:
        cands = [k for k in keys if (k < vid if step < 0 else k > vid)]
        return (cands[-1] if step < 0 else cands[0]) if cands else None
    i = keys.index(vid) + step
    return keys[i] if 0 <= i < len(keys) else None


SLUGS = {}  # filled in main()
KNOWN = set()  # every verse id in the printed editions, filled in main()


def label(vid):
    return "%s %d:%d" % (SLUGS.get(int(vid[:2]), vid[:2]), int(vid[2:5]), int(vid[-3:]))


def judge(p, text):
    """What one manuscript has at one passage, or None when it does not cover the place."""
    ids = verse_ids(p["ref"], p.get("only"))
    have = [v for v in ids if v in text]
    if not have:
        return None
    if p["kind"] == "words":
        raw = text[have[0]]
        if verse_state(raw) != "p":
            state = "l" if verse_state(raw) == "l" else "a"
        elif has_words(p["words"], clean(raw)):
            state = "p"
        else:
            state = "l" if damaged(raw) else "a"
        states = {have[0]: state}
    else:
        states = {v: verse_state(text[v]) for v in have}
    known = [s for s in states.values() if s != "l"]
    if not known:
        overall = "l"
    elif all(s == "p" for s in known):
        overall = "p"
    elif all(s == "a" for s in known):
        overall = "a"
    else:
        overall = "s"  # some verses written, some not
    out = {"s": overall, "v": {"%d:%d" % (int(v[2:5]), int(v[-3:])): s for v, s in states.items()}}
    if overall != "l":
        shown = {}
        before = neighbour(text, ids[0], -1)
        after = neighbour(text, ids[-1], 1)
        for vid in ([before] if before else []) + have + ([after] if after else []):
            if vid in text and verse_state(text[vid]) == "p":
                shown[label(vid)] = lines(text[vid])
        out["t"] = shown
        out["c"] = any(re.search(r"[ab]?\{", text[v]) and "x{" in text[v] for v in have)
    return out


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--src", help="local copy of the CNTR transcriptions repository")
    ap.add_argument("--no-translations", action="store_true", help="skip the English wording from CNTR's translation pages")
    args = ap.parse_args()
    SLUGS.update(nt_slugs())
    src = source(args.src)
    readme = open(os.path.join(src, "README.txt"), encoding="utf8").read()
    generated = (re.search(r"Generated on ([0-9/]+)", readme) or [None, ""])[1]

    witnesses = {}
    folder = os.path.join(src, "class 1")
    for name in sorted(os.listdir(folder)):
        if name.endswith(".txt"):
            witnesses[name[:-4]] = read(os.path.join(folder, name))
    editions = {e: read(os.path.join(src, "critical texts", e + ".txt")) for e in EDITIONS}
    for text in editions.values():
        KNOWN.update(text)

    passages = []
    for p in PASSAGES:
        ids = verse_ids(p["ref"], p.get("only"))
        row = {"id": p["id"], "book": BOOKS[p["ref"][0]], "ref": p["ref"][1:], "kind": p["kind"], "mss": {}, "eds": {}}
        if p.get("only"):
            row["only"] = p["only"]
        if p["kind"] == "words":
            row["words"] = p["words"]
        for sig, text in witnesses.items():
            got = judge(p, text)
            if got:
                row["mss"][sig] = got
        for ed, text in editions.items():
            raw = " ".join(text.get(v, "") for v in ids).strip()
            if p["kind"] == "words":
                row["eds"][ed] = "p" if has_words(p["words"], raw) else "a"
            else:
                row["eds"][ed] = "p" if plain(raw) else "a"
        if not args.no_translations:
            row["en"] = {label(v): translation(v) for v in ids}
        passages.append(row)
        counts = {k: sum(1 for m in row["mss"].values() if m["s"] == k) for k in "pasl"}
        print("%-16s has %d, lacks %d, part %d, lost %d" % (p["id"], counts["p"], counts["a"], counts["s"], counts["l"]))

    os.makedirs(os.path.dirname(OUT), exist_ok=True)
    data = {
        "source": "Center for New Testament Restoration (CNTR), Electronic Transcriptions, by Alan Bunning. CC BY-SA 4.0. " + REPO,
        "translations": "KJV 1769 (Pure Cambridge Edition, public domain), World English Bible 2020 (public domain), Berean Standard Bible 2023 (CC0), from https://greekcntr.org/translation/",
        "generated": generated,
        "passages": passages,
    }
    with open(OUT, "w", encoding="utf8") as f:
        json.dump(data, f, ensure_ascii=False, separators=(",", ":"))
        f.write("\n")
    print("wrote", os.path.relpath(OUT, ROOT), os.path.getsize(OUT), "bytes")


if __name__ == "__main__":
    sys.exit(main())

#!/usr/bin/env python3
"""Build the four books put forward as the lost Scrolls (Suhuf) of Abraham and Moses (data/further/).

  testament-of-abraham  W. A. Craigie's translation, Ante-Nicene Fathers 9 (1896), both versions, from CCEL.
  sefer-yetzirah        W. Wynn Westcott's translation (third edition, 1893), from the Internet Archive scan
                        sepheryetzirahb00rittgoog (its plain OCR text).
  testament-of-moses    R. H. Charles's translation of the Assumption (Testament) of Moses, in The Apocrypha and
                        Pseudepigrapha of the Old Testament, vol. 2 (Oxford, 1913), pp. 414-424, from the Internet
                        Archive scan apocryphapseudep02charuoft (its OCR with word positions).
  apocalypse-of-moses   Alexander Walker's translation, "Revelation of Moses", Ante-Nicene Fathers 8 (1886), from CCEL.

All four are public domain. Each source is fetched once, a second apart, into tools/.cache/suhuf/ (git-ignored).

Testament of Abraham: each printed chapter (I. to XX. of the longer version, I. to XIV. of the shorter) is one
chapter with one paragraph, labelled with its numeral; the shorter version keeps Craigie's printed order (XII.
stands after VIII.). Footnotes and page numbers are dropped.
Sefer Yetzirah: chapters I. to VI. and their numbered sections; Westcott's footnote marks are dropped, his second
"5." in chapter II is section 6, and the two supplements, which he says are modern and not in the ancient copies,
are left out.
Testament of Moses: the text is set in type about 31 pixels tall, Charles's notes in 26; the script keeps the large
type and reads the verse numbers from the margin, as listed in VERSES below (the OCR misreads several, such as
"τι" for 11). A verse begins at the start of its line when the line before ends a sentence, else at the first stop
in its line. Chapter 10 is verse, one line to a line.
Apocalypse of Moses: Walker's paragraphs, in one chapter; his translation does not print Tischendorf's chapter
numbers. The opening title line is the heading.

Usage:  python3 tools/build_suhuf.py
"""
import html, json, os, re, statistics, sys, time, urllib.request
import xml.etree.ElementTree as ET

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
CACHE = os.path.join(ROOT, "tools", ".cache", "suhuf")
OUT = os.path.join(ROOT, "data", "further")
UA = "thebible.exposed-importer/1.0 (https://thebible.exposed/)"
CCEL = "https://www.ccel.org/ccel/schaff/"
IA = "https://archive.org/download/"
ROMAN = ["", "I", "II", "III", "IV", "V", "VI", "VII", "VIII", "IX", "X", "XI", "XII", "XIII", "XIV", "XV", "XVI", "XVII", "XVIII", "XIX", "XX"]
NOTE = "One of the books put forward as the lost Scrolls (Suhuf) of Abraham and Moses named in the Qur'an (53:36-37, 87:18-19). The identification is a proposal by scholars, not a teaching of any community."


def fetch(name, url):
    path = os.path.join(CACHE, name)
    if not os.path.exists(path):
        os.makedirs(CACHE, exist_ok=True)
        with urllib.request.urlopen(urllib.request.Request(url, headers={"User-Agent": UA}), timeout=600) as r:
            open(path, "wb").write(r.read())
        time.sleep(1)
    return path


def paragraphs(page):
    """The text of each <p> of a CCEL page, without its footnotes and page numbers."""
    t = open(fetch(page + ".html", CCEL + page + ".html"), encoding="utf-8").read()
    t = re.sub(r'<sup class="Note".*?</sup>', "", t, flags=re.S)
    t = re.sub(r'<span class="mnote".*?</span></span>', "", t, flags=re.S)
    t = re.sub(r'<span class="pb".*?</span>', "", t, flags=re.S)
    out = []
    for p in re.findall(r"<p[^>]*>(.*?)</p>", t, re.S):
        s = html.unescape(re.sub(r"<[^>]+>", "", p)).replace("\xa0", " ")
        s = re.sub(r"\s+", " ", s).strip()
        if s:
            out.append(s)
    return out


def source(title, url, credit, short, translator, turl, note=""):
    return {"title": title, "url": url, "credit": credit, "short": short, "note": note, "licence": "Public domain",
            "licence_url": "", "translator": {"type": "Person", "name": translator, "url": turl}}


def write(slug, data):
    with open(os.path.join(OUT, slug + ".json"), "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=1)
        f.write("\n")
    print("%s: %d chapters, %d verses" % (slug, len(data["chapters"]), sum(len(c["verses"]) for c in data["chapters"])))


def abraham():
    chapters = []
    for page, version in (("anf09.ix.iii.i", "Longer version"), ("anf09.ix.iii.ii", "Shorter version")):
        for p in paragraphs(page):
            m = re.match(r"^\(?([IVXL]+)\.\)?\s+(.*)$", p)
            if not m:
                continue
            chapters.append({"n": len(chapters) + 1, "title": "%s, %s" % (version, m.group(1)), "heading": "",
                             "verses": [{"n": 1, "l": m.group(1), "t": m.group(2)}]})
    write("testament-of-abraham", {
        "slug": "testament-of-abraham", "name": "Testament of Abraham", "language": "Greek",
        "translation": "W. A. Craigie, 1896",
        "source": source("The Testament of Abraham, translated by W. A. Craigie", CCEL + "anf09.ix.iii.html",
                         "The translation from Greek by W. A. Craigie, in The Ante-Nicene Fathers, vol. 9 (1896), as transcribed by the Christian Classics Ethereal Library",
                         "W. A. Craigie, 1896", "William A. Craigie", "https://en.wikipedia.org/wiki/William_A._Craigie",
                         "Both Greek versions are given: the longer (chapters I to XX) and the shorter (I to XIV), each chapter as one paragraph. " + NOTE),
        "chapters": chapters,
    })


def yetzirah():
    lines = open(fetch("sepheryetzirahb00rittgoog.txt", IA + "sepheryetzirahb00rittgoog/sepheryetzirahb00rittgoog_djvu.txt"), encoding="utf-8").read().splitlines()
    start = next(i for i, l in enumerate(lines) if l.strip() == "CHAPTER I." and i > 500)
    end = next(i for i, l in enumerate(lines) if "The End of the" in l and i > start)
    keep, skip = [], False
    for l in lines[start:end]:
        s = l.strip()
        if s.startswith("Supplement to Chapter"):
            skip = True
            continue
        if re.match(r"^CHAPTER [IV]+\.$", s):
            skip = False
        if skip or not s or s in ("Hosted by", "Google") or re.fullmatch(r"[\^\dij\s]{1,4}", s):
            continue
        keep.append(s)
    chapters, text = [], ""
    for s in keep + ["CHAPTER END."]:
        if re.match(r"^CHAPTER [IVEND]+\.$", s):
            if chapters:
                chapters[-1]["raw"] = text
            chapters.append({"n": len(chapters) + 1, "heading": "", "verses": []})
            text = ""
            continue
        text = text[:-1] + s if text.endswith("-") else (text + " " + s).strip()
    chapters.pop()
    for c in chapters:
        t = c.pop("raw")
        t = re.sub(r"[\^\"*]+\d*|(?<=\w)/\"?|'{2,}", " ", t)  # footnote marks
        t = re.sub(r"^Section i\.\s*", "", t)
        parts = re.split(r"\s+(\d{1,2})\.\s+(?=[A-Z])", " " + t)
        verses = [parts[0].strip()] + [parts[i + 1].strip() for i in range(1, len(parts) - 1, 2)]
        for a, b in YETZIRAH_FIX:
            verses = [v.replace(a, b) for v in verses]
        verses = [re.sub(r"(?<=[a-z,;.])\d{1,2}(?=[\s.,;])", "", v) for v in verses]
        verses = [re.sub(r"(?<=[\w.])'(?=[\s.,;]|$)|(?<=\s)'(?=\s)", "", v) for v in verses]  # stray marks read as quotes  # a footnote number after a word
        c["verses"] = [{"n": i + 1, "t": re.sub(r"\s+([;:,.!?])", r"\1", re.sub(r"\s+", " ", v)).strip()} for i, v in enumerate(verses)]
    write("sefer-yetzirah", {
        "slug": "sefer-yetzirah", "name": "Sefer Yetzirah (Book of Formation)", "language": "Hebrew",
        "translation": "W. Wynn Westcott, 1893",
        "source": source("Sepher Yetzirah: The Book of Formation, translated by W. Wynn Westcott (1893)", "https://archive.org/details/sepheryetzirahb00rittgoog",
                         "The translation from Hebrew by W. Wynn Westcott, Sepher Yetzirah: The Book of Formation and the Thirty Two Paths of Wisdom, third edition (London: Theosophical Publishing Society, 1893)",
                         "W. Wynn Westcott, 1893", "William Wynn Westcott", "https://en.wikipedia.org/wiki/William_Wynn_Westcott",
                         "Westcott's notes and the two supplements he marks as modern are left out. The last section ascribes the book's teaching to Abraham. " + NOTE),
        "chapters": chapters,
    })


YETZIRAH_FIX = [("IsraeP", "Israel"), ("tongne", "tongue"), ("fron ", "from "), ("rales ", "rules "), ("theui", "them"), ("FertiHty", "Fertility"),
                ("HoHness", "Holiness"), ("fiYe", "five"), ("J ah", "Jah"), (" che ", " the "), ("media cor", "mediator"), ("Qoph\\-", "Qoph;"),
                (" fseven", "; seven"), ("NisanYiar", "Nisan, Iyar"), ("perceived. and", "perceived, and"), ("void-made", "void made"), ("SoHtude", "Solitude")]


# The Testament of Moses: the verses whose margin numbers stand beside each text line (by line, counted from 0),
# with a new chapter where the first number is written "c:v". Lines not listed carry no number.
VERSES = {
    0: ["1:1"], 1: [2], 2: [3], 3: [4], 4: [5], 5: [6, 7], 6: [8], 7: [9], 9: [10], 10: [11, 12], 11: [13], 13: [14],
    15: [15], 16: [16], 17: [17], 19: [18],
    22: ["2:1"], 23: [2], 25: [3], 27: [4], 30: [5], 31: [6, 7], 33: [8], 34: [9],
    36: ["3:1"], 37: [2], 38: [3], 39: [4], 40: [5], 42: [6], 43: [7], 44: [8], 45: [9], 47: [10], 48: [11], 50: [12], 52: [13], 54: [14],
    55: ["4:1"], 56: [2], 59: [3], 60: [4], 61: [5], 62: [6], 64: [7], 65: [8], 67: [9],
    69: ["5:1"], 70: [2, 3], 73: [4], 74: [5], 76: [6],
    79: ["6:1"], 80: [2], 82: [3], 83: [4], 84: [5], 85: [6], 86: [7], 87: [8], 88: [9],
    90: ["7:1"], 91: [2, 3], 92: [4], 94: [5, 6], 95: [7], 97: [8], 98: [9], 99: [10],
    101: ["8:1"], 104: [2], 105: [3], 106: [4], 108: [5],
    111: ["9:1"], 112: [2], 114: [3], 115: [4], 117: [5], 118: [6], 120: [7],
    121: ["10:1"], 124: [2], 127: [3], 130: [4], 133: [5], 136: [6], 139: [7], 142: [8], 145: [9], 148: [10], 151: [11, 12], 152: [13], 153: [14, 15],
    156: ["11:1"], 157: [2], 158: [3, 4], 161: [5, 6], 162: [7], 163: [8], 165: [9, 10], 166: [11], 168: [12], 171: [13], 172: [14], 173: [15], 174: [16], 178: [17], 182: [18], 184: [19],
    185: ["12:1", 2], 187: [3, 4], 190: [5], 192: [6], 193: [7], 194: [8], 195: [9], 197: [10], 198: [11], 199: [12], 200: [13],
}
POETRY = range(121, 151)
MOSES_FIX = [("nowthey", "now they"), ("taroundy", "around"), ("evex", "even"), ("¢hem", "them"), (" iz reality", " in reality"),
             ("s/em", "them"), ("77m", "Him"), ("¢ezr", "their"), ("endedj", "ended"), ("}mount", "mount"), ("habitationt", "habitation"),
             (" asa ", " as a "), ("one.that", "one that"), ("ΑἹ]", "All"), ("shalt.see", "shalt see"), ("priests,a", "priests, a"),
             ("(Yea).all", "(Yea) all"), ("them. ‘And they shall sacrifice", "them. And they shall sacrifice"), (" ;", ";"), (" :", ":"), ("‘ ", "‘"), ("advent. there", "advent there"), ("priests ; of", "priests of")]
BREAK = re.compile(r"[.?!:;][’”']?\)?\s+(?=[A-Z‘“(\[])")


def moses_lines():
    """The text lines of pp. 414-424, in the large type, with the running heads dropped."""
    xml = fetch("apot2_djvu.xml", IA + "apocryphapseudep02charuoft/apocryphapseudep02charuoft_djvu.xml")
    out, n = [], -1
    for _, el in ET.iterparse(xml):
        if el.tag != "OBJECT":
            continue
        n += 1
        if 433 <= n <= 443:
            words = []
            for w in el.iter("WORD"):
                if w.text and w.text.strip():
                    l, b, r, t = map(int, w.get("coords").split(",")[:4])
                    if b - t < 60:
                        words.append((l, b, r, t, w.text.strip()))
            lines = []
            for w in sorted(words, key=lambda w: (w[1] + w[3]) / 2):
                y = (w[1] + w[3]) / 2
                if lines and abs(lines[-1][0] - y) < 20:
                    lines[-1][1].append(w)
                    lines[-1][0] = sum((v[1] + v[3]) / 2 for v in lines[-1][1]) / len(lines[-1][1])
                else:
                    lines.append([y, [w]])
            for _, ln in lines:
                ln.sort()
                h = statistics.median(w[1] - w[3] for w in ln)
                t = " ".join(w[4] for w in ln)
                if "MOSES" in t:
                    continue
                if h <= 27.5 and len(ln) >= 4:
                    break  # the notes
                if h >= 29:
                    out.append(t)
        el.clear()
    return out


def moses():
    lines = moses_lines()
    chapters, cur = [], None
    for i, t in enumerate(lines):
        nums = VERSES.get(i, [])
        t = re.sub(r"^(?:[0-9IΙ,:_.}~;᾿΄ςνοδτgjἄὰῷὸ¢]+\s)+", "", t) if (nums or re.match(r"^[}:_~τ¢ο᾿΄δg]+\s", t)) else t
        t = re.sub(r"^(?:\d(?=[a-z])|\d(?=[A-Z][a-z])|~J |ro\. |τι |τῷ |τὸ |to (?=the world))", "", t)
        for a, b in MOSES_FIX:
            t = t.replace(a, b)
        cuts = []
        for k, v in enumerate(nums):
            if isinstance(v, str):
                chapters.append({"n": len(chapters) + 1, "heading": "", "verses": [{"n": 1, "t": ""}]})
                cur = chapters[-1]["verses"]
                continue
            if not cuts and (i in POETRY or not cur[-1]["t"] or re.search(r"[.?!:;][’”']?\)?$", cur[-1]["t"])):
                cuts.append((0, v))
                continue
            after = cuts[-1][0] + 1 if cuts else 0
            m = BREAK.search(t, after) or re.compile(r"[,:;]\s+").search(t, after + 1)
            cuts.append((m.end() if m else after, v))
        pos = 0
        for at, v in cuts:
            add(cur, t[pos:at])
            cur.append({"n": v, "t": ""})
            pos = at
        add(cur, t[pos:], "\n" if i in POETRY and cur[-1]["t"] else None)
    for c in chapters:
        for v in c["verses"]:
            v["t"] = v["t"].strip()
    write("testament-of-moses", {
        "slug": "testament-of-moses", "name": "Testament of Moses (Assumption of Moses)", "language": "Latin",
        "translation": "R. H. Charles, 1913",
        "source": source("R. H. Charles, The Assumption of Moses, in The Apocrypha and Pseudepigrapha of the Old Testament (1913)", "https://archive.org/details/apocryphapseudep02charuoft",
                         "The translation from Latin by R. H. Charles, in The Apocrypha and Pseudepigrapha of the Old Testament in English, vol. 2 (Oxford: Clarendon Press, 1913), pp. 414-424",
                         "R. H. Charles, 1913", "Robert Henry Charles", "https://en.wikipedia.org/wiki/Robert_Henry_Charles",
                         "Charles's round brackets mark words he supplies, his square brackets words he takes as added later; his notes are left out. The only manuscript breaks off in chapter 12. " + NOTE),
        "chapters": chapters,
    })


def add(verses, text, sep=None):
    text = text.strip()
    if not text:
        return
    v = verses[-1]
    if v["t"].endswith("-"):
        v["t"] = v["t"][:-1] + text
    else:
        v["t"] = (v["t"] + (sep or " ") + text) if v["t"] else text


def apocalypse():
    ps = paragraphs("anf08.vii.xxxviii")
    first = next(i for i, p in enumerate(ps) if p.startswith("Account and life of Adam"))
    verses = [{"n": i + 1, "t": p} for i, p in enumerate(ps[first + 1:])]
    write("apocalypse-of-moses", {
        "slug": "apocalypse-of-moses", "name": "Apocalypse of Moses (Life of Adam and Eve, Greek)", "language": "Greek",
        "translation": "Alexander Walker, 1886",
        "source": source("Revelation of Moses, translated by Alexander Walker", CCEL + "anf08.vii.xxxviii.html",
                         "The translation from Greek by Alexander Walker, Revelation of Moses, in The Ante-Nicene Fathers, vol. 8 (1886), as transcribed by the Christian Classics Ethereal Library",
                         "Alexander Walker, 1886", "Alexander Walker", "",
                         "Walker's paragraphs are numbered in order; his translation does not print the chapter numbers of Tischendorf's edition. " + NOTE),
        "chapters": [{"n": 1, "heading": ps[first], "verses": verses}],
    })


def main():
    abraham()
    yetzirah()
    moses()
    apocalypse()
    return 0


if __name__ == "__main__":
    sys.exit(main())

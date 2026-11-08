#!/usr/bin/env python3
"""Build 1 Enoch (data/further/1-enoch.json) from Richard Laurence's translation.

Source: Richard Laurence, The Book of Enoch the Prophet, translated from an Ethiopic manuscript in the Bodleian
Library (London: Kegan Paul, Trench and Co., 1883), public domain; the scan of the Cornell University Library
copy on the Internet Archive, item cu31924090062823. This is the translation the site owner pasted from
Wikisource. The script downloads the OCR with word positions once into tools/.cache/enoch/ (git-ignored).

Each image is an opening of two pages. The text is set in type about 39 pixels tall; the footnotes, running
heads and page numbers about 29. The script keeps the large type of each page, left page first, from the title
"THE BOOK OF ENOCH." on, and drops footnote marks (^, *, a lone quotation mark). Only the chapter headings are
set in the large type with "CHAP."; their numerals are often misread ("CHAP. xni."), so they are counted in
order, 1 to 105, Laurence's own numbering, which differs from R. H. Charles's. Laurence has no chapter XI; his
note "(No CHAP. XI.)" stands for it. Section marks ("[SECT. II.]") are left out. Nothing is reworded.

Usage:  python3 tools/build_enoch.py
"""
import json, os, re, statistics, sys, urllib.request
import xml.etree.ElementTree as ET

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
CACHE = os.path.join(ROOT, "tools", ".cache", "enoch")
XML = os.path.join(CACHE, "laurence_djvu.xml")
OUT = os.path.join(ROOT, "data", "further", "1-enoch.json")
ITEM = "cu31924090062823"
UA = "thebible.exposed-importer/1.0 (https://thebible.exposed/)"
TENS = ["", "X", "XX", "XXX", "XL", "L", "LX", "LXX", "LXXX", "XC", "C"]
ONES = ["", "I", "II", "III", "IV", "V", "VI", "VII", "VIII", "IX"]
NO_XI = "(Laurence has no chapter XI.)"


def roman(n):
    return ("C" + TENS[(n - 100) // 10] if n >= 100 else TENS[n // 10]) + ONES[n % 10]


def numeral(read):
    """The value of a chapter numeral as the OCR read it ("XXIIL" 23, "XCVIll" 98, "cm." 103, "11." 2), or 0."""
    t = re.sub(r"[^A-Za-z1]", "", read.split(".")[0]).upper().replace("1", "I").replace("M", "III")
    t = re.sub(r"(?<=I)L$", "I", t)
    if not re.fullmatch(r"[CLXVI]+", t):
        return 0
    vals = {"C": 100, "L": 50, "X": 10, "V": 5, "I": 1}
    total = 0
    for i, ch in enumerate(t):
        v = vals[ch]
        total += -v if i + 1 < len(t) and vals[t[i + 1]] > v else v
    return total if 0 < total < 110 and roman(total) == t else 0


def fetch():
    if not os.path.exists(XML):
        os.makedirs(CACHE, exist_ok=True)
        url = "https://archive.org/download/%s/%s_djvu.xml" % (ITEM, ITEM)
        with urllib.request.urlopen(urllib.request.Request(url, headers={"User-Agent": UA}), timeout=300) as r:
            open(XML, "wb").write(r.read())


def page_lines(obj):
    """Each page of the opening as lines, left page first: [[(height, text), ...], [...]]."""
    width = int(obj.get("width"))
    words = []
    for w in obj.iter("WORD"):
        if w.text and w.text.strip():
            l, b, r, t = map(int, w.get("coords").split(",")[:4])
            words.append((l, b, r, t, w.text.strip()))
    pages = []
    for left in (True, False):
        ws = sorted((w for w in words if (w[2] < width / 2) == left), key=lambda w: (w[1] + w[3]) / 2)
        lines, cur = [], []
        for w in ws:
            if cur and abs((w[1] + w[3]) / 2 - (cur[-1][1] + cur[-1][3]) / 2) > 20:
                lines.append(cur)
                cur = []
            cur.append(w)
        if cur:
            lines.append(cur)
        page = []
        for ln in lines:
            ln.sort(key=lambda w: w[0])
            # A line of one or two small words ("swear ;") has a low median height; its tallest word still tells.
            hs = [w[1] - w[3] for w in ln]
            page.append([statistics.median(hs), " ".join(w[4] for w in ln), min(w[3] for w in ln), len(ln)])
        # A line of one to three small words ("swear ;") measures low; it is text when the lines above and below
        # it are text, a normal line apart.
        for i in range(1, len(page) - 1):
            a, b, c = page[i - 1], page[i], page[i + 1]
            if b[0] < 34 and b[3] <= 3 and a[0] >= 34 and c[0] >= 34 and b[2] - a[2] < 90 and c[2] - b[2] < 90:
                b[0] = 34
        pages.append([(h, t) for h, t, _, _ in page])
    return pages


def mend(text):
    text = re.sub(r"\s*[\^*°§]+", "", text)  # footnote marks
    text = re.sub(r"(?<=\s)['\"’]{1,2}(?=\s)", "", " " + text + " ")  # a footnote mark read as a lone quotation mark
    text = re.sub(r"\[SECT\.[^\]]*\]", "", text)
    text = re.sub(r"\s+([;:,.!?])", r"\1", text)  # the spaced stops of the typesetting ("said ; We")
    return re.sub(r"\s+", " ", text).strip()


def verses_of(text):
    text = re.sub(r"(?<=\s)1 (\d)\.\s", r"1\1. ", text)  # "1 8." for 18.
    text = re.sub(r"^1[.,]\s*", "", text)
    marks, want = [], 2
    for m in re.finditer(r"(?<![\w(])(\d{1,3})[.,]\s*(?=[\[(‘“\"'A-Z])", text):
        if int(m.group(1)) == want:
            marks.append((want, m.start(), m.end()))
            want += 1
    starts = [(1, 0, 0)] + marks
    out = []
    for i, (v, s, e) in enumerate(starts):
        end = starts[i + 1][1] if i + 1 < len(starts) else len(text)
        out.append({"n": v, "t": text[e:end].strip()})
    return out


def main():
    fetch()
    rows, on = [], False
    for obj in ET.parse(XML).getroot().iter("OBJECT"):
        for page in page_lines(obj):
            for h, t in page:
                if h > 55 and "BOOK OF ENOCH" in t.upper():
                    on = True
                elif on and (h >= 34 or t.startswith("(No CHAP")):
                    rows.append(t)
    chapters, cur, numerals = [], None, []
    for t in rows:
        if t.startswith("(No CHAP"):
            chapters.append([NO_XI])
            numerals.append("XI")
            cur = None
            continue
        m = re.match(r"^[.,:; ]*CHAP[.,]?\s+(\S+)", t)
        if m:
            cur = []
            chapters.append(cur)
            numerals.append(m.group(1))
        elif cur is not None:
            cur.append(t)
    # Number the headings by their numerals where the OCR read one that fits, in order; a chapter whose heading the
    # scan lost stays inside the chapter before and is listed.
    values = [numeral(r) for r in numerals]
    for i in range(len(values) - 1):
        if values[i] and values[i] == values[i + 1] and (values[i - 1] if i else 0) == values[i] - 2:
            values[i] -= 1  # "XLVIIL" then "XLVIIL^": the first is XLVII
    problems, numbered, last = [], [], 0
    for read, v, lines in zip(numerals, values, chapters):
        if not (last < v <= last + 3):
            if v == last and numbered:
                numbered[-1][1].extend(lines)  # the same heading again: one chapter
                continue
            problems.append("heading %r after chapter %d read as %d" % (read, last, last + 1))
            v = last + 1
        for missing in range(last + 1, v):
            problems.append("chapter %d: its heading is not in the scan's text; its words stay in chapter %d" % (missing, missing - 1))
            numbered.append((missing, None))
        numbered.append((v, lines))
        last = v
    out = []
    for c, lines in numbered:
        if lines is None:
            out.append({"n": c, "heading": "The scan of the 1883 edition does not show where this chapter begins; its words are in the chapter before.", "verses": []})
            continue
        if lines == [NO_XI]:
            out.append({"n": c, "heading": NO_XI.strip("()"), "verses": []})
            continue
        text = ""
        for ln in lines:
            text = text[:-1] + ln if text.endswith("-") and ln[:1].islower() else (text + " " + ln).strip()
        out.append({"n": c, "heading": "", "verses": verses_of(mend(text))})
    if len(out) != 105:
        problems.append("found %d chapters, not 105" % len(out))
    data = {
        "slug": "1-enoch",
        "name": "1 Enoch",
        "language": "Ge'ez",
        "translation": "Richard Laurence, 1883",
        "source": {
            "title": "Richard Laurence, The Book of Enoch the Prophet (1883)",
            "url": "https://archive.org/details/" + ITEM,
            "credit": "The translation from Ge’ez by Richard Laurence, The Book of Enoch the Prophet (London: Kegan Paul, Trench and Co., 1883)",
            "short": "Richard Laurence, 1883",
            "church": "It is not a translation authorised by the Ethiopian Orthodox Tewahedo Church.",
            "note": "Chapters and verses follow Laurence’s numbering, which differs from R. H. Charles’s; the text is read from the scan of the 1883 edition, without his notes.",
            "licence": "Public domain",
            "licence_url": "",
            "translator": {"type": "Person", "name": "Richard Laurence", "url": "https://en.wikipedia.org/wiki/Richard_Laurence"},
        },
        "chapters": out,
    }
    with open(OUT, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=1)
        f.write("\n")
    print("1 Enoch: %d chapters, %d verses" % (len(out), sum(len(c["verses"]) for c in out)))
    print(" ", [len(c["verses"]) for c in out])
    for p in problems:
        print("   ", p)
    return 0


if __name__ == "__main__":
    sys.exit(main())

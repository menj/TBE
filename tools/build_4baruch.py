#!/usr/bin/env python3
"""Build 4 Baruch in Greek (data/further/4-baruch.json) from J. Rendel Harris's edition.

Source: J. Rendel Harris, The Rest of the Words of Baruch (Cambridge: C. J. Clay and Sons, 1889), public domain;
the scan of the Cornell University Library copy on the Internet Archive, item cu31924096046218. No public-domain
English translation was found, so the site gives Harris's Greek text, "Ta Paraleipomena Ieremiou tou Prophetou".

The Internet Archive's own OCR reads the Greek as Latin letters, so the script reads the pages again itself. It
downloads the ten page openings once into tools/.cache/4baruch/ (git-ignored), cuts each page out of the opening,
keeps the block of text between the running head and the critical notes, and reads it with Tesseract
("grc+eng"); the reading is kept beside the image. It then drops lines that are only stray accents, the running
heads, the notes and the marginal references, mends the common misreadings of Greek as Latin ("Bapovy" for
Baruch), joins hyphenated words, and splits the text at Harris's chapter numerals (I. to IX.) and the verse
numbers in his margins. Where a margin number was misread, the verses are counted in order. A verse begins at the
first full stop or colon in the line its number stands beside. Where the scan lost a margin number, that verse
stays in the one before, which is labelled with both numbers ("23–24"). The Greek is not yet proofread against the scan.

Requires: tesseract with the grc and eng languages, and Pillow.
The English is MENJ's translation for the site, verse by verse, in tools/4baruch_en.json (keyed "chapter:verse"); each
verse gets it as "t" and keeps the Greek as "o", which the reader prints under the English.

Beside it, each chapter shows S. E. Robinson's translation (Charlesworth, The Old Testament Pseudepigrapha, 1985),
which the owner supplied as a scan under licence. It was read with Tesseract, its margin numbers, cross-references
and footnotes dropped, split at Robinson's verse marks, checked against his verse count in each chapter, and kept
in tools/4baruch_robinson.json (a list of chapters, each a list of verses).

Usage:  python3 tools/build_4baruch.py
"""
import json, os, re, subprocess, sys, time, urllib.request

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
CACHE = os.path.join(ROOT, "tools", ".cache", "4baruch")
OUT = os.path.join(ROOT, "data", "further", "4-baruch.json")
ITEM = "cu31924096046218"
UA = "thebible.exposed-importer/1.0 (https://thebible.exposed/)"
OPENINGS = range(54, 74, 2)  # chapter I starts on the right page of opening 54; IX ends on the left page of 72

# Greek words the OCR read as Latin letters or digits, in Harris's spelling.
MEND = [
    (r"\bBapov[yxX]\b|\bBapody\b|\bΒαρουχ\b|\bIJαροὺχ|Ἰ\)αροὺχ", "Βαροὺχ"),
    (r"\bKa[il]\b", "Καὶ"), (r"\bKat\b", "Καὶ"),
    (r"[‘'“\"]?\b[lI]epe[mp]i[ao]u\b|‘?lepeplov", "Ἱερεμίου"),
    (r"[‘'“\"]?\b[lI]epe[mp]ia[sr]\b", "Ἱερεμίας"), (r"[‘'“\"]?\bTepeuia[rn]\b", "Ἱερεμίαν"),
    (r"[‘'“\"]?\b(?:[lI]epe[mp]i|Tepeui)a\b", "Ἱερεμία"),
    (r"\b(?:Iatep|Watep|Llatep|Ilatep)\b", "Πάτερ"),
    (r"\bTHY\b", "τὴν"), (r"\bTas\b", "τὰς"), (r"\bta\b", "τὰ"), (r"\bara\b", "ἀλλὰ"),
    (r"\b[MW]eds\b", "Θεὸς"), (r"\bpov\b", "μου"), (r"\bpera\b", "μετὰ"), (r"\bMera\b", "Μετὰ"),
    (r"\bAutos\b", "Αὐτὸς"), (r"\bAi yap\b", "Αἱ γὰρ"), (r"\byap\b", "γὰρ"), (r"\beis\b", "εἰς"),
    (r"‘Os\b", "Ὡς"), (r"‘O\b", "Ὁ"), (r"(?<=\s)6(?=\s)", "ὁ"), (r"\bTh\b", "Τῇ"),
    (r"᾿Λβιμέλεχ|᾿Αβιμελεχ", "᾿Αβιμέλεχ"), (r"᾿Β(?=[λγ])", "᾿Ε"), (r"“(?=[Ἱ᾿Ἔ])", ""),
]
ROMAN = {"I": 1, "II": 2, "III": 3, "IV": 4, "V": 5, "VI": 6, "VII": 7, "VIII": 8, "IX": 9}


def image(n):
    path = os.path.join(CACHE, "p%d.jpg" % n)
    if not os.path.exists(path):
        os.makedirs(CACHE, exist_ok=True)
        url = "https://archive.org/download/%s/page/n%d_w3000.jpg" % (ITEM, n)
        with urllib.request.urlopen(urllib.request.Request(url, headers={"User-Agent": UA}), timeout=120) as r:
            open(path, "wb").write(r.read())
        time.sleep(1)
    return path


def ocr(n):
    """The text of each page of opening n, left page first, as Tesseract reads it (cached)."""
    from PIL import Image
    cached = os.path.join(CACHE, "p%d.txt" % n)
    if os.path.exists(cached):
        return open(cached, encoding="utf-8").read().split("\n=====\n")
    img = Image.open(image(n)).convert("L")
    W, H = img.size
    halves = (1,) if n == 54 else (0,) if n == 72 else (0, 1)
    out = []
    for half in halves:
        pg = img.crop((0, 0, W // 2, H) if half == 0 else (W // 2, 0, W, H))
        w, h = pg.size
        bw = pg.point(lambda p: 0 if p < 140 else 255)
        ink = [sum(1 for x in range(int(w * 0.1), int(w * 0.9), 3) if bw.getpixel((x, y)) == 0) > 2 for y in range(h)]
        runs, y = [], 0
        while y < h:
            if ink[y]:
                s = y
                while y < h and ink[y]:
                    y += 1
                runs.append((s, y))
            y += 1
        runs = [r for r in runs if r[1] - r[0] > 8]
        if len(runs) < 3:
            continue
        # The first run is the running head; the notes start after the widest gap in the lower part of the page.
        gaps = [(runs[i + 1][0] - runs[i][1], i) for i in range(1, len(runs) - 1) if runs[i][1] > h * 0.3]
        last = max(gaps)[1] if gaps else len(runs) - 1
        crop = pg.crop((0, runs[1][0] - 10, w, runs[last][1] + 10))
        crop = crop.resize((crop.width * 2, crop.height * 2))
        tmp = os.path.join(CACHE, "crop.png")
        crop.save(tmp)
        r = subprocess.run(["tesseract", tmp, "-", "-l", "grc+eng", "--psm", "6"], capture_output=True, text=True, check=True)
        out.append(r.stdout)
        os.remove(tmp)
    open(cached, "w", encoding="utf-8").write("\n=====\n".join(out))
    return out


def greek_share(t):
    letters = [c for c in t if c.isalpha()]
    return (sum(1 for c in letters if "Ͱ" <= c <= "Ͽ" or "ἀ" <= c <= "῿") / len(letters)) if letters else 0, len(letters)


def lines_of(page):
    """(chapter numeral or None, margin verse number or None, text) for each line of text on a page."""
    out = []
    for raw in page.splitlines():
        t = raw.strip()
        if re.search(r"ΠΡΟΦΗΤ|ΠΑΡΑΛΕΙΠ|ΙΕΡΕ[ΜΛ]", t):
            continue  # the running head
        if re.match(r"^\W{0,3}(?:v|vy|Ὁ|υ)\.?\s*\d+\s", t) or re.search(r"\baeth\b|\|\s*v\.", t):
            break  # the critical notes
        t = re.sub(r"^(?:[|\]\[,͵'`’‘]|F\|)\s*", "", t)
        t = re.sub(r"\s+(?:Ioh|Ps|Is|Jer|Zap|Gen|Ex|Matt?)\.[\w. ]*$", "", t)  # marginal references
        while True:  # stray marks from the binding at the end of a line
            m = re.search(r"\s+[|'‘’\"ἹὶἸΊΪ:.,ν1J*{}᾿]{1,2},?$", t)
            if not m:
                break
            t = t[: m.start()]
        num = None
        m = re.match(r"^(\d{1,2})\s+", t) or re.search(r"\s+(\d{1,2})$", t)
        if m:
            num = int(m.group(1))
            t = (t[: m.start()] + t[m.end():]).strip() if m.start() else t[m.end():]
        chap = None
        m = re.match(r"^(?:\d\s+)?([IΙVlΧXΠῚTΥ]{1,4})[.,]\s+", t)
        if m and len(m.group(1)) <= 4:
            chap, t = m.group(1), t[m.end():]
        share, n = greek_share(t)
        if chap is None and (n < 8 or share < 0.5):
            continue  # a line of stray accents
        out.append((chap, num, t))
    return out


def mend(t):
    for a, b in MEND:
        t = re.sub(a, b, t)
    t = t.replace("..", ".").replace("ε΄", "ε")
    return re.sub(r"\s+([,.;·:])", r"\1", re.sub(r"\s+", " ", t)).strip()


BREAK = re.compile(r"[.;·:]\s+(?=[Ͱ-Ͽἀ-῿᾿῾‘“\"'A-Z])")


def main():
    lines = []
    for n in OPENINGS:
        for page in ocr(n):
            lines.extend(lines_of(page))
    chapters, verse, text = [], None, ""
    for chap, num, t in lines:
        if chap is not None:
            chapters.append({"n": len(chapters) + 1, "heading": "", "verses": [{"n": 1, "t": ""}]})
            verse, num = chapters[-1]["verses"][0], None
        if not chapters:
            continue
        cur = chapters[-1]["verses"]
        if num is not None:
            want = cur[-1]["n"] + 1
            n = num if want <= num <= want + 1 else want
            prev = cur[-1]["t"]
            m = None if re.search(r"[.;·:]$", prev) else BREAK.search(t)
            head, t = (t[: m.start() + 1], t[m.end():]) if m else ("", t)
            if head:
                cur[-1]["t"] = join(prev, head)
            if n > want:  # a margin number the scan lost: that verse is still inside the one before
                cur[-1]["l"] = "%d–%d" % (cur[-1]["n"], n - 1)
            cur.append({"n": n, "t": ""})
        cur[-1]["t"] = join(cur[-1]["t"], t)
    problems = []
    for c in chapters:
        for v in c["verses"]:
            v["t"] = mend(v["t"])
        joined = [v["l"] for v in c["verses"] if "l" in v]
        if joined:
            problems.append("chapter %d: verses run together %s" % (c["n"], ", ".join(joined)))
    english = json.load(open(os.path.join(ROOT, "tools", "4baruch_en.json"), encoding="utf-8"))
    for c in chapters:
        for v in c["verses"]:
            key = "%d:%d" % (c["n"], v["n"])
            if key not in english:
                problems.append("no English for " + key)
                continue
            v["o"], v["t"] = v["t"], english.pop(key)
    problems += ["English for a verse not in the Greek: " + k for k in english]
    if len(chapters) != 9:
        problems.append("found %d chapters, not 9" % len(chapters))
    data = {
        "slug": "4-baruch",
        "name": "4 Baruch (The Rest of the Words of Baruch)",
        "language": "Greek",
        "translation": "MENJ, from J. Rendel Harris's Greek text (1889)",
        "source": {
            "title": "J. Rendel Harris, The Rest of the Words of Baruch (1889)",
            "url": "https://archive.org/details/" + ITEM,
            "credit": "Translated verse by verse by MENJ for thebible.exposed from the Greek text edited by J. Rendel Harris, The Rest of the Words of Baruch: A Christian Apocalypse of the Year 136 A.D. (Cambridge: C. J. Clay and Sons, 1889)",
            "short": "MENJ, from Harris's Greek (1889)",
            "note": "No English translation in the public domain was found, so MENJ made one for the site, verse by verse, and prints Harris's Greek under each verse. The Greek is read by machine from the scan of the 1889 edition and is not yet proofread, so some letters and accents are wrong; the translation follows the sense of Harris's text where the reading is damaged. Harris's critical notes are left out.",
            "licence": "Public domain",
            "licence_url": "",
            "translator": {"type": "Person", "name": "MENJ", "url": "https://thebible.exposed/"},
        },
        "chapters": chapters,
        "parallel": {
            "title": "S. E. Robinson's translation",
            "credit": "S. E. Robinson, \u201c4 Baruch,\u201d in J. H. Charlesworth (ed.), The Old Testament Pseudepigrapha, vol. 2 (New York: Doubleday, 1985), pp. 418\u2013425, used with the site owner's licence. His verse numbers follow the edition of R. A. Kraft and A.-E. Purintun, which has some lines Harris's Greek lacks.",
            "chapters": {str(i + 1): ch for i, ch in enumerate(json.load(open(os.path.join(ROOT, "tools", "4baruch_robinson.json"), encoding="utf-8")))},
        },
    }
    with open(OUT, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=1)
        f.write("\n")
    print("4 Baruch: %d chapters, verses %s" % (len(chapters), [len(c["verses"]) for c in chapters]))
    for p in problems:
        print("   ", p)
    return 0


def join(a, b):
    if not a:
        return b
    if not b:
        return a
    return a[:-1] + b if a.endswith("-") else a + " " + b


if __name__ == "__main__":
    sys.exit(main())

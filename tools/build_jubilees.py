#!/usr/bin/env python3
"""Build Jubilees (data/further/jubilees.json) from R. H. Charles's translation of 1902.

Source: R. H. Charles, The Book of Jubilees or the Little Genesis, translated from the editor's Ethiopic text
(London: Adam and Charles Black, 1902), public domain. The scan and its OCR are on the Internet Archive, item
bookofjubileesor00char. The script downloads the OCR with word positions (bookofjubileesor00char_djvu.xml)
once into tools/.cache/jubilees/ (git-ignored) and reads it from there afterwards.

Each page sets Charles's translation across the full width in larger type, his notes below it in two columns
of smaller type, and A.M. dates in the margin. The script keeps the words of the translation only: lines whose
type is at least 33 pixels tall, above the first line of notes, with margin dates dropped. The short italic
summary Charles prints before each chapter is dropped too. The text is then split at the chapter numerals
(I. to L.) and at the verse numbers, in order. Nothing is reworded; only OCR slips that are certain are mended
(the small capitals of GOD read as "Gop", a drop capital, a hyphen at a line end, Charles's dagger marks).
The script reports every chapter whose verse numbers do not run 1, 2, 3 ... without a gap.

Usage:  python3 tools/build_jubilees.py
"""
import json, os, re, statistics, sys, urllib.request
import xml.etree.ElementTree as ET

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
CACHE = os.path.join(ROOT, "tools", ".cache", "jubilees")
XML = os.path.join(CACHE, "charles_djvu.xml")
OUT = os.path.join(ROOT, "data", "further", "jubilees.json")
ITEM = "bookofjubileesor00char"
UA = "thebible.exposed-importer/1.0 (https://thebible.exposed/)"
# Places where words of Charles's notes run into his translation on the same line of the scan, or the scan is
# misread, mended by hand against the page images: (chapter, verse): [(misread, as printed), ...].
CORRECTIONS = {
    (3, 8): [("{πὶ the first week", "In the first week")],
    (4, 15): [(" 34 THE BOOK OF JUBILEES", ""), ("should do Jerahmeel, xxiv. 8. exposition is given in Syncellus judgment", "should do judgment")],
    (33, 22): [("Reuben, 16. anticipates (His own) possession = Nas ἱερατικὸς Simeon", "Reuben, Simeon")],
    (38, 20): [("from ᾿Ατηδβδαᾶ, reigned", "from [a place name the scan does not read], reigned")],
    (38, 24): [(" 14. day. was finally 20. LXX, Σαλαμά tributary by Hyrcanus.", "")],
    (47, 9): [("And 5. Syr. appears in Syr. Frag. as «26.952, as Θέρafterwards,", "And afterwards,")],
}
ROMAN = ["", "I", "II", "III", "IV", "V", "VI", "VII", "VIII", "IX", "X"]
DATE = re.compile(r"^[\d\-–.,]+$|^[aA]\.?[mM]\.?,?$|^B\.?C\.?$|^ANNO$|^MUNDI\.?$|^[A-Z.]+$")


def roman(n):
    tens = ["", "X", "XX", "XXX", "XL", "L"]
    return tens[n // 10] + ROMAN[n % 10]


def fetch():
    if os.path.exists(XML):
        return
    os.makedirs(CACHE, exist_ok=True)
    url = "https://archive.org/download/%s/%s_djvu.xml" % (ITEM, ITEM)
    with urllib.request.urlopen(urllib.request.Request(url, headers={"User-Agent": UA}), timeout=300) as r:
        open(XML, "wb").write(r.read())


def page_lines(obj):
    """The page's words grouped into lines by height on the page: [{l, r, y, h, ws}]."""
    words = []
    for w in obj.iter("WORD"):
        if w.text and w.text.strip():
            l, b, r, t = map(int, w.get("coords").split(",")[:4])
            words.append((l, b, r, t, w.text.strip()))
    words.sort(key=lambda w: (w[1] + w[3]) / 2)
    lines, cur = [], []
    for w in words:
        cy = (w[1] + w[3]) / 2
        if cur and abs(cy - cur[-1][0]) > 22:
            lines.append(cur)
            cur = []
        cur.append((cy, w))
    if cur:
        lines.append(cur)
    out = []
    for ln in lines:
        ws = sorted((w for _, w in ln), key=lambda w: w[0])
        out.append({"l": ws[0][0], "r": max(w[2] for w in ws), "y": min(w[3] for w in ws), "h": statistics.median(w[1] - w[3] for w in ws), "ws": ws})
    return out


def text_of(ln):
    return " ".join(w[4] for w in ln["ws"])


def translation_blocks(root):
    """The translation, page by page, as blocks of lines separated by a wide gap: [[line, ...], ...]."""
    pages = [page_lines(o) for o in root.iter("OBJECT")]
    first = next(i for i, p in enumerate(pages) if any(text_of(l).startswith("Tuis is the history") for l in p))
    last = next(i for i, p in enumerate(pages) if i > first and any(text_of(l).strip() in ("INDEX I", "INDEX I.") for l in p[:3]))
    blocks = []
    for lines in pages[first:last]:
        if lines and lines[0]["y"] < 200 and re.search(r"CHAPTER|BOOK|^\S+$", text_of(lines[0])):
            lines = lines[1:]  # the running head
        big = [l for l in lines if l["h"] >= 36 and l["r"] - l["l"] > 1000]
        if not big:
            continue
        left = statistics.median(l["l"] for l in big)
        right = statistics.median(l["r"] for l in big)
        # The notes start at the first of three lines in a row set in the small type.
        # The notes start at the first line, below the start of the text, set in the small type with the next
        # line small too.
        small = [l["h"] < 34 and l["r"] - l["l"] > 300 for l in lines]
        notes = next((lines[i]["y"] for i in range(len(lines) - 1) if lines[i]["y"] > big[0]["y"] and small[i] and small[i + 1]), 10 ** 9)
        kept = []
        for l in lines:
            if l["y"] >= notes:
                break
            if l["h"] > 55 and "JUBILEES" in text_of(l):
                continue  # the book's title over chapter I
            # Verse numbers of the poems stand out in the left margin ("24."); dates there are dropped.
            ws = [w for w in l["ws"] if not ((w[0] > right + 15 or w[2] < left - 15) and DATE.match(w[4]) and not (w[2] < left and re.match(r"^\d{1,2}\.$", w[4])))]
            ws = [w for w in ws if not (w[0] > right + 15 and len(w[4]) <= 3)]
            if not ws or statistics.median(w[1] - w[3] for w in ws) < 20:
                continue
            # A short last line of text can share its height on the page with the first line of a note beside
            # it: split the line at wide gaps and drop a piece set in the small type.
            segs, seg = [], [ws[0]]
            for a, b in zip(ws, ws[1:]):
                if b[0] - a[2] > 60:
                    segs.append(seg)
                    seg = []
                seg.append(b)
            segs.append(seg)
            if len(segs) > 1:
                ws = [w for sg in segs if statistics.median(x[1] - x[3] for x in sg) >= 34 or re.match(r"^\d{1,2}\.$", sg[0][4]) for w in sg]
                if not ws:
                    continue
            # Word by word: a word with a capital or a tall letter stands about 41 pixels in the text and 30 in
            # the notes. Drop the notes' words, and a short word between two dropped ones.
            tall = re.compile(r"[A-Zbdfhklt0-9()\[\]]")
            note = [bool(tall.search(w[4])) and (w[1] - w[3]) < 33 and not re.match(r"^\d{1,2}\.$", w[4]) for w in ws]
            for i, w in enumerate(ws):
                if not tall.search(w[4]) and 0 < i < len(ws) - 1 and note[i - 1] and note[i + 1]:
                    note[i] = True
            if sum(note) and sum(note) < len(ws):
                ws = [w for w, n in zip(ws, note) if not n]
            line_text = " ".join(w[4] for w in ws)
            greek = len(re.findall(r"[\u0370-\u03ff\u1f00-\u1fff]", line_text))
            if greek > 6 and greek > 0.25 * len(line_text.replace(" ", "")):
                continue  # a line of Greek from the notes: the translation has none
            kept.append({"t": line_text, "y": l["y"], "in": ws[0][0] - left})
        # A chapter's summary is set with a hanging indent: its first line full width, the rest indented about
        # 130 pixels, and it cites verses ("1-16."). Drop the run and its first line.
        drop = set()
        i = 0
        while i < len(kept):
            j = i
            while j < len(kept) and 110 <= kept[j]["in"] <= 180:
                j += 1
            if j - i >= 1:
                run = list(range(max(0, i - 1) if i > 0 and kept[i - 1]["in"] < 60 else i, j))
                text = " ".join(kept[k]["t"] for k in run)
                if len(re.findall(r"\d+(?:-\s?\d+)?[.,;:)]", text)) >= 1 and not re.search(r"^[IVXL]+\.", kept[i]["t"]):
                    drop.update(run)
                i = j
            else:
                i += 1
        prev_y, block = None, []
        for k, l in enumerate(kept):
            if k in drop:
                if block:
                    blocks.append(block)
                    block = []
                prev_y = None
                continue
            if prev_y is not None and l["y"] - prev_y > 110 and block:
                blocks.append(block)
                block = []
            block.append(l["t"])
            prev_y = l["y"]
        if block:
            blocks.append(block)
    return blocks


MARGIN = r"(?:A\.?\s?M\.?|\d{1,4}(?:-\d{1,4})?\s?[aA]\.?\s?[mM]\.?|ANNO|MUNDI\.?)"


def strip_margin(line):
    """Drop the A.M. dates the OCR ran into the start or end of a line ("2172am. XLV. And Israel ...")."""
    line = re.sub(r"^(?:%s\s+)+" % MARGIN, "", line.strip())
    return re.sub(r"(?:\s+%s)+$" % MARGIN, "", line)


def trim_summary(block):
    """Cut a chapter summary from the end of a block that runs into the next chapter's numeral.

    A summary is a run of short clauses, each closed by the verses it covers ("Return of Judah, 15-18."),
    usually ending with "(Cf. Gen. ...)". Lines are dropped from the end while they read like that; a line
    holding the end of the chapter's own text and the summary's first words keeps the text part.
    """
    text = " ".join(block)
    starts = [x.start() for x in re.finditer(r"(?:^|(?<=[.!?”’]) )(?=[A-Z(])", text)]
    cut = None
    for k, st in enumerate(starts):
        tail = text[st:]
        first = text[st:starts[k + 1]] if k + 1 < len(starts) else tail
        if not re.search(r"[,:]\s?\d+(?:-\s?\d+)?[a-z]?\s?[.,;:)(]|\(c[ft]\.", first, re.I):
            continue
        refs = re.findall(r"[,:]\s?\d+(?:-\s?\d+)?[a-z]?\s?(?:[.,;:)]|\(cf)|\(C[ft]\.?", tail)
        words = len(tail.split())
        if refs and len(refs) * 14 >= words and re.search(r"(\d+[.)]|\.\))\s*$", tail.strip()):
            cut = st
            break
    if cut is None:
        return block, 0
    kept = text[:cut].strip()
    return ([kept] if kept else []), 1


def heading(line, n):
    """Whether a line opens chapter n: its numeral, allowing the OCR's 1 or l for I and a final I read as L."""
    tok = line.split(" ", 1)[0]
    want = roman(n)
    norm = tok.replace("1", "I").replace("l", "I").rstrip(".,")
    if want.endswith("I") and norm == want[:-1] + "L":
        return True  # a final I read as L: "XXL." for XXI., "XXVIL" for XXVII.
    return tok.endswith((".", ",")) and norm == want


def verse_marks(text):
    """The verse marks in a chapter's text, in order: [(verse, start, end)], verse 1 at the start.

    Candidates are a number of one or two digits, or a single glyph the OCR made of one, followed by a full stop
    or comma. The longest rising run of numbers is kept; then a gap of missing numbers is filled from the
    candidates between, nearest the next kept number first, so a "3." read as "ὃ." still counts.
    """
    cands = []
    for m in re.finditer(r"(?<![\w(\[,])(\d{1,2}|[^\x00-\x7f\s\w“”‘’—–]|[{}]|[bIlOS][\dlI]|\d[lIO])[.,]\s+(?=\S)", text):
        tok = m.group(1)
        cands.append((m.start(), m.end(), int(tok) if tok.isdigit() else None))
    nums = [(i, c[2]) for i, c in enumerate(cands) if c[2] is not None and 2 <= c[2] <= 60]
    # Longest strictly rising run, ties broken towards the earliest numbers.
    best = [1] * len(nums)
    prev = [-1] * len(nums)
    for j in range(len(nums)):
        for i in range(j):
            if nums[i][1] < nums[j][1] and best[i] + 1 > best[j] and nums[j][1] - nums[i][1] <= 4:
                best[j], prev[j] = best[i] + 1, i
    keep = []
    if nums:
        j = max(range(len(nums)), key=lambda k: (best[k], -nums[k][1]))
        while j >= 0:
            keep.append(nums[j])
            j = prev[j]
        keep.reverse()
    marks = {cands[i][0]: (v, cands[i][0], cands[i][1]) for i, v in keep}
    # Fill the gaps.
    anchors = [(1, 0)] + [(v, cands[i][0]) for i, v in keep]
    used = {cands[i][0] for i, _ in keep}
    for (a, pa), (b, pb) in zip(anchors, anchors[1:]):
        if b - a < 2:
            continue
        between = [c for c in cands if pa < c[0] < pb and c[0] not in used]
        for k, c in enumerate(reversed(between[-(b - a - 1):])):
            marks[c[0]] = (b - 1 - k, c[0], c[1])
    # After the last kept number, take a mark the OCR misread ("bl." for 31., "52." for 32.).
    last_v, last_p = anchors[-1]
    for c in cands:
        if c[0] > last_p and c[0] not in used and like(text[c[0]:c[1]].rstrip(" .,"), last_v + 1):
            marks[c[0]] = (last_v + 1, c[0], c[1])
            last_v += 1
    return [(1, 0, 0)] + sorted(marks.values(), key=lambda m: m[1])


SEEN_AS = {"b": "3", "l": "1", "I": "1", "O": "0", "o": "0", "S": "5", "s": "5", "B": "8"}
CONFUSED = {frozenset("53"), frozenset("83"), frozenset("65"), frozenset("17"), frozenset("38")}


def like(tok, n):
    """Whether an OCR token is plausibly the verse number n: equal after reading look-alike letters as digits,
    or one digit off by a common confusion (5 and 3, 8 and 3)."""
    tok = "".join(SEEN_AS.get(ch, ch) for ch in tok)
    want = str(n)
    if tok == want:
        return True
    if len(tok) != len(want) or not tok.isdigit():
        return False
    diff = [(a, b) for a, b in zip(tok, want) if a != b]
    return len(diff) == 1 and frozenset(diff[0]) in CONFUSED


def tidy(text):
    """Drop the stray glyphs the OCR made of specks and margin marks: a lone Greek letter or symbol between words,
    and a verse mark left at the very end."""
    text = re.sub(r"(?<=\s)[\u0370-\u03ff\u1f00-\u1fff᾿’ʼ;:]{1,3}[.,]?(?=\s)", "", " " + text + " ")
    text = re.sub(r"\s\d{1,2}\.?\s*$", "", text.strip())
    return re.sub(r"\s+", " ", text).strip()


def mend(text):
    text = re.sub(r"\s\d{1,4}(?:-\d{1,4})?\.?\s?a\.\s?m\.?,?(?=\s)", "", text)  # "she 1-77a.m, gave": a margin date
    text = re.sub(r"\s(?:a\.m\.|A\.M\.?)(?=\s)", "", text)
    text = re.sub(r"\bGop\b", "God", text)
    text = re.sub(r"\bGop’s\b", "God’s", text)
    text = text.replace("©", "‘").replace("}", "").replace("†", "")
    text = re.sub(r"(?<!\w)\+\s*|\s*\+(?!\w)", " ", text)
    return re.sub(r"\s+", " ", text).strip()


def join(lines):
    out = ""
    for ln in lines:
        ln = ln.strip()
        if out.endswith("-") and ln[:1].islower():
            out = out[:-1] + ln
        else:
            out = (out + " " + ln).strip()
    return out


def main():
    fetch()
    blocks = translation_blocks(ET.parse(XML).getroot())
    # Drop each chapter's summary: the block just before a chapter numeral (or the prologue) that cites verses.
    flat, n, dropped = [], 1, 0
    for i, b in enumerate(blocks):
        if heading(b[0], n):
            n += 1
        nxt = blocks[i + 1][0] if i + 1 < len(blocks) else ""
        opens = heading(nxt, n) or nxt.startswith("Tuis is the history")
        if opens:
            b, cut = trim_summary(b)
            dropped += cut

        for k, line in enumerate(b):
            flat.append((k == 0, strip_margin(line)))
    # Split into the prologue and chapters at the chapter numerals, in order.
    chapters, cur, prologue, lost = [], None, [], []
    for first, line in flat:
        tok = line.split(" ", 1)[0]
        skip = heading(line, len(chapters) + 2) and not tok[:1].isdigit() and len(chapters) >= 1
        if (heading(line, len(chapters) + 1) or skip) and (first or not tok[:1].isdigit()):
            if skip:
                lost.append(len(chapters) + 1)
                chapters.append([])
            cur = [line.split(" ", 1)[1] if " " in line else ""]
            chapters.append(cur)
        elif cur is None:
            prologue.append(line)
        else:
            cur.append(line)
    prologue_text = mend(join(prologue)).replace("Tuis is", "This is", 1)
    out, problems = [], ["chapter %d: its numeral was not found; it is still inside chapter %d" % (n, n - 1) for n in lost]
    for c, lines in enumerate(chapters, 1):
        text = mend(join(lines))
        trimmed, cut = trim_summary([text])
        if cut and trimmed:
            text = trimmed[0]
        starts = verse_marks(text)
        verses = []
        for i, (v, st, e) in enumerate(starts):
            end = starts[i + 1][1] if i + 1 < len(starts) else len(text)
            t = tidy(text[e:end])
            for bad, good in CORRECTIONS.get((c, v), []):
                if bad not in t:
                    problems.append("chapter %d verse %d: correction not applied: %s" % (c, v, bad))
                t = t.replace(bad, good)
            verses.append({"n": v, "t": tidy(t)})
        missing = sorted(set(range(1, starts[-1][0] + 1)) - {v for v, _, _ in starts})
        if missing:
            problems.append("chapter %d: no mark for verse %s (its words stay with the verse before)" % (c, ", ".join(map(str, missing))))
        out.append({"n": c, "heading": "", "verses": verses})
    if len(chapters) != 50:
        problems.append("found %d chapters, not 50" % len(chapters))
    data = {
        "slug": "jubilees",
        "name": "Jubilees",
        "language": "Ge'ez",
        "translation": "R. H. Charles, 1902",
        "source": {
            "title": "R. H. Charles, The Book of Jubilees or the Little Genesis (1902)",
            "url": "https://archive.org/details/" + ITEM,
            "credit": "The translation from Ge’ez by R. H. Charles, The Book of Jubilees or the Little Genesis (London: Adam and Charles Black, 1902)",
            "short": "R. H. Charles, 1902",
            "church": "It is not a translation authorised by the Ethiopian Orthodox Tewahedo Church.",
            "note": "The text is read from the scan of the 1902 edition, without Charles’s notes and chapter summaries; where the scan lost a verse number, that verse’s words stay with the verse before.",
            "translator": {"type": "Person", "name": "R. H. Charles", "url": "https://en.wikipedia.org/wiki/Robert_Henry_Charles"},
            "licence": "Public domain",
            "licence_url": "",
        },
        "prologue": prologue_text,
        "chapters": out,
    }
    with open(OUT, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=1)
        f.write("\n")
    print("Jubilees: %d chapters, %d verses; %d chapter summaries dropped" % (len(out), sum(len(c["verses"]) for c in out), dropped))
    for c in out:
        print("  %2d: %d verses" % (c["n"], len(c["verses"])))
    for p in problems:
        print("   ", p)
    return 0


if __name__ == "__main__":
    sys.exit(main())

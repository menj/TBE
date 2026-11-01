#!/usr/bin/env python3
"""Turn Ahmed Deedat's "Combat Kit Against Bible Thumpers" (IPCI, 1992) into commentary entries.

The booklet carries an "Open Order" giving permission to reproduce or translate it, and its PDF
metadata says "No Copyright". We reproduce the author's words as published, attribute them, and
change only: capitalisation of Bible references (GENESIS 12:19 -> Genesis 12:19, so they can link),
the join of wrapped lines, and a few obvious typesetting slips (listed in TYPOS below).

Usage:  python3 tools/build_combat_kit.py combat-kit.pdf
Needs:  pdftotext (poppler). Writes data/commentary/combat-kit.json.
"""
import html, json, os, re, subprocess, sys

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
OUT = os.path.join(ROOT, "data", "commentary", "combat-kit.json")
SOURCE = "Ahmed Deedat, Combat Kit Against Bible Thumpers (Islamic Propagation Centre International, 1992)"

# Topics not imported, and why. They stay out until the site owner decides.
HELD_BACK = {
    39: "Its heading and glosses characterise gay and lesbian people in demeaning terms.",
    31: "A cross-reference only (\"see Swine\"), no content of its own.",
    42: "A cross-reference only (\"see Israelites\"), no content of its own.",
}
# Topics whose own heading is a demeaning generalisation about a people. The verses and notes are kept;
# the heading is left out and the entry title uses only the neutral topic word.
NO_HEADING = {17, 23, 25}
# Topics whose lettered items are independent claims about different passages: one entry per item.
SPLIT = {3, 8, 10, 14}


# Entry titles: the author's topic word, kept neutral (his headings stay out of titles).
TOPIC_TITLES = {
    1: "Arabs and Arabia", 2: "Abraham", 3: "Absurdities", 4: "Alcohol", 5: "Apostasy", 6: "Bastard",
    7: "Circumcision", 8: "Contradictions", 9: "David", 10: "God: qualities attributed to Him",
    11: 'God with a small "g"', 12: "God: seeing God", 13: "God: confusion and evil",
    14: "God: further contradictions", 15: "Holy Ghost", 16: "Incest", 17: "Israelites", 18: "Jeremiah",
    19: "Jesus (pbuh)", 20: "Jesus (pbuh): power and knowledge", 21: "Jesus (pbuh): the mission to Israel",
    22: "Jesus (pbuh): sundry points", 23: "Jews", 24: "Keturah", 25: "Massacre", 26: "Melchisedek",
    27: "Messiah", 28: "Muhummed (pbuh)", 29: "Onanism", 30: "Paul", 32: "Polygamy", 33: "Prophecies",
    34: "Prophets (but naked)", 35: "Rape", 36: "Sabbath", 37: "Sarah", 38: "Slavery", 40: "Sons of God",
    41: "Swine", 43: "Women", 44: "Worship",
}
# Where the topic's own subject lives, when it is not the first passage the author cites.
ANCHOR_OVERRIDES = {37: "Genesis 21:1-2"}

# Typesetting slips in the PDF text layer (original -> corrected).
TYPOS = {
    "DEUTRONOMY": "DEUTERONOMY",
    "GENE5IS": "GENESIS", "15-I8": "15-18", "marraige": "marriage", "CONDTRADICTS": "CONTRADICTS",
    "UNSATIABLE": "UNSATIABLE", "l slept": "I slept", "Thamar in \"c\" above": "Thamar in \"c\" above",
    "BEGOTTEN              thee": "BEGOTTEN thee",
}

# The author's footnotes sit at the foot of a page whose last topic is not always the one they belong to:
# (topic the parser found, page) -> topic the footnote is about.
FOOTNOTE_REASSIGN = {(22, 21): 21, (33, 27): 30}


def load_books():
    with open(os.path.join(ROOT, "data", "books.json"), encoding="utf8") as f:
        return [b["name"] for b in json.load(f)]


def book_regex(names):
    extra = {"1 COR": "1 Corinthians", "2 COR": "2 Corinthians"}
    alts = sorted(list(names) + list(extra), key=len, reverse=True)
    pattern = r"(?<![A-Za-z0-9])(" + "|".join(re.escape(a) for a in alts) + r")\.?(?=\s+\d{1,3}:\d)"
    canon = {a.lower(): a for a in names}
    canon.update({k.lower(): v for k, v in extra.items()})
    return re.compile(pattern, re.I), canon


def pdf_text(pdf):
    return subprocess.run(["pdftotext", "-layout", pdf, "-"], capture_output=True, text=True, check=True).stdout


def split_pages(text):
    pages = {}
    current = None
    for line in text.split("\n"):
        m = re.match(r"^\s*Combat Kit\s+Page\s+(\d+)\s*$", line)
        if m:
            current = int(m.group(1))
            pages[current] = []
            continue
        if current is not None:
            pages[current].append(line.rstrip())
    return pages


def parse_topics(pages):
    """Return [{n, title, subtitle, page, lines:[(page, line)], footnotes:{page:[(num,text)]}}]."""
    topics = []
    expected = 1
    for page in sorted(pages):
        lines = pages[page]
        i = 0
        while i < len(lines):
            line = lines[i]
            m = re.match(r"^\s*(\d{1,2})\.\s+(.+?)\s*:\s*(.*)$", line)
            if m and int(m.group(1)) == expected and expected <= 44:
                topics.append({"n": expected, "title": m.group(2).strip(), "subtitle": m.group(3).strip(),
                               "page": page, "lines": [], "footnotes": {}})
                expected += 1
                i += 1
                continue
            fn = re.match(r"^\s*(\d)\.\s+(\S.*)$", line)
            if topics and fn and (page not in topics[-1]["footnotes"] or True) and _is_footnote(lines, i):
                text = [fn.group(2).strip()]
                j = i + 1
                while j < len(lines) and lines[j].strip() and not re.match(r"^\s*\d\.\s", lines[j]):
                    text.append(lines[j].strip())
                    j += 1
                topics[-1]["footnotes"].setdefault(page, []).append((int(fn.group(1)), " ".join(text)))
                i = j
                continue
            if topics and "\x0c" not in line:
                topics[-1]["lines"].append((page, line))
            i += 1
    return topics


def _is_footnote(lines, i):
    """A footnote is a numbered line in the bottom block of a page (nothing but footnotes after it)."""
    rest = [l for l in lines[i:] if l.strip()]
    return all(re.match(r"^\s*\d\.\s", l) or re.match(r"^\s{2,}\S", l) or re.match(r"^\S", l) for l in rest) and \
        not any(re.match(r"^\s*\(\w\)", l) or re.match(r"^\s*\d{1,2}\.\s+[A-Z].*:\s", l) for l in rest[1:])


def _is_caps(s):
    letters = re.sub(r"[^A-Za-z]", "", s)
    return len(letters) >= 8 and letters.isupper() and '"' not in s and not re.search(r"\d:\d", s)


def _is_subhead(s):
    """The author's run-in sub-headings: 'Contradicted by:', 'INCEST BETWEEN MOTHER AND SON:', 'WHOLESALE RAPE ...!'"""
    if '"' in s or "\u201c" in s or len(s) > 90:
        return False
    if s.endswith(":") and len(s) <= 70:
        return True
    letters = re.sub(r"[^A-Za-z]", "", s)
    return len(letters) >= 10 and letters.isupper() and s[-1] in ":!" and not re.search(r"\d:\d", s)


def paragraphs(lines):
    """Group wrapped lines into paragraphs. A blank line, a lettered label or a sub-heading starts a new one;
    a sentence that runs across a page break is kept together."""
    paras, cur = [], []
    last_page = None
    pending_break = False
    for page, raw in lines:
        s = raw.strip()
        if not s:
            pending_break = True
            continue
        joined = " ".join(cur)
        crosses_page = last_page is not None and page != last_page
        unfinished = bool(cur) and bool(re.search(r"[A-Za-z,;]$", joined))
        if pending_break and cur and not (crosses_page and unfinished):
            paras.append(joined)
            cur = []
        elif cur and _is_caps(cur[-1]) and _is_caps(s):
            pass                                            # a wrapped all-caps heading
        elif cur and (re.match(r"^\([a-z]\)\s", s) or _is_subhead(s) or _is_subhead(cur[-1])):
            paras.append(joined)
            cur = []
        cur.append(s)
        pending_break = False
        last_page = page
    if cur:
        paras.append(" ".join(cur))
    return [re.sub(r"\s+", " ", p).strip() for p in paras if p.strip()]


def fix_typos(s):
    for bad, good in TYPOS.items():
        s = s.replace(bad, good)
    return s


def first_reference(text, book_re, canon):
    """First Bible reference in the text (skipping Qur'an citations): (book, chapter, verse, end)."""
    for m in book_re.finditer(text):
        before = text[max(0, m.start() - 14):m.start()].lower()
        if "qur" in before or "surah" in before:
            continue
        rest = text[m.end():]
        mm = re.match(r"\s+(\d{1,3}):(\d{1,3})(?:\s*[-–]\s*(\d{1,3}))?", rest)
        if mm:
            name = canon[re.sub(r"\.$", "", m.group(1)).lower()]
            return name, int(mm.group(1)), int(mm.group(2)), int(mm.group(3) or mm.group(2))
    return None


def normalise_refs(text, book_re, canon):
    return book_re.sub(lambda m: canon[re.sub(r"\.$", "", m.group(1)).lower()], text)


def blocks(paras, strip_label):
    """WordPress block markup: Scripture quotes for quoted text, paragraphs for the rest."""
    out = []
    for p in paras:
        label = ""
        m = re.match(r"^(\([a-z]\))\s+(.*)$", p)
        body = p
        if m and strip_label:
            body = m.group(2)
        quote = re.match(r"^(\([a-z]\)\s+)?[\"“]|^(\([a-z]\)\s+)?\.\s\.\s\.", body)
        esc = html.escape(body, quote=False)
        if quote:
            out.append('<!-- wp:quote {"className":"is-style-scripture"} --><blockquote class="wp-block-quote is-style-scripture"><p>%s</p></blockquote><!-- /wp:quote -->' % esc)
        else:
            out.append("<!-- wp:paragraph --><p>%s</p><!-- /wp:paragraph -->" % esc)
    return "\n".join(out)


def title_case(s):
    small = {"and", "of", "a", "the", "in", "to", "but"}
    words = s.strip().split()
    out = []
    for i, w in enumerate(words):
        bare = w.strip("()\"").lower()
        if bare == "pbuh":
            out.append(w.upper())
        elif i and bare in small:
            out.append(w.lower())
        else:
            out.append(w[:1].upper() + w[1:].lower() if not w.startswith(("(", '"')) else w[0] + w[1:2].upper() + w[2:].lower())
    return " ".join(out)


def main():
    if len(sys.argv) < 2:
        sys.exit(__doc__)
    names = load_books()
    book_re, canon = book_regex(names)
    pages = split_pages(pdf_text(sys.argv[1]))
    topics = [t for t in parse_topics({p: v for p, v in pages.items() if p >= 4}) if t["n"] <= 44]

    entries, skipped = [], []
    for t in topics:
        if t["n"] in HELD_BACK:
            skipped.append((t["n"], title_case(t["title"]), HELD_BACK[t["n"]]))
            continue
        topic = TOPIC_TITLES.get(t["n"]) or title_case(t["title"])
        sub = re.sub(r"\s+-\s*$", "", fix_typos(t["subtitle"]))
        # A short phrase is the author's heading; a long one, one that starts with a quotation, or one that
        # cites a verse is already body text that happens to sit on the topic line.
        is_heading = bool(sub) and len(sub) <= 80 and not re.search(r"\d{1,3}:\d", sub) and not sub.startswith(('"', "“", ". . ."))
        runs_on = bool(t["lines"]) and bool(t["lines"][0][1].strip()) and not t["subtitle"].rstrip().endswith(("-", ":"))
        if runs_on:
            is_heading = False
        lines = list(t["lines"])
        if sub and not is_heading:
            lines.insert(0, (t["page"], sub))
        paras = [fix_typos(p) for p in paragraphs(lines)]
        heading = [sub] if is_heading and t["n"] not in NO_HEADING else []

        if t["n"] in SPLIT:
            items, cur = [], []
            for p in paras:
                if re.match(r"^\([a-z]\)\s", p) and cur and re.match(r"^\([a-z]\)\s", cur[0]):
                    items.append(cur)
                    cur = []
                cur.append(p)
            if cur:
                items.append(cur)
            groups = [(re.match(r"^\(([a-z])\)", g[0]).group(1) if re.match(r"^\(([a-z])\)", g[0]) else None, g) for g in items]
        else:
            groups = [(None, paras)]

        merged = []
        for letter, g in groups:
            ref = first_reference(" ".join(g), book_re, canon)
            if ref is None and merged:                      # no Bible passage of its own: belongs with the previous item
                merged[-1][1].extend(g)
                continue
            merged.append([letter, list(g)])

        for k, (letter, g) in enumerate(merged):
            text = normalise_refs(" ".join(g), book_re, canon)
            ref = first_reference(text, book_re, canon)
            if ref is None:
                skipped.append((t["n"], topic, "No Bible passage to anchor the entry to."))
                continue
            book, ch, vs, ve = ref
            if k == 0 and t["n"] in ANCHOR_OVERRIDES:
                m2 = re.match(r"^(.+?) (\d+):(\d+)(?:-(\d+))?$", ANCHOR_OVERRIDES[t["n"]])
                book, ch, vs, ve = m2.group(1), int(m2.group(2)), int(m2.group(3)), int(m2.group(4) or m2.group(3))
            passage = "%s %d:%d%s" % (book, ch, vs, "-%d" % ve if ve > vs else "")
            title = "%s%s: %s" % (topic, " (%s)" % letter if letter else "", passage)
            body_paras = [normalise_refs(p, book_re, canon) for p in g]
            lead = [("Deedat's heading: " + normalise_refs(h, book_re, canon)) for h in heading] if k == 0 else []
            body = ""
            if lead:
                body += "\n".join('<!-- wp:paragraph --><p><em>%s</em></p><!-- /wp:paragraph -->' % html.escape(x, quote=False) for x in lead) + "\n"
            body += blocks(body_paras, strip_label=bool(letter))

            notes = []
            for (n_found, pg), target in [(key, tgt) for key, tgt in FOOTNOTE_REASSIGN.items()]:
                pass
            for owner in topics:
                for pg, fns in owner["footnotes"].items():
                    if FOOTNOTE_REASSIGN.get((owner["n"], pg), owner["n"]) != t["n"]:
                        continue
                    if letter and k != len(merged) - 1:
                        continue                              # footnotes go with the last item of a split topic
                    for num, ftext in fns:
                        notes.append("Author's note: " + normalise_refs(fix_typos(ftext), book_re, canon))
            seen = set()
            for n in notes:
                if n not in seen:
                    seen.add(n)
                    body += '\n<!-- wp:paragraph {"className":"tbe-source"} --><p class="tbe-source">%s</p><!-- /wp:paragraph -->' % html.escape(n, quote=False)
            body += '\n<!-- wp:paragraph {"className":"tbe-source"} --><p class="tbe-source">From %s, no. %d, p. %d. Reproduced under the publisher\'s open permission to reproduce. This is the author\'s argument, set beside the text so it can be weighed; the ESV appears on hover.</p><!-- /wp:paragraph -->' % (html.escape(SOURCE, quote=False), t["n"], t["page"])
            entries.append({
                "key": "combat-kit:%d%s" % (t["n"], ":" + letter if letter else ""),
                "title": title,
                "passage": passage,
                "themes": [topic],
                "section": "critical-commentary",
                "page": t["page"],
                "content": body,
            })

    known = {n.lower() for n in names} | {"1 cor", "2 cor"}
    for e in entries:
        plain = re.sub(r"<[^>]+>", " ", e["content"])
        for m in re.finditer(r"((?:[1-3]\s)?[A-Z][A-Za-z]+(?:\sof\s[A-Z][a-z]+)?)\.?\s+\d{1,3}:\d", plain):
            if m.group(1).lower() not in known and m.group(1).lower() not in {"page", "verse", "surah", "qur"}:
                print("   WARNING unrecognised book name in %s: %r" % (e["key"], m.group(1)))
    os.makedirs(os.path.dirname(OUT), exist_ok=True)
    pack = {
        "id": "combat-kit",
        "name": "Ahmed Deedat — Combat Kit Against Bible Thumpers",
        "source": SOURCE,
        "licence": "Open Order: permission to reproduce or translate, for sale or free distribution.",
        "held_back": [{"topic": n, "title": t, "reason": r} for n, t, r in skipped],
        "entries": entries,
    }
    json.dump(pack, open(OUT, "w", encoding="utf8"), ensure_ascii=False, indent=1)
    print("topics read: %d | entries: %d | held back or skipped: %d" % (len(topics), len(entries), len(skipped)))
    for n, t, r in skipped:
        print("   skipped %d %s: %s" % (n, t, r))


if __name__ == "__main__":
    main()

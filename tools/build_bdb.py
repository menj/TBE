#!/usr/bin/env python3
"""Build the Brown-Driver-Briggs articles for the Hebrew lexicon pages (data/lexicon/bdb/{shard}.json).

Source: Francis Brown, S. R. Driver and Charles A. Briggs, A Hebrew and English Lexicon of the Old Testament, with
an appendix containing the Biblical Aramaic (Oxford: Clarendon Press, 1906), public domain, in the electronic
"Enhanced Brown-Driver-Briggs" pages at www.ericlevy.com/revel/bdb/bdb/, which the site owner asked to use.
tools/fetch_bdb.py keeps a copy of the pages in tools/.cache/bdb/ (git-ignored); this script reads the copy.

Each article starts at a headword followed by its Strong's number ("S 24"); everything up to the next numbered
headword (cross-reference headwords, the senses on later paragraphs) belongs to it. The Strong's number keys the
article to the lexicon page /lexicon/h0024/. The TWOT and GK numbers the electronic edition adds are left out
(they belong to other reference works); BDB's own text is kept, with its Hebrew, its sense numbers and its
references. An article that names several Strong's numbers is filed under each.

Writes data/lexicon/bdb/{n // 100}.json: {"H0024": ["<p>...</p>", ...]}, as simple HTML: Hebrew in
<span lang="he" dir="rtl">, glosses in <i>, sense numbers in <b>.

Usage:  python3 tools/build_bdb.py
"""
import glob, html, json, os, re, sys

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
CACHE = os.path.join(ROOT, "tools", ".cache", "bdb")
OUT = os.path.join(ROOT, "data", "lexicon", "bdb")
ORDER = ["1/al", "2/bet", "3/gim", "4/dal", "5/he", "6/waw", "7/zay", "8/het", "9/tet", "10/yod", "11/kap", "12/lam",
         "13/mem", "14/num", "15/sam", "16/ayi", "17/pe", "18/sad", "19/kop", "20/res", "21/sin", "22/taw", "ext/aram"]
HEBREW = {"textStyle4", "textStyle5", "textStyle8", "textStyle9"}
SPAN = re.compile(r'<SPAN ID="(textStyle\d+)">(.*?)</SPAN>|<sup>(.*?)</sup>', re.S | re.I)


def spans(paragraph):
    """The paragraph's pieces in order: [(style, text)]."""
    out = []
    for m in SPAN.finditer(paragraph):
        if m.group(1):
            out.append((m.group(1), html.unescape(re.sub(r"<[^>]+>", "", m.group(2)))))
        else:
            out.append(("sup", html.unescape(re.sub(r"<[^>]+>", " ", m.group(3)))))
    return out


def render(pieces):
    """Simple HTML for a paragraph, without the TWOT and GK numbers."""
    out, skip = [], False
    for style, text in pieces:
        if skip and style == "textStyle6":
            skip = False
            continue
        skip = False
        if style == "textStyle3" and text.strip() in ("TWOT", "GK", "S"):
            skip = True
            continue
        if style == "sup":
            if text.strip(" .") == "":
                continue
            out.append("<small>" + html.escape(text.strip()) + "</small>")
        elif style in HEBREW or re.search(r"[֐-׿]", text) and not re.search(r"[A-Za-z]", text):
            out.append('<span lang="he" dir="rtl">' + html.escape(text.strip()) + "</span>")
        elif style == "textStyle11":
            out.append("<i>" + html.escape(text) + "</i>")
        elif style == "textStyle15":
            out.append("<b>" + html.escape(text) + "</b>")
        elif style == "textStyle3" and text.strip() == "•":
            continue  # the bullet the electronic edition puts before numbered headwords
        else:
            out.append(html.escape(text))
    s = " ".join(out)
    s = re.sub(r"\s+([,.;:)])", r"\1", s)
    s = re.sub(r"\(\s+", "(", s)
    return re.sub(r"\s+", " ", s).strip()


def strongs(pieces):
    """The Strong's numbers the paragraph gives its headword: the numbers after an "S" label."""
    nums, take = [], False
    for style, text in pieces:
        if style == "textStyle3":
            take = text.strip() == "S"
            continue
        if take and style == "textStyle6":
            nums += re.findall(r"\d+", text)
            continue
        take = False
    return ["H%04d" % int(n) for n in nums]


def main():
    articles, current = {}, None
    for prefix in ORDER:
        folder, stem = prefix.rsplit("/", 1)
        pages = sorted(glob.glob(os.path.join(CACHE, folder, stem + "[0-9]*.html")), key=lambda p: int(re.search(r"(\d+)\.html$", p).group(1)))
        for page in pages:
            body = open(page, encoding="latin-1").read()
            for para in re.findall(r"<P ID=\"pStyle[^\"]*\">(.*?)</P>", body, re.S | re.I):
                pieces = spans(para)
                if not pieces:
                    continue
                nums = strongs(pieces)
                text = render(pieces)
                if nums:
                    current = {"nums": nums, "p": [text]}
                    for n in nums:
                        articles.setdefault(n, []).append(current)
                elif current is not None and text and not re.match(r'^<span lang="he" dir="rtl">[^<]*</span> v\. ', text):
                    current["p"].append(text)  # a later paragraph of the article; a bare "see under" headword is left out
        print(prefix, len(pages), "pages", flush=True)
    shards = {}
    for num, arts in articles.items():
        paras = []
        for a in arts:
            paras += a["p"]
        shards.setdefault(int(num[1:]) // 100, {})[num] = paras
    os.makedirs(OUT, exist_ok=True)
    for key, data in shards.items():
        with open(os.path.join(OUT, "%d.json" % key), "w", encoding="utf-8") as f:
            json.dump(dict(sorted(data.items())), f, ensure_ascii=False, separators=(",", ":"))
    print("BDB: %d Strong's numbers with an article" % len(articles))
    return 0


if __name__ == "__main__":
    sys.exit(main())

#!/usr/bin/env python3
"""Index the public-domain manuscript facsimiles on the Internet Archive by the passage under each plate.

Each plate in these facsimiles carries a printed caption naming the passage it shows ("Ioh. VII. 43 - VIII. 4",
"JOHN vii. 46 - viii. 16."). This script fetches the strip of every page that holds the caption, reads it with
Tesseract, and writes tools/.cache/facsimiles/{item}.json: leaf -> {"caption", "book", "from": [c, v], "to": [c, v]}.
Pages are fetched one at a time, a second apart, and cached, so a second run asks nothing again. The index is a
working aid for choosing leaves for data/manuscripts/images.json; every page chosen is still checked by eye.

Usage:  python3 tools/index_facsimiles.py [item ...]
"""
import io, json, os, re, subprocess, sys, tempfile, time, urllib.request

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
CACHE = os.path.join(ROOT, "tools", ".cache", "facsimiles")
UA = "thebible.exposed-importer/1.0 (https://thebible.exposed/)"
# item: (number of leaves, caption strip as fractions of the page height, manuscript)
ITEMS = {
    # The Bezae facsimile's small italic captions defeat the OCR; its leaves are found by eye from the captions.
    "facsimileofwashi02sand": (410, (0.76, 0.86), "032"),
}
BOOKS = {"toh": "john", "loh": "john", "lob": "john", "mait": "matthew", "matt": "matthew", "marc": "mark", "mark": "mark", "luc": "luke", "luke": "luke", "ioh": "john",
         "joh": "john", "john": "john", "act": "acts", "acts": "acts"}
ROMAN = {"i": 1, "v": 5, "x": 10, "l": 50}


def roman(s):
    # OCR reads i as l, j or 1; no chapter reaches 50 (L), so these are all i.
    s = s.lower().replace("j", "i").replace("l", "i").replace("1", "i") if not s.isdigit() else s
    if s.isdigit():
        return int(s)
    total = 0
    for i, ch in enumerate(s):
        if ch not in ROMAN:
            return None
        v = ROMAN[ch]
        total += -v if i + 1 < len(s) and ROMAN.get(s[i + 1], 0) > v else v
    return total


def parse(caption):
    """'Ioh . VII . 43 _ VIII . 4' -> ("john", [7, 43], [8, 4])."""
    t = re.sub(r"[_—–]", "-", caption)
    t = re.sub(r"\b\w*(?:ATT|TTH|THEW|IATI)\w*\b", "Matthew", t)  # the OCR's many readings of MATTHEW
    m = re.search(r"\b(Matt|Mait|Marc|Mark|Luc|Luke|Ioh|Joh|John|Toh|Loh|Acts?)\w*\W+([IVXLivxlj1]+)\W+(\d+)(?:\s*-\s*(?:([IVXLivxlj1]+)\W+)?(\d+))?", t, re.I)
    if not m:
        return None
    book = BOOKS.get(m.group(1).lower()[:4]) or BOOKS.get(m.group(1).lower()[:3])
    c1, v1 = roman(m.group(2)), int(m.group(3))
    c2 = roman(m.group(4)) if m.group(4) else c1
    v2 = int(m.group(5)) if m.group(5) else v1
    if not (book and c1 and c2):
        return None
    return book, [c1, v1], [c2, v2]


def caption(item, leaf, strip):
    from PIL import Image

    url = "https://archive.org/download/%s/page/n%d_w1200.jpg" % (item, leaf)
    with urllib.request.urlopen(urllib.request.Request(url, headers={"User-Agent": UA}), timeout=120) as r:
        img = Image.open(io.BytesIO(r.read())).convert("L")
    w, h = img.size
    crop = img.crop((int(w * 0.2), int(h * strip[0]), int(w * 0.85), int(h * strip[1])))
    crop = crop.resize((crop.width * 3, crop.height * 3)).point(lambda p: 255 if p > 170 else 0)
    with tempfile.NamedTemporaryFile(suffix=".png") as f:
        crop.save(f.name)
        out = subprocess.run(["tesseract", f.name, "-", "--psm", "6", "-l", "eng"], capture_output=True, text=True)
    return re.sub(r"\s+", " ", out.stdout).strip()


def main():
    os.makedirs(CACHE, exist_ok=True)
    for item in sys.argv[1:] or list(ITEMS):
        leaves, strip, ms = ITEMS[item]
        path = os.path.join(CACHE, item + ".json")
        index = json.load(open(path)) if os.path.exists(path) else {}
        for leaf in range(leaves):
            if str(leaf) in index:
                continue
            try:
                text = caption(item, leaf, strip)
            except Exception as e:  # a missing page is noted and skipped
                text = "ERROR %s" % e
            hit = parse(text)
            index[str(leaf)] = {"caption": text}
            if hit:
                index[str(leaf)].update({"book": hit[0], "from": hit[1], "to": hit[2]})
            if leaf % 25 == 0:
                json.dump(index, open(path, "w"), indent=0)
                print(item, leaf, text[:60], flush=True)
            time.sleep(1)
        json.dump(index, open(path, "w"), indent=0)
        print(item, "done:", sum(1 for v in index.values() if "book" in v), "of", len(index), "captions read", flush=True)


if __name__ == "__main__":
    sys.exit(main())

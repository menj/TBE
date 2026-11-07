#!/usr/bin/env python3
"""Fetch the manuscript photographs listed in data/manuscripts/images.json from Wikimedia Commons.

Each entry names a Commons file, the passage page it belongs on and the manuscript it shows, with a caption
written for the site. Every image is chosen by hand and checked by eye to show the passage. This script then:
  1. reads the file's licence, author and description page from the Commons API, and refuses any file that is
     not in the public domain or under CC0, CC BY or CC BY-SA;
  2. downloads a copy no wider than 1200 pixels (Commons' own thumbnail when the original is wider);
  3. saves it as assets/img/manuscripts/{passage}-{manuscript}.jpg (re-encoded with Pillow, metadata dropped);
  4. writes the licence, author and link back into images.json, so the page can credit it.

It identifies itself as Wikimedia asks, makes one request at a time, waits between them and backs off on a 429.
Files already fetched are skipped; --force fetches again.

Usage:  python3 tools/fetch_manuscript_images.py [--force]
"""
import argparse, html, io, json, os, re, sys, time, urllib.error, urllib.parse, urllib.request

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
LIST = os.path.join(ROOT, "data", "manuscripts", "images.json")
OUT = os.path.join(ROOT, "assets", "img", "manuscripts")
API = "https://commons.wikimedia.org/w/api.php?"
UA = "thebible.exposed-importer/1.0 (https://thebible.exposed/)"
FREE = re.compile(r"^(public domain|pd\b|cc0|cc by(-sa)? [0-9.]+)", re.I)
WIDTH = 1200


def fetch(url):
    """The body of one request, waiting and retrying on a rate limit."""
    for wait in (0, 60, 120, 240, 480):
        time.sleep(wait)
        try:
            with urllib.request.urlopen(urllib.request.Request(url, headers={"User-Agent": UA}), timeout=60) as r:
                body = r.read()
        except urllib.error.HTTPError as e:
            if e.code != 429:
                raise
            print("  rate limited, waiting", file=sys.stderr)
            continue
        if not body.startswith(b"You are making too many"):
            return body
        print("  rate limited, waiting", file=sys.stderr)
    raise SystemExit("Wikimedia kept refusing; try again later")


def text(meta, key):
    value = html.unescape(meta.get(key, {}).get("value", ""))
    return re.sub(r"\s+", " ", re.sub(r"<[^>]+>", "", value)).strip()


def info(title):
    params = {"action": "query", "prop": "imageinfo", "iiprop": "url|size|extmetadata", "iiurlwidth": WIDTH,
              "titles": title, "format": "json", "maxlag": 5}
    data = json.loads(fetch(API + urllib.parse.urlencode(params)))
    page = next(iter(data["query"]["pages"].values()))
    if "imageinfo" not in page:
        raise SystemExit("not on Commons: " + title)
    return page["imageinfo"][0]


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--force", action="store_true")
    args = ap.parse_args()
    from PIL import Image

    entries = json.load(open(LIST, encoding="utf8"))
    os.makedirs(OUT, exist_ok=True)
    for e in entries["images"]:
        name = "%s-%s.jpg" % (e["passage"], e["ms"].lower())
        path = os.path.join(OUT, name)
        if os.path.exists(path) and e.get("license") and not args.force:
            continue
        print(e["file"])
        ii = info(e["file"])
        meta = ii.get("extmetadata", {})
        licence = text(meta, "LicenseShortName")
        if not FREE.match(licence):
            print("  skipped: licence is", repr(licence))
            continue
        url = ii["thumburl"] if ii.get("width", 0) > WIDTH and ii.get("thumburl") else ii["url"]
        time.sleep(5)
        img = Image.open(io.BytesIO(fetch(url))).convert("RGB")
        if img.width > WIDTH:
            img = img.resize((WIDTH, round(img.height * WIDTH / img.width)), Image.LANCZOS)
        img.save(path, "JPEG", quality=85, optimize=True, progressive=True)
        e.update({
            "src": "assets/img/manuscripts/" + name,
            "width": img.width,
            "height": img.height,
            "license": licence,
            "license_url": text(meta, "LicenseUrl"),
            "author": text(meta, "Artist")[:200],
            "page": ii["descriptionurl"],
        })
        print("  saved", name, img.width, "x", img.height, licence)
        json.dump(entries, open(LIST, "w", encoding="utf8"), ensure_ascii=False, indent=1)
        time.sleep(10)
    json.dump(entries, open(LIST, "w", encoding="utf8"), ensure_ascii=False, indent=1)


if __name__ == "__main__":
    sys.exit(main())

#!/usr/bin/env python3
"""Fetch the manuscript photographs listed in data/manuscripts/images.json from Wikimedia Commons.

Each entry names a Commons file, the passage page it belongs on and the manuscript it shows, with a caption
written for the site. Every image is chosen by hand and checked by eye to show the passage. For each entry not
yet fetched, this script uses tools/commons_api.py to:
  1. read the file's licence, author and page (from Commons, or from Magnus Manske's Commons API on Toolforge
     when Commons refuses), and skip any file that is not public domain, CC0, CC BY or CC BY-SA;
  2. save a JPEG no wider than 1200 pixels, metadata dropped, as assets/img/manuscripts/{passage}-{ms}.jpg;
  3. write the size, licence, author and page back into images.json, so the page can credit it.

The client spaces its requests, honours Wikimedia's Retry-After, and caches every answer, so a run cut short by
a rate limit picks up where it stopped. Files already fetched are skipped; --force fetches them again.

Usage:  python3 tools/fetch_manuscript_images.py [--force] [--local "File:Name.jpg=path/to/copy.jpg" ...]
        --local uses a copy of the image already downloaded from Commons, when upload.wikimedia.org keeps
        refusing; the licence, author and page are still read from Commons.
"""
import argparse, json, os, sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import commons_api  # noqa: E402

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
LIST = os.path.join(ROOT, "data", "manuscripts", "images.json")
OUT = os.path.join(ROOT, "assets", "img", "manuscripts")


def save(entries):
    with open(LIST, "w", encoding="utf8") as f:
        json.dump(entries, f, ensure_ascii=False, indent=1)
        f.write("\n")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--force", action="store_true")
    ap.add_argument("--local", action="append", default=[], help='"File:Name=path": read this image from a local copy')
    args = ap.parse_args()
    local = dict(item.split("=", 1) for item in args.local)

    entries = json.load(open(LIST, encoding="utf8"))
    failed = 0
    for e in entries["images"]:
        name = "%s-%s.jpg" % (e["passage"], e["ms"].lower())
        path = os.path.join(OUT, name)
        if os.path.exists(path) and e.get("license") and not args.force:
            continue
        print(e["file"])
        try:
            meta = commons_api.info(e["file"])
            if not commons_api.is_free(meta["licence"]):
                print("  skipped: the licence is", repr(meta["licence"]))
                continue
            w, h, meta = commons_api.download(e["file"], path, local=local.get(e["file"]))
        except commons_api.RateLimited:
            print("  Wikimedia kept refusing; run again later to fetch it")
            failed += 1
            continue
        e.update({"src": "assets/img/manuscripts/" + name, "width": w, "height": h, "license": meta["licence"],
                  "author": meta["author"][:200], "page": meta["page"]})
        print("  saved", name, w, "x", h, meta["licence"])
        save(entries)
    save(entries)
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())

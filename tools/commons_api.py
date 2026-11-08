#!/usr/bin/env python3
"""A small, polite client for Wikimedia Commons: search files, read their licence and author, download a copy.

Two APIs, tried in this order:
  - the MediaWiki API of Commons itself (https://commons.wikimedia.org/w/api.php), for search and file details
    (licence, author, description, size, the original and a thumbnail address);
  - Magnus Manske's Commons API on Toolforge (https://magnustools.toolforge.org/commonsapi/, documented at
    https://commons.wikimedia.org/wiki/Commons:Commons_API), for file details when Commons answers with a rate
    limit. It names the licence templates, which licence_name() turns into "Public domain", "CC0", "CC BY 4.0",
    "CC BY-SA 3.0" and so on.

How it behaves, as Wikimedia asks (https://foundation.wikimedia.org/wiki/Policy:User-Agent_policy):
  - every request names this client and the site in its User-Agent;
  - one request at a time, at least DELAY seconds apart on the same host;
  - on a 429 or a "too many requests" page it waits as long as the Retry-After header says (or 60, 120, 240,
    480 seconds), then tries again, and gives up with RateLimited after that;
  - answers are cached in tools/.cache/commons/ (git-ignored), so running again asks nothing it already asked.

Licences: is_free() accepts public domain, CC0, CC BY and CC BY-SA. GFDL and the rest are refused: GFDL would
need the licence's full text beside every copy, and others may forbid reuse.

Command line:
  python3 tools/commons_api.py search "Codex Bezae John"         # file titles matching the words
  python3 tools/commons_api.py info "File:Mark16-B.JPG" ...      # licence, author, size, page, description
  python3 tools/commons_api.py download "File:Mark16-B.JPG" out.jpg [--width 1200]
                                                                  # a JPEG no wider than --width, metadata dropped
  Add --json to search and info for machine-readable output.

As a library (tools/fetch_manuscript_images.py uses it):
  import commons_api
  commons_api.search("Codex Sinaiticus Mark 16")  -> ["File:...", ...]
  commons_api.info("File:Mark16-B.JPG")           -> {"title", "url", "thumb", "width", "height", "licence",
                                                      "author", "description", "page", "source"}
  commons_api.download("File:Mark16-B.JPG", "out.jpg", width=1200) -> (width, height, info)
"""
import argparse, hashlib, html, io, json, os, re, sys, time, urllib.error, urllib.parse, urllib.request
import xml.etree.ElementTree as ET

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
CACHE = os.path.join(ROOT, "tools", ".cache", "commons")
API = "https://commons.wikimedia.org/w/api.php"
TOOLFORGE = "https://magnustools.toolforge.org/commonsapi/"
UA = "thebible.exposed-importer/1.0 (https://thebible.exposed/; Commons reuse with credit)"
DELAY = 5
WAITS = (60, 120, 240, 480)
WIDTH = 1200
FREE = re.compile(r"^(public domain|pd\b|cc0|cc by(-sa)? [0-9.]+)", re.I)

_last = {}


class RateLimited(Exception):
    """Wikimedia kept answering "too many requests"."""


def log(msg):
    print(msg, file=sys.stderr)


def _limited(body):
    return body[:200].lstrip().startswith(b"You are making too many")


def get(url, cache=True):
    """The body of one GET, cached, spaced from the last request to the same host, retried on a rate limit."""
    key = os.path.join(CACHE, hashlib.sha1(url.encode()).hexdigest())
    if cache and os.path.exists(key):
        with open(key, "rb") as f:
            return f.read()
    host = urllib.parse.urlsplit(url).netloc
    for attempt in range(len(WAITS) + 1):
        gap = DELAY - (time.time() - _last.get(host, 0))
        if gap > 0:
            time.sleep(gap)
        _last[host] = time.time()
        wait = WAITS[min(attempt, len(WAITS) - 1)]
        try:
            with urllib.request.urlopen(urllib.request.Request(url, headers={"User-Agent": UA}), timeout=60) as r:
                body = r.read()
            if not _limited(body):
                if cache:
                    os.makedirs(CACHE, exist_ok=True)
                    with open(key, "wb") as f:
                        f.write(body)
                return body
        except urllib.error.HTTPError as e:
            if e.code != 429:
                raise
            retry = e.headers.get("Retry-After", "")
            wait = int(retry) if retry.isdigit() else wait
        if attempt == len(WAITS):
            break
        log("  %s asks us to slow down; waiting %d s" % (host, wait))
        time.sleep(wait)
    raise RateLimited(url)


def _api(params):
    return json.loads(get(API + "?" + urllib.parse.urlencode(dict(params, format="json", maxlag=5))))


def _plain(value):
    value = re.sub(r"<span[^>]*display:\s*none;?[^>]*>.*?</span>", "", html.unescape(value or ""))
    return re.sub(r"\s+", " ", re.sub(r"<[^>]+>", "", value)).strip()


def title_of(name):
    """"Mark16-B.JPG" or "File:Mark16-B.JPG" or a Commons URL -> "File:Mark16-B.JPG"."""
    name = urllib.parse.unquote(name.rsplit("/wiki/", 1)[-1]).replace("_", " ").strip()
    return name if name.lower().startswith("file:") else "File:" + name


def licence_name(tags):
    """Toolforge licence templates (["CC-PD-Mark", "PD-old-100-expired"]) -> a short name ("Public domain")."""
    for tag in tags:
        m = re.match(r"^cc-by(-sa)?-([0-9.]+)", tag, re.I)
        if m:
            return "CC BY%s %s" % ("-SA" if m.group(1) else "", m.group(2))
    for tag in tags:
        if re.match(r"^(cc-?zero|cc0)", tag, re.I):
            return "CC0"
        if re.match(r"^(pd\b|pd-|cc-pd)", tag, re.I):
            return "Public domain"
    return tags[0] if tags else ""


def is_free(licence):
    """Public domain, CC0, CC BY or CC BY-SA: reusable on the site with a credit."""
    return bool(FREE.match(licence or ""))


def search(query, limit=10):
    """File titles on Commons that match the words."""
    data = _api({"action": "query", "list": "search", "srnamespace": 6, "srlimit": limit, "srsearch": query})
    return [r["title"] for r in data["query"]["search"]]


def _info_commons(title, width):
    data = _api({"action": "query", "prop": "imageinfo", "iiprop": "url|size|extmetadata", "iiurlwidth": width,
                 "titles": title})
    page = next(iter(data["query"]["pages"].values()))
    if "imageinfo" not in page:
        raise LookupError("not on Commons: " + title)
    ii = page["imageinfo"][0]
    meta = ii.get("extmetadata", {})
    field = lambda k: _plain(meta.get(k, {}).get("value", ""))
    return {
        "title": page["title"], "url": ii["url"], "thumb": ii.get("thumburl", ""),
        "width": ii.get("width", 0), "height": ii.get("height", 0),
        "licence": field("LicenseShortName"), "author": field("Artist"),
        "description": field("ImageDescription"), "page": ii["descriptionurl"], "source": "commons",
    }


def _info_toolforge(title):
    root = ET.fromstring(get(TOOLFORGE + "?" + urllib.parse.urlencode({"image": title[5:].replace(" ", "_")})))
    f = root.find("file")
    if f is None:
        raise LookupError("not on Commons: " + title)
    desc = root.find("description/language[@code='en']")
    if desc is None:
        desc = root.find("description/language")
    return {
        "title": title, "url": f.findtext("urls/file", "").split("?")[0], "thumb": "",
        "width": int(f.findtext("width", "0") or 0), "height": int(f.findtext("height", "0") or 0),
        "licence": licence_name([lic.findtext("name", "") for lic in root.findall("licenses/license")]),
        "author": _plain(f.findtext("author", "")),
        "description": _plain(desc.text if desc is not None else ""),
        "page": f.findtext("urls/description", ""), "source": "toolforge",
    }


def info(name, width=WIDTH):
    """Details of one file: from Commons, or from Toolforge when Commons refuses."""
    title = title_of(name)
    try:
        return _info_commons(title, width)
    except RateLimited:
        log("  Commons refused; asking Toolforge")
        return _info_toolforge(title)


def thumb_url(url, width):
    """The Commons thumbnail address for an upload.wikimedia.org original at the given width."""
    m = re.match(r"^(https://upload\.wikimedia\.org/wikipedia/commons)/(\w/\w\w)/(.+)$", url)
    return "%s/thumb/%s/%s/%dpx-%s" % (m.group(1), m.group(2), m.group(3), width, m.group(3)) if m else url


def download(name, path, width=WIDTH, local=None):
    """Save the file as a JPEG no wider than width (metadata dropped). Returns (width, height, info).
    With local, the image is read from that copy of the same file instead of upload.wikimedia.org; the licence,
    author and page still come from Commons."""
    from PIL import Image

    meta = info(name, width)
    url = meta["url"]
    if meta["width"] > width:
        url = meta["thumb"] or thumb_url(url, width)
    img = Image.open(local if local else io.BytesIO(get(url))).convert("RGB")
    if img.width > width:
        img = img.resize((width, round(img.height * width / img.width)), Image.LANCZOS)
    os.makedirs(os.path.dirname(os.path.abspath(path)), exist_ok=True)
    img.save(path, "JPEG", quality=85, optimize=True, progressive=True)
    return img.width, img.height, meta


def main():
    ap = argparse.ArgumentParser(description="Search Wikimedia Commons, read a file's licence, download a copy.")
    sub = ap.add_subparsers(dest="cmd", required=True)
    s = sub.add_parser("search", help="file titles matching the words")
    s.add_argument("query")
    s.add_argument("--limit", type=int, default=10)
    s.add_argument("--json", action="store_true")
    i = sub.add_parser("info", help="licence, author, size and page of files")
    i.add_argument("files", nargs="+")
    i.add_argument("--json", action="store_true")
    d = sub.add_parser("download", help="save a file as a JPEG no wider than --width")
    d.add_argument("file")
    d.add_argument("out")
    d.add_argument("--width", type=int, default=WIDTH)
    d.add_argument("--any-licence", action="store_true", help="download even when the licence is not free")
    args = ap.parse_args()

    try:
        if args.cmd == "search":
            found = search(args.query, args.limit)
            print(json.dumps(found, ensure_ascii=False, indent=1) if args.json else "\n".join(found))
        elif args.cmd == "info":
            rows = [info(f) for f in args.files]
            if args.json:
                print(json.dumps(rows, ensure_ascii=False, indent=1))
            for r in [] if args.json else rows:
                print("%s\n  %d x %d, %s%s\n  author: %s\n  %s\n  %s\n" % (
                    r["title"], r["width"], r["height"], r["licence"], "" if is_free(r["licence"]) else " (NOT free to reuse)",
                    r["author"], r["description"][:300], r["page"]))
        else:
            meta = info(args.file)
            if not is_free(meta["licence"]) and not args.any_licence:
                return "refused: the licence is %r (use --any-licence to download anyway)" % meta["licence"]
            w, h, meta = download(args.file, args.out, args.width)
            print("saved %s, %d x %d, %s, %s" % (args.out, w, h, meta["licence"], meta["page"]))
    except RateLimited as e:
        return "Wikimedia kept refusing (%s); try again later" % e
    except LookupError as e:
        return str(e)


if __name__ == "__main__":
    sys.exit(main())

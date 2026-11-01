#!/usr/bin/env python3
"""Download the JPS 1917 English Tanakh (public domain) from Sefaria's API, chapter by chapter.

Usage:  python3 tools/fetch_jps.py --books genesis [--delay 3]
Writes data/jps/{book}/{chapter}.json in the same shape as data/esv: {"book", "chapter", "verses": [{"n", "t"}]}.
Resumable (existing chapters are skipped) and gentle: one request every few seconds, stopping at once on a
429 (rate limit). Sefaria API: https://developers.sefaria.org/reference/get-v3-texts
Text: "The Holy Scriptures: A New Translation (JPS 1917)", Public Domain (Jewish Publication Society, via the
Open Siddur Project and Sefaria).
"""
import argparse, json, os, sys, time, urllib.error, urllib.parse, urllib.request

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "data")
OUT = os.path.join(ROOT, "jps")
VERSION = "english|The Holy Scriptures: A New Translation (JPS 1917)"
# Sefaria's titles where they differ from ours.
SEFARIA = {
    "1-samuel": "I Samuel", "2-samuel": "II Samuel", "1-kings": "I Kings", "2-kings": "II Kings",
    "1-chronicles": "I Chronicles", "2-chronicles": "II Chronicles", "song-of-solomon": "Song of Songs",
}


def fetch(title, chapter):
    url = "https://www.sefaria.org/api/v3/texts/%s.%d?%s" % (
        urllib.parse.quote(title), chapter,
        urllib.parse.urlencode({"version": VERSION, "return_format": "text_only"}))
    req = urllib.request.Request(url, headers={"User-Agent": "thebible.exposed-importer/1.0"})
    for attempt in range(3):
        try:
            with urllib.request.urlopen(req, timeout=40) as r:
                return json.load(r)
        except urllib.error.HTTPError as e:
            if e.code == 429:
                sys.exit("Rate limited by Sefaria on %s %d. Stopping; run again later." % (title, chapter))
            print("  HTTP %d on %s %d" % (e.code, title, chapter), flush=True)
        except Exception as e:  # network trouble: wait and try again
            print("  error %s on %s %d" % (e, title, chapter), flush=True)
        time.sleep(20 * (attempt + 1))
    sys.exit("Giving up on %s %d" % (title, chapter))


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--books", required=True, help="comma-separated book slugs, e.g. genesis")
    ap.add_argument("--delay", type=float, default=3.0)
    args = ap.parse_args()
    books = {b["slug"]: b for b in json.load(open(os.path.join(ROOT, "books.json"), encoding="utf8"))}
    done = 0
    for slug in [s.strip() for s in args.books.split(",") if s.strip()]:
        book = books.get(slug)
        if not book or book["testament"] != "OT":
            sys.exit("%s is not an Old Testament book" % slug)
        os.makedirs(os.path.join(OUT, slug), exist_ok=True)
        for ch in range(1, int(book["chapters"]) + 1):
            path = os.path.join(OUT, slug, "%d.json" % ch)
            if os.path.exists(path):
                continue
            data = fetch(SEFARIA.get(slug, book["name"]), ch)
            version = (data.get("versions") or [{}])[0]
            text = version.get("text") or []
            if text and isinstance(text[0], list):
                text = text[0]
            if not text or version.get("license") != "Public Domain":
                sys.exit("No public-domain JPS 1917 text for %s %d (warnings: %s)" % (book["name"], ch, data.get("warnings")))
            verses = [{"n": i + 1, "t": " ".join(str(t).split())} for i, t in enumerate(text) if t]
            with open(path, "w", encoding="utf8") as f:
                json.dump({"book": slug, "chapter": ch, "verses": verses}, f, ensure_ascii=False, separators=(",", ":"))
            done += 1
            if done % 10 == 0:
                print("%d fetched (%s %d)" % (done, book["name"], ch), flush=True)
            time.sleep(args.delay)
    print("Finished. fetched=%d" % done)


if __name__ == "__main__":
    main()

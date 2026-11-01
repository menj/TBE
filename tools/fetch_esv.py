#!/usr/bin/env python3
"""Download the complete ESV text, chapter by chapter, from api.esv.org.

Usage:  ESV_API_TOKEN=xxxx python3 tools/fetch_esv.py [--delay 15] [--max-delay 30] [--max 0]
The token is read from the environment only. Never commit it.
Resumable: chapters already on disk are skipped.

Deliberately gentle: the API allows 60/min, 1000/hr and 5000/day, and this script
stays far below that (default one request every 15-30 seconds, random). If the API
answers 429 (rate limited) the script stops at once instead of retrying; just run it
again later. --max N stops after N new chapters, handy for "a little each day".
"""
import argparse, json, os, random, re, sys, time, urllib.parse, urllib.request, urllib.error

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "data")
OUT = os.path.join(ROOT, "esv")

BOOKS = [
 ("genesis","Genesis",50,"OT"),("exodus","Exodus",40,"OT"),("leviticus","Leviticus",27,"OT"),
 ("numbers","Numbers",36,"OT"),("deuteronomy","Deuteronomy",34,"OT"),("joshua","Joshua",24,"OT"),
 ("judges","Judges",21,"OT"),("ruth","Ruth",4,"OT"),("1-samuel","1 Samuel",31,"OT"),
 ("2-samuel","2 Samuel",24,"OT"),("1-kings","1 Kings",22,"OT"),("2-kings","2 Kings",25,"OT"),
 ("1-chronicles","1 Chronicles",29,"OT"),("2-chronicles","2 Chronicles",36,"OT"),("ezra","Ezra",10,"OT"),
 ("nehemiah","Nehemiah",13,"OT"),("esther","Esther",10,"OT"),("job","Job",42,"OT"),
 ("psalms","Psalms",150,"OT"),("proverbs","Proverbs",31,"OT"),("ecclesiastes","Ecclesiastes",12,"OT"),
 ("song-of-solomon","Song of Solomon",8,"OT"),("isaiah","Isaiah",66,"OT"),("jeremiah","Jeremiah",52,"OT"),
 ("lamentations","Lamentations",5,"OT"),("ezekiel","Ezekiel",48,"OT"),("daniel","Daniel",12,"OT"),
 ("hosea","Hosea",14,"OT"),("joel","Joel",3,"OT"),("amos","Amos",9,"OT"),("obadiah","Obadiah",1,"OT"),
 ("jonah","Jonah",4,"OT"),("micah","Micah",7,"OT"),("nahum","Nahum",3,"OT"),("habakkuk","Habakkuk",3,"OT"),
 ("zephaniah","Zephaniah",3,"OT"),("haggai","Haggai",2,"OT"),("zechariah","Zechariah",14,"OT"),
 ("malachi","Malachi",4,"OT"),
 ("matthew","Matthew",28,"NT"),("mark","Mark",16,"NT"),("luke","Luke",24,"NT"),("john","John",21,"NT"),
 ("acts","Acts",28,"NT"),("romans","Romans",16,"NT"),("1-corinthians","1 Corinthians",16,"NT"),
 ("2-corinthians","2 Corinthians",13,"NT"),("galatians","Galatians",6,"NT"),("ephesians","Ephesians",6,"NT"),
 ("philippians","Philippians",4,"NT"),("colossians","Colossians",4,"NT"),
 ("1-thessalonians","1 Thessalonians",5,"NT"),("2-thessalonians","2 Thessalonians",3,"NT"),
 ("1-timothy","1 Timothy",6,"NT"),("2-timothy","2 Timothy",4,"NT"),("titus","Titus",3,"NT"),
 ("philemon","Philemon",1,"NT"),("hebrews","Hebrews",13,"NT"),("james","James",5,"NT"),
 ("1-peter","1 Peter",5,"NT"),("2-peter","2 Peter",3,"NT"),("1-john","1 John",5,"NT"),
 ("2-john","2 John",1,"NT"),("3-john","3 John",1,"NT"),("jude","Jude",1,"NT"),("revelation","Revelation",22,"NT"),
]

# Verses in the books that have a single chapter.
ONE_CHAPTER = {"obadiah": 21, "philemon": 25, "2-john": 13, "3-john": 14, "jude": 25}

PARAMS = {
  "include-passage-references": "false", "include-verse-numbers": "true",
  "include-first-verse-numbers": "true", "include-footnotes": "false",
  "include-footnote-body": "false", "include-headings": "false",
  "include-short-copyright": "false", "include-copyright": "false",
  "include-passage-horizontal-lines": "false", "include-heading-horizontal-lines": "false",
  "indent-using": "space", "indent-paragraphs": "0", "indent-poetry": "true",
  "indent-poetry-lines": "4", "indent-declares": "0", "indent-psalm-doxology": "0",
  "line-length": "0",
}

def fetch(token, query):
    url = "https://api.esv.org/v3/passage/text/?" + urllib.parse.urlencode({"q": query, **PARAMS})
    req = urllib.request.Request(url, headers={"Authorization": "Token " + token, "User-Agent": "thebible.exposed-importer/1.0"})
    for attempt in range(3):
        try:
            with urllib.request.urlopen(req, timeout=30) as r:
                return json.load(r)
        except urllib.error.HTTPError as e:
            if e.code == 429:
                raise SystemExit(f"Rate limited by the ESV API on {query}. Stopping; run again later.")
            print(f"  HTTP {e.code} on {query}", flush=True); time.sleep(30 * (attempt + 1))
        except Exception as e:
            print(f"  error {e} on {query}", flush=True); time.sleep(30 * (attempt + 1))
    raise SystemExit(f"Giving up on {query}")

def parse(text):
    """Split '[n] text' markers into verses; keep poetry line breaks."""
    text = text.replace("\r", "")
    parts = re.split(r"\[(\d+)\]\s*", text)
    verses = []
    for i in range(1, len(parts), 2):
        body = parts[i + 1].rstrip()
        lines = [l.rstrip() for l in body.split("\n")]
        body = "\n".join(l for l in lines if l.strip() != "" or False)
        paragraph_break = parts[i + 1].rstrip(" ").endswith("\n\n")
        verses.append({"n": int(parts[i]), "t": body.strip("\n"), **({"p": 1} if paragraph_break else {})})
    return verses

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--delay", type=float, default=15.0, help="minimum seconds between requests (default 15)")
    ap.add_argument("--max-delay", type=float, default=30.0, help="maximum seconds between requests (default 30)")
    ap.add_argument("--max", type=int, default=0, help="stop after this many new chapters (0 = no limit)")
    args = ap.parse_args()
    token = os.environ.get("ESV_API_TOKEN")
    if not token: raise SystemExit("Set ESV_API_TOKEN in the environment.")
    os.makedirs(OUT, exist_ok=True)
    with open(os.path.join(ROOT, "books.json"), "w") as f:
        json.dump([{"slug": s, "name": n, "chapters": c, "testament": t, "order": i + 1}
                   for i, (s, n, c, t) in enumerate(BOOKS)], f, indent=1, ensure_ascii=False)
    done = skipped = 0
    for slug, name, chapters, _ in BOOKS:
        os.makedirs(os.path.join(OUT, slug), exist_ok=True)
        for ch in range(1, chapters + 1):
            path = os.path.join(OUT, slug, f"{ch}.json")
            if os.path.exists(path): skipped += 1; continue
            # "Jude 1" means verse 1 to the API, so one-chapter books are asked for by verse range.
            query = f"{name} 1:1-{ONE_CHAPTER[slug]}" if slug in ONE_CHAPTER else f"{name} {ch}"
            data = fetch(token, query)
            verses = parse(data["passages"][0]) if data.get("passages") else []
            if not verses: raise SystemExit(f"No verses for {name} {ch}")
            with open(path, "w", encoding="utf-8") as f:
                json.dump({"book": slug, "chapter": ch, "verses": verses}, f, ensure_ascii=False, separators=(",", ":"))
            done += 1
            if done % 10 == 0: print(f"{done} fetched ({name} {ch})", flush=True)
            if args.max and done >= args.max:
                print(f"Reached --max {args.max}. Run again to continue."); return
            time.sleep(random.uniform(args.delay, max(args.delay, args.max_delay)))
    print(f"Finished. fetched={done} skipped={skipped}")

if __name__ == "__main__":
    main()

#!/usr/bin/env python3
"""Fetch the Brown-Driver-Briggs pages from www.ericlevy.com/revel/bdb/bdb/ into tools/.cache/bdb/ (git-ignored).

The site sets out the Enhanced Brown-Driver-Briggs Hebrew and English Lexicon (based on the 1906 Clarendon Press
edition, public domain) letter by letter: for each letter a bookmark page lists the headwords and the pages
(al01.html, al02.html ...) that hold them, and the Biblical Aramaic appendix likewise. This script reads each
bookmark page, then fetches every page it names, one a second, and keeps them; a second run fetches nothing
already kept. tools/build_bdb.py reads the copies.

Usage:  python3 tools/fetch_bdb.py
"""
import os, re, sys, time, urllib.request

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
CACHE = os.path.join(ROOT, "tools", ".cache", "bdb")
BASE = "http://www.ericlevy.com/revel/bdb/bdb/"
UA = "thebible.exposed-importer/1.0 (https://thebible.exposed/)"
LETTERS = ["1/al", "2/bet", "3/gim", "4/dal", "5/he", "6/waw", "7/zay", "8/het", "9/tet", "10/yod", "11/kap", "12/lam",
           "13/mem", "14/num", "15/sam", "16/ayi", "17/pe", "18/sad", "19/kop", "20/res", "21/sin", "22/taw", "ext/aram"]


def get(path):
    local = os.path.join(CACHE, path)
    if os.path.exists(local):
        return open(local, "rb").read()
    os.makedirs(os.path.dirname(local), exist_ok=True)
    for attempt in range(4):
        try:
            with urllib.request.urlopen(urllib.request.Request(BASE + path, headers={"User-Agent": UA}), timeout=60) as r:
                data = r.read()
            open(local, "wb").write(data)
            time.sleep(1)
            return data
        except Exception as e:  # a failed page is retried after a pause, then skipped
            print("retry", path, e, flush=True)
            time.sleep(5 * (attempt + 1))
    return b""


def main():
    for letter in LETTERS:
        folder = letter.rsplit("/", 1)[0]
        marks = get(letter + "-BkMrk.html").decode("latin-1")
        pages = sorted(set(re.findall(r'href="([a-z]+\d+\.html)', marks)), key=lambda p: int(re.search(r"\d+", p).group()))
        last = max((int(re.search(r"\d+", p).group()) for p in pages), default=0)
        stem = re.match(r"[a-z]+", pages[0]).group() if pages else letter.rsplit("/", 1)[1]
        for n in range(1, last + 1):  # every page in the run, also those no headword starts on
            get("%s/%s%02d.html" % (folder, stem, n))
        print(letter, last, "pages", flush=True)
    return 0


if __name__ == "__main__":
    sys.exit(main())

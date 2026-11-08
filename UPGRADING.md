# Upgrading TBE

How to move a site from one version to the next, and where the theme is going. CHANGELOG.md says what changed; this file says what you do about it.

## Updating an existing site

1. Back up the database and `wp-content/`.
2. In the FTP program, turn on "show hidden files" (FileZilla: Server, Force showing hidden files), so `.htaccess` and `tools/.htaccess` go up.
3. Upload the new version to a temporary folder beside the old one, for example `wp-content/themes/TBE-new/`. Send its `.htaccess` files first, the theme folder's and then `tools/.htaccess`, so on Apache the paths they refuse stay closed while the rest arrives. On Nginx, which ignores them, add the lines under "Nginx" in `README.md` before the first FTP update; they cover `TBE-new` and `TBE-old` too.
4. Rename the old folder to `TBE-old` and, straight after, `TBE-new` to `TBE`, with the admin closed from before the first rename until the second is done: while no folder is named `TBE`, an admin page can switch the site to another theme. The live folder is `TBE`, upper case.
5. Open any admin screen once. The theme flushes its rewrite rules, refreshes the error pages in `wp-content/` and removes `.git`, `.claude` and `tools/` from a live site (keep them with `define( 'TBE_KEEP_DOTFILES', true );` in `wp-config.php`).
6. Read the note for each version you pass below, and do what it asks.
7. Delete `TBE-old` once the site looks right.

The temporary folder and rename is the safe way. Uploading straight over the old folder is riskier. While a file in `inc/` is missing or half-written, visitors get the static 503 page with `Retry-After: 300`, the owner can still log in, and every asset address keeps its old version until its own file is in place. But the guard lives in `functions.php`, so it cannot cover `functions.php` itself: while that file is half-written, a visitor can get a PHP parse error. Upload `functions.php` last if you overwrite in place.

Never upload WordPress core without `wp-config.php` in place, or the site starts its installer.

## Version notes

Only the versions that need something done by hand are listed.

### To 0.10.22
Nothing to do by hand. 1 to 3 Meqabyan answer at addresses the reader already serves, so the permalinks need no refresh.

### To 0.10.21
The theme now requires **PHP 8.4 or later**. Check the PHP version under Tools, Site Health, Info, Server before updating, and ask the host to upgrade if it is older; on older PHP the theme keeps working but every admin screen shows a notice until it is upgraded. The contents now cover pages, posts and glossary terms, nest level-three headings and read `[wp_toc]`. If the WP TOC plugin is installed, it can be deactivated: the theme does the same. While it stays active, the theme leaves posts and pages to it.

### To 0.10.20
Nothing to do. Clear any page cache so the home page shows the centred menu.

### To 0.10.19
Nothing to do. If a page cache sits in front of the site, clear it so the home page shows the new header.

### To 0.10.18
Nothing to do. The theme refreshes its address rules on the first load after the update, which adds `/manuscripts/` and `/lexicon/`. New files: `inc/lexicon.php`, `data/lexicon/`, `tools/build_lexicon.py`, `tools/build_metzger.py`, `tools/test_lexicon.php`, `inc/manuscripts.php`, `data/manuscripts/`, `assets/img/manuscripts/`, `tools/build_manuscripts.py`, `tools/commons_api.py`, `tools/fetch_manuscript_images.py`, `tools/test_manuscripts.php`. To rebuild the data after CNTR publishes a new release, run `python3 tools/build_manuscripts.py` on a copy with the tools folder.

### To 0.10.17
Nothing to do. The theme refreshes its address rules on the first load after the update. If a page cache sits in front of the site, clear it so the footer shows "Sources".

### To 0.10.16
Nothing to do. To use the new strip, write a commentary entry with no passage (an essay) and give it the same tags as the notes on the chapters it relates to.

### To 0.10.15
Nothing to do. Dark mode is gone: a site that had chosen the Night study scheme falls back to its custom colours (check Appearance, TBE). `assets/js/theme.js` is deleted, so remove it from the server when uploading. New files: `inc/frontend-edit.php`, `assets/js/frontend-edit.js`, `assets/css/frontend-edit.css`.

### To 0.10.14
Nothing to do. The change is data and one Credits paragraph.

### To 0.10.13
Nothing to do. The canon lists are data only.

### To 0.10.12
Nothing to do on a site at the domain root. On a site in a subfolder, put a copy of `assets/img/brand/favicon.ico` in the domain root (or, with a Site Icon set, that icon converted to an ICO file named `favicon.ico`), since browsers ask for `/favicon.ico` there.


### To 0.10.11
Nothing to do. The new files are `icon-192.png`, `icon-maskable-512.png` and `site.webmanifest` in `assets/img/brand/`. The next admin page writes the error pages again with the icon. If a `favicon.ico` file sits in the site root, the web server sends it and the theme never answers.

### To 0.10.10
Nothing to do. The FTP steps above now send the `.htaccess` files first.

### To 0.10.9
Nothing to do. The licence is now GPL version 3 or later; the full text is in `LICENSE`.

### To 0.10.8
- Nothing to do by hand. If the commentary template was saved in the Site Editor (Appearance, Editor, Templates, Single item: Commentary entry), reset it to take the new layout.
- The Commentary pages tab under Appearance, TBE has the new settings. The defaults show everything except the one-line next step in the middle of an entry.

### To 0.10.7
Nothing to do. The new documents (`readme.txt`, `SSOT.md`, this file) ship in the theme folder; the web server refuses the Markdown ones.

### To 0.10.6
- If the site's `.htaccess` has `ErrorDocument` lines that name `/wp-content/themes/tbe/errors/`, replace them with the lines from Appearance, TBE, Error pages. Those name `/wp-content/tbe-errors/`, which carry the real theme folder.
- Untick Settings, Reading, "Discourage search engines from indexing this site" when the site should be found. While it is ticked, every page says `noindex, nofollow`. WordPress's sitemap at `/wp-sitemap.xml` stays open either way; while an SEO plugin serves its own, the sitemap is at the plugin's address instead.
- Asset addresses change once, to file hashes. Nothing to clear.

### To 0.10.5
- Upload with hidden files shown, so `.htaccess` and `tools/.htaccess` go up. The first page load afterwards removes `.git`, `.gitignore` and `tools/` from the server; add `define( 'TBE_KEEP_DOTFILES', true );` to `wp-config.php` first to keep them.
- Paste the lines from Appearance, TBE, Error pages, "Requests the web server should refuse", into the site's `.htaccess`, after the WordPress section.
- If Jetpack is not active and a mobile app needs XML-RPC, add `define( 'TBE_ALLOW_XMLRPC', true );` to `wp-config.php`.

### To 0.10.3
- Paste the `ErrorDocument` lines from Appearance, TBE, Error pages into `.htaccess`. They name the copies in `wp-content/tbe-errors/`, which work in a subfolder too.

### To 0.8.0
- The theme now does the work of four plugins. While Reading Progress Bar, Unlist Posts & Pages or Pretty Search Permalinks is active, the theme's own version steps aside. The Minimalist Social Icons Pack is not detected, so deactivate it once the Profile links tab is filled in, so the site does not carry two rows of icons.

## Roadmap

These are plans, not promises. An item leaves this list when it ships, and CHANGELOG.md records it.

### Next
- Untick "Discourage search engines" on the live site. Why: thebible.exposed serves `noindex, nofollow` on its home page and a 67-byte `robots.txt` (checked 5 November 2026), so nothing is indexed. This is the owner's step; the theme only reminds.
- Answer credential-file probes with the short 404. Why: the November logs show `/google-credentials.json`, `/sa.json`, `/key.json`, `/google-key.json`, `/gcp-key.json`, `/gcp-sa.json`, `/firebase-key.json` and `/firebase-adminsdk.json` (4 or 5 requests each) and `/info` (12) still rendering the 74 KB theme 404.
- Serve `/favicon.png` from the brand icon, as `/favicon.ico` is. Why: 5 requests in the logs got the full 404 page.
- Test the Nginx lines in README.md on a real Nginx. Why: they are written from the Apache rules and marked untested.
- Settle the Qur'an cross-references. Why: the owner's rule that nothing Islamic goes on the site for now (restated 7 November 2026) meets references still shown on entry pages, in the reader, in the structured data and in link tips, and SSOT.md still calls the position "Islamic-leaning". The owner decides whether the public pages hide them; the admin field keeps what is saved.
- A Hebrew page in the manuscripts section. Why: 0.10.18 covers the Greek New Testament only, while the interlinear data records about 1,500 Hebrew written-and-read (ketiv and qere) places and the Dead Sea Scrolls comparison has Isaiah 1 to 5.
- More passages in the manuscripts section, and a per-book table of every place the editions differ. Why: 0.10.18 has twenty-two passages; the interlinear data records about 4,100 Greek words outside NA28 and about 3,850 variant readings, and `tools/build_manuscripts.py` takes a new passage in one line.

### Later
- More recorded readings. Why: only Genesis 1 (Hebrew) and Matthew 1 (Greek) have audio; `tools/align_audio.py` is ready for more, but each needs a recording with a licence, which the owner supplies.
- Isaiah 6 to 66 in the Dead Sea Scrolls comparison, then other scrolls. Why: only Isaiah 1 to 5 is compared; `tools/build_dss_variants.py` takes a chapter range, and each batch needs its spelling differences spot-checked.
- The JPS 1917 parallel beyond Genesis. Why: `tools/fetch_jps.py --books` adds a book at a time, and each needs its versification checked against `data/jps-versification.json`.
- The deuterocanonical, further Orthodox and remaining Ethiopian books. Why: 1 to 3 Meqabyan are readable since 0.10.22; the others are still listed as "Not on the site yet", and the Ethiopian list is still to be confirmed. Each needs a text with a licence; `inc/further.php` takes one as a JSON file.
- A check of 1 to 3 Meqabyan against a printed translation. Why: the Wikisource translation is a working one, its wording is uneven (1 Meqabyan reads "JAH", "irie" and "I-man"), it lacks 2 Meqabyan 16:9 and 21:9, and its pages were proposed for deletion in November 2026.
- The Ge'ez texts of the Ethiopian books. Why: the Ge'ez font is bundled and unused, and the texts need a licensed source. The owner asks the Ethiopian Orthodox Tewahedo Church first, since its permission so far covers names and notes from its site.
- The Greek Old Testament manuscripts in the Freer collection (Deuteronomy, Joshua, Psalms). Why: Henry A. Sanders, "The Old Testament Manuscripts in the Freer Collection" (University of Michigan, 1917, public domain) describes and collates them, but the scanned copy's Greek text is too poorly read by machine to use without checking by hand. Its plates are public domain too.
- Swanson's other volumes (Mark, Luke, John) for the passages outside Matthew. Why: the Matthew volume covers four of the twenty-two passages; the others list the same kind of evidence.
- More manuscript photographs. Why: Codex Bezae now shows on every passage it holds, but the papyri and Sinaiticus, Vaticanus and Alexandrinus are on few pages. The public-domain facsimiles of Bezae and Washingtonianus on the Internet Archive cover every passage those manuscripts hold, and each page needs only its leaf found by the plate caption, checked by eye and added to `data/manuscripts/images.json`. Alexandrinus at Mark 16 waits on Wikimedia.
- A Syriac interlinear. Why: the Estrangela font is bundled and unused, waiting on an open Syriac dataset. The owner has said not to build it yet.

### Ideas
- A spam check for comments and posts. Why: the theme has no spam filter of its own, and would need one before comments open.
- An image sitemap, named in `robots.txt`. Why: the reader and the brand pages carry images that `/wp-sitemap.xml` does not list.
- Links from glossary terms and scripture references to the commentary that treats them. Why: 0.10.8 links phrases from the shared map, and the glossary already marks the terms in each note; a term's link to its main note would close the loop.
- Phrases for this site in the shared link map. Why: the map has no entries for thebible.exposed yet, so links to it come only from the lines on the Commentary pages tab.
- A share row under each note. Why: the commentary is the part readers quote.
- Updates pulled from GitHub with Git Updater. Why: it replaces the FTP upload that caused the asset 404s and the half-uploaded theme seen in the November logs.

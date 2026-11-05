# Upgrading TBE

How to move a site from one version to the next, and where the theme is going. CHANGELOG.md says what changed; this file says what you do about it.

## Updating an existing site

1. Back up the database and `wp-content/`.
2. In the FTP program, turn on "show hidden files" (FileZilla: Server, Force showing hidden files), so `.htaccess` and `tools/.htaccess` go up.
3. Upload the new version to a temporary folder beside the old one, for example `wp-content/themes/TBE-new/`.
4. Rename the old folder to `TBE-old`, then `TBE-new` to `TBE`. The live folder is `TBE`, upper case.
5. Open any admin screen once. The theme flushes its rewrite rules, refreshes the error pages in `wp-content/` and removes `.git`, `.claude` and `tools/` from a live site (keep them with `define( 'TBE_KEEP_DOTFILES', true );` in `wp-config.php`).
6. Read the note for each version you pass below, and do what it asks.
7. Delete `TBE-old` once the site looks right.

The temporary folder and rename is the safe way. Uploading straight over the old folder is riskier. While a file in `inc/` is missing or half-written, visitors get the static 503 page with `Retry-After: 300`, the owner can still log in, and every asset address keeps its old version until its own file is in place. But the guard lives in `functions.php`, so it cannot cover `functions.php` itself: while that file is half-written, a visitor can get a PHP parse error. Upload `functions.php` last if you overwrite in place.

Never upload WordPress core without `wp-config.php` in place, or the site starts its installer.

## Version notes

Only the versions that need something done by hand are listed.

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

### Later
- More recorded readings. Why: only Genesis 1 (Hebrew) and Matthew 1 (Greek) have audio; `tools/align_audio.py` is ready for more, but each needs a recording with a licence.
- More Dead Sea Scrolls comparisons. Why: only Isaiah 1 to 5 is compared; it waits on checking the spelling heuristic against more chapters.
- The JPS 1917 parallel beyond Genesis. Why: `tools/fetch_jps.py --books` adds a book at a time, and each needs its versification checked against `data/jps-versification.json`.
- The deuterocanonical, further Orthodox and Ethiopian books. Why: they are listed as prepared sections, "Not on the site yet", and the Ethiopian list is still to be confirmed.
- A Syriac interlinear. Why: the Estrangela font is bundled and unused, waiting on an open Syriac dataset.

### Ideas
- A spam check for comments and posts. Why: the theme has no spam filter of its own, and would need one before comments open.
- An image sitemap, named in `robots.txt`. Why: the reader and the brand pages carry images that `/wp-sitemap.xml` does not list.
- Contextual links from glossary terms and scripture references to the commentary that treats them. Why: the glossary already marks the terms in each note; the link would close the loop.
- A share row under each note. Why: the commentary is the part readers quote.
- Updates pulled from GitHub with Git Updater. Why: it replaces the FTP upload that caused the asset 404s and the half-uploaded theme seen in the November logs.

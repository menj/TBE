# Changelog

## 0.10.18

A Manuscripts section and a Strong's lexicon. Nothing was removed.

### Added
- `/manuscripts/` lists twenty-two passages where the Greek manuscripts disagree, most of them verses and words that the King James Version prints and the earliest Greek manuscripts leave out: the long ending of Mark, the woman caught in adultery, Luke 22:43-44, "Father, forgive them", the ending of the Lord's Prayer, the three witnesses of 1 John 5:7, John 5:4, Acts 8:37 and twelve shorter verses, and two readings scribes changed: “today I have begotten you” at Luke 3:22 and “God was manifested” at 1 Timothy 3:16. Each has a page at `/manuscripts/{passage}/`.
- A passage page gives the KJV wording and two modern translations, a table of every early Greek manuscript (up to about AD 400) that survives at the place and whether it has the passage, what four printed Greek editions do, and each manuscript's own text, line by line as on the page, with the verses either side. A page that is lost counts neither way.
- Codex Bezae on every passage it holds: thirteen more plates from the 1899 facsimile, each found by the caption under it, among them Luke 23:34, where a later hand added “Father, forgive them” under the first scribe’s last line. `tools/index_facsimiles.py` reads the plate captions of the Washingtonianus facsimile into an index of leaves, to find its pages (Bezae’s italic captions defeat the OCR, so its leaves are found by eye).
- Photographs of manuscript pages from public-domain facsimiles on the Internet Archive: Codex Bezae (Cambridge, 1899) at John 7:43-8:4, where the story of the woman begins after 7:52, and at Luke 3:19-23, where the voice says “today I have begotten you”; and Codex Washingtonianus (Sanders, 1912) at John 7:46-8:16, where 7:52 runs straight into 8:12, and at Matthew 6:7-17, the Lord’s Prayer with “for thine is the kingdom”. Entries with an `archive` item and leaf in `data/manuscripts/images.json`; `fetch_manuscript_images.py --skip-commons` fetches only these. The credit under each picture now names its author where known (required for CC BY-SA) and its source.
- Photographs of the manuscript pages where Wikimedia Commons has them, each under "The pages themselves" with a caption, its licence and a link to its Commons page. Mark 16 has three: Vaticanus and Sinaiticus ending at 16:8, and Alexandrinus running on. The list is `data/manuscripts/images.json`, chosen by hand and checked by eye; `tools/fetch_manuscript_images.py` fetches each one, refuses any file that is not public domain, CC0, CC BY or CC BY-SA, and saves a copy no wider than 1200 pixels in `assets/img/manuscripts/`.
- `tools/commons_api.py`, a Wikimedia Commons client for the command line and for the other tools: `search`, `info` (licence, author, size, page; from Commons, or from Magnus Manske's Commons API on Toolforge when Commons refuses) and `download`. It names itself, spaces its requests, honours `Retry-After` and caches every answer.
- "Later manuscripts too" on every passage page: the evidence of the United Bible Societies *Greek New Testament* (its {A} to {D} rating, and the Greek manuscripts on each side, later ones included) and, for the four Matthew passages, of Swanson's *New Testament Greek Manuscripts: Matthew*. Each comes with the page number, a scan of the page and a link to the page in the full book, and a short key to the manuscript numbers. The evidence is in `data/manuscripts/editions.json` and the scans in `assets/img/manuscripts/books/`; the Sources page names both books. Used by the owner's permission.
- A Strong's lexicon at `/lexicon/`: a page for each of the 9,362 Greek and 8,723 Hebrew Strong's numbers (`/lexicon/g3056/`) with the word, transliteration, gloss, part of speech, every sense and its definition (its scripture references linked to the reader), how often the word is used and up to 120 places, each linked to its interlinear chapter. Lists by letter (`/lexicon/greek/alpha/`, `/lexicon/hebrew/aleph/`) and a look-up box (`/lexicon/?n=G3056`).
- Metzger's *Lexical Aids for Students of New Testament Greek*, Part I, at `/lexicon/greek/frequency/`: 967 words in 35 groups from "more than 500 times" down to "10 times", with his meanings and English derivatives, 961 of them linked to their entries; each Greek entry names its Metzger group and links to the page in the scan. Read from the scan by OCR, used by the owner's permission.
- Every Strong's number on the interlinear pages links to its lexicon entry. The book list links to the lexicon, and the Sources page credits it.
- `inc/lexicon.php` (class `TBE_Lexicon`), `data/lexicon/` with its `README.txt`, `tools/build_lexicon.py` (STEPBible's TBESG and TBESH, CC BY 4.0), `tools/build_metzger.py` and `tools/test_lexicon.php`.
- The home page lockup: the broken cross now stands beside "The Bible Exposed" instead of above it, at 1.6 times the letter height, and the pair is centred as one. The name had been wider than its column at full size and sat off centre; it now scales with the screen up to 4.6rem so the pair always fits on one line (`.tbe-hero` in `assets/css/base.css`, a grid on the existing blocks; the template is unchanged).
- The broken cross moves. On the home page it drops into place once with a small bounce, and hovering the cross or the name swings it from its top like a hanging sign while its gold-to-burgundy colour shifts; the small cross in the header and footer tilts on hover. The manuscripts and lexicon pages share the site's hover effects: photographs and page scans lift with a gold glow, manuscript texts and notes light up, lexicon letters lift into a gold or burgundy tile, and list rows take a gold wash. With "reduce motion" set, nothing moves (`assets/css/polish.css`). The apparatus page scans have rounded corners like the photographs.
- Running text is justified, with hyphenation: commentary, the Bible text, lexicon definitions and the prose of the reader pages (`assets/css/polish.css`). Centred lines, headings, lists, tables, the Greek set line by line on the manuscripts pages and any paragraph aligned in the editor keep their alignment.
- Labels, buttons and menus are set in Montserrat (SIL Open Font License, `assets/fonts/Montserrat-Regular.woff2`, a 36 KB Latin subset), with the system fonts behind it, on the site and the login screen. The Sources page credits it under Typefaces.
- A third starter-commentary pack, `gospels-and-canon` (8 entries), after two videos by C. J. Cornthwaite and restated in the site's own words: how Matthew, Luke and John rework Mark (the family that thought Jesus mad, “could not” become “did not”, four tellings of the baptism, Galilee against Jerusalem, Lazarus built from Luke, the temple cleansing moved), Tatian's harmony and Irenaeus's four gospels, and a long essay on how the New Testament came together, filed under 2 Peter 3:15-16. The Sources page names the videos with the other commentary sources.
- A Bible chapter that holds one of the passages shows a short note under its heading, with the count and a link. The book list links to the section.
- `inc/manuscripts.php` (class `TBE_Manuscripts`), `data/manuscripts/passages.json` with its `README.txt`, `tools/build_manuscripts.py` and `tools/test_manuscripts.php`. The data is built from the Center for New Testament Restoration's transcriptions (Alan Bunning, CC BY-SA 4.0), and the English from the public-domain KJV, WEB and BSB on its translation pages. The Sources page credits it, and its Resources list links to it. The structured data names it as a Dataset.

## 0.10.17

The Credits page becomes Sources, and gains a Resources section. Nothing was removed.

### Changed
- The page is at `/sources/`, titled "Sources", and the footer link reads "Sources". The old `/credits/` answers 301 to it. `sources` joins the reserved search bases and the site-link sections.
- A new "Resources" section lists six places to read further: STEP Bible, Sefaria, the ETCBC Dead Sea Scrolls data, Beta maṣāḥǝft, the Ethiopian Orthodox Tewahedo Church and Wikipedia's "Biblical canon".

## 0.10.16

Thematic essays reach the chapters they relate to. Nothing was removed.

### Added
- A "Related essays" strip on a Bible chapter page: published commentary entries filed under no passage (the thematic essays) that share a tag with the notes on that chapter, at most four (`tbe_essays_for_entries()`, `tbe_render_related_essays()` in `inc/commentary.php`). `assets/css/article.css` now loads on chapter pages for its cards.
- Book and chapter introductions already worked (an entry whose passage is a book name, or a chapter with no verse); this release only documents them.

## 0.10.15

Front-end editing for people who may edit. Nothing was renamed or removed.

### Changed
- The site is light only, as the owner decided on 6 November 2026. All dark mode is removed: the switch and the `tbe/theme-toggle` block, `assets/js/theme.js`, the `allow_toggle` setting, the "Night study" scheme and every `data-theme="dark"` and `prefers-color-scheme: dark` rule (login screen and error pages included). A site that had chosen Night study falls back to its custom colours, which start from the Manuscript scheme.

### Added
- `inc/frontend-edit.php`, `assets/js/frontend-edit.js` and `assets/css/frontend-edit.css`. A signed-in user who can edit a commentary note sees an "Edit this note" button on it, and one who can create notes sees "Add a note here" on a Bible chapter. Both open a drawer with the title, the passage and WordPress's own block editor, built from core's `wp-block-editor`, `wp-components`, `wp-block-library`, `wp-format-library` and `wp-api-fetch` packages (no outside library), and save through the core REST API. New notes are saved as drafts, and a Publish button (shown to users who may publish) makes a note public. The scripts load only for those users.
- The "Ask a question" button on a commentary note carries an envelope icon (inline SVG, `.tbe-btn--icon` in `assets/css/article.css`), and the editor button an open-book icon.
- `tbe_store_passage()` in `inc/commentary.php` holds the passage check that the meta box already used; the drawer uses it through `rest_after_insert_tbe_commentary` (request parameter `tbe_passage_text`).

## 0.10.14

The Ethiopian sections take the church's own names and short notes. Nothing was removed.

### Changed
- `data/canons.json`: the Ethiopian books carry the church's names (Sirate Tsion, Tizaz, Gitsew, Abtilis, Dominos, Bengorion) and short notes from the church's pages.
- `assets/fonts/AbyssinicaSIL-Ethiopic.woff2` (Ge'ez script, SIL Open Font License, with its licence file) is registered as the `geez` face in `tbe_font_faces()` and credited under Typefaces. No page uses it yet.
- The Credits page thanks the Ethiopian Orthodox Tewahedo Church, whose site is used with its permission.

## 0.10.13

The canon lists are checked against Wikipedia's "Biblical canon" article (facts only). Nothing was removed.

### Changed
- `data/canons.json`: the deuterocanonical, further Orthodox and Ethiopian sections are corrected and completed, and a new section lists the early Christian writings once read as scripture (1 and 2 Clement, the Didache, Barnabas, the Shepherd of Hermas, the Apocalypse of Peter, 3 Corinthians, Laodiceans), each with the badge "Once read as scripture".
- `inc/canon.php` prints that badge and a short note on each prepared book; `assets/css/reader.css` styles them.
- The Credits page thanks Wikipedia for the canon lists (CC BY-SA 4.0).

## 0.10.12

The docs name the subfolder favicon step. No feature removed.

### Changed
- `readme.txt` and `README.md` say that a site in a subfolder needs a copy of `favicon.ico` at the domain root, because browsers ask for it there and WordPress never sees the request.

## 0.10.11

### Added
- `assets/img/brand/icon-192.png`, `icon-maskable-512.png` (the mark at 80% on the brand red) and `site.webmanifest`, rendered from `favicon.svg`.
- `tbe_brand_icon_tags()` prints the icon tags, now with the manifest, and `theme-color`, on the login screen and the admin as well as the site. The icon tags still print only while no Site Icon is set.
- The error pages and drop-ins carry the mark as their tab icon, as a `data:` address (`tbe_error_icon_link()`), so it shows on any path and in a subfolder install.

### Changed
- `apple-touch-icon.png` is rendered again from `favicon.svg` on a solid background; it had transparent corners.
- `/favicon.ico` answers a list of ETags, a weak one or `*` with a 304, and a HEAD request gets the headers alone. `template_redirect` at priority 0 serves it, since WordPress ends a HEAD request before `do_favicon`.

## 0.10.10

### Changed
- The FTP update steps in `readme.txt` and `UPGRADING.md` send the theme's `.htaccess` files first (the theme folder's, then `tools/.htaccess`), so its private files stay closed to visitors while the rest uploads.
- The FTP update steps do the two renames straight after each other, with the admin closed, and point Nginx sites to the server lines in `README.md`.
- `.claude/skills/theme-docs/SKILL.md` takes the refreshed standard.

## 0.10.9

### Changed
- The theme is licensed under the GNU General Public License, version 3 or later, as the owner decided on 5 November 2026. `style.css` and `readme.txt` name it, and `LICENSE` holds the full, unmodified text. The fonts, texts, data and images keep their own licences and credits.
- `.claude/skills/theme-docs/SKILL.md` gains its Licence section.

## 0.10.8

The single commentary entry gets the pieces a long article needs, in reading order. No sales prompt, no byline: the author stays in the structured data only. Nothing was renamed or removed.

### Added
- A breadcrumb above the title (the site, Commentary, the entry), the same trail as the `BreadcrumbList` in the structured data.
- The excerpt under the title as a dek, then a meta row: published, updated when that is a later day, "Last reviewed" when the new Key points and review box gives a date, and the reading time.
- An "In short" box with the entry's key points, one a line in that box. A `tbe/answer-box` block can carry its own summary and points.
- A contents list of the entry's level-two headings, from three of them up. The headings get ids from their text. Inline by default; a sticky bar that opens on a tap is the other style.
- A notes and sources section: the entry's footnotes move there, with the passage in the ESV, the word-by-word page where there is one, and the Qur'an cross-references. The footnotes also feed the Article `citation`.
- A next step at the end: "Read the passage" in the reader, the Hebrew or Greek word by word, the next entry on the same book, and "Ask a question" when the settings name a page. An optional one-line next step goes before the third section.
- Related entries on the same book as cards, scored by chapter, shared tags and shared sections.
- A FAQ style for Details blocks and a "Questions and answers" pattern. The page's graph gains a `FAQPage` only when those questions are on the page.
- `speakable` on the page, naming the dek and the "In short" box when they are there.
- Tables in an entry scroll on their own on a phone, as a labelled region a keyboard can reach, with the caption in the theme's style.
- Contextual links (`inc/contextual-links.php`): the first mention of a phrase in the shared link map (`data/link-map.json`) links to the page that treats it, once a target and at most once a paragraph, never inside scripture, headings, tables, quotations or links. Scripture cards and glossary tooltips come first. Lines of your own go in the settings.
- A Commentary pages tab under Appearance, TBE, for all of the above.
- `tools/test_article.php` checks the heading ids, the mid-article next step and the contextual links.

### Changed
- On an entry's own page the "Textual variants" title is a level-two heading, so the headings run in order; inside the reader's boxes it stays as it was.
- The passage line, the apparatus, the glossary tooltips and the contextual links all find the entry's body the same way (`tbe_is_entry_body()`): the queried entry, in or out of the classic Loop. On a WordPress that renders the Post Content block outside the Loop they used to be left out, and a contextual link could take "John 3:16" from its scripture card.
- Two answer boxes on one page get headings with ids of their own, and a box that carries its own points counts as speakable.
- A heading id the author set is reserved, so a made id never repeats it. A heading in Hebrew, Greek or Arabic keeps its letters in its id, so its contents link finds it. The reading time counts every word, Hebrew, Greek and Arabic included. "Last reviewed" takes only a real calendar date, and shows the date saved in any time zone.
- A contextual link never takes a chapter and verse, and a ">" inside an attribute no longer splits a tag. The footnotes move to Notes and sources only when that block follows the body in the template, and are read whether WordPress stored them as JSON or as an array.
- Related entries and the next entry read every entry on the book, with their terms from the cache. Ties go to the nearer chapter, then the nearer verse.
## 0.10.7

The theme's documents, brought to one standard. Nothing in the site changed for visitors, and nothing was renamed or removed.

### Added
- `readme.txt`, for site owners in plain words: what the theme does, installing and updating by FTP (with the temporary folder and rename), each settings tab, questions, credits and an upgrade notice.
- `SSOT.md`, the fixed facts and rules: identity (the live folder is `TBE`), purpose, owner decisions with their dates, content counts, addresses, the option, filter, constant and token names that must not change, the credits that must stay, the contributor rules and the release checklist.
- `UPGRADING.md`, the steps for updating a site, one note per version that needs action, and a roadmap (Next, Later, Ideas) with the evidence for each item.
- `.claude/CLAUDE.md` and `.claude/skills/theme-docs/SKILL.md`, the documentation standard the five documents follow. `.gitattributes` marks `/.claude` `export-ignore`.

### Changed
- README.md gains an "At a glance" section (version, requirements, Apache and Nginx, multisite), the boot sequence and a full file map, the settings and their option, the hooks, filters and constants, the commands to test and release, Nginx lines for the `.htaccess` rules, and a performance note. Its file table lists every stylesheet and script, its settings list names the Starter commentary and Error pages tabs, and the ZIP size reads about 21 MB (it said 14). The note that New Testament notes wait for the New Testament text is gone: all 66 books are bundled.
- The theme's `.htaccess` refuses `*.md` in any letter case (`(?i)\.md$`). Dot folders, `.claude/` among them, were refused already. Checked on Apache 2.4: the four Markdown documents, an `x.MD` file, `.claude/`, `.git/config` and `tools/` answer 403; `readme.txt`, the stylesheets and `errors/` answer 200.
- The changelog no longer names other sites, and gains the 0.7.0 and 0.7.1 entries from the history.
- `tools/test_docs.php` checks that the five documents are there, that the version agrees across `style.css`, `functions.php`, `readme.txt`, `CHANGELOG.md`, README.md and UPGRADING.md, that they carry no em dash or contraction, and the `.htaccess` and `.gitattributes` rules. The README title loses its em dash.

## 0.10.6

Fixes from the live logs of thebible.exposed. Nothing was renamed or removed.

- The live theme folder is `TBE`, and 0.10.2 had set the README's `ErrorDocument` paths and the font address in `errors/*.html` to `/wp-content/themes/tbe/`, which answers 404 there. No code names the folder now. The copies in `wp-content/tbe-errors/` and the drop-ins already took it from `get_stylesheet_directory_uri()`, and the Error pages tab builds its lines from `content_url()`; the network check now reads the folder name from the stylesheet directory too (`tbe_theme_folder()`), so `TBE` matches `TBE` on a case-sensitive server. The theme's own `errors/*.html` cannot know the folder, so they name the font relative to `errors/` (`TBE_ERROR_FONT_RELATIVE`, `../assets/fonts/Angel.woff2`); the 503 page the upload guard sends from `errors/` gets the real address on the way out. The README says the folder may be `TBE` and that the `ErrorDocument` lines name the copies in `wp-content/tbe-errors/`, which the Error pages tab gives exactly. `tools/test_error_pages.php` runs with the folder named `TBE` and fails if any code names `themes/tbe/`.
- The owner uploads `style.css` and `functions.php` first, so the new `?ver=` went out before the new asset files were there, and visitors and Googlebot got 404s for the stylesheets, scripts and fonts. Every stylesheet, script, font and brand icon now carries a hash of its own file as its version (`tbe_asset_version()`, xxh3, or crc32b before PHP 8.1; under a millisecond for all of them), with `tbe_enqueue_theme_style()` and `tbe_enqueue_theme_script()`. An address changes only once its file has changed on the server, and it changes whenever the contents do, so a part-written file, a file replaced twice in one second and one uploaded with its old time each get an address of their own. While a file is missing, its handle is an empty one: the stylesheets that depend on it and its inline CSS still load, and no address that answers 404 goes out. The `@font-face` rules moved from the stylesheets into PHP (`tbe_font_faces()`, `tbe_font_face_css()`): each goes inline with the stylesheet that uses it, and to the block editor, at a versioned address, and a font whose file is missing is left out, so the text falls back to the next face in its stack. The font preloads name the same versioned addresses. The brand icons, the social preview image and the JSON-LD logo and image wait for their files too (`tbe_font_preload()`, `tbe_brand_tag()`, `tbe_brand_url()`). In the classic editor, TinyMCE loads `base.css` and `scripture.css` by address; they get their hashes too, and a missing one is left out (`tbe_mce_css()`). The block editor reads the editor styles from disk and puts them inline, so it requests no address. Both editors get the font rules: the block editor through `block_editor_settings_all`, TinyMCE through its `content_style` (`tbe_mce_font_faces()`). The error pages version their font too, with the first 12 characters of its MD5 (`tbe_error_font_version()`), the same on every PHP version so `--check` holds; a new font makes the copies in `wp-content/tbe-errors/` outdated, and the next admin page writes them again. `/favicon.ico`, which cannot carry a version, is cached for a day instead of a week and carries the file's hash as its `ETag`, so a browser holding the same icon gets 304 and a changed one is sent at once; while the file is missing, WordPress answers as it would without the theme.
- `/wp-sitemap.xml` answered the theme's 404. The cause was Settings, Reading, "Search engine visibility": the box was ticked on the live site, so WordPress turned its sitemaps off (the 404), left the sitemap out of `robots.txt` (67 bytes, no `Sitemap:` line) and put `noindex, nofollow` on every page. No filter, route, query trap or `site-links` rule of the theme was involved. `inc/sitemaps.php` keeps WordPress's sitemaps on either way (`tbe_core_sitemaps_enabled()` on `wp_sitemaps_enabled` at priority 1), unless Yoast SEO, Rank Math, All in One SEO, SEOPress or The SEO Framework serves its own (`tbe_plugin_sitemap_url()`). `robots.txt` names the live sitemap once (`tbe_robots_sitemap()`). The box stays as the owner set it; while it is ticked, the dashboard and Appearance, TBE say so (`tbe_search_visibility_notice()`). `add_filter( 'tbe_core_sitemaps', '__return_false' );` turns the sitemaps off.
- Scanner probes rendered the full 74 KB theme 404. `tbe_short_circuit_probe()` now answers them with `Not found.` (10 bytes, `404`, `noindex`, `no-store`) on `parse_request` at priority 0, before the REST API, the query and the template. A probe (`tbe_is_probe()`) is a dot file or folder anywhere in the path (`/.env*`, `/.git*`, `/.aws`); a PHP file, or a backup of one, that is not a WordPress entry point or in `wp-admin/`; or `wp-config`, `phpinfo` or `_environment`, bare or with an extension. `.well-known`, the admin, AJAX, cron, the login screen, REST routes and any path with a real file behind it are never caught.
- `tools/test_assets_sitemaps.php` is new and checks the asset versions and the sitemaps; `tools/test_live_logs.php` checks the probe paths from the logs and the site's own paths; `tools/test_login.php` checks the login stylesheet's version. Checked on WordPress 7.1.4 under PHP's built-in server, with the theme in a folder named `TBE` and "Discourage search engines" ticked: `/wp-sitemap.xml` answers 200, `robots.txt` names it, the probes answer 10 bytes, the site's pages, the REST API, `wp-login.php` and the theme 404 for other paths answer as before, and with `reader.css`, `reader.js` and `SBL_BLit.woff2` taken away the chapter page names none of them.

### Upgrading from 0.10.5
- Upload as before. The asset addresses change once, to file hashes.
- If `.htaccess` has `ErrorDocument` lines that name `/wp-content/themes/tbe/errors/`, replace them with the lines from Appearance, TBE, Error pages, which name `/wp-content/tbe-errors/`.
- Untick Settings, Reading, "Discourage search engines from indexing this site" when the site should be found. WordPress's sitemap stays open either way, and while an SEO plugin serves its own, that one is used.

## 0.10.5

Fixes from the live logs. Nothing was renamed or removed.

- The owner uploads by FTP from a working copy, which puts `.git` and `tools/` on the live server. A new theme `.htaccess` refuses every dot entry (`.well-known` stays open), the `tests`, `tools`, `bin` and `docs` folders and every Markdown file, with a mod_rewrite fallback where mod_alias is missing. `tools/.htaccess` refuses its folder wherever the theme sits.
- `inc/dev-files.php` removes the hidden entries and `tools/` on the first request of any kind after the version changes (`tbe_cleanup_on_version_change()` on `init`). One request runs it, under a lease taken with `add_option()`, renewed once a minute while the walk runs and taken over after five minutes by one compare-and-set UPDATE (`tbe_swap_lock()`). The version is recorded only when nothing is left; an entry that cannot be removed is tried again five minutes later and logged once a day. A local or development site keeps everything, and so does `TBE_KEEP_DOTFILES`.
- Every PHP script in `tools/` exits at once unless PHP runs it from the command line.
- An upload that sent `functions.php` before `inc/` would end every request in a fatal error. `functions.php` now lists its files (`tbe_theme_files()`, and `tbe_theme_support_files()` for the two that other files load), checks every one before any loads, and loads them inside a `try`, so a half-written file is caught too. Visitors get the static 503 page (the site's copy in `wp-content/tbe-errors/`, else the theme's) with `Retry-After: 300`; the log gets one line per problem every ten minutes (`tbe_incomplete_theme()`). A missing file leaves the admin alone; a file that breaks part-way sends the page to the admin too. The login screen, AJAX, cron and the command line never get it (`tbe_incomplete_serves()`).
- `/wp/v2/users` and `/wp/v2/users/<id>` were public. `inc/hardening.php` removes them for visitors who are not logged in (`tbe_hide_rest_users()`); the block editor keeps them. `?author=N` and `?author_name=` go to the home page with a 301 before WordPress's canonical redirect names the account (`tbe_block_author_query()`), and the users sitemap goes.
- XML-RPC answered password guesses. It is now off (`xmlrpc_enabled` false, no methods, no pingback header or RSD link, and `xmlrpc.php` answers 403), unless Jetpack is active or `TBE_ALLOW_XMLRPC` is set (`tbe_xmlrpc_allowed()`).
- The theme does not write the site's `.htaccess`, so the Error pages tab shows the lines to paste for it (`tbe_security_htaccess_snippet()`): "Block xmlrpc.php" (left out while XML-RPC is allowed) and "TBE: deny direct core files", which answers 403 for the PHP files in `wp-includes` (TinyMCE's loader and `ms-files.php` excepted), `wp-settings.php`, `wp-config-sample.php`, `readme.html` and `wp-admin/install.php`. The README gives them too.
- `tools/test_live_logs.php` checks all of the above. Checked on Apache 2.4 with mod_php, with and without mod_alias: every denied path answers 403, and the login screen, the admin, the REST API, TinyMCE's loader, the stylesheets, fonts, error pages and data files answer 200. With a stubbed WordPress, a missing or half-written `inc/` file gives the 503 page and one log line.

### Upgrading from 0.10.4
- Upload the whole theme folder with hidden files shown in the FTP client, so `.htaccess` and `tools/.htaccess` go up. The first page load afterwards removes `.git`, `.gitignore` and `tools/` from the theme folder on the server; add `define( 'TBE_KEEP_DOTFILES', true );` to `wp-config.php` first to keep them.
- Paste the lines from Appearance, TBE, Error pages, "Requests the web server should refuse", into the site's `.htaccess`, after the WordPress section.
- If Jetpack or a mobile app needs XML-RPC and Jetpack is not active, add `define( 'TBE_ALLOW_XMLRPC', true );` to `wp-config.php`.

## 0.10.4

The home page reads as one story, and the error pages are shared properly on a network. Nothing was renamed or removed.

- The home page runs claim, question, evidence, next step: the hero with its tagline and passage search, then "What the commentary finds" (the six latest notes, a heading that read "Latest commentary"), then "Read the text yourself" with the Read the books, Interlinear and Browse commentary buttons, which used to sit in the hero. Each section heading has one line under it (`.tbe-section-lede`). The README sets out each step and its job.
- The links the block templates and parts carry as root paths (`/bible/`, `/commentary/`, `/glossary/`, `/credits/` and their subpages: 16 in the templates and footer, 4 in the header menu) left the site on a subfolder install. `inc/site-links.php` rewrites them on render, in `core/html`, `core/button`, `core/paragraph` and `core/navigation-link` blocks, to the full address under `home_url()`. `tools/test_site_links.php` checks every one at the root and in a subfolder.
- On a phone the site name in the hero ran past the edge of the screen (the page scrolled sideways by 34 pixels at 375). It now scales to fit.
- The header and footer parts were a `<header>` inside the template's `<header>` and a `<footer>` inside its `<footer>`, which gave every page two banner and two contentinfo landmarks. The inner groups are now `<div>`s with the same classes. axe reports no violations on the home page at 1280 and 375 pixels.
- On a network, 0.10.3 let only the main site write or remove the error pages. A theme switch on the main site then deleted the shared files while subsites still ran TBE, and a network whose main site runs another theme never got them. `tbe_error_files_owner()` now also lets the lowest-ID subsite that runs TBE write them while the main site runs another theme (`tbe_error_files_first_theme_site()`), and `tbe_remove_dropins()` keeps them while any other site still runs TBE (`tbe_error_files_in_use_elsewhere()`), so the last site to switch away removes them. Both read the sites 500 at a time. The writer's next admin page also writes the files back when one is missing (`tbe_error_files_missing()`), even when the stamp says they are current. `tools/test_error_pages.php` covers the main site switching away, the single writer and a network past 500 sites.

## 0.10.3

The web server error pages work on a site in a subfolder. Nothing was renamed or removed.

- `tbe_install_dropins()` also writes a copy of each static page (400, 401, 403, 404, 500, 502, 503, 504) into `wp-content/tbe-errors/`, with the site's home address for the links and the real path of the Angel font. The Error pages tab lists them, `tbe_remove_dropins()` deletes them on a theme switch, and `tbe_htaccess_snippet()` points at them. `errors/` stays as the copy for a site at the domain root.
- `tbe_error_page_html()` takes the home address as a third argument (default `/`), so the drop-ins also link to a subfolder home.
- The admin check rewrites the files when the home address or the theme folder changes, as well as after an update (option `tbe_dropins`).
- On a network only the main site writes or removes the files (`tbe_error_files_owner()`), since every site shares `wp-content`; a subsite no longer overwrites the main site's copies.
- When a plugin serves theme files from another host, the pages ask for the Angel font at its full address (`tbe_error_font_url()`).
- `tools/build_error_pages.php --check` fails on a stale page in `errors/`. `tools/test_error_pages.php` covers the copies in a subfolder.

## 0.10.2

The error pages, after a check on a live copy of WordPress. Nothing was renamed or removed.

- A drop-in another theme left behind, marked "Written by the … theme; safe to delete", is now replaced when the theme installs its own. It used to be detected and kept, so a site that switched from another theme went on showing that theme's pages. A file with no such mark is still never touched. The Error pages tab says "written by another theme: press the button to replace it".
- A 504 page (gateway timeout), `errors/504.html`, in the same design as the 502 page, with its own three wordings. The `.htaccess` lines in the tab and the README gain `ErrorDocument 504`.
- The README and the static pages named the theme folder `TBE`. The folder is `tbe` (the install steps say so, and the tab builds its lines from the real folder), so the README's `ErrorDocument` paths and the font address in `errors/*.html` now read `/wp-content/themes/tbe/`. (Corrected in 0.10.6: the live folder is `TBE`, and no file names the folder any more.)
- `tools/test_error_pages.php` checks the 504 page and the replacement of another theme's leftover.

## 0.10.1

The structured data names the author. Nothing visible changes, and nothing was renamed or removed.

- One Person node, `#author` at the site root: "Mohd Elfie Nieshaem Juferi", `url` `https://menj.bio`, `sameAs` `https://menj.bio` (`tbe_ld_author_node()` in `inc/schema.php`). It is added once to every graph. `tbe_ld_person()` now returns a reference to it, so every commentary Article points to it, whoever the WordPress user is; before, each Article embedded the user's display name and author archive.
- `tbe_ld_one_author()` points every `author` in the graph to that Person and gives page nodes (WebPage, ItemPage, CollectionPage, SearchResultsPage and the like) the same reference. The publisher stays the site.
- `tools/test_schema_author.php` checks the Person, the references, one Person per graph and the publisher.
- The front end was searched for wording that presents other sites as related ("sister", "network", "our other site", "family of sites"). There is none to change: the hits are Bible text and the names of social networks.

## 0.10.0

The error pages, checked against the brief for all eight themes. The drop-ins, the static pages, the button and the `.htaccess` lines were already here. Nothing was renamed or removed.

- The installer no longer replaces a drop-in another theme wrote. It used to treat any "Written by the … theme; safe to delete" file as replaceable; now it writes only a missing file or its own, and the Error pages tab says "written by another theme; left alone".
- The Error pages tab tells a current copy from an outdated one ("installed (this theme), outdated"). `tbe_dropin_state()` returns `outdated` when the file differs from what the theme would write now. `TBE_DROPIN_VERSION` is 2, so the first admin page after the update writes the new copies.
- Each write goes to a temporary file and is renamed into place, so WordPress never reads half a drop-in.
- `php-error.php` opens on "Something went wrong" and `db-error.php` on "We cannot reach our database"; the other variants stay and the script still picks one at random.
- Every error page's title tag reads "<page> | The Bible Exposed" (it used " · ") and stays under 60 characters: a variant title over 32 characters falls back to the page's short name (`tbe_error_title_tag()`), in PHP and in the script.
- The drop-ins send `X-Robots-Tag: noindex` beside the robots meta tag.
- The code above the heading takes the rubric red instead of the gold, which fell below 4.5:1 on the light card.
- The 404 page sends `noindex, follow`, and its title reads "Page not found | The Bible Exposed" (it used an en dash).
- `tools/test_error_pages.php`.

## 0.9.0

A login screen in the site's dress. Nothing was renamed or removed.

- `inc/login.php` and `assets/css/login.css` cover every screen wp-login.php serves: log in, lost password, reset, registration and the box shown when a session expires. The broken-cross mark and the name in Angel sit above the form and link home. The card, fields and button take the active colour scheme, custom colours included, and dark mode follows the choice the visitor saved on the site, because `theme.js` loads on the login screen too.
- The stylesheet loads only through `login_enqueue_scripts`, and every rule is scoped to `body.tbe-login` (`tbe-login--interim` in the session box). The title tag reads "Log In | The Bible Exposed", cut to stay under 60 characters.
- WCAG 2.2 AA: field edges and focus rings reach 3:1 or more, text 4.5:1 or more in light and dark, and the links, checkbox and buttons are at least 24px. On phones the button runs the full width of the card.
- A new tab, Appearance → TBE → Login screen, holds a switch (`login_design`, on by default), a logo URL (`login_logo`) and a line above the form (`login_message`).
- While a plugin that styles the login screen is active (LoginPress, Custom Login, Login Logo, Branda, Admin Custom Login, Login Designer, Erident), the theme leaves the screen alone; the `tbe_login_plugin_active` filter adds others. The module builds no login address, so WPS Hide Login keeps moving it.
- The clean brand mark is now `tbe_brandmark_svg()`, used by the `tbe/brandmark` block and the login screen.
- `tools/test_login.php` checks all of this.

## 0.8.2

Two fixes to the search base, from a second code review.

- A page whose address is the base now refuses it, and the fallback candidates are checked the same way, so a page at `/search/` keeps its child paths.
- When `search`, `find` and `search-results` are all taken, the fallback numbers the last (`search-results-2`, `search-results-3` and on) until one is free.
- `tools/test_plugin_features.php` checks both.

## 0.8.1

Fixes to the search base from a code review. Nothing was renamed or removed.

- The list of refused bases also takes the live category and tag bases from Settings → Permalinks and the rewrite slug of every registered post type and taxonomy (`tbe_search_live_slugs()`).
- A base stored before it was refused left the saved rewrite rules on the old base. The option `tbe_search_rules_base` records the base the rules were built with, and `init` flushes once when the base in force differs.
- An array posted for the field made `sanitize_key()` throw a TypeError on PHP 8. The value is checked with `is_string()` first.
- A post type registered on `init` after priority 1 could take the stored base. The late `init` hook now sets `$wp_rewrite->search_base` to the base in force on every load, before any flush, so the rebuilt rules and the search links use it.
- When the chosen base is refused, the fallback is the first free one of `search`, `find` and `search-results` (`tbe_search_fallback_base()`), since a post type or taxonomy may register `search` itself. The notice names the base used.
- `tools/test_plugin_features.php` checks all of these.

## 0.8.0

The features of four plugins, built into the theme. Nothing was renamed or removed.

- Profile links: icons from the Minimalist Social Icons Pack in the footer, set under Appearance → TBE → Profile links. Also the `tbe/social` block and the `[tbe_social]` shortcode.
- Reading progress bar: a new Reading bar tab sets where it shows, the edge, the height and the colours. It is decorative and hidden from screen readers.
- Unlisted posts: an Unlist box in the editor, Quick Edit, Bulk Edit and an Unlisted view. An unlisted entry keeps its address, drops out of every listing, feed, search and sitemap, and is marked noindex. Its state is post meta, so two saves at once cannot undo each other, and a change made by the plugin to its own list is taken over.
- Search addresses: `/?s=term` redirects to `/search/term/`, with the base set under Settings → Permalinks. A base such as `bible`, which the reader's own routes use, is refused and `search` is kept.
- Each of the last three steps aside while its plugin is active.
- `tools/test_plugin_features.php` tests the unlisted filtering and the search redirect.

## 0.7.1

- The site name in the hero stays on one line. Its size follows the space inside the side padding (Angel is about 8em wide), so it fits from 200 pixels up with no sideways scroll.

## 0.7.0

The first version in this repository.

- The ESV reader, the Hebrew and Greek interlinear, the commentary post type with its sections, the glossary, the search and the index, the settings screen under Appearance, and the error pages.
- `data/canons.json` records which tradition counts each book as scripture: Jewish, Protestant, Catholic, Orthodox and Ethiopian. Every book page says who counts it, the book list has a filter that dims the books a tradition does not count, and the deuterocanonical, further Orthodox and Ethiopian books sit below the New Testament as prepared sections, marked "Not on the site yet".

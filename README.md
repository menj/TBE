# TBE: The Bible Exposed (child theme)

Child theme of **Twenty Twenty-Five** for https://thebible.exposed.

**Scope: the complete ESV, the Hebrew Old Testament and the Greek New Testament, word by word.** Recorded readings exist for Genesis 1 (Hebrew) and Matthew 1 (Greek) so far. The interface is English only. Requires WordPress 6.7+ (the parent theme's minimum) and PHP 8.0+.

## Before you upload
- **Install Twenty Twenty-Five first.** This is a child theme; WordPress refuses it without its parent.
- **Hosting:** PHP 8.0+ with the `dom` and `mbstring` extensions (almost every host has both; without `dom` the glossary tooltips and scripture hover cards quietly switch off). The ZIP is about 21 MB, so check the host's upload limit (Appearance → Themes → Add New → Upload shows it), or upload by SFTP into the theme folder under `wp-content/themes/`. The folder may be called `tbe` or `TBE` (the live site uses `TBE`); the theme reads its own folder name at run time, and the Error pages tab gives the exact `.htaccess` lines for it.
- After activating, go to Settings → Permalinks and press **Save** once. Pretty permalinks (not "Plain") are required for `/bible/…`.
- **Uploading by FTP:** turn on "show hidden files" in the FTP client (in FileZilla: Server, Force showing hidden files), so the theme's `.htaccess` and `tools/.htaccess` go up with the rest. They keep the web server from serving `.git`, `tools/` and the Markdown files while they are on the server. Upload `functions.php` last, or use the temporary folder and rename in UPGRADING.md. While a file in `inc/` is missing or half-written, a visitor gets the 503 page with `Retry-After`, never a fatal error. The guard lives in `functions.php`, so it cannot cover `functions.php` itself: a visitor who arrives while that one file is half-written can get a PHP parse error. Each stylesheet, script, font and icon address carries a hash of its own file as `?ver=`, so an address changes only once its new file is on the server, and a font, brand icon or preload whose file is not there yet is left out. The theme writes the `@font-face` rules itself (`tbe_font_face_css()` in `inc/enqueue.php`), so a font's address carries its version too.

## Install
1. Install and keep **Twenty Twenty-Five** (the parent) on the site.
2. Download this repository as a ZIP (or zip its contents into a folder named `tbe` or `TBE`) and upload it under Appearance → Themes → Add New, then activate it. The theme files live at the repository root.
3. Add the ESV API token to `wp-config.php` (optional, only needed for the live fallback):
   ```php
   define( 'TBE_ESV_API_TOKEN', 'your-token' );
   ```
   Never commit the token. It is not stored in the database or shown in the admin.
4. Visit Settings → Permalinks and press Save once if `/bible/` returns a 404.

## Architecture

**Boot sequence.** WordPress loads Twenty Twenty-Five, then this theme's `functions.php`, which:
1. defines `TBE_VERSION`, `TBE_DIR` and `TBE_URI` (the last two from the stylesheet directory, so the folder name is never written down);
2. checks that every file in `tbe_theme_files()` and `tbe_theme_support_files()` is readable, and sends the static 503 page if one is missing (`tbe_incomplete_theme()`);
3. loads the files in `inc/` in the order `tbe_theme_files()` gives, inside a `try`, so a half-written file is caught too;
4. adds editor-style support and, on `init` at 99, flushes the rewrite rules once whenever `TBE_VERSION` changes (option `tbe_rewrite_version`).

**File map.**

| Path | Purpose |
|---|---|
| `functions.php` | Version, paths, the upload guard and the loader |
| `style.css` | The theme header only |
| `theme.json` | Block settings and styles on top of the parent's |
| `inc/settings.php` | The settings screen, defaults and colour schemes |
| `inc/bible.php`, `inc/canon.php` | Book data, the reference parser and the canon labels |
| `inc/routes.php`, `inc/reader.php` | The `/bible/` routes and the reader views |
| `inc/interlinear.php`, `inc/dss.php`, `inc/audio.php` | Interlinear pages, Dead Sea Scroll variants and recorded readings |
| `inc/lexicon.php`, `data/lexicon/` | The Strong's lexicon pages and their data (STEPBible TBESG and TBESH, CC BY 4.0; Metzger's frequency list) |
| `tools/build_lexicon.py`, `tools/build_metzger.py` | Build `data/lexicon/` from STEPBible and from Metzger's scan |
| `inc/manuscripts.php` | The `/manuscripts/` pages and the note under a chapter (class `TBE_Manuscripts`) |
| `inc/esv-api.php`, `inc/quran.php` | The live ESV fallback and the Qur'an references |
| `inc/frontend-edit.php` | Front-end editing: the Edit / Add a note drawer built on core's block editor packages, for users who can edit |
| `inc/commentary.php`, `inc/entry-fields.php`, `inc/starter-commentary.php` | The commentary post type, its boxes, and the starter packs |
| `inc/glossary.php`, `inc/enrich.php` | The glossary, tooltips and scripture hover cards |
| `inc/search.php`, `inc/search-permalinks.php` | The Bible search and the `/search/term/` addresses |
| `inc/schema.php` | The JSON-LD graph |
| `inc/blocks.php`, `inc/site-links.php` | The theme's blocks and the template links under `home_url()` |
| `inc/article.php` | The single commentary page: breadcrumb, dek, meta row, answer box, contents, notes and sources, next step, related entries, the FAQ style and pattern, and the Key points and review box |
| `inc/contextual-links.php`, `data/link-map.json` | Contextual links in an entry, from the shared link map and the settings |
| `inc/enqueue.php` | Stylesheets, scripts, fonts and their versions |
| `inc/branding.php` | Favicon, touch icon, web app manifest, `/favicon.ico`, social preview and theme colour |
| `inc/error-pages.php`, `inc/error-page-html.php` | Drop-ins, static error pages and the Error pages tab |
| `inc/social.php`, `inc/progress.php`, `inc/unlist.php`, `inc/unlist-admin.php`, `inc/login.php` | Profile links, the reading bar, unlisted posts and the login screen |
| `inc/hardening.php`, `inc/sitemaps.php`, `inc/dev-files.php` | Hardening and probes, sitemaps, and the dev-file clean-up |
| `assets/css/` | `base.css` (tokens, chrome), `reader.css`, `scripture.css`, `components.css`, `interlinear.css`, `polish.css` (loaded last), `article.css` (single commentary pages), `progress.css`, `social.css`, `login.css`, `admin.css` |
| `assets/js/` | `reader.js`, `tooltips.js`, `interlinear.js`, `audio.js`, `article.js` (the sticky contents bar), `progress.js`, `admin.js`, `admin-entry.js`, `unlist-admin.js` |
| `assets/fonts/`, `assets/img/`, `assets/audio/` | Fonts with their licences, brand and social images, recordings |
| `data/esv/{book}/{chapter}.json` | Bundled ESV text, one file per chapter (1,189) |
| `data/books.json`, `data/canons.json`, `data/jewish-names.json` | The 66 books and their order, the canons, the Jewish book names |
| `data/interlinear/` | Hebrew Old Testament and Greek New Testament, word by word (STEP Bible, CC BY 4.0; see its README.txt) |
| `data/jps/`, `data/jps-versification.json`, `data/dss/`, `data/audio/` | JPS 1917 text, Dead Sea Scroll comparisons, word timings |
| `data/manuscripts/passages.json` | The manuscripts section: each passage, each early manuscript's reading and text, the editions and the English (CNTR, CC BY-SA 4.0; see its README.txt) |
| `data/commentary/` | The starter commentary packs |
| `templates/`, `parts/` | Block templates (front page, reader, commentary and glossary single and archive, 404), header and footer |
| `errors/` | The theme's own static error pages, built by `tools/build_error_pages.php` |
| `tools/` | Build scripts and self-tests, refused on the web and removed from a live site |
| `tools/fetch_esv.py` | Downloads the ESV text slowly and resumably (default one request every 15-30 s; stops on any rate-limit reply; `--max N` per run) |
| `tools/build_interlinear.py` | Downloads the open STEPBible data and builds `data/interlinear/` |
| `data/manuscripts/editions.json`, `assets/img/manuscripts/books/` | The UBS and Swanson evidence for each passage, and the scanned pages |
| `tools/commons_api.py` | A polite Wikimedia Commons client: search, licence and author (Commons, else Toolforge), download a resized copy |
| `tools/fetch_manuscript_images.py`, `data/manuscripts/images.json`, `assets/img/manuscripts/` | The manuscript photographs from Wikimedia Commons, their list and credits |
| `tools/build_manuscripts.py` | Clones the CNTR transcriptions, reads CNTR's translation pages, and builds `data/manuscripts/passages.json` |
| `tools/ttf2woff2.js` | Dependency-free lossless TTF to WOFF2 converter |

## At a glance
- Version 0.10.18. WordPress 6.7 or later with Twenty Twenty-Five, PHP 8.0 or later with `dom` and `mbstring`.
- Apache 2.2 or 2.4 reads the theme's `.htaccess` and `tools/.htaccess` as they are. On Nginx those files do nothing; add the lines under "Nginx" below to the server block.
- Multisite works: each site keeps its own settings, and one site writes the shared error pages (see Error pages).
- The theme makes no remote call from a visitor's browser. The server calls the ESV API only for a chapter the bundle lacks, and only with a token.

## Test and release
```bash
set -e
for t in tools/test_*.php; do php "$t"; done
for f in functions.php inc/*.php tools/*.php; do php -l "$f" >/dev/null; done
php tools/build_error_pages.php --check
python3 tools/check_packs.py
```
With `set -e` the first failure stops the run with a non-zero exit code. A release bumps `Version:` in `style.css`, `TBE_VERSION` in `functions.php` and `Stable tag:` in `readme.txt`, adds the entry to `CHANGELOG.md` and its short form to `readme.txt`, and adds a note to `UPGRADING.md` when the owner must act. `SSOT.md` holds the full checklist.

## The home page as a story
`templates/front-page.html` reads top to bottom as one argument, the way the site works: make the claim, open the text, show what the notes find, then hand the reader the whole text.

| Step | Section | Its job |
|---|---|---|
| 1 | Hero: the broken cross, the name, the tagline | The claim: textual criticism, critical commentary and reflection on the Bible (the tagline, Appearance → TBE). |
| 2 | Passage search, in the hero | The reader's question: go straight to any passage or word. |
| 3 | "What the commentary finds", the six latest notes | The evidence. Its line says each note is filed under the passage it reads, so it can be checked against the text. |
| 4 | "Read the text yourself", the Read the books, Interlinear and Browse commentary buttons | The next step: the 66 books of the ESV with the Hebrew and Greek beneath them. |

The buttons sat in the hero until 0.10.4 and now close the page; the header menu carries the same links at the top. The templates write their links as root paths (`/bible/`); `inc/site-links.php` turns them into full addresses under `home_url()` as each block renders, so a subfolder install keeps them inside the site. A new template link to another section goes in `tbe_site_link_sections()`. Each section heading is an ornamented `h2` followed by one line (`.tbe-section-lede`). A front-page template saved in the Site Editor keeps its own layout; reset it (Appearance → Editor → Templates → Front page → Reset) to take this one.

## Settings (Appearance → TBE)
Tabbed: **General** (tagline, structured data), **Appearance** (three colour schemes, burgundy and gold by default, or fully custom colours), **Reader and commentary** (text size, verse numbers, section names), **Commentary pages** (contents and its style, the answer box, the next step and its mid-article line, related entries, an Ask a question page, contextual links), **Reading bar** (on or off, where it shows, top or bottom, height, colours), **Profile links** (the address of each of the site's profiles), **ESV and licence** (token status, local-text count, live fallback, copyright notice), **Login screen** (the theme's dress on wp-login.php on or off, a logo URL, a line above the form), **Starter commentary** (import the packs again as drafts) and **Error pages** (the drop-ins, the static copies and the `.htaccess` lines). The search base is set under Settings → Permalinks.

Every setting is one key in the option `tbe_settings`, read with `tbe_setting( $key )` and merged with the defaults in `inc/settings.php`, which also draws the tabs (`tbe_render_settings_page()`). The Error pages tab is drawn by `tbe_render_errors_tab()` in `inc/error-pages.php`; the Starter commentary tab by `inc/starter-commentary.php`. The keys, by tab:

| Tab | Keys |
|---|---|
| General | `tagline`, `schema`, `pack_sync`, `error_pages` |
| Appearance | `scheme`, `colors` |
| Reader and commentary | `font_scale`, `verse_numbers`, `layout`, `boxes_open`, `glossary_tooltips`, `ref_cards`, `label_textual`, `label_critical`, `label_islamic`, `label_reflection` |
| Commentary pages | `toc`, `toc_style`, `answer_box`, `next_step`, `mid_next`, `related_count`, `ask_url`, `ctx_out`, `ctx_out_max`, `ctx_own_max`, `ctx_extra` |
| Reading bar | `progress`, `progress_types`, `progress_position`, `progress_height`, `progress_color`, `progress_track` |
| Profile links | `social_profiles` |
| ESV and licence | `esv_live_fallback`, `copyright` |
| Login screen | `login_design`, `login_logo`, `login_message` |

## Built in from four plugins (0.8.0)
The theme does the work of the Minimalist Social Icons Pack, Reading Progress Bar, Unlist Posts & Pages and Pretty Search Permalinks. None of them existed in the theme before. The last three turn themselves off while their plugin is active, so nothing runs twice.

- **Profile links** (`inc/social.php`, `assets/css/social.css`, `assets/img/social/`). Twelve icons from the Minimalist Social Icons Pack 2.8, GPL, stripped of their titles. Each is inline SVG filled with `currentColor`, marked `aria-hidden` and `focusable="false"`, inside a link named by its `aria-label` and 44 pixels square. The footer carries the `tbe/social` block; the `[tbe_social]` shortcode puts the row anywhere else. With no address set, nothing prints and the stylesheet does not load. The pack's LinkedIn and Scribd icons come from Font Awesome under CC BY 4.0 and are left out. Filter: `tbe_social_networks`.
- **Reading progress bar** (`inc/progress.php`, `assets/css/progress.css`, `assets/js/progress.js`). It shows on single posts, pages and commentary notes, and on Bible chapter and interlinear pages, as chosen on the Reading bar tab. It never shows on the home page. The script uses a passive scroll listener with one `requestAnimationFrame` per frame and moves the fill with a transform; reduced motion drops the transition. Above 600 pixels a top bar starts under the admin bar, and below that the admin bar covers it until it scrolls away. The bar is `aria-hidden`, because the scroll bar already says how far down the page you are and a live `progressbar` would be announced on every scroll.
- **Unlisted posts** (`inc/unlist.php`, `inc/unlist-admin.php`, `assets/js/unlist-admin.js`). Each public post type, commentary and glossary terms included, gets an Unlist box in the editor sidebar, an Unlisted column, Quick Edit and Bulk Edit, the "Unlisted" post state and an "Unlisted (n)" view. An unlisted entry opens at its address. It is left out of the home page, archives, feeds, search, REST collections, the commentary boxes under the verses, the commentary index, the core and Rank Math sitemaps, `wp_list_pages()`, the page-list block and the previous and next links. Its head carries `noindex`, through `rank_math/frontend/robots` when Rank Math is active. Admin screens, and AJAX calls from this site's admin, still see it. Each entry keeps its state in the post meta `_tbe_unlisted`, and every save rebuilds the option `tbe_unlisted` from it, so two saves at once cannot undo each other. The list is mirrored to the plugin's `unlist_posts`, and a change the plugin later makes to that list is taken over on the theme's next read. `tbe_unlisted_live_ids()` returns whichever list is live. Filter: `tbe_unlist_enabled`.
- **Search addresses** (`inc/search-permalinks.php`). `/?s=term` answers 301 to `/search/term/`, and `/?s=term&paged=2` to `/search/term/page/2/`. Other query arguments are kept, arrays such as `post_type[]` included, and an empty search is not redirected. Set the base under Settings → Permalinks → Optional (option `tbe_search_base`): lower-case letters, digits and hyphens. A base the theme's own routes use (`bible`, `interlinear`, `credits`, `sources`, `commentary`, `section`, `glossary`) one WordPress keeps, or a live category, tag, post type or taxonomy base is refused with a notice, and `search` is used instead (filter `tbe_search_reserved_bases`). When the base in force differs from the one the rewrite rules were built with (`tbe_search_rules_base`), the rules are flushed once. The Bible search at `/bible/search/` is its own route and is unchanged. The rules are flushed by the existing version check in `functions.php`, never on an ordinary load. Filter: `tbe_search_permalinks_enabled`.

`php tools/test_plugin_features.php` checks the unlisted filtering and the search addresses.

## Login screen (0.9.0)
`inc/login.php` and `assets/css/login.css`: the broken-cross mark and the name in Angel above the form, linking home, on the active colour scheme, light or dark as the visitor chose on the site. Every screen wp-login.php serves is covered, the session-expired box included. Body classes `tbe-login` and `tbe-login--interim`; title "<screen> | <site name>" under 60 characters. It steps aside while a login styling plugin is active (filter `tbe_login_plugin_active`) and builds no login address, so WPS Hide Login keeps working. `php tools/test_login.php` checks it.

## How a chapter is laid out
Modelled on the verse-by-verse commentary book format, with web-only improvements:
- **Chapter opener**: chapter number, book title, an optional **chapter introduction**, and a clickable list of the notes in the chapter. Book pages show a **book introduction**.
- **Verse by verse**: each verse is its own line (switch to continuous paragraphs with one click). A **commentary box** sits directly under the verse it discusses; a ✦ beside the verse opens it and lights up every verse it covers.
- **Footnotes pop up on hover** instead of sending you to the back of the page.
- **Scripture references in commentary** (`John 3:16`, `Rom 8:28`, `Qur'an 112:1-4`, `Q 2:255`) become links with a hover card showing the ESV verse; Qur'an references open quran.com.
- **Glossary terms** are underlined once per note with a tooltip and a link to the term page. The Bible text itself is never annotated.
- **Textual variants table** and **Qur'an cross-references** (validated against the 114 surahs) can be attached to any note.
- **Index of commentary** at `/bible/index/` (by passage, theme and section), **glossary** at `/glossary/`, **search** at `/bible/search/`.

## Shape and motion
`assets/css/polish.css` (loaded last) holds the rounded corners, soft shadows and hover states. Radii are tokens (`--tbe-radius-sm`, `--tbe-radius`, `--tbe-radius-lg`, `--tbe-radius-pill`), and every hover colour is mixed from the active scheme (accent, gold, rubric), so custom colours and dark mode follow automatically. Old Testament books answer in burgundy and New Testament books in gold; chapter numbers fill with burgundy, bronze or red gradients; verses wash gold; notes, interlinear words and cards lift with a coloured glow; section chips take the colour of what they name. Text on every filled hover keeps at least 4.9:1 contrast. With "reduce motion" on, colours still change but nothing moves.

## Structured data (schema.org)
Every page outputs one JSON-LD graph (`inc/schema.php`), switchable under Appearance → TBE → General:
- **Site-wide:** `Organization`, `WebSite` with a `SearchAction`, the page itself (`WebPage`, `CollectionPage`, `ItemPage` or `SearchResultsPage`), a `BreadcrumbList`, the header and footer (`WPHeader`, `WPFooter`) and each navigation link (`SiteNavigationElement`).
- **The Bible:** the ESV edition as a `Book` (publisher and copyright holder Crossway), each biblical book as a `Book`, each chapter as a `Chapter`, each verse as a `Quotation`.
- **Commentary:** each note is an `Article` about the verses it covers, with its sections, themes, author and dates; its variants table is a `Table`; its Qur'an cross-references and its footnotes are `citation`s; the glossary terms it uses are `mentions`. On the note's own page the `ItemPage` carries `speakable` (`.tbe-dek`, `.tbe-answer`, when shown), and a `FAQPage` node lists the questions of its FAQ-style Details blocks, only when there are some on the page (`tbe_article_ld()`).
- **Glossary:** a `DefinedTermSet` of `DefinedTerm`s (with alternate names).
- **Interlinear:** the Hebrew of each verse as a `Quotation` (`inLanguage` `he`, a `workTranslation` link to the ESV verse) and the STEPBible `Dataset` it came from.
- **Search and index:** results and the commentary index as `ItemList`s.

Individual interlinear words are not marked up: schema.org has no suitable type, and hundreds of nodes per page would only add weight. Check the output with `python3 tools/check_schema.py page.json`, which tests every type, property and cross-reference against the official vocabulary (downloaded to `tools/.cache/` on first use).

## Interlinear (Hebrew and Greek)
Each Old Testament chapter links to a word-by-word Hebrew interlinear at `/bible/{book}/{chapter}/interlinear/`: Hebrew in SBL Hebrew, running right to left, with English gloss, transliteration, Strong's number, root word and grammar. Hover or focus a word for its root, meaning and grammar spelled out. Readers can switch each layer on or off and hide the vowel points or cantillation marks. Qere corrections and other variant readings are outlined and flagged.

Every New Testament chapter has the same page in Greek: SBL Greek, left to right, the Nestle-Aland 28th edition with the same layers, plus Robinson-style grammar spelled out. Words that are in other editions (the Textus Receptus, for example) but not in the Nestle-Aland text are outlined, and a toggle shows or hides the flags.

Data: STEP Bible (https://www.STEPBible.org), CC BY 4.0, based on work at Tyndale House Cambridge.

## Recorded readings (audio)
A chapter can have a recording, timed word by word. The interlinear page then shows a player, a play button on every verse, click-a-word-to-hear, and highlights the word being read. **Genesis 1** (Hebrew) and **Matthew 1** (Greek) are done. Files: `assets/audio/{he|gr}/{book}-{chapter}.mp3` (web copy) and `data/audio/{he|gr}/{book}-{chapter}.json` (start/end time of every word, and a flag for the words whose timing is only an estimate). Add a `"credit"` line to that JSON to show a credit under the player.

`tools/align_audio.py` makes the timings: a speech model (faster-whisper) finds when words are spoken, and a global alignment matches what it heard against the exact Hebrew words we already have, so timings are tied to the known text rather than to a transcription. Boundaries are then snapped to the quietest instant nearby. Editors see a **Check timings** button that outlines the estimated words so they can be checked by ear. For Genesis 1: 434 words, 97% matched by sound, 94% confident, and every verse cut from the recording by these timings is heard by the model as that verse. The recording starts with about 20 seconds of spoken introduction; the timings begin at verse 1.

## Front-end editing
`inc/frontend-edit.php`, `assets/js/frontend-edit.js` and `assets/css/frontend-edit.css` give signed-in users a drawer for commentary notes. On a note, "Edit this note" opens it for a user who may edit it; on a Bible chapter, "Add a note here" opens a blank note with the chapter filled in for a user who may create notes. The drawer is built from WordPress's own packages (`wp-block-editor`, `wp-components`, `wp-block-library`, `wp-format-library`, `wp-api-fetch`), loaded only for those users, and saves through `/wp/v2/tbe_commentary` as the signed-in user. **Save** keeps the note's state; **Publish** (shown only to users who may publish) makes a draft public. New notes start as drafts. The passage goes with the request as `tbe_passage_text`: `rest_pre_insert_tbe_commentary` refuses one that is not a real reference, and `rest_after_insert_tbe_commentary` stores it with `tbe_store_passage()`, the function the meta box uses as well.

## Introductions and essays
An entry whose passage is only a book name introduces that book and shows at the top of the book page; an entry for a chapter with no verse shows at the top of that chapter. An entry with no passage at all is a thematic essay. A chapter page lists up to four essays under "Related essays" when they share a tag with the notes on that chapter (`tbe_essays_for_entries()`, `tbe_render_related_essays()`).

## Canons and labels
`data/canons.json` records which tradition counts which book as scripture: **Jewish** (the Tanakh), **Protestant**, **Catholic**, **Orthodox** (Eastern) and **Ethiopian** (Tewahedo). Every book page says who counts it ("Counted as scripture by …", each label explaining itself on hover), and the book list has a row of buttons that dims the books a tradition does not count (choose *Jewish* and the New Testament fades). The 66 books follow their testament (`inc/canon.php`). Below the New Testament the list carries **prepared sections**, clearly marked "Not on the site yet": the deuterocanonical books, the further Orthodox books and the Ethiopian Orthodox books (1 Enoch, Jubilees, 1 to 3 Meqabyan). Nothing is readable there yet. A last section lists the **early Christian writings once read as scripture** (1 and 2 Clement, the Didache, Barnabas, the Shepherd of Hermas, the Apocalypse of Peter, 3 Corinthians and Laodiceans), each labelled "Once read as scripture" because no church counts them today; writings that were never put forward as scripture are not listed. Church lists differ, so each section says so, and the Ethiopian list is flagged as still to be confirmed. To add a book later, give it an entry in `canons.json`, add its chapters, and register it as a reader book.

## Dead Sea Scrolls variants
On the Hebrew interlinear, words where a Dead Sea Scroll reads differently from the Masoretic text are outlined in teal, with a note under the verse ("The scroll reads … where the Masoretic text has …", "The scroll adds …", "The scroll has no …"). **Two levels:** differences of *meaning* (a different word, one added or left out, a different form) show by default; differences of *spelling* only (the same word written with different vowel letters, a longer pronoun, a different word division) appear with the **Spelling too** button. Readings the editors have restored, or are unsure of, are marked. Words are matched by `tools/build_dss_variants.py` against the dataset from the CACCHT project (ETCBC, built on Martin Abegg's data, CC BY-NC 4.0; Zenodo v2.0); the data is not stored here, only the comparison, in `data/dss/{book}/{chapter}.json`. **Isaiah 1 to 5 so far**, against the Great Isaiah Scroll (1QIsa-a). The spelling rules are a heuristic: they can hide a real difference as spelling and show a spelling one as meaning, which is why the spelling view exists.

## Lexicon (0.10.18)
`/lexicon/` (`inc/lexicon.php`, class `TBE_Lexicon`; views `lexicon`, `lexicon-letter`, `lexicon-frequency`, `lexicon-entry`; query vars `tbe_lang` and `tbe_ms`) has a page per Strong's number at `/lexicon/{g|h}{number}/` (any of `g3056`, `G3056`, `h430` works; `TBE_Lexicon::normalise()`), lists by letter at `/lexicon/{greek|hebrew}/{letter name}/`, Metzger's frequency list at `/lexicon/greek/frequency/`, and a look-up box that answers `/lexicon/?n=…` with a 302 to the entry. The interlinear's Strong's numbers link to their entries (`tbe_il_strongs_links()`). An entry's structured data is a `DefinedTerm`.

`python3 tools/build_lexicon.py` downloads STEPBible's TBESG and TBESH (CC BY 4.0) into `tools/.cache/lexicon/`, folds STEP's split numbers (`H0122a`, `G2060A`) under the plain number the interlinear uses, normalises the text to NFC, keeps `<b>` and `<i>`, turns `<ref='Mat.8.8'>` into `[[matthew 8 8|label]]` (linked by `TBE_Lexicon::definition()`), counts each number in `data/interlinear/` and keeps up to 120 places. It writes `data/lexicon/{g|h}/{n}.json` (numbers n×100 to n×100+99, so a page reads one small file) and `data/lexicon/{g|h}-index.json` for the letter lists.

`python3 tools/build_metzger.py` builds `data/lexicon/metzger.json` from Part I of Metzger's *Lexical Aids* (`https://thebible.exposed/pdf/lexical-aids.pdf`, or `--pdf PATH`). The scan is sideways two-page spreads with no text layer: the script extracts the images, turns them upright and reads them with Tesseract (`grc+eng`; needs `tesseract-ocr` and `tesseract-ocr-grc`), caching the text in `tools/.cache/metzger/`. It splits the groups at each "WORDS OCCURRING … TIMES" heading, joins wrapped lines, keeps apart lines whose headword the OCR misread, strips the Greek forms the OCR garbles from the meanings, and matches each headword to a Strong's number (accents ignored, deponents under STEP's active form, a close spelling only where its count fits the group). `CORRECTIONS` holds meanings set by hand from the page image.

A passage of kind `words` may list several spellings separated by `|`; they match as whole words (`has_words()`), so 1 Timothy 3:16 counts both ΘΕΟΣ and its short form ΘΣ as “God” without mistaking ΟΣ inside ΘΕΟΣ. A verse counts as lost, never as lacking the words, only where part of it is lost (`*`, `&`), unreadable (U+FFFD) or supplied whole by the editors (`~`, `+`); a letter the editors restored inside a surviving word (`^`) leaves the reading clear.

## Manuscripts (0.10.18)
`/manuscripts/` lists twenty-two passages where the Greek manuscripts disagree, most of them words the King James Version prints and the earliest manuscripts leave out; `/manuscripts/{id}/` shows one (`inc/manuscripts.php`, routes in `inc/routes.php`, view names `manuscripts` and `manuscript`, query var `tbe_ms`). A passage page has the KJV wording and the WEB and BSB, a table of every CNTR "class 1" manuscript (papyri and parchment up to about AD 400) that covers the place, the four printed editions (Textus Receptus, Robinson-Pierpont, Westcott-Hort, CNTR's Statistical Restoration), and each manuscript's first-hand text line by line, with the verse either side. A chapter that holds a passage shows `tbe_manuscripts_chapter_note()` under its heading.

The data is `data/manuscripts/passages.json`, built by `python3 tools/build_manuscripts.py` (or `--src DIR` for a local copy; `--no-translations` skips the English). It clones https://github.com/Center-for-New-Testament-Restoration/transcriptions into `tools/.cache/cntr` and fetches one translation page a second from `https://greekcntr.org/translation/data/{verse}.html` into `tools/.cache/cntr-translation`. A whole verse counts from CNTR's own marks (`-` absent; `-~`, `+~` or an empty line lost); a few words inside a verse count by their key words in the first scribe's text, and a damaged verse without them counts as lost. To add a passage, add a line to `PASSAGES` in the script and a title and sentence to `TBE_Manuscripts::about()`. The transcriptions are CC BY-SA 4.0, so `passages.json` and the pages built from it carry that licence and the credit to Alan Bunning and the CNTR (`tbe_manuscripts_credit()`). The NET Bible on CNTR's translation pages is left out: CNTR shows it by the publisher's permission, which does not pass on.

Printed apparatuses: `data/manuscripts/editions.json` holds, for each passage, the evidence of the United Bible Societies *Greek New Testament* (`ubs`: letter rating, book page, PDF page, readings with their Greek manuscripts) and, for the Matthew passages, of Swanson's *New Testament Greek Manuscripts: Matthew* (`swanson`). It was transcribed by hand from the page images, since the scans' text layers are unreliable. `sources` names each book and the PDF the owner serves under `/pdf/`, which the page links to at the right page (`#page=N`). The page scans are `assets/img/manuscripts/books/{ubs|swanson}-{pdf page}.jpg`, greyscale, 1000 pixels wide. `TBE_Manuscripts::apparatus()` reads the file and `tbe_manuscripts_apparatus()` renders it under "Later manuscripts too". The owner holds the rights to both books (SSOT.md, owner decisions).

Photographs: `data/manuscripts/images.json` lists Wikimedia Commons files by passage and manuscript, with a caption. Each is chosen by hand and checked by eye to show the passage. `python3 tools/fetch_manuscript_images.py` (with `--local "File:Name=path"` to use a copy already downloaded from Commons when its image server keeps refusing) refuses anything but public domain, CC0, CC BY or CC BY-SA, saves a JPEG no wider than 1200 pixels to `assets/img/manuscripts/{passage}-{ms}.jpg`, and writes the size, licence, author and Commons page back into the list. A run cut short by a rate limit exits 1; run it again and it picks up where it stopped.

It talks to Wikimedia through `tools/commons_api.py`, which also works on its own:

```bash
python3 tools/commons_api.py search "Codex Bezae John"
```

```bash
python3 tools/commons_api.py info "File:Mark16-B.JPG"
```

```bash
python3 tools/commons_api.py download "File:Mark16-B.JPG" /tmp/mark16.jpg --width 1200
```

`info` reads the Commons API and falls back to Magnus Manske's Commons API on Toolforge (`https://magnustools.toolforge.org/commonsapi/`) when Commons answers with a rate limit; Toolforge's licence templates become short names (`licence_name()`). `download` refuses a file that is not free unless given `--any-licence`. Every request names the client and the site in its User-Agent, waits at least 5 seconds after the last one to the same host, honours `Retry-After` on a 429 (else waits 60, 120, 240 and 480 seconds), and is cached in `tools/.cache/commons/`. `tbe_manuscripts_gallery()` shows only entries that have a licence and a file, each linked to its Commons page.

## Parallel view: Christian and Jewish translations
Chapters that have a JPS 1917 text (**Genesis so far**; the rest of the Old Testament is added a book at a time with `python3 tools/fetch_jps.py --books exodus`) show a choice above the text: **Christian · ESV**, **Jewish · JPS 1917**, or **Side by side**, verse by verse, so the differences are plain (heaven and earth, "expanse" and "firmament", "first day" and "one day"). The choice is remembered, and on a phone the two stack. The JPS 1917 is in the public domain; it comes from Sefaria's API and is credited on the Sources page. Old Testament book pages and titles also give the Jewish name of the book (Bereshit, Shemot...) in Hebrew and transliteration beneath the title (`data/jewish-names.json`).

## Cross-references between the Testaments
`data/commentary/cross-references.json` holds 32 critical cross-references, each written once and filed on **both** passages, so the note appears under the New Testament verse and under the Old Testament verse it cites. They cover how Paul reworks the Law (Galatians 3:10-17, Romans 3, 9 and 10, 1 Corinthians 9), how New Testament authors quote the Old Testament differently from its wording (Ephesians 4:8, Hebrews 10:5, Acts 7, Luke 3:36), and Jesus’ own use of Scripture (Matthew 5, 15 and 22, Mark 2 and 12, Luke 4, John 10 and 17). Each note sets the two ESV texts side by side and says plainly where they differ. They are tagged **Cross-reference**, imported and kept in step like the other starter commentary, and carry no typology: they compare wording and context only.

## Hooks, filters and constants

| Name | Kind | What it does |
|---|---|---|
| `TBE_ESV_API_TOKEN` | constant | The ESV API token for the live fallback. Set it in `wp-config.php` only. |
| `TBE_KEEP_DOTFILES` | constant | Keeps `.git`, `.claude`, `tools/` and the other dev files on a live site. A local or development site keeps them anyway. |
| `TBE_ALLOW_XMLRPC` | constant | Keeps XML-RPC on. Jetpack keeps it on without this. |
| `tbe_core_sitemaps` | filter | `false` turns WordPress's sitemaps off. |
| `tbe_plugin_sitemap_url` | filter | The address of an SEO plugin's sitemap the theme does not detect. |
| `tbe_login_plugin_active` | filter | `true` makes the login design step aside. |
| `tbe_search_permalinks_enabled` | filter | `false` turns the `/search/term/` addresses off. |
| `tbe_search_reserved_bases` | filter | Slugs the search base may not take. |
| `tbe_social_meta` | filter | `false` drops the theme's `og:image` and Twitter tags. |
| `tbe_social_networks` | filter | The list of profile networks. |
| `tbe_unlist_enabled` | filter | `false` turns unlisted posts off. |
| `tbe/social`, `[tbe_social]` | block, shortcode | The profile links row. |
| `tbe_contextual_links_map` | filter | The link map read from `data/link-map.json` (`sites`, `links` with `site`, `path`, `phrases`). |
| `tbe_contextual_links_enabled` | filter | `false` turns contextual links off for one entry (second argument, the post). |
| `tbe/breadcrumbs`, `tbe/dek`, `tbe/article-meta`, `tbe/answer-box`, `tbe/contents`, `tbe/sources`, `tbe/next-step`, `tbe/related` | blocks | The pieces of the single commentary page. `tbe/answer-box` takes `summary` and `points` (one a line); `tbe/next-step` takes `variant` (`end` or `mid`). |

Constants the theme defines for itself: `TBE_VERSION`, `TBE_DIR`, `TBE_URI`, `TBE_DROPIN_VERSION`, `TBE_STATIC_ERRORS`, `TBE_ERROR_FONT_RELATIVE`, `TBE_UNLISTED`, `TBE_UNLISTED_META`, `TBE_SEARCH_MAX_HITS`, `TBE_SEARCH_PER_PAGE`.

## Performance
- Assets are versioned by a hash of each file (`tbe_asset_version()`): about 1 ms for every stylesheet, script, font and icon together, and a browser keeps each file until it really changes.
- The Bible font and Angel are preloaded at the same versioned address their `@font-face` rules name.
- Scanner probes get a 10-byte 404 before the query runs, where they used to render a 74 KB page with every query of a page view.
- Search results are cached per query and theme version (`inc/search.php`).

## Sitemaps (0.10.6)
WordPress serves `/wp-sitemap.xml` only while Settings, Reading, "Search engine visibility" is unticked. On the live site the box was ticked, so `/sitemap.xml` answered 301 to `/wp-sitemap.xml`, which answered 404, `robots.txt` named no sitemap, and every page carried `noindex, nofollow`. `inc/sitemaps.php` keeps WordPress's sitemaps on either way (`tbe_core_sitemaps_enabled()` on `wp_sitemaps_enabled` at priority 1), unless an SEO plugin serves its own: Yoast SEO, Rank Math, All in One SEO, SEOPress or The SEO Framework, while its sitemap is on (`tbe_plugin_sitemap_url()`). A plugin that turns WordPress's sitemaps off still does so. `robots.txt` names the live sitemap, the plugin's or WordPress's, once (`tbe_robots_sitemap()`). The box itself stays as the owner set it; while it is ticked, the dashboard and Appearance, TBE say so, with a link to Settings, Reading. To serve no sitemap at all, add `add_filter( 'tbe_core_sitemaps', '__return_false' );`.

## Hardening (0.10.5)
Fixes from the live logs, where an FTP upload of a working copy, password guessing and bots asking for WordPress's own files showed up again and again.

- **Development files.** The theme's `.htaccess` refuses every file and folder whose name begins with a dot (`.well-known` stays open), the `tests`, `tools`, `bin` and `docs` folders, and every Markdown file. `tools/.htaccess` refuses its folder wherever the theme sits. Where mod_alias is missing, mod_rewrite refuses the same paths. On the first request after the version changes, `inc/dev-files.php` removes the hidden entries and `tools/` from a live site, once, under a lease that only one request can hold. A local or development site keeps them, and so does `define( 'TBE_KEEP_DOTFILES', true );` in `wp-config.php`. Every PHP script in `tools/` exits unless it runs from the command line.
- **A theme caught half uploaded.** `functions.php` checks every file in `inc/` before it loads any (`tbe_theme_files()`, `tbe_theme_support_files()`), and loads them inside a `try`. A missing or half-written file gives the static 503 page with `Retry-After: 300` and one line in the error log every ten minutes (`tbe_incomplete_theme()`). A missing file leaves the admin alone; the login screen, AJAX, cron and the command line never get the page.
- **Accounts.** `/wp/v2/users` and `/wp/v2/users/<id>` are refused to visitors who are not logged in (`tbe_hide_rest_users()`), `?author=N` and `?author_name=` go to the home page with a 301 (`tbe_block_author_query()`), and the users sitemap goes.
- **Scanner probes** (0.10.6) get a short plain 404, `Not found.`, from `tbe_short_circuit_probe()` on `parse_request` at priority 0, before the REST API, the query and the 74 KB theme 404. A probe (`tbe_is_probe()`) is a path with a dot file or folder in it (`/.env`, `/.env.local`, `/sitemaps/.env`, `/.git/config`, `/.aws/credentials`); a PHP file, or a backup of one (`x.php~`, `x.php.bak`), that is not one of WordPress's entry points or in `wp-admin/`; or `wp-config`, `phpinfo` or `_environment`, bare or with an extension (`/phpinfo`, `/_profiler/phpinfo`, `/webroot/index.php/_environment`). `.well-known`, the admin, AJAX, cron, the login screen, REST routes and any path with a real file behind it, such as a plugin's own PHP endpoint, are never caught.
- **XML-RPC** is off: no methods, no pingback header, and `xmlrpc.php` answers 403. It stays on while Jetpack is active, or with `define( 'TBE_ALLOW_XMLRPC', true );` in `wp-config.php`.
- **At the web server.** The theme does not write the site's `.htaccess`. The Error pages tab shows the lines to paste after the WordPress section (`tbe_security_htaccess_snippet()`): a "Block xmlrpc.php" section, left out while XML-RPC is allowed, and "TBE: deny direct core files", which answers 403 for the PHP files in `wp-includes` (TinyMCE's loader and `ms-files.php` excepted), `wp-settings.php`, `wp-config-sample.php`, `readme.html` and `wp-admin/install.php`. With WordPress in its own subfolder the lines carry it. At the domain root they read:

  ```
  # BEGIN Block xmlrpc.php
  <Files xmlrpc.php>
  <IfModule mod_authz_core.c>
  Require all denied
  </IfModule>
  <IfModule !mod_authz_core.c>
  Order Deny,Allow
  Deny from all
  </IfModule>
  </Files>
  # END Block xmlrpc.php
  # BEGIN TBE: deny direct core files
  <IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteRule ^wp-includes/(?!js/tinymce/|ms-files\.php$).+\.php$ - [F,L]
  RewriteRule ^(wp-settings|wp-config-sample)\.php$ - [F,L]
  RewriteRule ^readme\.html$ - [F,L]
  RewriteRule ^wp-admin/install\.php$ - [F,L]
  </IfModule>
  # END TBE: deny direct core files
  ```

  `php tools/test_live_logs.php` checks all of the above.

## Nginx
Nginx reads no `.htaccess`. These lines in the site's `server` block do the same work as the theme's `.htaccess`, `tools/.htaccess`, the "Block xmlrpc.php" and "TBE: deny direct core files" sections, and the `ErrorDocument` lines, for WordPress at the domain root:

```nginx
# Dot files and folders anywhere (.git, .env, .claude), but .well-known.
location ~ /\.(?!well-known(/|$)) { deny all; }
# The theme's dev folders and Markdown documents, whatever the folder is called.
location ~ ^/wp-content/themes/[^/]+/(tests|tools|bin|docs)(/|$) { deny all; }
location ~* ^/wp-content/themes/[^/]+/.*\.md$ { deny all; }
# XML-RPC. Nginx cannot read TBE_ALLOW_XMLRPC or see Jetpack: leave this
# line out on a site that needs XML-RPC (Jetpack, an old mobile app).
location = /xmlrpc.php { deny all; }
# WordPress files never meant to be opened by address.
location ~ ^/wp-includes/(?!js/tinymce/|ms-files\.php$).+\.php$ { deny all; }
location ~ ^/(wp-settings|wp-config-sample)\.php$ { deny all; }
location = /readme.html { deny all; }
location = /wp-admin/install.php { deny all; }
# The theme's error pages.
error_page 400 /wp-content/tbe-errors/400.html;
error_page 401 /wp-content/tbe-errors/401.html;
error_page 403 /wp-content/tbe-errors/403.html;
error_page 404 /wp-content/tbe-errors/404.html;
error_page 500 /wp-content/tbe-errors/500.html;
error_page 502 /wp-content/tbe-errors/502.html;
error_page 503 /wp-content/tbe-errors/503.html;
error_page 504 /wp-content/tbe-errors/504.html;
```

Put them above the `location ~ \.php$` block. These lines are not tested by the theme's tools; check them on a staging server first.

## Error pages
Errors get their own pages, in the site's look (the broken cross, Angel, burgundy and gold, light and dark) and with a dry edge aimed at the doctrines, not the people.

- **Errors WordPress answers.** `maintenance.php` (503), `db-error.php` (the database cannot be reached) and `php-error.php` (a fatal PHP error) are written into `wp-content` by the theme, so they show even when WordPress is down. They are installed on activation and after each update, refreshed from **Appearance → TBE → Error pages**, and removed again if another theme is activated. They answer `503` with `Retry-After` (maintenance, database) or `500` ("Something went wrong"), with `noindex` and `no-store`. The tab shows each as not installed, installed and current, installed and outdated, or someone else's file. A copy another theme left behind, marked "safe to delete", is replaced (from 0.10.2); a file with no such mark is yours and is never overwritten. Switch it off under General.
- **Errors the web server answers.** 400, 401, 403, 404 for files, 500, 502, 503 and 504 come from Apache before WordPress starts. The theme writes a static page for each into `wp-content/tbe-errors/`, with the drop-ins and under the same marker. Each copy carries the site's real home address for its links and the real path of the Angel font, so the pages work on a site in a subfolder or with `wp-content` moved. The drop-ins use the same addresses. The copies go when another theme is activated. The Error pages tab gives the `ErrorDocument` lines to paste into `.htaccess`, built from the real address of `wp-content`. At the domain root they read:

  ```
  ErrorDocument 400 /wp-content/tbe-errors/400.html
  ErrorDocument 401 /wp-content/tbe-errors/401.html
  ErrorDocument 403 /wp-content/tbe-errors/403.html
  ErrorDocument 404 /wp-content/tbe-errors/404.html
  ErrorDocument 500 /wp-content/tbe-errors/500.html
  ErrorDocument 502 /wp-content/tbe-errors/502.html
  ErrorDocument 503 /wp-content/tbe-errors/503.html
  ErrorDocument 504 /wp-content/tbe-errors/504.html
  ```

  Use these copies in `wp-content/tbe-errors/`, as the lines above do. `errors/` in the theme keeps the theme's own copies, with links from the domain root and the font named relative to `errors/` (`../assets/fonts/Angel.woff2`, with the font's version), since the theme folder may be `tbe` or `TBE`. A relative font address resolves only when a page is opened at its own address, so an `ErrorDocument` line that names `errors/` shows the page in Georgia; the copies in `wp-content/tbe-errors/` carry the real folder. The 503 page the upload guard sends from `errors/` gets the real font address on the way out. Rebuild `errors/` with `php tools/build_error_pages.php`; `--check` fails on a stale page. If the font cannot be found, a page falls back to Georgia.

- **On a network** every site shares `wp-content`, so one site writes the files: the main site, or, while the main site runs another theme, the subsite with the lowest ID that runs TBE (`tbe_error_files_owner()`). A theme switch removes them only when no other site, the main site included, still runs TBE (`tbe_error_files_in_use_elsewhere()`); the last site to switch away removes them, even a subsite. Both scans read the sites 500 at a time.
- Missing pages inside the site use `templates/404.html`, whose message is chosen at random on each load.
- Every page has several wordings (`inc/error-page-html.php`); a different one greets each visit. Static pages and the drop-ins pick one in the browser, so a visitor without JavaScript sees the first.

## Branding
The brand mark is a hollow, inverted cross that is broken through the shaft, drawn in two forms: a clean vector built into the header and footer, and a worn, hand-drawn version (`assets/img/brand/mark-textured.png`, used as a mask so the active colour scheme tints it). The break follows the report in Sahih al-Bukhari 2476 that Jesus, son of Mary, will break the cross on his return; it is cited, in English, on the Sources page. It sits beside the site name in the header and above the title on the home page, and it takes its colour from the scheme, in light and dark. The site name itself is set in Angel.

`assets/img/brand/` also holds the favicon (`favicon.svg`, `favicon-32.png` and `favicon.ico` at 16, 32 and 48px), the touch icon (`apple-touch-icon.png`, on a solid background), the app icons (`icon-192.png`, `icon-maskable-512.png`) with `site.webmanifest`, the logo used in structured data (`icon-512.png`) and the 1200 × 630 social preview (`social-preview.png`). They are used unless a Site Icon is set under Appearance → Customize, which takes precedence. `tbe_brand_icon_tags()` prints them on the site, the login screen and the admin. `/favicon.ico` reaches WordPress when no such file sits in the site root, and `tbe_serve_favicon()` answers it with a day's cache and an ETag (304 on a match, HEAD covered through `template_redirect`). The manifest is static, with relative icon paths. The theme `.htaccess` refuses dot entries, the development folders and Markdown only, so the icons are served. The error pages carry the mark as a `data:` tab icon. The theme also sets the browser theme colour from the scheme and the organisation logo in the structured data. On a site in a subfolder, such as `example.com/blog/`, browsers still ask for `example.com/favicon.ico`, which WordPress never sees: put a copy of `assets/img/brand/favicon.ico` at the domain root there (on a multisite in subfolders every site shares that one file). The head tags cover the pages either way. If a Site Icon is set, put that icon there, converted to an ICO file named favicon.ico, instead.

## Starter commentary
Entry keys must start with the pack id (`combat-kit:…`, `cross-references:…`), because that is how a later update finds, updates and removes the imported entries; `python3 tools/check_packs.py` checks this.

`data/commentary/` holds ready-made entries. They are imported and **published automatically** when the theme is activated (or the first time an administrator opens the dashboard after uploading it). **Appearance → TBE → Starter commentary** remains for importing again as drafts. The first pack is a set of critical-commentary notes (its source is on the Sources page): 70 entries, one per topic or per item, each attached to the first passage it discusses, filed under **Critical Commentary**, tagged by topic, and credited. The entries are written for this site: each restates an argument from the source in our own words and quotes scripture only from the bundled ESV. The source is named on the Sources page, and nowhere else.

**Staying in step with the theme.** Once a pack has been imported it follows the theme. After a theme update, an entry whose text changed is overwritten with the new text, entries new to the pack arrive as drafts, and entries the pack no longer contains go to the Trash (restorable). A pack that was never imported is left alone, and the theme only ever touches entries it created. To keep your own changes to one entry, tick **Keep my edits** in its Starter commentary box. The whole behaviour can be switched off under Appearance → TBE → General.


## The commentary page (0.10.8)
`templates/single-tbe_commentary.html` places the pieces in reading order: `tbe/breadcrumbs`, the title, the section chips, `tbe/dek` (the excerpt), `tbe/article-meta`, `tbe/answer-box`, `tbe/contents`, the content, `tbe/sources`, `tbe/next-step`, the tags and `tbe/related`. Each renders nothing when it has nothing to show. There is no byline: the author is named in the structured data only.

- The meta row shows the published date, the updated date when it falls on a later day, "Last reviewed" from post meta `tbe_reviewed` (a real `Y-m-d` date), and the reading time at 220 words a minute, counting every run of non-space characters.
- The answer box lists post meta `tbe_takeaways`, one point a line. Both meta keys are set in the Key points and review box and shown in REST.
- The contents list comes from the level-two headings in the stored text (`tbe_article_headings()`), from three up; the rendered headings get the same ids (`tbe_article_heading_ids()`, `the_content` at 12).
- The footnotes block renders empty in the body and its list prints in `tbe/sources`, when the current template has that block after Post Content. Author-set heading ids are reserved before any id is made. The Qur'an cross-references move there too, and the variants title becomes a level-two heading.
- Related entries come from the same book, scored 3 for the same chapter, 2 a shared tag and 1 a shared section, then the nearer chapter and verse; mirror copies of cross-references are left out.
- Tables (`core/table`) in an entry become a focusable region labelled by their caption.

The passage line, the apparatus, the tooltips and the article pieces all test for the entry's body with `tbe_is_entry_body()` (the queried entry, in or out of the classic Loop, never an excerpt). Contextual links run on `the_content` at 100, after the scripture cards and glossary tooltips, on the entry's own page only. Each target is linked once, a paragraph gets one new link at most, and nothing inside a link, heading, table, quotation, figure, footnote, the passage line, the apparatus or text marked Hebrew, Greek, Arabic or Syriac is touched. Outbound links carry `tbe-ctx-link--out` and `rel="noopener"`. `php tools/test_article.php` checks the ids, the mid-article line and the links.

## Writing commentary
Commentary → Add New. Fill the **Passage** box (for example `John 1:1-18`, `Psalm 23`, `Jude 5`), choose one or more **Sections** (Textual Criticism, Critical Commentary, Islamic Perspective, Reflection), and write the entry in the block editor. Entries appear in a box under the verse they begin at, with a ✦ marker beside it.

- Passage `John 3` (no verses) makes a **chapter introduction**; a bare book name such as `John` makes a **book introduction**.
- Use the **Footnote** block for notes; they pop up on hover, and on the entry's page they list under Notes and sources.
- Fill **Key points and review** for the "In short" box and the "Last reviewed" date. For questions and answers, insert the **Questions and answers** pattern (Details blocks in the FAQ style).
- Add **Textual variants** (unit, reading, witnesses, notes) and **Qur'an cross-references** (`2:255`, `112:1-4`, `19`) in the boxes below the editor.
- Glossary: Glossary → Add New. Give a short **Excerpt** (shown in the tooltip) and optional spellings/abbreviations. The theme adds 13 starter terms as **drafts** for you to review and publish.

## Refreshing the ESV text
```bash
ESV_API_TOKEN=... python3 tools/fetch_esv.py --delay 15 --max-delay 30 --max 100
```
Existing chapter files are skipped, so delete a file to re-fetch it. The script is deliberately slow and stops immediately if the API reports a rate limit.

## Licence note
The theme is licensed under the GNU General Public License, version 3 or later; the full text is in `LICENSE`.

The profile icons in `assets/img/social/` are from the Minimalist Social Icons Pack 2.8, released under the GPL. The brand marks stay trademarks of their owners.

The ESV text is © Crossway. The copyright notice shown in the footer is editable under ESV and licence; use the exact wording your Crossway permission requires.

## Fonts

**Bible text only** is set in the SBL fonts in `assets/fonts/`: SBL BibLit for the ESV, SBL Hebrew for the Hebrew words and SBL Greek for the Greek words, via `assets/css/scripture.css`, with the `@font-face` rules written by `tbe_font_face_css()` in `inc/enqueue.php`. The site name ("The Bible Exposed", in the header and on the home page) is set in **Angel** by JuliaL, a freeware font; its notes are kept in `assets/fonts/LICENSE-ANGEL-FONT.txt` and it is credited, with a link back to Tangled in Destiny, on the Sources page. A Syriac face, **Estrangelo** (`assets/fonts/Estrangela.woff2`), is ready for the Syriac interlinear and not yet used on any page; its notes are in `assets/fonts/NOTES-ESTRANGELA.txt`. The Arabic of the hadith on the Sources page is set in **Noto Naskh Arabic** (SIL Open Font License; notes in `assets/fonts/LICENSE-NOTO-NASKH-ARABIC.txt`), used there and nowhere else. Labels, buttons, menus and the other interface text set in `--tbe-ui` use **Montserrat** Regular by the Montserrat Project Authors (SIL Open Font License; `assets/fonts/LICENSE-MONTSERRAT.txt`), registered as `montserrat` in `tbe_font_faces()` and loaded with the base and login stylesheets, with the system fonts behind it. It is cut to Latin, Latin-1 and the common punctuation (`pyftsubset … --flavor=woff2` from the owner's OTF, 36 KB); there is one weight, so bold interface text is drawn bold by the browser. Commentary prose and headings keep their existing fonts.

**Every font in the theme is WOFF2.** Montserrat is a Latin subset of the owner's OTF; the others are lossless conversions of the original TTFs, made with `node tools/ttf2woff2.js in.ttf out.woff2` (identical glyphs, much smaller). The site owner holds the SBL / Tiro Typeworks licence; its text is kept in `assets/fonts/LICENSE-SBL-FONTS.txt`.

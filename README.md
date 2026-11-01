# TBE — The Bible Exposed (child theme)

Child theme of **Twenty Twenty-Five** for https://thebible.exposed.

**Scope: the complete ESV, the Hebrew Old Testament and the Greek New Testament, word by word.** Recorded readings exist for Genesis 1 (Hebrew) and Matthew 1 (Greek) so far. The interface is English only. Requires WordPress 6.7+ (the parent theme's minimum) and PHP 8.0+.

## Before you upload
- **Install Twenty Twenty-Five first.** This is a child theme; WordPress refuses it without its parent.
- **Hosting:** PHP 8.0+ with the `dom` and `mbstring` extensions (almost every host has both; without `dom` the glossary tooltips and scripture hover cards quietly switch off). The ZIP is about 14 MB, so check the host's upload limit (Appearance → Themes → Add New → Upload shows it), or upload by SFTP into `wp-content/themes/tbe/`.
- After activating, go to Settings → Permalinks and press **Save** once. Pretty permalinks (not "Plain") are required for `/bible/…`.

## Install
1. Install and keep **Twenty Twenty-Five** (the parent) on the site.
2. Download this repository as a ZIP (or zip its contents into a folder named `tbe`) and upload it under Appearance → Themes → Add New, then activate it. The theme files live at the repository root.
3. Add the ESV API token to `wp-config.php` (optional, only needed for the live fallback):
   ```php
   define( 'TBE_ESV_API_TOKEN', 'your-token' );
   ```
   Never commit the token. It is not stored in the database or shown in the admin.
4. Visit Settings → Permalinks and press Save once if `/bible/` returns a 404.

## What is where
| Path | Purpose |
|---|---|
| `assets/css/` | `base.css` (tokens, chrome), `reader.css` (scripture + commentary), `admin.css` |
| `assets/js/` | `theme.js` (light/dark), `reader.js` (reader tools), `admin.js` (settings) |
| `data/esv/{book}/{chapter}.json` | Bundled ESV text, one file per chapter (1,189) |
| `data/books.json` | The 66 books, chapter counts, order |
| `inc/` | Settings, Bible data and reference parser, commentary post type, routes, reader, blocks |
| `templates/`, `parts/` | Block-theme templates: front page, Bible reader, commentary single/archive, header, footer |
| `tools/fetch_esv.py` | Downloads the ESV text slowly and resumably (default one request every 15-30 s; stops on any rate-limit reply; `--max N` per run) |
| `data/interlinear/` | Hebrew Old Testament and Greek New Testament, word by word (STEP Bible, CC BY 4.0; see its README.txt) |
| `tools/build_interlinear.py` | Downloads the open STEPBible data and builds `data/interlinear/` |
| `tools/ttf2woff2.js` | Dependency-free lossless TTF to WOFF2 converter |

## Settings (Appearance → TBE)
Tabbed: **General** (tagline, structured data, light/dark toggle), **Appearance** (four colour schemes, burgundy and gold by default, or fully custom colours), **Reader and commentary** (text size, verse numbers, section names), **ESV and licence** (token status, local-text count, live fallback, copyright notice).

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
- **Commentary:** each note is an `Article` about the verses it covers, with its sections, themes, author and dates; its variants table is a `Table`; its Qur'an cross-references are `citation`s; the glossary terms it uses are `mentions`.
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

## Starter commentary
`data/commentary/` holds ready-made entries. They are imported and **published automatically** when the theme is activated (or the first time an administrator opens the dashboard after uploading it). **Appearance → TBE → Starter commentary** remains for importing again as drafts. The first pack is a set of critical-commentary notes (its source is on the Credits and references page): 70 entries, one per topic or per item, each attached to the first passage it discusses (those anchored in the New Testament show once the NT text is added), filed under **Critical Commentary**, tagged by topic, and credited. The entries are written for this site: each restates an argument from the source in our own words and quotes scripture only from the bundled ESV. The source is named on the Credits and references page, and nowhere else.

**Staying in step with the theme.** Once a pack has been imported it follows the theme. After a theme update, an entry whose text changed is overwritten with the new text, entries new to the pack arrive as drafts, and entries the pack no longer contains go to the Trash (restorable). A pack that was never imported is left alone, and the theme only ever touches entries it created. To keep your own changes to one entry, tick **Keep my edits** in its Starter commentary box. The whole behaviour can be switched off under Appearance → TBE → General.


## Writing commentary
Commentary → Add New. Fill the **Passage** box (for example `John 1:1-18`, `Psalm 23`, `Jude 5`), choose one or more **Sections** (Textual Criticism, Critical Commentary, Islamic Perspective, Reflection), and write the entry in the block editor. Entries appear in a box under the verse they begin at, with a ✦ marker beside it.

- Passage `John 3` (no verses) makes a **chapter introduction**; a bare book name such as `John` makes a **book introduction**.
- Use the **Footnote** block for notes; they pop up on hover.
- Add **Textual variants** (unit, reading, witnesses, notes) and **Qur'an cross-references** (`2:255`, `112:1-4`, `19`) in the boxes below the editor.
- Glossary: Glossary → Add New. Give a short **Excerpt** (shown in the tooltip) and optional spellings/abbreviations. The theme adds 13 starter terms as **drafts** for you to review and publish.

## Refreshing the ESV text
```bash
ESV_API_TOKEN=... python3 tools/fetch_esv.py --delay 15 --max-delay 30 --max 100
```
Existing chapter files are skipped, so delete a file to re-fetch it. The script is deliberately slow and stops immediately if the API reports a rate limit.

## Licence note
The ESV text is © Crossway. The copyright notice shown in the footer is editable under ESV and licence; use the exact wording your Crossway permission requires.

## Fonts

**Bible text only** is set in the SBL fonts in `assets/fonts/`: SBL BibLit for the ESV, SBL Hebrew for the Hebrew words and SBL Greek for the Greek words, via `assets/css/scripture.css`. The site name ("The Bible Exposed", in the header and on the home page) is set in **Angel** by JuliaL, a freeware font; its notes are kept in `assets/fonts/LICENSE-ANGEL-FONT.txt` and it is credited, with a link back to Tangled in Destiny, on the Credits page. Everything else (commentary, headings, menus, buttons) uses system fonts.

**Every font in the theme is WOFF2.** They are lossless conversions of the original TTFs, made with `node tools/ttf2woff2.js in.ttf out.woff2` (identical glyphs, much smaller). The site owner holds the SBL / Tiro Typeworks licence; its text is kept in `assets/fonts/LICENSE-SBL-FONTS.txt`.

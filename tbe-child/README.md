# TBE — child theme

Child theme of **Twenty Twenty-Five** for https://thebible.exposed. Requires WordPress 6.6+ and PHP 8.0+.

## Install
1. Install and keep **Twenty Twenty-Five** (the parent) on the site.
2. Zip the `tbe-child/` folder and upload it under Appearance → Themes → Add New, then activate it.
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
| `tools/ttf2woff2.js` | Dependency-free lossless TTF to WOFF2 converter |

## Settings (Appearance → TBE)
Tabbed: **General** (tagline, Bismillah band, light/dark toggle), **Appearance** (three colour schemes or fully custom colours), **Reader and commentary** (text size, verse numbers, section names), **ESV and licence** (token status, local-text count, live fallback, copyright notice).

## How a chapter is laid out
Modelled on the verse-by-verse commentary book format, with web-only improvements:
- **Chapter opener**: chapter number, book title, an optional **chapter introduction**, and a clickable list of the notes in the chapter. Book pages show a **book introduction**.
- **Verse by verse**: each verse is its own line (switch to continuous paragraphs with one click). A **commentary box** sits directly under the verse it discusses; a ✦ beside the verse opens it and lights up every verse it covers.
- **Footnotes pop up on hover** instead of sending you to the back of the page.
- **Scripture references in commentary** (`John 3:16`, `Rom 8:28`, `Qur'an 112:1-4`, `Q 2:255`) become links with a hover card showing the ESV verse; Qur'an references open quran.com.
- **Glossary terms** are underlined once per note with a tooltip and a link to the term page. The Bible text itself is never annotated.
- **Textual variants table** and **Qur'an cross-references** (validated against the 114 surahs) can be attached to any note.
- **Index of commentary** at `/bible/index/` (by passage, theme and section), **glossary** at `/glossary/`, **search** at `/bible/search/`.

## Writing commentary
Commentary → Add New. Fill the **Passage** box (for example `John 1:1-18`, `Psalm 23`, `Jude 5`), choose one or more **Sections** (Textual Criticism, Critical Commentary, Islamic Perspective, Reflection), and write the entry in the block editor. Entries appear in a box under the verse they begin at, with a ✦ marker beside it.

- Passage `John 3` (no verses) makes a **chapter introduction**; a bare book name such as `John` makes a **book introduction**.
- Use the **Footnote** block for notes; they pop up on hover.
- Add **Textual variants** (unit, reading, witnesses, notes) and **Qur'an cross-references** (`2:255`, `112:1-4`, `19`) in the boxes below the editor.
- Glossary: Glossary → Add New. Give a short **Excerpt** (shown in the tooltip) and optional spellings/abbreviations. The theme adds 13 starter terms as **drafts** for you to review and publish.

## Languages
A header switcher offers English, Bahasa Melayu and Arabic (right-to-left). The choice is kept in a cookie. Scripture stays English (ESV) in all three. Translations live in `languages/` (visitor-facing strings only; admin screens stay English). To change a translation, edit `tools/build_translations.py`, then run `python3 tools/build_translations.py`. If you use a page cache, make it vary by the `tbe_lang` cookie. The Malay and Arabic text is a first draft and should be reviewed by a native speaker.

## Refreshing the ESV text
```bash
ESV_API_TOKEN=... python3 tools/fetch_esv.py --delay 15 --max-delay 30 --max 100
```
Existing chapter files are skipped, so delete a file to re-fetch it. The script is deliberately slow and stops immediately if the API reports a rate limit.

## Licence note
The ESV text is © Crossway. The copyright notice shown in the footer is editable under ESV and licence; use the exact wording your Crossway permission requires.

## Fonts
**Bible text only** is set in the SBL fonts in `assets/fonts/` (SBL BibLit for English, SBL Hebrew, SBL Greek), via `assets/css/scripture.css`. This covers the ESV in the reader and any Quote block given the *Scripture* style. Everything else uses system fonts: an old-style serif for prose, and Amiri / Scheherazade New / system Naskh for the Bismillah.

The fonts are lossless WOFF2 conversions of the original SBL TTFs, made with `node tools/ttf2woff2.js in.ttf out.woff2` (about a quarter of the size, identical glyphs). The site owner holds the SBL / Tiro Typeworks licence; its text is kept in `assets/fonts/LICENSE-SBL-FONTS.txt`.

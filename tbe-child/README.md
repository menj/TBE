# The Bible, Exposed — child theme

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
| `tools/fetch_esv.py` | Downloads the ESV text slowly and resumably (default one request per ~8 s; stops on any rate-limit reply; `--max N` per run) |
| `tools/ttf2woff2.js` | Dependency-free lossless TTF to WOFF2 converter |

## Settings (Appearance → Bible Exposed)
Tabbed: **General** (tagline, Bismillah band, light/dark toggle), **Appearance** (three colour schemes or fully custom colours), **Reader and commentary** (text size, verse numbers, section names), **ESV and licence** (token status, local-text count, live fallback, copyright notice).

## Writing commentary
Commentary → Add New. Fill the **Passage** box (for example `John 1:1-18`, `Psalm 23`, `Jude 5`), choose one or more **Sections** (Textual Criticism, Critical Commentary, Islamic Perspective, Reflection), and write the entry in the block editor. Entries appear under the chapter in the reader with a ✦ marker beside the verse they begin at.

## Refreshing the ESV text
```bash
ESV_API_TOKEN=... python3 tools/fetch_esv.py --delay 8 --max 100
```
Existing chapter files are skipped, so delete a file to re-fetch it. The script is deliberately slow and stops immediately if the API reports a rate limit.

## Licence note
The ESV text is © Crossway. The copyright notice shown in the footer is editable under ESV and licence; use the exact wording your Crossway permission requires.

## Fonts
**Bible text only** is set in the SBL fonts in `assets/fonts/` (SBL BibLit for English, SBL Hebrew, SBL Greek), via `assets/css/scripture.css`. This covers the ESV in the reader and any Quote block given the *Scripture* style. Everything else uses system fonts: an old-style serif for prose, and Amiri / Scheherazade New / system Naskh for the Bismillah.

The fonts are lossless WOFF2 conversions of the original SBL TTFs, made with `node tools/ttf2woff2.js in.ttf out.woff2` (about a quarter of the size, identical glyphs). The site owner holds the SBL / Tiro Typeworks licence; its text is kept in `assets/fonts/LICENSE-SBL-FONTS.txt`.

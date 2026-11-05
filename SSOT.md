# Single source of truth

The fixed facts and the rules of the TBE theme. When README.md, readme.txt, CHANGELOG.md, UPGRADING.md, a comment or a test disagrees with this file, this file is right and the other one gets corrected in the same change. Keep it short and factual. How things work belongs in README.md; what a site owner needs belongs in readme.txt.

## Identity

| Fact | Value |
|---|---|
| Site name | The Bible Exposed |
| Domain | https://thebible.exposed |
| Theme name | TBE (`Theme Name:` in `style.css`) |
| Theme folder | `TBE`, upper case, on the live server. A folder named `tbe` works too. No code names the folder: it is read at run time (`get_stylesheet_directory()`, `get_stylesheet_directory_uri()`, `TBE_DIR`, `TBE_URI`). |
| Author in `style.css` | MENJ |
| Author for schema | Mohd Elfie Nieshaem Juferi, https://menj.bio (`tbe_ld_author_node()`) |
| Text domain | `tbe-child` |
| Repository | https://github.com/menj/TBE, branch `main` |
| Parent theme | Twenty Twenty-Five (`twentytwentyfive`). It must stay installed. |
| Version | `Version:` in `style.css`, `TBE_VERSION` in `functions.php`, `Stable tag:` in `readme.txt` and the top heading of `CHANGELOG.md`, always equal |
| Requires | WordPress 6.7, PHP 8.0, with the `dom` and `mbstring` extensions |
| Licence | GNU General Public License v3 or later (owner decision, 5 November 2026), full text in `LICENSE`. Fonts, texts, data and images keep their own licences. |

## Purpose and position

- The site reads the Bible critically: textual criticism, critical commentary and reflection, from an Islamic-leaning position.
- The Bible text is the ESV, shown as published. The commentary is the site's own and is filed under the verses it discusses, so a reader can check every note against the text.
- The Hebrew and Greek are shown word by word, so a claim about the original language can be checked too.
- The commentary quotes scripture only from the bundled ESV.

## Owner decisions

`.claude/CLAUDE.md` records no owner decisions yet. The ones below come from the code and the changelog, with the date each was made. A new one goes into `.claude/CLAUDE.md` first, then here word for word.

- 4 November 2026: the structured data names Mohd Elfie Nieshaem Juferi as the author of every entry, whoever the WordPress user is. Nothing visible changes (`tbe_ld_person()`, `tools/test_schema_author.php`).
- 5 November 2026: the live theme folder is `TBE`, and nothing in the theme may assume `tbe` (0.10.6).
- 5 November 2026: every theme is licensed under the GNU General Public License, version 3 or later (0.10.9). This one came in the owner's brief for this session and is not yet in `.claude/CLAUDE.md`; the owner adds it there, and this line is then its copy.

- 5 November 2026: the owner asked for a complete favicon set: "make sure you have favicon too." Every brand icon is drawn from `assets/img/brand/favicon.svg`, and a Site Icon set by the owner always wins (0.10.11). This came in the owner's brief for this session and is not yet in `.claude/CLAUDE.md`.

## Content facts

- The ESV is bundled: 66 books, 1,189 chapter files in `data/esv/{book}/{chapter}.json`. The live API fallback needs `TBE_ESV_API_TOKEN` in `wp-config.php`. The token is never committed, stored in the database or shown in the admin.
- The Hebrew Old Testament and the Greek New Testament, word by word, come from STEPBible (CC BY 4.0) in `data/interlinear/`.
- Recorded readings: Genesis 1 (Hebrew) and Matthew 1 (Greek) only.
- Dead Sea Scrolls comparison: Isaiah 1 to 5 against 1QIsa-a only. The CACCHT source data (CC BY-NC 4.0) is never stored in the theme, only the comparison in `data/dss/`.
- JPS 1917 parallel text: Genesis only (`data/jps/`).
- Starter commentary packs in `data/commentary/`: `combat-kit` (70 entries) and `cross-references` (32 cross-references, each filed on both passages, so 64 entries). Every entry key starts with its pack id.
- Glossary: 13 starter terms, created as drafts (`tbe_seed_terms()`).
- Canons in `data/canons.json`: Jewish, Protestant, Catholic, Orthodox and Ethiopian. The deuterocanonical, further Orthodox and Ethiopian books are prepared sections marked "Not on the site yet". The Ethiopian list is still to be confirmed.
- The brand mark is a broken inverted cross. It follows Sahih al-Bukhari 2476, cited on the Credits page.

## Addresses

- Reader: `/bible/`, `/bible/{book}/`, `/bible/{book}/{chapter}/`, `/bible/{book}/{chapter}/interlinear/`, `/bible/interlinear/`, `/bible/index/`, `/bible/search/` and `/credits/` (`inc/routes.php`).
- Commentary: post type `tbe_commentary` at `/commentary/`, its sections at `/section/`. Glossary: post type `tbe_term` at `/glossary/`.
- Search: `/search/{term}/`. The base is set under Settings, Permalinks (option `tbe_search_base`).
- Sitemap: WordPress's `/wp-sitemap.xml`, unless an SEO plugin serves its own. `robots.txt` names the live sitemap. There is no users sitemap.
- Error pages: the drop-ins in `wp-content/` and the static copies in `wp-content/tbe-errors/`.

## Names that must not change

- Options: `tbe_settings` (every setting on Appearance, TBE), `tbe_search_base`, `tbe_search_rules_base`, `tbe_unlisted`, `tbe_rewrite_version`, `tbe_dropins`, `tbe_dev_files_version`, `tbe_dev_files_lock`, `tbe_glossary_seeded`, `tbe_sections_seeded`, `tbe_themes_to_tags`, `tbe_unlisted_src`, and the `tbe_pack_sync_*` family.
- Post meta: `_tbe_unlisted`, `tbe_takeaways`, `tbe_reviewed`.
- Post types and taxonomy: `tbe_commentary`, `tbe_term`, the `section` rewrite slug.
- Constants a site sets in `wp-config.php`: `TBE_ESV_API_TOKEN`, `TBE_KEEP_DOTFILES`, `TBE_ALLOW_XMLRPC`.
- Filters: `tbe_core_sitemaps`, `tbe_plugin_sitemap_url`, `tbe_login_plugin_active`, `tbe_search_permalinks_enabled`, `tbe_search_reserved_bases`, `tbe_social_meta`, `tbe_social_networks`, `tbe_unlist_enabled`, `tbe_contextual_links_map`, `tbe_contextual_links_enabled`.
- Block and shortcode: `tbe/social`, `[tbe_social]`. The commentary page blocks: `tbe/breadcrumbs`, `tbe/dek`, `tbe/article-meta`, `tbe/answer-box`, `tbe/contents`, `tbe/sources`, `tbe/next-step`, `tbe/related`. The block style `is-style-faq` on `core/details` and the pattern `tbe/faq`.
- Design tokens in `assets/css/base.css`: `--tbe-paper`, `--tbe-surface`, `--tbe-ink`, `--tbe-muted`, `--tbe-accent`, `--tbe-rubric`, `--tbe-gold`, `--tbe-line`, `--tbe-scale`, `--tbe-ui`, and the radius tokens in `assets/css/polish.css`.
- The default scheme: paper `#f4eedc`, ink `#231d14`, accent (burgundy) `#6e1f2e`, gold `#a67c2e`.

## Credits that must stay

- The ESV copyright line (Crossway), editable under ESV and licence but never removed.
- STEPBible and Tyndale House Cambridge for the interlinear data.
- Sefaria for the JPS 1917 text, and CACCHT (ETCBC, Martin Abegg's data) for the scroll comparison.
- The licences in `assets/fonts/` for SBL, Angel and Noto Naskh Arabic, and the Angel credit with its link on the Credits page.
- The Minimalist Social Icons Pack 2.8 for the profile icons.
- The source of the critical-commentary pack, on the Credits and references page and nowhere else.

## Contributor rules

- Never remove a feature. A rename comes with a redirect or a fallback.
- Prose in the five documents, admin strings and commit messages is plain, direct English: no contractions, no em dashes, none of the banned words and constructions of the `anti-ai-writing-style` skill, short paragraphs.
- Never describe the site as related to other sites. Links to other sites are fine.
- The commentary page shows no byline: the author is named in the structured data only. A next step is only ever keep reading, check the sources or ask a question; never a price, a buy button or "get started" wording (0.10.8).
- `FAQPage` goes in the graph only for questions shown on the page, from Details blocks in the FAQ style.
- "Discourage search engines" under Settings, Reading is the owner's setting. The theme keeps the sitemap open either way and says on the dashboard when the box is ticked; it never changes the setting (0.10.6).
- The title tags the theme builds (the reader views, the login screen and the error pages) read `<page> | The Bible Exposed` and stay under 60 characters. That leaves 32 characters for the page part. A new view keeps the same rule.
- Every theme stylesheet and script goes through `tbe_enqueue_theme_style()` or `tbe_enqueue_theme_script()`, every font through `tbe_font_faces()`, every brand image through `tbe_brand_url()`. Each address carries a hash of its own file, and a missing file is left out. No stylesheet carries an `@font-face` rule of its own.
- The theme writes no `.htaccess` outside its own folder. Lines for the site's `.htaccess` are shown on Appearance, TBE, Error pages.
- Error pages come from `inc/error-page-html.php`. After a change, run `php tools/build_error_pages.php` and commit `errors/`.
- Every PHP script in `tools/` exits at once unless PHP runs it from the command line.
- A local or development site keeps `.git`, `.claude` and `tools/`. A live site loses them on the first request after an update (`inc/dev-files.php`), unless `TBE_KEEP_DOTFILES` is set.

## Release checklist

1. The version is the same in `style.css`, `functions.php`, `readme.txt` (Stable tag and its changelog) and `CHANGELOG.md`.
2. `CHANGELOG.md` has the new entry, and `readme.txt` has the short form.
3. `UPGRADING.md` has a note if the site owner must do anything, and its roadmap is updated.
4. `README.md` covers any new hook, filter, constant, setting, file or rule; this file covers any new fixed fact or owner decision.
5. Every path named in a document exists.
6. Every `tools/test_*.php` passes, `php -l` passes on each changed PHP file, and `php tools/build_error_pages.php --check` passes.

## Documents

| File | For | Served on the live site |
|---|---|---|
| `readme.txt` | Site owners, in plain words | Yes, in WordPress's readme format |
| `README.md` | Developers and hosts | No |
| `SSOT.md` | The fixed facts and rules (this file) | No |
| `CHANGELOG.md` | Everyone | No |
| `UPGRADING.md` | Site owners and developers: upgrade notes and the roadmap | No |

The theme's `.htaccess` refuses every `*.md` file, in any letter case, and every dot file and folder (`.claude/` included) except `.well-known`, which stays open for certificate checks. The documents ship in the theme folder; `.claude/` is `export-ignore` in `.gitattributes`. `.claude/skills/theme-docs/SKILL.md` is the standard the five documents follow.

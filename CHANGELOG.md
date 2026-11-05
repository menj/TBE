# Changelog

## 0.10.5

Fixes from the live logs of the sister sites. Nothing was renamed or removed.

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

- A drop-in another theme left behind, marked "Written by the … theme; safe to delete", is now replaced when the theme installs its own. It used to be detected and kept, so a site that switched from a sibling theme went on showing that theme's pages. A file with no such mark is still never touched. The Error pages tab says "written by another theme: press the button to replace it".
- A 504 page (gateway timeout), `errors/504.html`, in the same design as the 502 page, with its own three wordings. The `.htaccess` lines in the tab and the README gain `ErrorDocument 504`.
- The README and the static pages named the theme folder `TBE`. The folder is `tbe` (the install steps say so, and the tab builds its lines from the real folder), so the README's `ErrorDocument` paths and the font address in `errors/*.html` now read `/wp-content/themes/tbe/`.
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

Two fixes to the search base, from the review of the same code on injil.help.

- A page whose address is the base now refuses it, as on injil.help, and the fallback candidates are checked the same way, so a page at `/search/` keeps its child paths.
- When `search`, `find` and `search-results` are all taken, the fallback numbers the last (`search-results-2`, `search-results-3` and on) until one is free.
- `tools/test_plugin_features.php` checks both.

## 0.8.1

Fixes to the search base from the review of the same code on injil.help. Nothing was renamed or removed.

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

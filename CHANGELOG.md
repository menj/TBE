# Changelog

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

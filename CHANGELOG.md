# Changelog

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

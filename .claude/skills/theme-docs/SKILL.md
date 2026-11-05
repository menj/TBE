---
name: theme-docs
description: The documentation standard for the owner's WordPress themes. Use it whenever a theme is created, released, or changed in a way that affects features, settings, paths, constants, hooks, security rules or upgrade steps. It defines the five base documents every theme carries (readme.txt, README.md, SSOT.md, CHANGELOG.md, UPGRADING.md), who each one is for, what each must contain, and the checks to run before a release.
---

# Theme documentation standard

Every theme carries five base documents. Each has one audience and one job. Keep them in step with the code on every release.

The reference implementation is the injil.help theme (menj/injil.help), which keeps all five at the theme root. Read it when in doubt.

## The five documents

| File | Audience | Job |
|---|---|---|
| `readme.txt` | Site owners and lay readers | What the theme does and how to use it, in plain words |
| `README.md` | Web developers, hosts, coders | How the theme works and how to build, test, deploy and extend it |
| `SSOT.md` | Anyone changing the theme, people or agents | The fixed facts and rules. It wins over every other file on matters of fact |
| `CHANGELOG.md` | Everyone | What changed in each version, newest first |
| `UPGRADING.md` | Site owners and developers | How to move from one version to the next, and where the theme is going next |

## Where they live

- Keep each theme's existing locations. Some themes keep the docs at the root, others in `docs/` or `doc/`. Settings pages, tests, deny rules and links point at those paths, so never move a file without updating every reference (`git grep` for the path first).
- A new theme puts all five at the root, as injil.help does.
- `readme.txt` stays where WordPress and the theme's admin expect it.

## What each one must contain

### readme.txt (plain)
- The standard WordPress headers (`=== Name ===`, Contributors, Requires at least, Tested up to, Requires PHP, Stable tag, License), with Stable tag equal to the version in `style.css`.
- **Description:** what the theme is for, in two or three short paragraphs.
- **Installation and updating:** written for someone using FTP or the WordPress uploader.
  - Show hidden files, so `.htaccess` files go up.
  - Upload to a temporary folder, then rename it into place.
  - Never upload WordPress core without `wp-config.php` in place.
- **Settings:** each settings tab and what it does.
- **Frequently Asked Questions.**
- **Changelog:** a short version, with the full one in CHANGELOG.md.
- **Upgrade Notice.**
- **Credits.**
- No code, no hook names, no file maps.

### README.md (technical)
- **At a glance:** the version, requirements and hosting notes (Apache or Nginx, PHP version, multisite).
- **Install, build, test and release commands.**
- **Architecture:** the boot sequence, a file map, templates, parts, patterns and blocks.
- **Settings:** option names and where each tab's code lives.
- **Hooks and filters, and constants:** every `*_KEEP_*`, `*_ALLOW_*` and `*_BLOCK_*` constant and what it switches.
- **Security:**
  - the upload guard;
  - REST users hidden;
  - XML-RPC;
  - the theme `.htaccess` and deny files;
  - the synced `.htaccess` blocks, with the exact lines and their Nginx equivalents;
  - the production dev-file cleanup.
- **Error pages:** the drop-ins, the static pages, the per-site copies, ErrorDocument lines, and multisite behaviour.
- **SEO and structured data**, and **Performance.**

### SSOT.md (fixed facts)
- **Identity:** site name, domain, theme folder name (with its exact case on the live server), author for schema, text domain.
- **Purpose and position.**
- **Owner decisions:** copied word for word from `.claude/CLAUDE.md`, with the date each was made.
- **Contributor rules:**
  - banned words;
  - the title format `<page> | <site name>` under 60 characters;
  - the writing style;
  - the release checklist below.
- **Design tokens, data files, options and filters that must not change.**
- **Credits that must stay.**

### CHANGELOG.md
- **One heading per version, newest first** (`## 1.2.3`), with the date if the theme records dates.
- **Grouped entries where useful:** Added, Changed, Fixed, Security.
- **Each entry says what changed for the site,** not how the code was edited.
- **The top version matches** `style.css`, `readme.txt` (Stable tag), `functions.php` (the version constant) and any `files.json` or manifest.

### UPGRADING.md (upgrade notes and roadmap)
- **Updating an existing site:** the general steps, then one note per version that needs action ("To 1.2.3: paste the new lines from the Error pages tab").
- **Roadmap, in three groups:**
  - **Next:** work with a clear reason and a known shape.
  - **Later:** wanted, but waiting on something.
  - **Ideas:** worth weighing.
- **Each roadmap item gets one line on why.** Ground every item in evidence:
  - open GitHub issues and PRs;
  - TODOs in the code;
  - documented gaps and limits;
  - findings from the server logs;
  - features the other themes have that this one lacks.
- **Mark the roadmap as plans, not promises.** Remove items once they ship, and record them in CHANGELOG.md.

## Rules for every document

- **Write all English prose to the `anti-ai-writing-style` skill:**
  - no contractions;
  - no em dashes;
  - no banned words or negative parallelisms;
  - short paragraphs.
- **Respect each repo's own rules in `.claude/CLAUDE.md` and `SSOT.md`,** such as banned words, names that must not appear, and title limits.
- **Never announce publicly that the sites are related, sister sites or a network.** Cross-links are fine.
- **Facts must match the code on main.** If a document and the code disagree, fix the document or the code in the same change.
- **Packaging:** the docs ship in the theme zip, but the theme `.htaccess` refuses `*.md` on the live site, except files the settings pages link to on purpose. `.claude/` is always `export-ignore`.

## Licence

Every theme is licensed under the GNU General Public License, version 3 or later. The owner set this on 2026-11-05.
- `style.css` carries `License: GNU General Public License v3 or later` and `License URI: https://www.gnu.org/licenses/gpl-3.0.html`.
- `readme.txt` carries the same two lines in its header.
- A `LICENSE` file at the theme root holds the full, unmodified GPLv3 text from https://www.gnu.org/licenses/gpl-3.0.txt.
- `README.md` and `SSOT.md` state the licence in one line each.
- Third-party fonts, images and libraries keep their own notices in the credits.

## Release checklist

Before merging a change:
1. The version is the same in `style.css`, `readme.txt` (Stable tag and changelog), `functions.php`, `CHANGELOG.md` and any manifest.
2. `CHANGELOG.md` has the new entry, and `readme.txt` has the short form.
3. `UPGRADING.md` has a note if the site owner must do anything, and the roadmap is updated: shipped items removed, new gaps added.
4. `README.md` covers any new hook, filter, constant, setting, file or rule.
5. `SSOT.md` covers any new fixed fact or owner decision.
6. Every path named in a document exists (`git grep` or `ls`).
7. The licence lines match the Licence section above.
8. The repo's own checks pass (tests, the style linter, and banned-word greps).

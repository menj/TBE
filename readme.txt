=== TBE ===
Contributors: menj
Tags: blog, one-column, custom-colors, editor-style, full-site-editing, block-styles
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.10.24
Template: twentytwentyfive
License: GNU General Public License v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Child theme of Twenty Twenty-Five, built for one site: The Bible Exposed at thebible.exposed.

== Description ==

TBE turns a WordPress site into a Bible reader with commentary. It carries the whole ESV, the Hebrew Old Testament and the Greek New Testament word by word, and a commentary you write in the block editor, filed under the verses it discusses.

What a visitor gets:

* The 66 books of the ESV, one verse per line, at /bible/.
* A word-by-word Hebrew or Greek page for every chapter, with gloss, transliteration, Strong's number and grammar.
* Commentary boxes under the verses they discuss, with footnotes that pop up on hover.
* A glossary, an index of the commentary and a passage search.
* Recorded readings of Genesis 1 (Hebrew) and Matthew 1 (Greek), with each word lit as it is read.
* A Christian and Jewish side-by-side view for Genesis (ESV and JPS 1917), and Dead Sea Scroll readings for Isaiah 1 to 5.
* A lexicon at /lexicon/: every Hebrew and Greek word by its Strong's number, with its meaning, how often it is used and where, and Metzger's list of Greek words by how often they occur. Each Strong's number on the word-by-word pages opens its entry.
* A Manuscripts section at /manuscripts/: twenty-two places where the Greek manuscripts disagree, most of them passages that the King James Version prints and the earliest Greek manuscripts leave out, such as the long ending of Mark and the woman caught in adultery, with each manuscript's own words line by line.
* Three colour schemes, or your own colours. The site is light only.

The theme is made for thebible.exposed and is not meant for other sites.

== Installation ==

1. Install Twenty Twenty-Five first, and keep it. TBE does not work without it.
2. If you use FTP, turn on "show hidden files" in your FTP program first (in FileZilla: Server, Force showing hidden files), so the .htaccess files go up with the rest.
3. Upload the theme folder to wp-content/themes/. Name it TBE, as on the live site.
4. Activate TBE under Appearance, Themes.
5. Go to Settings, Permalinks and press Save once. Do not use "Plain" permalinks.
6. Optional: the theme can fetch ESV text live from Crossway as a fallback. That needs an ESV API token in the site's configuration file; ask your developer, or see README.md.

On activation the theme publishes its starter commentary and adds 13 glossary terms as drafts for you to check.

== Updating ==

1. Turn on "show hidden files" in your FTP program.
2. Upload the new version to a temporary folder next to the old one, for example wp-content/themes/TBE-new. Send its .htaccess files first: the one at the top of the folder, then the one in tools. On Apache they close the theme's private files to visitors while the rest arrives. On Nginx, which ignores them, add the server lines from README.md before the first FTP update.
3. When every file is up, rename the old folder (for example to TBE-old) and, straight after, rename TBE-new to TBE. The site switches over in a moment. Keep the admin closed from before the first rename until the second is done: while no folder is named TBE, an admin page can switch the site to another theme.
4. Open any admin screen once. The theme refreshes its error pages and settles in.
5. Read UPGRADING.md in the theme folder for anything a version asks you to do by hand. Delete TBE-old when all is well.

The temporary folder and rename is the safe way. Uploading straight over the old folder is riskier. While other theme files are still missing, visitors see a short "back shortly" page and you can still log in. But functions.php holds that safety check itself, so while functions.php is half written, the theme cannot show that page: visitors get a plain PHP error from the server instead. If you do upload over the old folder, send functions.php last.

Never upload a fresh copy of WordPress itself without the site's wp-config.php in place, or the site starts its installer.

== Settings ==

Everything lives under Appearance, TBE:

* General: the tagline, structured data and whether starter commentary follows theme updates.
* Appearance: the colour scheme, or your own colours.
* Reader and commentary: text size, verse numbers and section names.
* Commentary pages: the contents list (in the page, or a bar that stays at the top), the "In short" box, the next step at the end, related entries, an optional "Ask a question" page, and links from known phrases to the pages that treat them.
* Reading bar: the thin bar that shows how far down a page you are.
* Profile links: the addresses of the site's social profiles, shown in the footer.
* ESV and licence: the token status, the live fallback and the copyright line.
* Login screen: the site's look on the login page.
* Starter commentary: import the ready-made notes again, as drafts.
* Error pages: the site's own pages for errors, and lines to paste into .htaccess.

== Writing commentary ==

Go to Commentary, Add New. Type the passage (for example John 1:1-18), pick one or more sections, and write. The note shows under the first verse of the passage, with a mark beside the verse.

A passage with no verses, such as John 3, makes a chapter introduction. A book name alone, such as John, makes a book introduction.

Each entry's own page shows, in order: the breadcrumb, the title, the excerpt under it, the dates and reading time, the "In short" box, the contents, the text, the notes and sources, the next step and related entries on the same book. Fill the Key points and review box beside the editor for the "In short" list and the "Last reviewed" date. An entry with three or more sections (level-two headings) gets a contents list.

For questions and answers, insert the "Questions and answers" pattern: each question is a Details block in the FAQ style. Search engines are told about the questions only when they are on the page.

== Search engines ==

If Settings, Reading, "Discourage search engines from indexing this site" is ticked, every page tells search engines to stay away. The dashboard reminds you while it is ticked. Untick it when the site should be found. WordPress's own sitemap at /wp-sitemap.xml stays open either way. While an SEO plugin serves its own sitemap, the sitemap is at the plugin's address instead.

== Frequently Asked Questions ==

= /bible/ shows "page not found". =
Go to Settings, Permalinks and press Save.

= Can I use an SEO plugin? =
Yes. While Yoast SEO, Rank Math, All in One SEO, SEOPress or The SEO Framework serves a sitemap, the theme uses that one.

= Where do the error pages come from? =
Appearance, TBE, Error pages writes them into wp-content, and gives you a few lines to paste into the site's .htaccess file so the web server uses them too.

= A visitor saw "Back shortly" during an update. =
That is the theme waiting for the rest of its files. It goes away by itself once the upload finishes.

= Can I turn the reading bar or the login design off? =
Yes, on the Reading bar and Login screen tabs.

= My site is in a subfolder. Where does the browser icon go? =

Browsers ask for /favicon.ico at the top of the domain, such as example.com/favicon.ico, even when the site lives in example.com/blog/. WordPress never sees that request, so put a copy of the theme's favicon.ico in the domain root folder. The icon tags in each page cover the pages either way. If you have set a Site Icon, put that icon there, converted to an ICO file named favicon.ico, instead.

== Credits ==

TBE is distributed under the GNU General Public License v3 or later. The full text is in the LICENSE file.

* The ESV text is copyright Crossway, used by permission. The copyright line in the footer is set under ESV and licence.
* The Hebrew and Greek word-by-word data is from STEPBible (www.STEPBible.org), CC BY 4.0, based on work at Tyndale House Cambridge.
* The JPS 1917 translation is in the public domain and comes through Sefaria.
* The Dead Sea Scroll comparison is built from the CACCHT project's data (ETCBC, after Martin Abegg), CC BY-NC 4.0.
* The Manuscripts pages are built from the transcriptions of the early Greek manuscripts by Alan Bunning and the Center for New Testament Restoration, CC BY-SA 4.0, and share that licence. Their English wording is from the public-domain KJV, World English Bible and Berean Standard Bible. The lexicon's definitions are from STEP Bible's brief lexicons (CC BY 4.0), and its Greek frequency list from Bruce M. Metzger's Lexical Aids for Students of New Testament Greek. The manuscript photographs come from Wikimedia Commons, each credited under the picture. The evidence of later manuscripts comes from the United Bible Societies Greek New Testament and from Reuben J. Swanson's New Testament Greek Manuscripts: Matthew, used by the owner's permission.
* Fonts: SBL BibLit, SBL Hebrew and SBL Greek under the SBL and Tiro Typeworks licence; Angel, freeware by JuliaL; Noto Naskh Arabic, Abyssinica SIL and Montserrat under the SIL Open Font License.
* Profile icons: the Minimalist Social Icons Pack 2.8, GPL.

== Changelog ==

= 0.10.24 =
* A fourth starter commentary pack, "How the canon was formed", with four notes: Luke 24:44, Jude 14 to 15, Hebrews 1:3 and Revelation 22:18 to 19, after Samuel Davidson's history of the canon (1877). The canon comparison page and the 2 Peter note cite his pages. Nothing removed, nothing renamed.

= 0.10.23 =
* The TBE settings page has a new look: a header with the version, tabs with icons that scroll on a phone, cards, switches for on and off options, sliders that show their value, and cards for choosing between styles. Every option, name and saved value is unchanged.

= 0.10.22 =
* Accessibility pass (WCAG 2.2 AA): the interlinear words outside the verse keep readable text, and tooltips stay open while the pointer rests on them. Nothing removed, nothing renamed.

= 0.10.21 =
* Phone layout pass: long words and web addresses wrap, wide tables and code blocks in posts scroll inside their own box, embeds scale with the column, and small links are easier to tap. Larger screens look the same.

= 0.10.20 =
* On the home page the menu is centred above the cross and the name, with no empty space beside it.

= 0.10.19 =
* The home page shows the cross and the name once, in the hero, and its search box is full width again.

= 0.10.18 =
* A lexicon page for every Strong's number, with Metzger's frequency list for Greek.
* New commentary on how the gospels rework one another and on how the New Testament came together.
* A new Manuscripts section shows, manuscript by manuscript, which early Greek copies have twenty-two disputed passages, with their own text, photographs of the pages, and the evidence of later manuscripts from two printed editions. A chapter that holds one says so under its heading.

= 0.10.17 =
* The Credits page is now Sources, at /sources/, with a Resources section; the old address redirects.

= 0.10.16 =
* A chapter page lists thematic essays that share a tag with the notes on it.

= 0.10.15 =
* The site is light only; the light and dark switch is off by default.
* People who may edit can edit a commentary note, or add one for a chapter, from the front of the site in a drawer that uses WordPress's own block editor.

= 0.10.14 =
* The Ethiopian books show the church's own names and short notes, credited on the Credits page.

= 0.10.13 =
* The canon lists are checked and completed, and a new section names the early Christian writings once read as scripture.

= 0.10.12 =
* The readme says where the browser icon goes on a site in a subfolder: a copy of favicon.ico in the domain root.

= 0.10.11 =
* The browser icon set is complete: a 192px icon, a maskable icon and a web app manifest join the brand icons, and the touch icon sits on a solid background. The icons show on the login screen and in the admin too, and the error pages show the mark in the tab. A Site Icon you set still wins.

= 0.10.10 =
* The FTP update steps send the theme's .htaccess files first, so its private files stay closed while the rest uploads.

= 0.10.9 =
* The theme is now licensed under the GNU General Public License, version 3 or later. The full text is in the LICENSE file.

= 0.10.8 =
* Each commentary entry's page gains a breadcrumb, the excerpt under the title, the dates and reading time, an "In short" box, a contents list, a notes and sources section, a next step ("Read the passage") and related entries on the same book.
* Tables in an entry scroll on a phone and carry their caption. A FAQ style for Details blocks, with a pattern.
* Known phrases in an entry link to the pages that treat them. A new Commentary pages tab sets all of this.

= 0.10.7 =
* The theme's documents are complete: this readme, the facts file, the upgrade notes and the roadmap.

= 0.10.6 =
* The theme reads its own folder name, so the TBE folder works everywhere.
* Stylesheets, scripts and fonts no longer go missing for visitors while an upload is running.
* The sitemap answers even while search engines are discouraged, and robots.txt names it.
* Scanner requests for files such as .env or phpinfo.php get a tiny "not found" reply.

The full history is in CHANGELOG.md.

== Upgrade Notice ==

= 0.10.24 =
A new starter commentary pack is available under Appearance, TBE, Starter commentary. Nothing else to do by hand.

= 0.10.23 =
Settings page redesign only. Nothing to do by hand.

= 0.10.22 =
Nothing to do by hand.

= 0.10.21 =
Nothing to do by hand.

= 0.10.20 =
Nothing to do by hand.

= 0.10.19 =
Nothing to do by hand.

= 0.10.18 =
Nothing to do by hand. The first page load after the update adds the /manuscripts/ addresses.

= 0.10.17 =
Nothing to do by hand. The first page load after the update refreshes the address rules.

= 0.10.16 =
Nothing to do by hand.

= 0.10.15 =
Nothing to do by hand.

= 0.10.14 =
Nothing to do by hand.

= 0.10.13 =
Nothing to do by hand.

= 0.10.12 =
Upload the whole theme folder. On a site in a subfolder, also put a copy of assets/img/brand/favicon.ico in the domain root folder (or, with a Site Icon set, that icon converted to an ICO file named favicon.ico); otherwise nothing to do.

= 0.10.11 =
Nothing to do by hand. The next admin page writes the error pages again with the icon.

= 0.10.10 =
Nothing to do by hand. The FTP update steps now send the .htaccess files first.

= 0.10.9 =
Nothing to do by hand. The licence is now GPL version 3 or later.

= 0.10.8 =
Nothing to do by hand. If you saved the commentary template in the Site Editor, reset it to get the new layout.

= 0.10.7 =
Nothing to do by hand.

= 0.10.6 =
If your .htaccess has error-page lines that point into the theme folder, replace them with the lines on Appearance, TBE, Error pages. Untick "Discourage search engines" under Settings, Reading when the site should be found.

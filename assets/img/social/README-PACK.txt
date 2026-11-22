MINIMALIST SOCIAL & PLATFORM ICONS PACK
========================================

41 single-color, minimalist icons for social media platforms, music
platforms, and identity/library authority services. Every icon is a
clean monochrome glyph with no gradients, shadows, or extra detail —
just the mark.

INCLUDED (44 — every icon has both SVG and PNG)
--------------------------------------------------
Facebook, Instagram, X (Twitter), LinkedIn, YouTube, TikTok,
Pinterest, Snapchat, WhatsApp, Telegram, Reddit, Discord, Threads,
WeChat, Tumblr, Twitch, Vimeo, Medium, Behance, Dribbble, Mastodon,
Bluesky, LINE, Signal, GitHub, Spotify, Goodreads, Wikidata,
Wikipedia, Academia.edu, Quora, Issuu, Substack, Flickr, Scribd,
GTribe, WordPress, WordPress.org Profile, SoundCloud, Suno, ORCID,
OCLC, VIAF, ISNI, Fiverr

Every file in this pack now has a vector (svg/black, svg/white) and
a raster (png/black, png/white) version — no PNG-only exceptions.

NEWLY ADDED IN THIS MERGE (8)
------------------------------
Every one of these was checked against an actual source file, not
assumed from the platform name. Several went through corrections
before landing here — full history below for anyone who wants it,
but the short version: all 8 are now verified against either the
supplied favicon or (for Suno and ISNI) the brand's own official
logo file, fetched directly after the favicons turned out to be too
low-res to trace with real confidence.

Confirmed as accurate matches to the supplied source, sourced from
CC0 (public-domain) icon sets and re-fitted to this pack's 24x24
viewBox / single-path / black-and-white convention:
  - WordPress   (svg/png, black+white) — matches profiles_wordpress_org.ico
  - SoundCloud  (svg/png, black+white) — matches soundcloud.ico
  - ORCID       (svg/png, black+white) — matches ORCID.ico
  - OCLC        (svg/png, black+white) — matches oclc-entities.ico
                (three interlocking rings, same structure as source)

Did NOT match the supplied source, so traced/rebuilt instead of
using the general-brand icon:
  - Fiverr — the CC0 icon set's mark is the black "fiverr." wordmark,
             but the supplied fiverr.ico is a different Fiverr asset:
             a green circular badge with a white "fh" ligature (the
             monogram used as their favicon/app icon). Isolated the
             ligature and traced it to vector — mark-only, no
             circle, matching this pack's convention for every other
             badge-style logo (Spotify, LINE, etc. also drop their
             colour/background the same way). First trace used only
             the 16x16 favicon, which gave a blocky, staircase-edged
             result — the 'f' hook should be a smooth curve, not a
             right-angle step. Retraced from a clean 980x980
             reference of the same mark; the curve is now correct.
  - Suno    — the CC0 icon set's mark is Suno's current abstract
             logo (two joined crescents), which turned out to be
             unrelated to the "SUNO" wordmark in the supplied
             favicon. Suno rebranded on 1 July 2024, so this may
             just be an old favicon that never got updated. Resolved
             properly rather than guessed: fetched Suno's own official
             2024 logo file (Suno_Logo_2024.svg) and extracted the
             real wordmark paths directly — no tracing or
             reconstruction involved, this is the actual brand
             vector, letter-for-letter. (First extraction attempt
             concatenated the four letter paths into one path string
             and broke their positioning — S, U, N, O need to stay
             as separate <path> elements since each one's original
             coordinates assume its own fresh starting point. Fixed
             and re-verified against the source file directly.)

Not available in any open icon set at all, so hand-built (same
approach as the two corrections above) and traced to vector:
  - GTribe — was PNG-only in the previous version of this pack (the
             trace kept clipping); retraced from the existing
             512x512 PNG silhouette and now ships as gtribe.svg in
             both black and white, matching every other icon. This
             one's a genuine trace of the original artwork, not a
             redraw — the source PNG was clean and high-res.
  - VIAF   — the first version substituted a plain bold sans
             wordmark instead of matching the actual logo — an
             error, not an intentional simplification. Re-examined
             at full resolution (48x48): the real mark is a bold
             italic serif "VI" over "AF" in a tight 2x2 letter grid,
             right-aligned. Rebuilt to match that structure and
             traced to vector.
  - ISNI   — same resolution problem as Suno: the only source was a
             16x16 favicon, too low-res to confirm real letterforms,
             so earlier versions were best-effort reconstructions,
             not verified matches. Resolved the same way: fetched
             ISNI's own official logo file (isni-logo.png, 2665x1613)
             directly from isni.org and traced it properly this time.
             Turned out the real logo isn't a white wordmark on a
             solid badge at all — it's a solid indigo badge with the
             letters cut out as transparent holes. The first trace
             attempt didn't account for that and picked up the
             badge's rounded corners as stray artifacts (the corner
             transparency and the letter-hole transparency looked
             identical to a plain threshold). Fixed by isolating
             which transparent regions are actually enclosed by the
             badge (the letters) versus connected to the outside
             (the corners), then traced only the enclosed shapes.
             Also lowercase "isni", not "ISNI" — the real wordmark is
             lowercase throughout, which no earlier version had
             right either. This is now a genuine trace, not a
             reconstruction.

NOT MERGED
----------
(nothing outstanding — see the correction note below for
profiles_wordpress_org.ico)

CORRECTION
----------
profiles_wordpress_org.ico is the icon for profiles.wordpress.org
(the WordPress.org user-profile directory) — a distinct service
from wordpress.svg/png (the general WordPress platform mark), even
though both use the same "W" glyph. It's now its own file,
wordpress-profile.svg/png, rather than folded into wordpress.svg.
Use whichever matches what you're actually linking to.

NOT INCLUDED (from the original pack)
--------------------------------------
- Open Library — no open, GPL-compatible source mark was found for
  it. The "open.png" sign sent originally didn't look like it was
  meant to represent this (no book/library imagery), so it wasn't
  added.
- Google Play Books — only a generic "Google Play" storefront icon
  was available, not a Books-specific mark.

FOLDERS
-------
svg/black/   vector icons, black fill      -> for light backgrounds
svg/white/   vector icons, white fill      -> for dark backgrounds
png/black/   512x512 raster, black, transparent background
png/white/   512x512 raster, white, transparent background

Use the SVGs wherever you can — they're crisp at any size, and the
files are only ~0.3-2KB each, so a full social row costs almost
nothing in page weight (good for Core Web Vitals / LCP). The PNGs
are there as a fallback for tools or builders that don't accept SVG.

USAGE
-----
- Files are named after the platform (e.g. instagram.svg,
  instagram.png) so you can drop them straight into a footer or
  "follow us" block.
- To recolor an SVG: open it in a text editor and change the
  fill="#..." value, or inline the <svg> in your HTML and set
  `fill: currentColor` to control it from CSS.

LICENSE
-------
This pack — the compiled set, the 24x24 single-path/black-white
convention, and every redrawn or traced mark (GTribe, Fiverr, Suno,
VIAF, ISNI) — is released under the GNU General Public License
(GPL). 36 of the 43 icons started from CC0 (public-domain) source
material, which carries no attribution requirement and imposes no
restriction on relicensing the combined work under GPL. The
remaining 7 (GTribe, Fiverr, Suno, VIAF, ISNI, plus the two Font
Awesome exceptions below) are simple text/glyph marks traced or
redrawn from the brand's own logo file, below the threshold most
jurisdictions require for copyright protection on a logo of that
simplicity — the same reasoning under which Wikimedia Commons hosts
the ISNI wordmark itself as public domain.

EXCEPTION — LinkedIn and Scribd:
These two icons originate from Font Awesome Free, licensed CC BY
4.0. That license's attribution condition is a term of the license
itself, not a courtesy — it can't be dropped and these two files
can't be relicensed under GPL without Font Awesome's terms
following them. So, for linkedin.svg/png and scribd.svg/png only:
  Icons by Font Awesome (https://fontawesome.com), CC BY 4.0.
Keep that credit somewhere on the site (a footer or credits page
covering just those two files is enough) if you use them. Everything
else in the pack is unaffected.

The brand names and logos themselves remain trademarks of their
respective companies regardless of the icon file's license. Using
them to link to your own official profiles (a "follow us" row) is
standard, low-risk practice — just don't imply endorsement or
affiliation beyond that.

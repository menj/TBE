<?php
/**
 * Self-test for the five base documents (.claude/skills/theme-docs/SKILL.md). Run: php tools/test_docs.php
 *
 * Checks that readme.txt, README.md, SSOT.md, CHANGELOG.md and UPGRADING.md exist; that the version is the same in
 * style.css, functions.php, the readme.txt Stable tag and changelog, the top of CHANGELOG.md and README.md's "At a
 * glance"; that the five carry no em dash and no contraction; that the theme's .htaccess refuses Markdown in any
 * letter case and every dot folder; and that .claude/ is export-ignore. Exit code 0 on success.
 *
 * @package tbe-child
 */

// Run from the command line only: on a web server this file does nothing.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

$root = dirname( __DIR__ );
$fail = 0;
function check( $ok, $label ) {
	global $fail;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	$fail += $ok ? 0 : 1;
}
$read = static fn( string $f ): string => is_readable( "$root/$f" ) ? (string) file_get_contents( "$root/$f" ) : '';

$docs = array( 'readme.txt', 'README.md', 'SSOT.md', 'CHANGELOG.md', 'UPGRADING.md' );
foreach ( $docs as $doc ) {
	check( '' !== $read( $doc ), "$doc is there" );
}

preg_match( '/^Version:\s*(\S+)/m', $read( 'style.css' ), $m );
$ver = $m[1] ?? '';
check( '' !== $ver, "style.css names the version ($ver)" );
check( false !== strpos( $read( 'functions.php' ), "define( 'TBE_VERSION', '$ver' );" ), 'TBE_VERSION matches' );
check( 1 === preg_match( '/^Stable tag:\s*' . preg_quote( $ver, '/' ) . '\s*$/m', $read( 'readme.txt' ) ), 'the readme.txt Stable tag matches' );
check( 1 === preg_match( '/== Changelog ==\s+= ' . preg_quote( $ver, '/' ) . ' =/', $read( 'readme.txt' ) ), 'the readme.txt changelog starts with it' );
check( 1 === preg_match( '/^## (\S+)/m', $read( 'CHANGELOG.md' ), $c ) && $ver === $c[1], 'CHANGELOG.md starts with it' );
check( false !== strpos( $read( 'README.md' ), "- Version $ver." ), 'README.md "At a glance" names it' );
check( false !== strpos( $read( 'UPGRADING.md' ), "### To $ver" ), 'UPGRADING.md has a note for it' );

// Prose rules. Code blocks and inline code are left out; a possessive 's is no contraction.
foreach ( $docs as $doc ) {
	$text = preg_replace( '/```.*?```/s', '', $read( $doc ) );
	$text = preg_replace( '/`[^`\n]*`/', '', (string) $text );
	check( false === strpos( (string) $text, "\u{2014}" ), "$doc has no em dash" );
	preg_match_all( "/\\b[A-Za-z]+['\u{2019}](t|re|ve|ll|d|m)\\b|\\b(it|that|there|here|what|who|let|he|she)['\u{2019}]s\\b/i", (string) $text, $hits );
	check( array() === $hits[0], "$doc has no contraction (" . implode( ', ', array_unique( $hits[0] ) ) . ')' );
}

$ht = $read( '.htaccess' );
check( false !== strpos( $ht, '<FilesMatch "(?i)\.md$">' ), 'the theme .htaccess refuses Markdown in any letter case' );
check( false !== strpos( $ht, 'RedirectMatch 403 /\.(?!well-known(/|$))[^/]+(/|$)' ), 'and every dot file and folder, .claude/ among them' );
// readme.txt stays open: no FilesMatch, RedirectMatch or RewriteRule pattern in the file matches it.
$denies = 0;
preg_match_all( '~^\s*<FilesMatch "([^"]+)">~m', $ht, $fm );
foreach ( $fm[1] as $rx ) {
	$denies += preg_match( '~' . str_replace( '~', '\~', $rx ) . '~', 'readme.txt' );
}
preg_match_all( '~^\s*RedirectMatch 403 (\S+)~m', $ht, $rm );
foreach ( $rm[1] as $rx ) {
	$denies += preg_match( '~' . str_replace( '~', '\~', $rx ) . '~', '/wp-content/themes/TBE/readme.txt' );
}
preg_match_all( '~^\s*RewriteRule (\S+) - \[F~m', $ht, $rr );
foreach ( $rr[1] as $rx ) {
	$denies += preg_match( '~' . str_replace( '~', '\~', $rx ) . '~', 'readme.txt' );
}
check( count( $fm[1] ) > 0 && count( $rm[1] ) > 0 && 0 === $denies, 'no rule in the theme .htaccess refuses readme.txt (' . ( count( $fm[1] ) + count( $rm[1] ) + count( $rr[1] ) ) . ' patterns checked)' );
// And the same patterns do refuse the Markdown documents and .claude/.
$md = 0;
foreach ( $fm[1] as $rx ) {
	$md += preg_match( '~' . str_replace( '~', '\~', $rx ) . '~', 'SSOT.md' ) + preg_match( '~' . str_replace( '~', '\~', $rx ) . '~', 'x.MD' );
}
$claude = 0;
foreach ( $rm[1] as $rx ) {
	$claude += preg_match( '~' . str_replace( '~', '\~', $rx ) . '~', '/wp-content/themes/TBE/.claude/CLAUDE.md' );
}
check( $md >= 2 && $claude >= 1, 'the same patterns refuse SSOT.md, x.MD and .claude/' );
check( 1 === preg_match( '~^/\.claude export-ignore$~m', $read( '.gitattributes' ) ), '.claude/ is export-ignore' );
check( '' !== $read( '.claude/skills/theme-docs/SKILL.md' ) && false !== strpos( $read( '.claude/CLAUDE.md' ), '.claude/skills/theme-docs/SKILL.md' ), 'the standard and the pointer to it are there' );

echo $fail ? "$fail failed\n" : "all passed\n";
exit( $fail ? 1 : 0 );

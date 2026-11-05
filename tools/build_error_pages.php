<?php
/**
 * Write the static error pages in errors/ (400, 401, 403, 404, 500, 502, 503, 504) from inc/error-page-html.php.
 * These are the theme's own copies: links from the domain root, and the font named relative to errors/, since the
 * theme folder may be tbe or TBE. The theme writes a copy for the site's own addresses and its real theme folder
 * into wp-content/tbe-errors/, and the ErrorDocument lines on the Error pages tab name those.
 * Usage: php tools/build_error_pages.php (add --check to fail when a page is stale)
 */

// Run from the command line only: on a web server this file does nothing.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}
define( 'ABSPATH', __DIR__ . '/' );
require __DIR__ . '/../inc/error-page-html.php';

$check = in_array( '--check', $argv, true );
$stale = 0;
$dir   = __DIR__ . '/../errors';
if ( ! $check && ! is_dir( $dir ) ) {
	mkdir( $dir, 0755, true );
}
foreach ( array( '400', '401', '403', '404', '500', '502', '503', '504' ) as $code ) {
	$html = tbe_error_page_html( $code ) . "\n";
	if ( $check ) {
		if ( ! is_readable( "$dir/$code.html" ) || file_get_contents( "$dir/$code.html" ) !== $html ) {
			fwrite( STDERR, "stale: errors/$code.html\n" );
			++$stale;
		}
		continue;
	}
	file_put_contents( $dir . '/' . $code . '.html', $html );
	echo $code, ".html\n";
}
exit( $stale ? 1 : 0 );

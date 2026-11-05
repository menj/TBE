<?php
/**
 * Write the static error pages in errors/ (400, 401, 403, 404, 500, 502, 503, 504) from inc/error-page-html.php.
 * Usage: php tools/build_error_pages.php
 */
define( 'ABSPATH', __DIR__ . '/' );
require __DIR__ . '/../inc/error-page-html.php';

$dir = __DIR__ . '/../errors';
if ( ! is_dir( $dir ) ) {
	mkdir( $dir, 0755, true );
}
foreach ( array( '400', '401', '403', '404', '500', '502', '503', '504' ) as $code ) {
	file_put_contents( $dir . '/' . $code . '.html', tbe_error_page_html( $code ) . "\n" );
	echo $code, ".html\n";
}

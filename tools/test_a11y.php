<?php
/**
 * Accessibility guards (WCAG 2.2 AA). Static checks on the styles and scripts the audit fixed.
 * The browser audit is tools/axe-audit.js. Run: php tools/test_a11y.php
 *
 * @package tbe-child
 */

// Run from the command line only: on a web server this file does nothing.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

$root = dirname( __DIR__ );
$fail = 0;
$check = static function ( $name, $ok ) use ( &$fail ) {
	if ( ! $ok ) {
		++$fail;
		echo "FAIL $name\n";
	}
};

$il  = (string) file_get_contents( $root . '/assets/css/interlinear.css' );
$tt  = (string) file_get_contents( $root . '/assets/js/tooltips.js' );
$cmp = (string) file_get_contents( $root . '/assets/css/components.css' );

$check( 'outside words are not dimmed with opacity (1.4.3)', ! preg_match( '/\.tbe-il__w--outside\s*\{[^}]*opacity/', $il ) );
$check( 'the tooltip can be hovered (1.4.13)', (bool) preg_match( '/\.tbe-tooltip\s*\{[^}]*pointer-events:\s*auto/', $cmp ) );
$check( 'the tooltip stays while the pointer is on it (1.4.13)', false !== strpos( $tt, 'closeSoon' ) && false !== strpos( $tt, 'cancelClose' ) );
$check( 'Escape dismisses the tooltip (1.4.13)', false !== strpos( $tt, "'Escape' === event.key" ) );

foreach ( glob( $root . '/assets/css/*.css' ) as $file ) {
	preg_match_all( '/([^{}]*:focus(?!-visible)[^{}]*)\{([^}]*outline:\s*(?:none|0)\s*;[^}]*)\}/', (string) file_get_contents( $file ), $m, PREG_SET_ORDER );
	foreach ( $m as $rule ) {
		if ( ! preg_match( '/box-shadow|border/', $rule[2] ) ) {
			$check( basename( $file ) . ': ' . trim( $rule[1] ) . ' removes the outline with no replacement', false );
		}
	}
}

echo $fail ? "$fail failed\n" : "test_a11y: all passed\n";
exit( $fail ? 1 : 0 );

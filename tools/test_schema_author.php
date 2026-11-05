<?php
/**
 * Self-test for the author in the structured data (inc/schema.php).
 * Run: php tools/test_schema_author.php
 *
 * The owner ruled on 4 November 2026 that the structured data names him as
 * the author of every entry. The test checks the author Person (name, url,
 * sameAs with https://menj.bio, a stable @id), that every author value in a
 * graph points to it, that every article and page node has one, that the
 * Person appears once, and that the publisher stays the site.
 * Exit code 0 on success.
 *
 * @package tbe-child
 */

// Run from the command line only: on a web server this file does nothing.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );

function add_action( $hook, $cb, $p = 10, $a = 1 ) {}
function add_filter( $hook, $cb, $p = 10, $a = 1 ) {}
function home_url( $path = '/' ) { return 'https://tbe.test' . $path; }

require dirname( __DIR__ ) . '/inc/schema.php';

$fail  = 0;
$check = static function ( $ok, $msg ) use ( &$fail ) {
	if ( ! $ok ) {
		echo "FAIL: $msg\n";
		$fail++;
	}
};

$person = tbe_ld_author_node();
$check( 'https://tbe.test/#author' === $person['@id'], 'author @id' );
$check( 'Mohd Elfie Nieshaem Juferi' === $person['name'], 'author name' );
$check( 'https://menj.bio' === $person['url'], 'author url' );
$check( in_array( 'https://menj.bio', $person['sameAs'], true ), 'author sameAs includes https://menj.bio' );
$check( array( '@id' => 'https://tbe.test/#author' ) === tbe_ld_person( 7 ), 'an entry author is a reference to the Person' );

$graph = tbe_ld_one_author(
	array(
		array( '@type' => 'Organization', '@id' => 'https://tbe.test/#organization', 'name' => 'TBE' ),
		array( '@type' => 'ItemPage', '@id' => 'https://tbe.test/x/#webpage', 'url' => 'https://tbe.test/x/' ),
		array( '@type' => 'Article', '@id' => 'https://tbe.test/x/#article', 'author' => array( '@type' => 'Person', 'name' => 'admin' ), 'publisher' => array( '@id' => 'https://tbe.test/#organization' ), 'hasPart' => array( array( '@type' => 'Article', 'author' => array( '@id' => 'https://tbe.test/author/admin/' ) ) ) ),
	)
);

$people = array_values( array_filter( $graph, static fn( $n ) => 'Person' === ( $n['@type'] ?? '' ) ) );
$check( 1 === count( $people ), 'one Person in the graph' );
$check( 'Mohd Elfie Nieshaem Juferi' === ( $people[0]['name'] ?? '' ), 'the Person is the author' );

$authors = array();
$walk    = static function ( $n ) use ( &$walk, &$authors ) {
	foreach ( (array) $n as $k => $v ) {
		if ( 'author' === $k ) {
			$authors[] = $v;
		} elseif ( is_array( $v ) ) {
			$walk( $v );
		}
	}
};
$walk( $graph );
$check( 3 === count( $authors ), 'page, article and nested article have an author' );
foreach ( $authors as $a ) {
	$check( array( '@id' => 'https://tbe.test/#author' ) === $a, 'author points to #author: ' . json_encode( $a ) );
}
$check( array( '@id' => 'https://tbe.test/#organization' ) === $graph[2]['publisher'], 'publisher stays the site' );
$check( ! isset( $graph[0]['author'] ), 'the Organization gets no author' );

$again = tbe_ld_one_author( $graph );
$check( 1 === count( array_filter( $again, static fn( $n ) => 'Person' === ( $n['@type'] ?? '' ) ) ), 'a second pass adds no second Person' );

echo $fail ? "$fail failure(s)\n" : "schema author: all checks passed\n";
exit( $fail ? 1 : 0 );

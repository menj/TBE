<?php
/**
 * Optional live fallback to the ESV API.
 *
 * The token is never stored in the theme or the database. Define it in wp-config.php:
 *     define( 'TBE_ESV_API_TOKEN', '...' );
 * or provide an ESV_API_TOKEN environment variable.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

final class TBE_ESV_API {

	public static function token(): string {
		if ( defined( 'TBE_ESV_API_TOKEN' ) && TBE_ESV_API_TOKEN ) {
			return (string) TBE_ESV_API_TOKEN;
		}
		$env = getenv( 'ESV_API_TOKEN' );
		return $env ? (string) $env : '';
	}

	/** Where the token was found, for the settings screen (never the token itself). */
	public static function token_source(): string {
		if ( defined( 'TBE_ESV_API_TOKEN' ) && TBE_ESV_API_TOKEN ) {
			return 'wp-config.php';
		}
		return getenv( 'ESV_API_TOKEN' ) ? 'environment' : '';
	}

	/** @return array<int,array{n:int,t:string,p?:int}>|null */
	public static function chapter( string $book_name, int $chapter ): ?array {
		$token = self::token();
		if ( '' === $token ) {
			return null;
		}
		$key    = 'tbe_esv_' . md5( $book_name . $chapter );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$url = add_query_arg(
			array(
				'q'                                => $book_name . ' ' . $chapter,
				'include-passage-references'       => 'false',
				'include-verse-numbers'            => 'true',
				'include-first-verse-numbers'      => 'true',
				'include-footnotes'                => 'false',
				'include-footnote-body'            => 'false',
				'include-headings'                 => 'false',
				'include-short-copyright'          => 'false',
				'include-copyright'                => 'false',
				'include-passage-horizontal-lines' => 'false',
				'include-heading-horizontal-lines' => 'false',
				'indent-using'                     => 'space',
				'indent-paragraphs'                => 0,
				'indent-poetry-lines'              => 4,
				'line-length'                      => 0,
			),
			'https://api.esv.org/v3/passage/text/'
		);
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 10,
				'headers' => array( 'Authorization' => 'Token ' . $token ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		$verses = isset( $body['passages'][0] ) ? self::parse( (string) $body['passages'][0] ) : array();
		if ( ! $verses ) {
			return null;
		}
		set_transient( $key, $verses, 30 * DAY_IN_SECONDS );
		return $verses;
	}

	/** Same format as the bundled JSON produced by tools/fetch_esv.py. */
	public static function parse( string $text ): array {
		$parts  = preg_split( '/\[(\d+)\]\s*/', str_replace( "\r", '', $text ), -1, PREG_SPLIT_DELIM_CAPTURE );
		$verses = array();
		for ( $i = 1, $n = count( $parts ); $i < $n; $i += 2 ) {
			$raw   = $parts[ $i + 1 ] ?? '';
			$lines = array_filter( array_map( 'rtrim', explode( "\n", rtrim( $raw ) ) ), static fn( $l ) => '' !== trim( $l ) );
			$verse = array(
				'n' => (int) $parts[ $i ],
				't' => trim( implode( "\n", $lines ), "\n" ),
			);
			if ( substr( rtrim( $raw, ' ' ), -2 ) === "\n\n" ) {
				$verse['p'] = 1;
			}
			$verses[] = $verse;
		}
		return $verses;
	}
}

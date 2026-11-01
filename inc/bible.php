<?php
/**
 * Bible data access: books, chapters, URLs and reference parsing.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

final class TBE_Bible {

	/** @var array<string,array>|null */
	private static $books = null;

	/** @var array<string,string>|null */
	private static $aliases = null;

	/** @return array<string,array> Books keyed by slug, in canonical order. */
	public static function books(): array {
		if ( null === self::$books ) {
			self::$books = array();
			$file        = TBE_DIR . '/data/books.json';
			$list        = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : array();
			foreach ( (array) $list as $book ) {
				self::$books[ $book['slug'] ] = $book;
			}
		}
		return self::$books;
	}

	public static function book( string $slug ): ?array {
		return self::books()[ $slug ] ?? null;
	}

	/** One JPS 1917 chapter in the Hebrew numbering the text is stored in. @return array<int,string> */
	private static function jps_raw( string $slug, int $chapter ): array {
		$file = TBE_DIR . '/data/jps/' . $slug . '/' . $chapter . '.json';
		if ( ! is_readable( $file ) ) {
			return array();
		}
		$data = json_decode( (string) file_get_contents( $file ), true );
		$out  = array();
		foreach ( (array) ( $data['verses'] ?? array() ) as $verse ) {
			$out[ (int) $verse['n'] ] = (string) $verse['t'];
		}
		return $out;
	}

	/**
	 * The JPS 1917 text for an ESV chapter, keyed by the ESV's verse numbers: the JPS follows the Hebrew
	 * numbering, which differs in places (Genesis 31:55 is 32:1 in Hebrew, and Genesis 32 runs one ahead),
	 * so data/jps-versification.json says how to line the two up. Empty until the book has been added.
	 *
	 * @return array<int,string>
	 */
	public static function jps( string $slug, int $chapter ): array {
		static $map = null;
		if ( null === $map ) {
			$file = TBE_DIR . '/data/jps-versification.json';
			$map  = is_readable( $file ) ? (array) json_decode( (string) file_get_contents( $file ), true ) : array();
		}
		$rules = (array) ( $map[ $slug ][ (string) $chapter ] ?? array() );
		$own   = self::jps_raw( $slug, $chapter );
		if ( isset( $rules['shift'] ) ) {
			$shifted = array();
			foreach ( $own as $n => $text ) {
				if ( $n - (int) $rules['shift'] >= 1 ) { // verse 1 of the Hebrew chapter belongs to the previous English chapter
					$shifted[ $n - (int) $rules['shift'] ] = $text;
				}
			}
			$own = $shifted;
		}
		foreach ( $rules as $verse => $from ) {
			if ( is_string( $from ) && preg_match( '/^(\d+):(\d+)$/', $from, $m ) ) {
				$other = self::jps_raw( $slug, (int) $m[1] );
				if ( isset( $other[ (int) $m[2] ] ) ) {
					$own[ (int) $verse ] = $other[ (int) $m[2] ];
				}
			}
		}
		ksort( $own );
		return $own;
	}

	/** The Jewish (Hebrew) name of an Old Testament book: array( 'he' => Hebrew, 'tr' => transliteration ), or null. */
	public static function jewish_name( string $slug ): ?array {
		static $names = null;
		if ( null === $names ) {
			$file  = TBE_DIR . '/data/jewish-names.json';
			$names = is_readable( $file ) ? (array) json_decode( (string) file_get_contents( $file ), true ) : array();
		}
		return isset( $names[ $slug ] ) ? $names[ $slug ] : null;
	}

	public static function total_chapters(): int {
		return (int) array_sum( array_column( self::books(), 'chapters' ) );
	}

	public static function local_chapter_count(): int {
		$count = 0;
		foreach ( glob( TBE_DIR . '/data/esv/*/*.json' ) ?: array() as $unused ) {
			++$count;
		}
		return $count;
	}

	/**
	 * Load a chapter: bundled JSON first, then (optionally) the live ESV API.
	 *
	 * @return array<int,array{n:int,t:string,p?:int}>|null
	 */
	public static function chapter( string $slug, int $chapter ): ?array {
		$book = self::book( $slug );
		if ( ! $book || $chapter < 1 || $chapter > $book['chapters'] ) {
			return null;
		}
		$file = TBE_DIR . '/data/esv/' . $slug . '/' . $chapter . '.json';
		if ( is_readable( $file ) ) {
			$data = json_decode( (string) file_get_contents( $file ), true );
			if ( ! empty( $data['verses'] ) ) {
				return $data['verses'];
			}
		}
		if ( tbe_setting( 'esv_live_fallback' ) ) {
			return TBE_ESV_API::chapter( $book['name'], $chapter );
		}
		return null;
	}

	/** Pretty URL for the book index, a book, a chapter or a verse. */
	public static function url( ?string $slug = null, ?int $chapter = null, ?int $verse = null ): string {
		$url = home_url( '/bible/' );
		if ( $slug ) {
			$url .= $slug . '/';
			if ( $chapter ) {
				$url .= $chapter . '/';
				if ( $verse ) {
					$url .= '#v' . $verse;
				}
			}
		}
		return $url;
	}

	/** Previous / next chapter, crossing book boundaries. */
	public static function neighbours( string $slug, int $chapter ): array {
		$slugs = array_keys( self::books() );
		$i     = array_search( $slug, $slugs, true );
		$book  = self::book( $slug );
		$prev  = null;
		$next  = null;
		if ( $chapter > 1 ) {
			$prev = array( $slug, $chapter - 1 );
		} elseif ( $i > 0 ) {
			$pb   = self::book( $slugs[ $i - 1 ] );
			$prev = array( $pb['slug'], $pb['chapters'] );
		}
		if ( $book && $chapter < $book['chapters'] ) {
			$next = array( $slug, $chapter + 1 );
		} elseif ( false !== $i && isset( $slugs[ $i + 1 ] ) ) {
			$next = array( $slugs[ $i + 1 ], 1 );
		}
		return array( $prev, $next );
	}

	/* ---------------------------------------------------------------------
	 * Reference parsing: "John 3:16", "1 Cor 13", "ps 23:1-4", "Jude 5"
	 * ------------------------------------------------------------------ */

	private static function normalise( string $s ): string {
		$s = strtolower( $s );
		$s = preg_replace( '/^(iii)\b\.?\s*/', '3', $s );
		$s = preg_replace( '/^(ii)\b\.?\s*/', '2', $s );
		$s = preg_replace( '/^(i)\b\.?\s*(?=[a-z])/', '1', $s );
		$s = preg_replace( '/^(first|1st)\s*/', '1', $s );
		$s = preg_replace( '/^(second|2nd)\s*/', '2', $s );
		$s = preg_replace( '/^(third|3rd)\s*/', '3', $s );
		return preg_replace( '/[^a-z0-9]/', '', $s );
	}

	private static function aliases(): array {
		if ( null !== self::$aliases ) {
			return self::$aliases;
		}
		$map = array();
		foreach ( self::books() as $slug => $book ) {
			$map[ self::normalise( $book['name'] ) ] = $slug;
			$map[ self::normalise( $slug ) ]         = $slug;
		}
		$extra = array(
			'gen' => 'genesis', 'exod' => 'exodus', 'exo' => 'exodus', 'ex' => 'exodus', 'lev' => 'leviticus',
			'num' => 'numbers', 'deut' => 'deuteronomy', 'deu' => 'deuteronomy', 'dt' => 'deuteronomy',
			'josh' => 'joshua', 'judg' => 'judges', 'jdg' => 'judges', 'rth' => 'ruth',
			'1sam' => '1-samuel', '2sam' => '2-samuel', '1kgs' => '1-kings', '2kgs' => '2-kings',
			'1chr' => '1-chronicles', '2chr' => '2-chronicles', 'neh' => 'nehemiah', 'esth' => 'esther',
			'ps' => 'psalms', 'psa' => 'psalms', 'psalm' => 'psalms', 'pss' => 'psalms', 'prov' => 'proverbs', 'pr' => 'proverbs',
			'eccl' => 'ecclesiastes', 'eccles' => 'ecclesiastes', 'qoheleth' => 'ecclesiastes',
			'song' => 'song-of-solomon', 'songofsongs' => 'song-of-solomon', 'sos' => 'song-of-solomon', 'canticles' => 'song-of-solomon',
			'isa' => 'isaiah', 'jer' => 'jeremiah', 'lam' => 'lamentations', 'ezek' => 'ezekiel', 'eze' => 'ezekiel',
			'dan' => 'daniel', 'hos' => 'hosea', 'obad' => 'obadiah', 'jon' => 'jonah', 'mic' => 'micah',
			'nah' => 'nahum', 'hab' => 'habakkuk', 'zeph' => 'zephaniah', 'hag' => 'haggai', 'zech' => 'zechariah', 'mal' => 'malachi',
			'matt' => 'matthew', 'mt' => 'matthew', 'mk' => 'mark', 'lk' => 'luke', 'jn' => 'john',
			'rom' => 'romans', '1cor' => '1-corinthians', '2cor' => '2-corinthians', 'gal' => 'galatians',
			'eph' => 'ephesians', 'phil' => 'philippians', 'php' => 'philippians', 'col' => 'colossians',
			'1thess' => '1-thessalonians', '2thess' => '2-thessalonians', '1th' => '1-thessalonians', '2th' => '2-thessalonians',
			'1tim' => '1-timothy', '2tim' => '2-timothy', 'tit' => 'titus', 'philem' => 'philemon', 'phlm' => 'philemon',
			'heb' => 'hebrews', 'jas' => 'james', 'jm' => 'james', '1pet' => '1-peter', '2pet' => '2-peter',
			'1jn' => '1-john', '2jn' => '2-john', '3jn' => '3-john', 'rev' => 'revelation', 'apocalypse' => 'revelation',
		);
		foreach ( $extra as $alias => $slug ) {
			$map[ $alias ] = $slug;
		}
		self::$aliases = $map;
		return $map;
	}

	private static function resolve_book( string $name ): ?string {
		$key = self::normalise( $name );
		if ( '' === $key ) {
			return null;
		}
		$aliases = self::aliases();
		if ( isset( $aliases[ $key ] ) ) {
			return $aliases[ $key ];
		}
		if ( strlen( $key ) < 3 ) {
			return null;
		}
		$hits = array();
		foreach ( $aliases as $alias => $slug ) {
			if ( 0 === strpos( $alias, $key ) ) {
				$hits[ $slug ] = true;
			}
		}
		return 1 === count( $hits ) ? (string) array_key_first( $hits ) : null;
	}

	/**
	 * @return array{book:string,chapter:int,vstart:int,vend:int}|null vstart 0 means "whole chapter".
	 */
	public static function parse_reference( string $input ): ?array {
		$input = trim( str_replace( array( '–', '—' ), '-', $input ) );
		if ( ! preg_match( '/^((?:[1-3]|i{1,3}|first|second|third|1st|2nd|3rd)?\.?\s*[a-z][a-z\s\.]*?)\s*(?:(\d+)(?:\s*[:.]\s*(\d+)(?:\s*-\s*(\d+))?)?)?$/i', $input, $m ) ) {
			return null;
		}
		$slug = self::resolve_book( $m[1] );
		$book = $slug ? self::book( $slug ) : null;
		if ( ! $book ) {
			return null;
		}
		$chapter = isset( $m[2] ) && '' !== $m[2] ? (int) $m[2] : 1;
		$vstart  = isset( $m[3] ) && '' !== $m[3] ? (int) $m[3] : 0;
		$vend    = isset( $m[4] ) && '' !== $m[4] ? (int) $m[4] : $vstart;

		// "Jude 5" on a single-chapter book means verse 5.
		if ( 1 === (int) $book['chapters'] && isset( $m[2] ) && ! isset( $m[3] ) && $chapter > 1 ) {
			$vstart  = $chapter;
			$vend    = $chapter;
			$chapter = 1;
		}
		if ( $chapter < 1 || $chapter > $book['chapters'] ) {
			return null;
		}
		if ( $vend < $vstart ) {
			$vend = $vstart;
		}
		return array(
			'book'    => $slug,
			'chapter' => $chapter,
			'vstart'  => $vstart,
			'vend'    => $vend,
		);
	}

	/** Human label, e.g. "John 1:1–18". */
	public static function label( string $slug, int $chapter, int $vstart = 0, int $vend = 0 ): string {
		$book  = self::book( $slug );
		$label = ( $book ? $book['name'] : $slug ) . ' ' . $chapter;
		if ( $vstart ) {
			$label .= ':' . $vstart;
			if ( $vend > $vstart ) {
				$label .= '–' . $vend;
			}
		}
		return $label;
	}
}

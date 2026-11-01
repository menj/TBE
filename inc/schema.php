<?php
/**
 * schema.org structured data (JSON-LD) for every page of the theme.
 *
 * One @graph per page: organisation, website (with site search), the page itself, breadcrumbs,
 * header, footer and navigation, plus the page's real content (the Bible, its books and chapters,
 * verses, commentary notes with their variants tables, Qur'an citations and glossary mentions,
 * glossary terms, search results, interlinear). Every type and property is checked against the
 * official schema.org vocabulary by tools/check_schema.py.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

/** A reference to a node defined elsewhere in the same graph. */
function tbe_ld_ref( string $id ): array {
	return array( '@id' => $id );
}

function tbe_ld_text( string $html ): string {
	return trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $html ) ) );
}

/** Canonical URL of the page being viewed. */
function tbe_ld_current_url(): string {
	global $wp;
	$path = isset( $wp->request ) ? (string) $wp->request : '';
	return trailingslashit( home_url( '/' . $path ) );
}

/* Shared nodes ------------------------------------------------------------ */

function tbe_ld_org_id(): string {
	return home_url( '/#organization' );
}

function tbe_ld_site_id(): string {
	return home_url( '/#website' );
}

function tbe_ld_crossway_id(): string {
	return home_url( '/#crossway' );
}

function tbe_ld_bible_id(): string {
	return home_url( '/bible/#bible' );
}

function tbe_ld_book_id( string $slug ): string {
	return home_url( '/bible/' . $slug . '/#book' );
}

function tbe_ld_chapter_id( string $slug, int $chapter ): string {
	return TBE_Bible::url( $slug, $chapter ) . '#chapter';
}

function tbe_ld_terms_set_id(): string {
	return home_url( '/glossary/#terms' );
}

function tbe_ld_site_nodes(): array {
	$nodes   = array();
	$nodes[] = array(
		'@type' => 'Organization',
		'@id'   => tbe_ld_org_id(),
		'name'  => get_bloginfo( 'name' ),
		'url'   => home_url( '/' ),
	);
	$nodes[] = array(
		'@type'           => 'WebSite',
		'@id'             => tbe_ld_site_id(),
		'url'             => home_url( '/' ),
		'name'            => get_bloginfo( 'name' ),
		'description'     => (string) tbe_setting( 'tagline' ),
		'inLanguage'      => 'en',
		'publisher'       => tbe_ld_ref( tbe_ld_org_id() ),
		'isAccessibleForFree' => true,
		'potentialAction' => array(
			'@type'       => 'SearchAction',
			'target'      => array(
				'@type'       => 'EntryPoint',
				'urlTemplate' => home_url( '/bible/search/?q={search_term_string}' ),
			),
			'query-input' => 'required name=search_term_string',
		),
	);
	$nodes[] = array(
		'@type'  => 'Organization',
		'@id'    => tbe_ld_crossway_id(),
		'name'   => 'Crossway',
		'url'    => 'https://www.crossway.org/',
	);
	return $nodes;
}

/** Header, footer and the main navigation links, as parts of every page. */
function tbe_ld_chrome_nodes( string $page_id ): array {
	$links = array(
		array( 'The Books', home_url( '/bible/' ) ),
		array( 'Commentary', home_url( '/commentary/' ) ),
		array( 'Index', home_url( '/bible/index/' ) ),
		array( 'Index', home_url( '/glossary/' ) ),
	);
	$nodes = array();
	$parts = array();
	foreach ( $links as $i => $link ) {
		$id      = $page_id . '-nav-' . ( $i + 1 );
		$nodes[] = array(
			'@type'    => 'SiteNavigationElement',
			'@id'      => $id,
			'name'     => $link[0],
			'url'      => $link[1],
			'position' => $i + 1,
		);
		$parts[] = tbe_ld_ref( $id );
	}
	$nodes[] = array(
		'@type'       => 'WPHeader',
		'@id'         => $page_id . '-header',
		'cssSelector' => '.tbe-header',
		'hasPart'     => $parts,
	);
	$nodes[] = array(
		'@type'       => 'WPFooter',
		'@id'         => $page_id . '-footer',
		'cssSelector' => '.tbe-footer',
		'copyrightNotice' => tbe_ld_text( (string) tbe_setting( 'copyright' ) ),
	);
	return $nodes;
}

/** BreadcrumbList from [ [name, url], ... ]. */
function tbe_ld_breadcrumb( string $page_id, array $trail ): array {
	$items = array();
	foreach ( $trail as $i => $step ) {
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'name'     => $step[0],
			'item'     => array( '@type' => 'WebPage', '@id' => $step[1], 'name' => $step[0] ),
		);
	}
	return array(
		'@type'           => 'BreadcrumbList',
		'@id'             => $page_id . '-breadcrumb',
		'itemListElement' => $items,
	);
}

/* Bible nodes ------------------------------------------------------------- */

function tbe_ld_bible_node( bool $with_books = false ): array {
	$books = array();
	if ( $with_books ) {
		foreach ( TBE_Bible::books() as $slug => $unused ) {
			$books[] = tbe_ld_ref( tbe_ld_book_id( $slug ) );
		}
	}
	$node = array(
		'@type'             => 'Book',
		'@id'               => tbe_ld_bible_id(),
		'name'              => 'The Holy Bible, English Standard Version',
		'alternateName'     => 'ESV',
		'url'               => home_url( '/bible/' ),
		'inLanguage'        => 'en',
		'genre'             => 'Religious text',
		'publisher'         => tbe_ld_ref( tbe_ld_crossway_id() ),
		'copyrightHolder'   => tbe_ld_ref( tbe_ld_crossway_id() ),
		'copyrightYear'     => 2001,
		'copyrightNotice'   => tbe_ld_text( (string) tbe_setting( 'copyright' ) ),
		'isAccessibleForFree' => true,
		'numberOfPages'     => TBE_Bible::total_chapters(),
	);
	if ( $books ) {
		$node['hasPart'] = $books;
	}
	return $node;
}

function tbe_ld_book_node( array $book, bool $with_chapters = false ): array {
	$node = array(
		'@type'      => 'Book',
		'@id'        => tbe_ld_book_id( $book['slug'] ),
		'name'       => $book['name'],
		'url'        => TBE_Bible::url( $book['slug'] ),
		'position'   => (int) $book['order'],
		'inLanguage' => 'en',
		'genre'      => 'OT' === $book['testament'] ? 'Old Testament' : 'New Testament',
		'isPartOf'   => tbe_ld_ref( tbe_ld_bible_id() ),
		'numberOfPages' => (int) $book['chapters'],
	);
	if ( $with_chapters ) {
		$chapters = array();
		for ( $i = 1; $i <= $book['chapters']; $i++ ) {
			$chapters[] = array(
				'@type'    => 'Chapter',
				'@id'      => tbe_ld_chapter_id( $book['slug'], $i ),
				'name'     => TBE_Bible::label( $book['slug'], $i ),
				'url'      => TBE_Bible::url( $book['slug'], $i ),
				'position' => $i,
				'isPartOf' => tbe_ld_ref( tbe_ld_book_id( $book['slug'] ) ),
			);
		}
		$node['hasPart'] = array_map( static fn( $c ) => tbe_ld_ref( $c['@id'] ), $chapters );
		return array( $node, $chapters );
	}
	return array( $node, array() );
}

function tbe_ld_person( int $user_id ): array {
	return array(
		'@type' => 'Person',
		'name'  => (string) get_the_author_meta( 'display_name', $user_id ),
		'url'   => (string) get_author_posts_url( $user_id ),
	);
}

/** Commentary entry as an Article, with its variants table, Qur'an citations and glossary mentions. */
function tbe_ld_note_nodes( WP_Post $entry, ?string $about_chapter_url = null ): array {
	$id      = get_permalink( $entry ) . '#article';
	$book    = (string) get_post_meta( $entry->ID, 'tbe_book', true );
	$chapter = (int) get_post_meta( $entry->ID, 'tbe_chapter', true );
	$vs      = (int) get_post_meta( $entry->ID, 'tbe_vstart', true );
	$ve      = max( $vs, (int) get_post_meta( $entry->ID, 'tbe_vend', true ) );
	$extra   = array();

	$about = array();
	if ( $book && $chapter && $vs ) {
		$base = TBE_Bible::url( $book, $chapter );
		for ( $v = $vs; $v <= min( $ve, $vs + 40 ); $v++ ) {
			$about[] = tbe_ld_ref( $base . '#v' . $v );
		}
	} elseif ( $book && $chapter ) {
		$about[] = tbe_ld_ref( tbe_ld_chapter_id( $book, $chapter ) );
	} elseif ( $book ) {
		$about[] = tbe_ld_ref( tbe_ld_book_id( $book ) );
	}

	$sections = array();
	$terms    = get_the_terms( $entry, 'tbe_section' );
	if ( is_array( $terms ) ) {
		foreach ( $terms as $term ) {
			$sections[] = $term->name;
		}
	}
	$themes   = array();
	$theme_terms = get_the_terms( $entry, 'post_tag' );
	if ( is_array( $theme_terms ) ) {
		foreach ( $theme_terms as $term ) {
			$themes[] = $term->name;
		}
	}

	$article = array(
		'@type'            => 'Article',
		'@id'              => $id,
		'url'              => get_permalink( $entry ),
		'mainEntityOfPage' => get_permalink( $entry ),
		'headline'         => get_the_title( $entry ),
		'inLanguage'       => 'en',
		'datePublished'    => get_post_time( 'c', true, $entry ),
		'dateModified'     => get_post_modified_time( 'c', true, $entry ),
		'author'           => tbe_ld_person( (int) $entry->post_author ),
		'publisher'        => tbe_ld_ref( tbe_ld_org_id() ),
		'isAccessibleForFree' => true,
	);
	if ( $about ) {
		$article['about'] = $about;
	}
	if ( $sections ) {
		$article['articleSection'] = $sections;
	}
	if ( $themes ) {
		$article['keywords'] = implode( ', ', $themes );
	}
	if ( '' !== trim( (string) $entry->post_excerpt ) ) {
		$article['abstract'] = tbe_ld_text( $entry->post_excerpt );
	}
	if ( $about_chapter_url ) {
		$article['isPartOf'] = tbe_ld_ref( $about_chapter_url );
	}

	// Qur'an cross-references as citations.
	$citations = array();
	foreach ( tbe_get_quran_refs( $entry->ID ) as $row ) {
		$ref = tbe_parse_quran_ref( (string) $row['ref'] );
		if ( $ref ) {
			$citations[] = array(
				'@type'    => 'CreativeWork',
				'name'     => tbe_quran_label( $ref ),
				'url'      => tbe_quran_url( $ref ),
				'isPartOf' => array( '@type' => 'Book', 'name' => 'The Qur\'an' ),
			);
		}
	}
	if ( $citations ) {
		$article['citation'] = $citations;
	}

	// Variants table.
	if ( tbe_get_variants( $entry->ID ) ) {
		$table_id         = $id . '-variants';
		$extra[]          = array(
			'@type'       => 'Table',
			'@id'         => $table_id,
			'name'        => 'Textual variants',
			'cssSelector' => '#note-' . $entry->ID . ' .tbe-table, .single-tbe_commentary .tbe-table',
			'isPartOf'    => tbe_ld_ref( $id ),
		);
		$article['hasPart'] = array( tbe_ld_ref( $table_id ) );
	}

	// Glossary terms mentioned in the note.
	$mentions = array();
	$html     = function_exists( 'tbe_entry_html' ) ? tbe_entry_html( $entry ) : '';
	if ( '' !== $html && preg_match_all( '/class="tbe-term" href="([^"]+)"/', $html, $found ) ) {
		foreach ( array_unique( $found[1] ) as $url ) {
			$mentions[] = tbe_ld_ref( $url . '#term' );
		}
	}
	if ( $mentions ) {
		$article['mentions'] = $mentions;
	}

	array_unshift( $extra, $article );
	return $extra;
}

function tbe_ld_term_node( array $term ): array {
	$node = array(
		'@type'            => 'DefinedTerm',
		'@id'              => $term['url'] . '#term',
		'name'             => $term['title'],
		'description'      => $term['tip'],
		'url'              => $term['url'],
		'inDefinedTermSet' => tbe_ld_ref( tbe_ld_terms_set_id() ),
	);
	$alt = array_values( array_diff( $term['names'], array( $term['title'] ) ) );
	if ( $alt ) {
		$node['alternateName'] = $alt;
	}
	return $node;
}

/** ItemList of posts. @param WP_Post[] $posts */
function tbe_ld_post_list( string $id, array $posts, string $name ): array {
	$items = array();
	foreach ( array_values( $posts ) as $i => $post ) {
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'url'      => get_permalink( $post ),
			'name'     => get_the_title( $post ),
		);
	}
	return array(
		'@type'           => 'ItemList',
		'@id'             => $id,
		'name'            => $name,
		'numberOfItems'   => count( $items ),
		'itemListElement' => $items,
	);
}

/* The graph --------------------------------------------------------------- */

function tbe_ld_graph(): array {
	$url     = tbe_ld_current_url();
	$page_id = $url . '#webpage';
	$request = tbe_request();
	$home    = array( get_bloginfo( 'name' ), home_url( '/' ) );
	$nodes   = tbe_ld_site_nodes();
	$extra   = array();
	$page    = array(
		'@type'              => 'WebPage',
		'@id'                => $page_id,
		'url'                => $url,
		'name'               => wp_get_document_title(),
		'isPartOf'           => tbe_ld_ref( tbe_ld_site_id() ),
		'inLanguage'         => str_replace( '_', '-', strtok( get_locale(), '_' ) ),
		'isAccessibleForFree' => true,
		'breadcrumb'         => tbe_ld_ref( $url . '#webpage-breadcrumb' ),
	);
	$trail = array( $home );

	if ( $request && 'books' === $request['view'] ) {
		$page['@type'] = 'CollectionPage';
		$trail[]       = array( __( 'The Books of the Bible', 'tbe-child' ), home_url( '/bible/' ) );
		$extra[]       = tbe_ld_bible_node( true );
		$items         = array();
		$pos           = 0;
		foreach ( TBE_Bible::books() as $book ) {
			[ $node ] = tbe_ld_book_node( $book );
			$extra[]  = $node;
			$items[]  = array( '@type' => 'ListItem', 'position' => ++$pos, 'item' => tbe_ld_ref( $node['@id'] ) );
		}
		$extra[]            = array( '@type' => 'ItemList', '@id' => $url . '#books', 'name' => 'Books of the Bible', 'numberOfItems' => count( $items ), 'itemListElement' => $items );
		$page['mainEntity'] = tbe_ld_ref( $url . '#books' );
		$page['about']      = tbe_ld_ref( tbe_ld_bible_id() );

	} elseif ( $request && 'book' === $request['view'] ) {
		$book     = $request['book'];
		$trail[]  = array( __( 'Books', 'tbe-child' ), home_url( '/bible/' ) );
		$trail[]  = array( $book['name'], TBE_Bible::url( $book['slug'] ) );
		[ $node, $chapters ] = tbe_ld_book_node( $book, true );
		$extra[]  = tbe_ld_bible_node();
		$extra[]  = $node;
		$extra    = array_merge( $extra, $chapters );
		foreach ( tbe_commentary_for_book( $book['slug'] ) as $entry ) {
			$extra = array_merge( $extra, tbe_ld_note_nodes( $entry ) );
		}
		$page['mainEntity'] = tbe_ld_ref( $node['@id'] );

	} elseif ( $request && in_array( $request['view'], array( 'chapter', 'interlinear' ), true ) ) {
		$book     = $request['book'];
		$chapter  = (int) $request['chapter'];
		$slug     = $book['slug'];
		$cid      = tbe_ld_chapter_id( $slug, $chapter );
		$trail[]  = array( __( 'Books', 'tbe-child' ), home_url( '/bible/' ) );
		$trail[]  = array( $book['name'], TBE_Bible::url( $slug ) );
		$trail[]  = array( TBE_Bible::label( $slug, $chapter ), TBE_Bible::url( $slug, $chapter ) );
		[ $book_node ] = tbe_ld_book_node( $book );
		$extra[]  = tbe_ld_bible_node();
		$extra[]  = $book_node;

		$verse_nodes = array();
		$verse_refs  = array();
		foreach ( (array) TBE_Bible::chapter( $slug, $chapter ) as $verse ) {
			$vid           = TBE_Bible::url( $slug, $chapter ) . '#v' . (int) $verse['n'];
			$verse_refs[]  = tbe_ld_ref( $vid );
			$verse_nodes[] = array(
				'@type'      => 'Quotation',
				'@id'        => $vid,
				'url'        => $vid,
				'position'   => (int) $verse['n'],
				'text'       => preg_replace( '/\s+/u', ' ', (string) $verse['t'] ),
				'inLanguage' => 'en',
				'isPartOf'   => tbe_ld_ref( $cid ),
			);
		}
		$chapter_node = array(
			'@type'           => 'Chapter',
			'@id'             => $cid,
			'name'            => TBE_Bible::label( $slug, $chapter ),
			'url'             => TBE_Bible::url( $slug, $chapter ),
			'position'        => $chapter,
			'inLanguage'      => 'en',
			'isPartOf'        => tbe_ld_ref( tbe_ld_book_id( $slug ) ),
			'copyrightHolder' => tbe_ld_ref( tbe_ld_crossway_id() ),
			'copyrightYear'   => 2001,
			'isAccessibleForFree' => true,
		);
		if ( $verse_refs ) {
			$chapter_node['hasPart'] = $verse_refs;
		}
		$extra[] = $chapter_node;
		$extra   = array_merge( $extra, $verse_nodes );

		if ( 'chapter' === $request['view'] ) {
			$page['mainEntity'] = tbe_ld_ref( $cid );
			foreach ( tbe_commentary_for_chapter( $slug, $chapter ) as $entry ) {
				$extra = array_merge( $extra, tbe_ld_note_nodes( $entry, $cid ) );
			}
			if ( TBE_Interlinear::available( $slug, $chapter ) ) {
				$page['significantLink'] = TBE_Interlinear::url( $slug, $chapter );
			}
		} else {
			// Interlinear page: describes the same chapter in Hebrew or Greek, with its data source.
			$greek = 'NT' === ( $request['book']['testament'] ?? '' );
			$lang  = $greek ? 'grc' : 'he';
			$trail[]            = array( sprintf( /* translators: %s: passage */ __( 'Interlinear: %s', 'tbe-child' ), TBE_Bible::label( $slug, $chapter ) ), TBE_Interlinear::url( $slug, $chapter ) );
			$page['about']      = tbe_ld_ref( $cid );
			$page['inLanguage'] = array( 'en', $lang );
			$page['isBasedOn']  = tbe_ld_ref( home_url( '/#step-bible-data' ) );
			$extra[]            = array(
				'@type'   => 'Dataset',
				'@id'     => home_url( '/#step-bible-data' ),
				'name'    => $greek ? 'STEPBible Translators Amalgamated Greek NT (TAGNT)' : 'STEPBible Translators Amalgamated Hebrew OT (TAHOT)',
				'url'     => 'https://github.com/STEPBible/STEPBible-Data',
				'license' => 'https://creativecommons.org/licenses/by/4.0/',
				'creator' => array( '@type' => 'Organization', 'name' => 'STEP Bible', 'url' => 'https://www.STEPBible.org' ),
				'inLanguage' => $lang,
			);
			$audio = TBE_Audio::chapter( $slug, $chapter );
			if ( $audio ) {
				$extra[]       = array(
					'@type'          => 'AudioObject',
					'@id'            => TBE_Interlinear::url( $slug, $chapter ) . '#audio',
					'name'           => sprintf( /* translators: %s: passage */ ( $greek ? __( 'Greek reading of %s', 'tbe-child' ) : __( 'Hebrew reading of %s', 'tbe-child' ) ), TBE_Bible::label( $slug, $chapter ) ),
					'contentUrl'     => $audio['url'],
					'encodingFormat' => 'audio/mpeg',
					'duration'       => TBE_Audio::iso_duration( (float) $audio['duration'] ),
					'inLanguage'     => $lang,
					'about'          => tbe_ld_ref( $cid ),
				);
				$page['audio'] = tbe_ld_ref( TBE_Interlinear::url( $slug, $chapter ) . '#audio' );
			}
			$data = TBE_Interlinear::chapter( $slug, $chapter );
			foreach ( (array) ( $data['v'] ?? array() ) as $n => $words ) {
				$hid     = TBE_Interlinear::url( $slug, $chapter ) . '#he-v' . (int) $n;
				$extra[] = array(
					'@type'           => 'Quotation',
					'@id'             => $hid,
					'position'        => (int) $n,
					'text'            => implode( ' ', array_map( static fn( $w ) => (string) $w[0], $words ) ),
					'inLanguage'      => $lang,
					'isPartOf'        => tbe_ld_ref( $cid ),
					'workTranslation' => tbe_ld_ref( TBE_Bible::url( $slug, $chapter ) . '#v' . (int) $n ),
				);
			}
		}

	} elseif ( $request && 'search' === $request['view'] ) {
		$page['@type'] = 'SearchResultsPage';
		$trail[]       = array( __( 'Search', 'tbe-child' ), home_url( '/bible/search/' ) );
		$items         = array();
		$pos           = 0;
		$found         = tbe_search_bible( (string) $request['q'] );
		foreach ( array_slice( $found['hits'], ( max( 1, (int) $request['page'] ) - 1 ) * TBE_SEARCH_PER_PAGE, TBE_SEARCH_PER_PAGE ) as $hit ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => ++$pos,
				'name'     => TBE_Bible::label( $hit['book'], $hit['chapter'], $hit['verse'] ),
				'url'      => TBE_Bible::url( $hit['book'], $hit['chapter'], $hit['verse'] ),
			);
		}
		$extra[]            = array( '@type' => 'ItemList', '@id' => $url . '#results', 'name' => 'Search results', 'numberOfItems' => count( $items ), 'itemListElement' => $items );
		$page['mainEntity'] = tbe_ld_ref( $url . '#results' );

	} elseif ( $request && 'index' === $request['view'] ) {
		$page['@type'] = 'CollectionPage';
		$trail[]       = array( __( 'Index of commentary', 'tbe-child' ), home_url( '/bible/index/' ) );
		$posts         = get_posts( array( 'post_type' => 'tbe_commentary', 'post_status' => 'publish', 'numberposts' => 500 ) );
		$extra[]            = tbe_ld_post_list( $url . '#notes', $posts, 'Index of commentary' );
		$page['mainEntity'] = tbe_ld_ref( $url . '#notes' );

	} elseif ( is_singular( 'tbe_commentary' ) ) {
		$entry   = get_queried_object();
		$trail[] = array( __( 'Commentary', 'tbe-child' ), home_url( '/commentary/' ) );
		$trail[] = array( get_the_title( $entry ), get_permalink( $entry ) );
		$notes   = tbe_ld_note_nodes( $entry );
		$extra   = array_merge( $extra, $notes );
		$page['mainEntity'] = tbe_ld_ref( $notes[0]['@id'] );
		$page['@type']      = 'ItemPage';
		$book = (string) get_post_meta( $entry->ID, 'tbe_book', true );
		if ( $book ) {
			[ $bn ] = tbe_ld_book_node( TBE_Bible::book( $book ) );
			$extra[] = tbe_ld_bible_node();
			$extra[] = $bn;
			$ch      = (int) get_post_meta( $entry->ID, 'tbe_chapter', true );
			if ( $ch ) {
				$extra[] = array(
					'@type'    => 'Chapter',
					'@id'      => tbe_ld_chapter_id( $book, $ch ),
					'name'     => TBE_Bible::label( $book, $ch ),
					'url'      => TBE_Bible::url( $book, $ch ),
					'position' => $ch,
					'isPartOf' => tbe_ld_ref( tbe_ld_book_id( $book ) ),
				);
				$vs = (int) get_post_meta( $entry->ID, 'tbe_vstart', true );
				$ve = max( $vs, (int) get_post_meta( $entry->ID, 'tbe_vend', true ) );
				if ( $vs ) {
					foreach ( TBE_Bible::chapter( $book, $ch ) ?: array() as $verse ) {
						if ( $verse['n'] >= $vs && $verse['n'] <= min( $ve, $vs + 40 ) ) {
							$extra[] = array(
								'@type'      => 'Quotation',
								'@id'        => TBE_Bible::url( $book, $ch ) . '#v' . (int) $verse['n'],
								'url'        => TBE_Bible::url( $book, $ch ) . '#v' . (int) $verse['n'],
								'position'   => (int) $verse['n'],
								'text'       => preg_replace( '/\s+/u', ' ', (string) $verse['t'] ),
								'inLanguage' => 'en',
								'isPartOf'   => tbe_ld_ref( tbe_ld_chapter_id( $book, $ch ) ),
							);
						}
					}
				}
			}
		}
	} elseif ( is_post_type_archive( 'tbe_commentary' ) || is_tag() || is_tax( 'tbe_section' ) ) {
		$page['@type'] = 'CollectionPage';
		$trail[]       = array( __( 'Commentary', 'tbe-child' ), home_url( '/commentary/' ) );
		global $wp_query;
		if ( is_tax() || is_tag() ) {
			$obj     = get_queried_object();
			$trail[] = array( $obj->name, $url );
		}
		$extra[]            = tbe_ld_post_list( $url . '#notes', (array) $wp_query->posts, 'Commentary' );
		$page['mainEntity'] = tbe_ld_ref( $url . '#notes' );

	} elseif ( is_post_type_archive( 'tbe_term' ) ) {
		$page['@type'] = 'CollectionPage';
		$trail[]       = array( __( 'Index', 'tbe-child' ), home_url( '/glossary/' ) );
		$terms         = tbe_glossary_terms();
		$set           = array(
			'@type'           => 'DefinedTermSet',
			'@id'             => tbe_ld_terms_set_id(),
			'name'            => 'Glossary',
			'url'             => home_url( '/glossary/' ),
			'hasDefinedTerm'  => array_map( static fn( $t ) => tbe_ld_ref( $t['url'] . '#term' ), $terms ),
		);
		$extra[] = $set;
		foreach ( $terms as $term ) {
			$extra[] = tbe_ld_term_node( $term );
		}
		$page['mainEntity'] = tbe_ld_ref( tbe_ld_terms_set_id() );

	} elseif ( is_singular( 'tbe_term' ) ) {
		$post    = get_queried_object();
		$trail[] = array( __( 'Index', 'tbe-child' ), home_url( '/glossary/' ) );
		$trail[] = array( get_the_title( $post ), get_permalink( $post ) );
		$page['@type'] = 'ItemPage';
		$extra[] = array( '@type' => 'DefinedTermSet', '@id' => tbe_ld_terms_set_id(), 'name' => 'Glossary', 'url' => home_url( '/glossary/' ) );
		foreach ( tbe_glossary_terms() as $term ) {
			if ( (int) $term['id'] === (int) $post->ID ) {
				$extra[]            = tbe_ld_term_node( $term );
				$page['mainEntity'] = tbe_ld_ref( $term['url'] . '#term' );
			}
		}

	} elseif ( is_front_page() ) {
		$page['@type'] = 'CollectionPage';
		$posts         = get_posts( array( 'post_type' => 'tbe_commentary', 'post_status' => 'publish', 'numberposts' => 6 ) );
		$extra[]            = tbe_ld_post_list( $url . '#latest', $posts, 'Latest commentary' );
		$page['mainEntity'] = tbe_ld_ref( $url . '#latest' );
		$page['about']      = tbe_ld_ref( tbe_ld_bible_id() );
		$extra[]            = tbe_ld_bible_node();
	}

	$chrome = tbe_ld_chrome_nodes( $page_id );
	$page['hasPart'] = array( tbe_ld_ref( $page_id . '-header' ), tbe_ld_ref( $page_id . '-footer' ) );
	$page['significantLink'] = $page['significantLink'] ?? array_map( static fn( $n ) => $n['url'], array_filter( $chrome, static fn( $n ) => 'SiteNavigationElement' === $n['@type'] ) );

	// Any glossary term mentioned by a note must be defined in the graph, with its term set.
	$mentioned = array();
	foreach ( array_merge( $extra, array( $page ) ) as $node ) {
		foreach ( (array) ( $node['mentions'] ?? array() ) as $m ) {
			$mentioned[ $m['@id'] ] = true;
		}
	}
	if ( $mentioned ) {
		foreach ( tbe_glossary_terms() as $term ) {
			if ( isset( $mentioned[ $term['url'] . '#term' ] ) ) {
				$extra[] = tbe_ld_term_node( $term );
			}
		}
		$extra[] = array(
			'@type' => 'DefinedTermSet',
			'@id'   => tbe_ld_terms_set_id(),
			'name'  => 'Glossary',
			'url'   => home_url( '/glossary/' ),
		);
	}

	$nodes[] = $page;
	$nodes[] = tbe_ld_breadcrumb( $url . '#webpage', $trail );
	$nodes   = array_merge( $nodes, $chrome, $extra );

	// De-duplicate nodes by @id (first definition wins; later ones with extra detail are merged).
	$by_id = array();
	$out   = array();
	foreach ( $nodes as $node ) {
		if ( isset( $node['@id'] ) ) {
			if ( isset( $by_id[ $node['@id'] ] ) ) {
				$out[ $by_id[ $node['@id'] ] ] = array_merge( $node, $out[ $by_id[ $node['@id'] ] ] );
				continue;
			}
			$by_id[ $node['@id'] ] = count( $out );
		}
		$out[] = $node;
	}
	return array(
		'@context' => 'https://schema.org',
		'@graph'   => array_values( $out ),
	);
}

add_action(
	'wp_footer',
	static function () {
		if ( ! tbe_setting( 'schema' ) || is_admin() || is_feed() ) {
			return;
		}
		$graph = tbe_ld_graph();
		echo '<script type="application/ld+json">' . wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	},
	99
);

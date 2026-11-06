<?php
/**
 * Commentary content type: one entry per passage, tagged by section and theme.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		register_post_type(
			'tbe_commentary',
			array(
				'labels'       => array(
					'name'          => __( 'Commentary', 'tbe-child' ),
					'singular_name' => __( 'Commentary entry', 'tbe-child' ),
					'add_new_item'  => __( 'Add commentary entry', 'tbe-child' ),
					'edit_item'     => __( 'Edit commentary entry', 'tbe-child' ),
					'menu_name'     => __( 'Commentary', 'tbe-child' ),
				),
				'public'       => true,
				'has_archive'  => 'commentary',
				'rewrite'      => array( 'slug' => 'commentary', 'with_front' => false ),
				'menu_icon'    => 'dashicons-book-alt',
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'excerpt', 'author', 'revisions', 'thumbnail', 'footnotes' ),
			)
		);

		register_taxonomy(
			'tbe_section',
			'tbe_commentary',
			array(
				'labels'       => array(
					'name'          => __( 'Sections', 'tbe-child' ),
					'singular_name' => __( 'Section', 'tbe-child' ),
				),
				'public'       => true,
				'hierarchical' => true,
				'show_in_rest' => true,
				'rewrite'      => array( 'slug' => 'section', 'with_front' => false ),
			)
		);

		// WordPress's own Tags, on commentary entries and index terms (replaces the old custom Themes taxonomy).
		register_taxonomy_for_object_type( 'post_tag', 'tbe_commentary' );
		register_taxonomy_for_object_type( 'post_tag', 'tbe_term' );

		foreach ( array(
			'tbe_book'    => 'string',
			'tbe_chapter' => 'integer',
			'tbe_vstart'  => 'integer',
			'tbe_vend'    => 'integer',
			'tbe_passage' => 'string',
		) as $key => $type ) {
			register_post_meta(
				'tbe_commentary',
				$key,
				array(
					'type'          => $type,
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => static fn() => current_user_can( 'edit_posts' ),
				)
			);
		}
	}
);

/** Section terms are created once; display names come from the settings screen. */
function tbe_section_defaults(): array {
	return array(
		'textual-criticism'  => 'label_textual',
		'critical-commentary' => 'label_critical',
		'islamic-perspective' => 'label_islamic',
		'reflection'         => 'label_reflection',
	);
}

add_action(
	'init',
	static function () {
		if ( get_option( 'tbe_sections_seeded' ) ) {
			return;
		}
		foreach ( tbe_section_defaults() as $slug => $setting ) {
			if ( ! term_exists( $slug, 'tbe_section' ) ) {
				wp_insert_term( (string) tbe_setting( $setting ), 'tbe_section', array( 'slug' => $slug ) );
			}
		}
		update_option( 'tbe_sections_seeded', 1, false );
	},
	20
);

/** Use the owner's configured wording for the four built-in sections. */
add_filter(
	'get_term',
	static function ( $term ) {
		if ( $term instanceof WP_Term && 'tbe_section' === $term->taxonomy && ! is_admin() ) {
			$map = tbe_section_defaults();
			if ( isset( $map[ $term->slug ] ) ) {
				$term->name = (string) tbe_setting( $map[ $term->slug ] );
			}
		}
		return $term;
	}
);

/* Passage meta box: one human-friendly field, parsed on save. ------------ */

add_action(
	'add_meta_boxes',
	static function () {
		add_meta_box( 'tbe_passage', __( 'Passage', 'tbe-child' ), 'tbe_passage_box', 'tbe_commentary', 'side', 'high' );
	}
);

function tbe_passage_box( WP_Post $post ): void {
	wp_nonce_field( 'tbe_passage', 'tbe_passage_nonce' );
	$value = (string) get_post_meta( $post->ID, 'tbe_passage', true );
	?>
	<p>
		<label for="tbe-passage-field" class="screen-reader-text"><?php esc_html_e( 'Passage reference', 'tbe-child' ); ?></label>
		<input type="text" id="tbe-passage-field" name="tbe_passage" class="widefat" value="<?php echo esc_attr( $value ); ?>" placeholder="John 1:1-18">
	</p>
	<p class="description"><?php esc_html_e( 'Book, chapter and verses, e.g. "Psalm 23", "1 Cor 13:4-7", "Jude 5". Leave verses out to cover a whole chapter, or give just the book name ("Genesis") for a book introduction.', 'tbe-child' ); ?></p>
	<?php
	$book = (string) get_post_meta( $post->ID, 'tbe_book', true );
	if ( $book ) {
		printf(
			'<p><strong>%s</strong> %s</p>',
			esc_html__( 'Resolved:', 'tbe-child' ),
			esc_html( tbe_passage_label( $book, (int) get_post_meta( $post->ID, 'tbe_chapter', true ), (int) get_post_meta( $post->ID, 'tbe_vstart', true ), (int) get_post_meta( $post->ID, 'tbe_vend', true ) ) )
		);
	}
}

add_action(
	'save_post_tbe_commentary',
	static function ( int $post_id ) {
		if ( ! isset( $_POST['tbe_passage_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tbe_passage_nonce'] ) ), 'tbe_passage' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		tbe_store_passage( $post_id, sanitize_text_field( wp_unslash( $_POST['tbe_passage'] ?? '' ) ) );
	}
);

/**
 * Parse a passage reference typed by an editor and store it (the label and its parts) on an entry; an empty or
 * unknown reference clears them. Used by the meta box and by the front-end editor (0.10.15).
 */
function tbe_store_passage( int $post_id, string $text ): void {
	$parsed = '' !== $text ? TBE_Bible::parse_reference( $text ) : null;
	if ( $parsed && ! preg_match( '/\d/', preg_replace( '/^\s*(?:[1-3]|i{1,3}|first|second|third)\b\.?/i', '', $text ) ) ) {
		// A book name with no chapter number: the entry introduces the whole book.
		$parsed['chapter'] = 0;
		$parsed['vstart']  = 0;
		$parsed['vend']    = 0;
	}
	if ( $parsed ) {
		update_post_meta( $post_id, 'tbe_passage', tbe_passage_label( $parsed['book'], $parsed['chapter'], $parsed['vstart'], $parsed['vend'] ) );
		update_post_meta( $post_id, 'tbe_book', $parsed['book'] );
		update_post_meta( $post_id, 'tbe_chapter', $parsed['chapter'] );
		update_post_meta( $post_id, 'tbe_vstart', $parsed['vstart'] );
		update_post_meta( $post_id, 'tbe_vend', $parsed['vend'] );
	} else {
		foreach ( array( 'tbe_passage', 'tbe_book', 'tbe_chapter', 'tbe_vstart', 'tbe_vend' ) as $key ) {
			delete_post_meta( $post_id, $key );
		}
	}
}

/**
 * Whether a the_content call renders the body of the entry this page is about. Block templates render the Post
 * Content block outside the classic Loop on some WordPress versions, so the queried entry is matched instead of
 * in_the_loop() (0.10.8). An excerpt built from the text is never treated as the body.
 */
function tbe_is_entry_body(): bool {
	return is_singular( 'tbe_commentary' ) && is_main_query() && ! is_admin() && ! is_feed() && ! doing_filter( 'get_the_excerpt' )
		&& (int) get_the_ID() === (int) get_queried_object_id();
}

/** On a single entry: show the passage with a link to the reader, then variants and Qur'an references. */
add_filter(
	'the_content',
	static function ( string $content ): string {
		if ( ! tbe_is_entry_body() ) {
			return $content;
		}
		$id   = get_the_ID();
		$book = (string) get_post_meta( $id, 'tbe_book', true );
		if ( ! $book ) {
			return $content;
		}
		$chapter = (int) get_post_meta( $id, 'tbe_chapter', true );
		$vstart  = (int) get_post_meta( $id, 'tbe_vstart', true );
		$url     = $chapter ? TBE_Bible::url( $book, $chapter, $vstart ?: null ) : TBE_Bible::url( $book );
		$intro   = sprintf(
			'<p class="tbe-passage"><span class="tbe-passage__label">%1$s</span> <a href="%2$s">%3$s</a></p>',
			esc_html__( 'Passage', 'tbe-child' ),
			esc_url( $url ),
			esc_html( (string) get_post_meta( $id, 'tbe_passage', true ) )
		);
		// The Qur'an references move to the notes and sources block when the template has one (0.10.8).
		$moved = function_exists( 'tbe_article_template_has_sources' ) && tbe_article_template_has_sources();
		return $intro . $content . tbe_render_entry_extras( $id, $moved ? 2 : 4, ! $moved );
	},
	20
);

/** Commentary entries for a chapter, ordered by starting verse. @return WP_Post[] */
function tbe_commentary_for_chapter( string $book, int $chapter ): array {
	static $cache = array();
	$key = $book . ':' . $chapter;
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}
	$query = new WP_Query(
		array(
			'post_type'              => 'tbe_commentary',
			'post_status'            => 'publish',
			'posts_per_page'         => 200,
			'no_found_rows'          => true,
			'update_post_term_cache' => true,
			'meta_query'             => array(
				'relation' => 'AND',
				array( 'key' => 'tbe_book', 'value' => $book ),
				array( 'key' => 'tbe_chapter', 'value' => $chapter, 'type' => 'NUMERIC' ),
			),
			'meta_key'               => 'tbe_vstart',
			'orderby'                => array( 'meta_value_num' => 'ASC', 'date' => 'ASC' ),
		)
	);
	$cache[ $key ] = $query->posts;
	return $cache[ $key ];
}

/** "John 1:1–18", "John 3" (chapter introduction) or "John" (book introduction). */
function tbe_passage_label( string $book, int $chapter, int $vstart = 0, int $vend = 0 ): string {
	if ( 0 === $chapter ) {
		$data = TBE_Bible::book( $book );
		return $data ? $data['name'] : $book;
	}
	return TBE_Bible::label( $book, $chapter, $vstart, $vend );
}

/** Introduction entries for a whole book (chapter 0). @return WP_Post[] */
function tbe_commentary_for_book( string $book ): array {
	$query = new WP_Query(
		array(
			'post_type'      => 'tbe_commentary',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'no_found_rows'  => true,
			'orderby'        => 'date',
			'order'          => 'ASC',
			'meta_query'     => array(
				'relation' => 'AND',
				array( 'key' => 'tbe_book', 'value' => $book ),
				array( 'key' => 'tbe_chapter', 'value' => 0, 'type' => 'NUMERIC' ),
			),
		)
	);
	return $query->posts;
}


/** A tag page lists commentary entries and index terms along with ordinary posts. */
add_action(
	'pre_get_posts',
	static function ( WP_Query $query ) {
		if ( ! is_admin() && $query->is_main_query() && $query->is_tag() ) {
			$query->set( 'post_type', array( 'post', 'tbe_commentary', 'tbe_term' ) );
		}
	}
);

/** One time: carry the old custom "Themes" over into WordPress Tags so nothing is lost. */
add_action(
	'admin_init',
	static function () {
		if ( get_option( 'tbe_themes_to_tags' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		global $wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT tr.object_id AS id, t.name AS name FROM {$wpdb->term_relationships} tr
			 JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'tbe_theme'
			 JOIN {$wpdb->terms} t ON t.term_id = tt.term_id"
		);
		foreach ( (array) $rows as $row ) {
			wp_set_post_tags( (int) $row->id, array( $row->name ), true );
		}
		update_option( 'tbe_themes_to_tags', 1, false );
	}
);


/**
 * Cross-reference notes are filed twice, once on each passage, so a plain list would show every one twice.
 * The home page and the commentary list show only the first copy (the New Testament side); the mirror
 * still appears under its own verse and in the index.
 */
function tbe_hide_mirror_notes( array $query ): array {
	$meta   = (array) ( $query['meta_query'] ?? array() );
	$meta[] = array(
		'relation' => 'OR',
		array( 'key' => 'tbe_pack_key', 'compare' => 'NOT EXISTS' ),
		array( 'key' => 'tbe_pack_key', 'value' => ':ot', 'compare' => 'NOT LIKE' ),
	);
	$query['meta_query'] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery
	return $query;
}
add_filter(
	'query_loop_block_query_vars',
	static function ( array $query ): array {
		return ( 'tbe_commentary' === ( $query['post_type'] ?? '' ) ) ? tbe_hide_mirror_notes( $query ) : $query;
	}
);
add_action(
	'pre_get_posts',
	static function ( WP_Query $query ) {
		if ( ! is_admin() && $query->is_main_query() && $query->is_post_type_archive( 'tbe_commentary' ) ) {
			$vars = tbe_hide_mirror_notes( array( 'meta_query' => (array) $query->get( 'meta_query' ) ) );
			$query->set( 'meta_query', $vars['meta_query'] );
		}
	}
);

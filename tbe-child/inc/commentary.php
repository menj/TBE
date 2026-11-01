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
				'supports'     => array( 'title', 'editor', 'excerpt', 'author', 'revisions', 'thumbnail' ),
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

		register_taxonomy(
			'tbe_theme',
			'tbe_commentary',
			array(
				'labels'       => array(
					'name'          => __( 'Themes', 'tbe-child' ),
					'singular_name' => __( 'Theme', 'tbe-child' ),
				),
				'public'       => true,
				'hierarchical' => false,
				'show_in_rest' => true,
				'rewrite'      => array( 'slug' => 'theme', 'with_front' => false ),
			)
		);

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
	<p class="description"><?php esc_html_e( 'Book, chapter and verses, e.g. "Psalm 23", "1 Cor 13:4-7", "Jude 5". Leave verses out to cover a whole chapter.', 'tbe-child' ); ?></p>
	<?php
	$book = (string) get_post_meta( $post->ID, 'tbe_book', true );
	if ( $book ) {
		printf(
			'<p><strong>%s</strong> %s</p>',
			esc_html__( 'Resolved:', 'tbe-child' ),
			esc_html( TBE_Bible::label( $book, (int) get_post_meta( $post->ID, 'tbe_chapter', true ), (int) get_post_meta( $post->ID, 'tbe_vstart', true ), (int) get_post_meta( $post->ID, 'tbe_vend', true ) ) )
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
		$text   = sanitize_text_field( wp_unslash( $_POST['tbe_passage'] ?? '' ) );
		$parsed = '' !== $text ? TBE_Bible::parse_reference( $text ) : null;
		if ( $parsed ) {
			update_post_meta( $post_id, 'tbe_passage', TBE_Bible::label( $parsed['book'], $parsed['chapter'], $parsed['vstart'], $parsed['vend'] ) );
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
);

/** Show the passage and a link to the reader at the top of a single entry. */
add_filter(
	'the_content',
	static function ( string $content ): string {
		if ( ! is_singular( 'tbe_commentary' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$id   = get_the_ID();
		$book = (string) get_post_meta( $id, 'tbe_book', true );
		if ( ! $book ) {
			return $content;
		}
		$chapter = (int) get_post_meta( $id, 'tbe_chapter', true );
		$vstart  = (int) get_post_meta( $id, 'tbe_vstart', true );
		$intro   = sprintf(
			'<p class="tbe-passage"><span class="tbe-passage__label">%1$s</span> <a href="%2$s">%3$s</a></p>',
			esc_html__( 'Passage', 'tbe-child' ),
			esc_url( TBE_Bible::url( $book, $chapter, $vstart ?: null ) ),
			esc_html( (string) get_post_meta( $id, 'tbe_passage', true ) )
		);
		return $intro . $content;
	}
);

/** Commentary entries for a chapter, ordered by starting verse. @return WP_Post[] */
function tbe_commentary_for_chapter( string $book, int $chapter ): array {
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
	return $query->posts;
}

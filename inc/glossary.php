<?php
/**
 * Glossary: a term post type, automatic tooltips inside commentary, and the A–Z page.
 * Scripture text is never annotated.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		register_post_type(
			'tbe_term',
			array(
				'labels'       => array(
					'name'          => __( 'Index', 'tbe-child' ),
					'singular_name' => __( 'Glossary term', 'tbe-child' ),
					'add_new_item'  => __( 'Add glossary term', 'tbe-child' ),
					'edit_item'     => __( 'Edit glossary term', 'tbe-child' ),
					'menu_name'     => __( 'Index', 'tbe-child' ),
				),
				'public'       => true,
				'has_archive'  => 'glossary',
				'rewrite'      => array( 'slug' => 'glossary', 'with_front' => false ),
				'menu_icon'    => 'dashicons-editor-spellcheck',
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'excerpt', 'revisions' ),
			)
		);
		foreach ( array( 'tbe_aliases' ) as $key ) {
			register_post_meta( 'tbe_term', $key, array( 'type' => 'string', 'single' => true, 'show_in_rest' => true ) );
		}
	}
);

/* Meta box ---------------------------------------------------------------- */

add_action(
	'add_meta_boxes',
	static function () {
		add_meta_box( 'tbe_term_fields', __( 'Term details', 'tbe-child' ), 'tbe_term_box', 'tbe_term', 'normal', 'high' );
	}
);

function tbe_term_box( WP_Post $post ): void {
	wp_nonce_field( 'tbe_term', 'tbe_term_nonce' );
	?>
	<p>
		<label for="tbe-aliases"><strong><?php esc_html_e( 'Other spellings and abbreviations', 'tbe-child' ); ?></strong></label>
		<input type="text" id="tbe-aliases" name="tbe_aliases" class="widefat" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, 'tbe_aliases', true ) ); ?>" placeholder="MT, Masoretic">
		<span class="description"><?php esc_html_e( 'Comma-separated. Each one also gets a tooltip wherever it appears in commentary.', 'tbe-child' ); ?></span>
	</p>
	<p class="description"><?php esc_html_e( 'The short summary shown in the tooltip is the Excerpt. If empty, the start of the main text is used.', 'tbe-child' ); ?></p>
	<?php
}

add_action(
	'save_post_tbe_term',
	static function ( int $post_id ) {
		tbe_flush_glossary();
		if ( ! isset( $_POST['tbe_term_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tbe_term_nonce'] ) ), 'tbe_term' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, 'tbe_aliases', sanitize_text_field( wp_unslash( $_POST['tbe_aliases'] ?? '' ) ) );
		tbe_flush_glossary();
	}
);

foreach ( array( 'wp_trash_post', 'untrash_post', 'before_delete_post' ) as $tbe_hook ) {
	add_action( $tbe_hook, 'tbe_flush_glossary' );
}
unset( $tbe_hook );

function tbe_flush_glossary(): void {
	delete_transient( 'tbe_glossary_v1' );
}

/* Seed terms (as drafts, for you to review and publish) ------------------- */

function tbe_seed_terms(): array {
	return array(
		array( 'Textual criticism', '', 'The scholarly discipline of comparing surviving manuscripts and versions of a text in order to identify variant readings and reconstruct the earliest form that can be recovered.' ),
		array( 'Variant reading', 'variant; variants; variation unit', 'A place where surviving manuscripts of the same work differ in wording, spelling, word order or omission. Scholars call each such place a variation unit.' ),
		array( 'Manuscript', 'manuscripts', 'A handwritten copy of a text, as opposed to a printed one.' ),
		array( 'Codex', 'codices', 'An early book form made of folded sheets bound along one edge, which gradually replaced the scroll.' ),
		array( 'Codex Sinaiticus', 'Sinaiticus', 'A fourth-century Greek manuscript of the Christian Bible, found at St Catherine\'s Monastery on Mount Sinai. It is one of the oldest nearly complete copies of the New Testament.' ),
		array( 'Codex Vaticanus', 'Vaticanus', 'A fourth-century Greek manuscript of the Bible, held in the Vatican Library since at least the fifteenth century.' ),
		array( 'Septuagint', 'LXX', 'The Greek translation of the Hebrew scriptures, begun in Alexandria in the third century BCE. Often abbreviated LXX.' ),
		array( 'Masoretic Text', 'MT; Masoretic', 'The standard Hebrew text of the Hebrew Bible, preserved and vocalised by Jewish scribes known as the Masoretes in the early medieval period. Often abbreviated MT.' ),
		array( 'Dead Sea Scrolls', '', 'Manuscripts discovered from 1947 in caves near Qumran, including the oldest known copies of many books of the Hebrew Bible.' ),
		array( 'Textus Receptus', '', 'The Greek New Testament text printed in the sixteenth century from a small number of late manuscripts, which underlies the King James Version. Latin for "received text".' ),
		array( 'Pericope', 'pericopes', 'A self-contained passage or unit of text, such as a parable or a miracle story.' ),
		array( 'Tafsir', 'tafseer', 'Explanation or interpretation: the Islamic discipline of explaining the Qur\'an. On this site it names the section where a Bible passage is read in light of Islamic scholarship.' ),
		array( 'Tadabbur', 'tadabur', 'Deep reflection or contemplation, especially on the meaning of the Qur\'an. On this site the matching section for Bible passages is called Reflection.' ),
	);
}

add_action(
	'init',
	static function () {
		if ( get_option( 'tbe_glossary_seeded' ) || ! is_admin() ) {
			return;
		}
		foreach ( tbe_seed_terms() as $seed ) {
			$existing = get_posts(
				array(
					'post_type'   => 'tbe_term',
					'post_status' => 'any',
					'title'       => $seed[0],
					'numberposts' => 1,
					'fields'      => 'ids',
				)
			);
			if ( $existing ) {
				continue;
			}
			$id = wp_insert_post(
				array(
					'post_type'    => 'tbe_term',
					'post_status'  => 'draft',
					'post_title'   => $seed[0],
					'post_content' => $seed[2],
					'post_excerpt' => $seed[2],
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $id, 'tbe_aliases', $seed[1] );
			}
		}
		update_option( 'tbe_glossary_seeded', 1, false );
	},
	30
);

/* Term index and annotation ---------------------------------------------- */

/** @return array<int,array{id:int,title:string,names:string[],tip:string,url:string}> */
function tbe_glossary_terms(): array {
	$cached = get_transient( 'tbe_glossary_v1' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$out   = array();
	$posts = get_posts(
		array(
			'post_type'   => 'tbe_term',
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby'     => 'title',
			'order'       => 'ASC',
		)
	);
	foreach ( $posts as $post ) {
		$names = array( $post->post_title );
		foreach ( preg_split( '/[,;\n]+/u', (string) get_post_meta( $post->ID, 'tbe_aliases', true ) ) as $alias ) {
			$alias = trim( $alias );
			if ( '' !== $alias ) {
				$names[] = $alias;
			}
		}
		$tip   = '' !== trim( $post->post_excerpt ) ? $post->post_excerpt : $post->post_content;
		$out[] = array(
			'id'     => $post->ID,
			'title'  => $post->post_title,
			'names'  => array_values( array_unique( $names ) ),
			'tip'    => wp_trim_words( wp_strip_all_tags( strip_shortcodes( $tip ) ), 45 ),
			'url'    => get_permalink( $post ),
		);
	}
	set_transient( 'tbe_glossary_v1', $out, DAY_IN_SECONDS );
	return $out;
}

/** Tooltips inside single commentary pages. */
add_filter(
	'the_content',
	static function ( string $content ): string {
		if ( is_singular( 'tbe_commentary' ) && in_the_loop() && is_main_query() ) {
			return tbe_enrich_commentary( $content );
		}
		return $content;
	},
	99
);

/** The A–Z glossary page. */
function tbe_render_glossary(): string {
	$terms = tbe_glossary_terms();
	if ( ! $terms ) {
		return '<p class="tbe-muted">' . esc_html__( 'The glossary is empty for now.', 'tbe-child' ) . '</p>';
	}
	$groups = array();
	foreach ( $terms as $term ) {
		$letter              = mb_strtoupper( mb_substr( $term['title'], 0, 1, 'UTF-8' ), 'UTF-8' );
		$groups[ $letter ][] = $term;
	}
	ksort( $groups );

	$out = '<nav class="tbe-az" aria-label="' . esc_attr__( 'Jump to letter', 'tbe-child' ) . '">';
	foreach ( array_keys( $groups ) as $letter ) {
		$out .= '<a href="#g-' . esc_attr( $letter ) . '">' . esc_html( $letter ) . '</a>';
	}
	$out .= '</nav>';
	foreach ( $groups as $letter => $items ) {
		$out .= '<section class="tbe-glossary-group" id="g-' . esc_attr( (string) $letter ) . '"><h2 class="tbe-ornament">' . esc_html( (string) $letter ) . '</h2><dl class="tbe-glossary">';
		foreach ( $items as $term ) {
			$out .= '<div class="tbe-glossary__item" id="term-' . (int) $term['id'] . '"><dt><a href="' . esc_url( $term['url'] ) . '">' . esc_html( $term['title'] ) . '</a>';
			$out .= '</dt><dd>' . esc_html( $term['tip'] ) . '</dd></div>';
		}
		$out .= '</dl></section>';
	}
	return $out;
}

<?php
/**
 * Unlisted posts in the admin (0.8.0): the box in the editor sidebar, the Unlisted column with
 * Quick Edit and Bulk Edit, the "Unlisted" post state and the Unlisted view of each list table.
 * Loaded by inc/unlist.php only in the admin and only while the plugin is inactive.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

/** The box, in the sidebar of the block editor and the classic editor alike. */
add_action(
	'add_meta_boxes',
	function () {
		add_meta_box( 'tbe-unlist', __( 'Unlist', 'tbe-child' ), 'tbe_unlist_box', array_values( tbe_unlist_post_types() ), 'side', 'high' );
	}
);

/**
 * The box's checkbox.
 *
 * @param WP_Post $post Post.
 */
function tbe_unlist_box( $post ) {
	wp_nonce_field( 'tbe_unlist_box', 'tbe_unlist_nonce' );
	?>
	<p>
		<label for="tbe-unlisted">
			<input type="checkbox" id="tbe-unlisted" name="tbe_unlisted" value="1" aria-describedby="tbe-unlisted-help" <?php checked( tbe_is_unlisted( $post->ID ) ); ?>>
			<?php esc_html_e( 'Unlist this post', 'tbe-child' ); ?>
		</label>
	</p>
	<p class="description" id="tbe-unlisted-help"><?php esc_html_e( 'The post opens for anyone with its address. It is left out of the home page, archives, feeds, search, the sitemaps and the previous and next links, and search engines are asked not to index it.', 'tbe-child' ); ?></p>
	<?php
}

/**
 * Save from the box, Quick Edit or Bulk Edit, each checked by its own nonce.
 *
 * @param int $post_id Post ID.
 */
function tbe_unlist_save( $post_id ) {
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || false !== wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification -- each branch verifies its nonce.
	if ( isset( $_REQUEST['bulk_edit'], $_REQUEST['tbe_unlist_bulk'] ) ) {
		if ( empty( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'bulk-posts' ) ) {
			return;
		}
		$value = sanitize_key( wp_unslash( $_REQUEST['tbe_unlist_bulk'] ) );
		if ( 'unlist' === $value || 'list' === $value ) {
			tbe_set_unlisted( $post_id, 'unlist' === $value );
		}
		return;
	}
	if ( isset( $_POST['tbe_unlist_qe_nonce'] ) ) {
		if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tbe_unlist_qe_nonce'] ) ), 'tbe_unlist_quick' ) ) {
			tbe_set_unlisted( $post_id, ! empty( $_POST['tbe_unlist_quick'] ) );
		}
		return;
	}
	if ( isset( $_POST['tbe_unlist_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tbe_unlist_nonce'] ) ), 'tbe_unlist_box' ) ) {
		tbe_set_unlisted( $post_id, ! empty( $_POST['tbe_unlisted'] ) );
	}
	// phpcs:enable WordPress.Security.NonceVerification
}
add_action( 'save_post', 'tbe_unlist_save' );

/** Whether the list table is showing the Unlisted view. */
function tbe_unlist_view_on() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a view switch only.
	return isset( $_GET['post_status'] ) && 'unlisted' === $_GET['post_status'];
}

add_filter(
	'display_post_states',
	function ( $states, $post ) {
		if ( ! tbe_unlist_view_on() && $post instanceof WP_Post && tbe_is_unlisted( $post->ID ) ) {
			$states['tbe_unlisted'] = __( 'Unlisted', 'tbe-child' );
		}
		return $states;
	},
	10,
	2
);

/* The Unlisted view, the column, Quick Edit and Bulk Edit on every public post type. */
add_action(
	'admin_init',
	function () {
		foreach ( tbe_unlist_post_types() as $type ) {
			add_filter( "views_edit-{$type}", 'tbe_unlist_view_link' );
			add_filter(
				"manage_{$type}_posts_columns",
				function ( $columns ) {
					$columns['tbe_unlisted'] = __( 'Unlisted', 'tbe-child' );
					return $columns;
				}
			);
			add_action(
				"manage_{$type}_posts_custom_column",
				function ( $column, $post_id ) {
					if ( 'tbe_unlisted' === $column ) {
						$on = tbe_is_unlisted( $post_id );
						printf( '<span class="tbe-unlisted" data-unlisted="%s">%s</span>', $on ? '1' : '0', $on ? esc_html__( 'Yes', 'tbe-child' ) : '&mdash;' );
					}
				},
				10,
				2
			);
		}
	}
);

/**
 * The "Unlisted (n)" link above the list.
 *
 * @param array $views Views.
 * @return array
 */
function tbe_unlist_view_link( $views ) {
	$ids = tbe_unlisted_ids();
	if ( ! $ids ) {
		return $views;
	}
	$screen = get_current_screen();
	$query  = new WP_Query(
		array(
			'post_type'      => $screen && $screen->post_type ? $screen->post_type : 'any',
			'post__in'       => $ids,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);
	if ( $query->found_posts ) {
		$views['tbe_unlisted'] = sprintf(
			'<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
			esc_url( add_query_arg( 'post_status', 'unlisted' ) ),
			tbe_unlist_view_on() ? ' class="current" aria-current="page"' : '',
			esc_html__( 'Unlisted', 'tbe-child' ),
			(int) $query->found_posts
		);
	}
	return $views;
}

add_action(
	'parse_query',
	function ( $query ) {
		global $pagenow;
		if ( is_admin() && 'edit.php' === $pagenow && $query->is_main_query() && tbe_unlist_view_on() ) {
			$ids = tbe_unlisted_ids();
			$query->query_vars['post__in']    = $ids ? $ids : array( 0 );
			$query->query_vars['post_status'] = 'any';
		}
	}
);

add_action(
	'quick_edit_custom_box',
	function ( $column, $type ) {
		if ( 'tbe_unlisted' !== $column || ! in_array( $type, tbe_unlist_post_types(), true ) ) {
			return;
		}
		wp_nonce_field( 'tbe_unlist_quick', 'tbe_unlist_qe_nonce' );
		?>
		<fieldset class="inline-edit-col-right"><div class="inline-edit-col">
			<label class="alignleft">
				<input type="checkbox" name="tbe_unlist_quick" value="1">
				<span class="checkbox-title"><?php esc_html_e( 'Unlist this post', 'tbe-child' ); ?></span>
			</label>
		</div></fieldset>
		<?php
	},
	10,
	2
);

add_action(
	'bulk_edit_custom_box',
	function ( $column, $type ) {
		if ( 'tbe_unlisted' !== $column || ! in_array( $type, tbe_unlist_post_types(), true ) ) {
			return;
		}
		?>
		<fieldset class="inline-edit-col-right"><div class="inline-edit-col">
			<label class="inline-edit-group">
				<span class="title"><?php esc_html_e( 'Unlisted', 'tbe-child' ); ?></span>
				<select name="tbe_unlist_bulk">
					<option value="-1"><?php esc_html_e( 'No change', 'tbe-child' ); ?></option>
					<option value="unlist"><?php esc_html_e( 'Unlist', 'tbe-child' ); ?></option>
					<option value="list"><?php esc_html_e( 'List', 'tbe-child' ); ?></option>
				</select>
			</label>
		</div></fieldset>
		<?php
	},
	10,
	2
);

/* Quick Edit opens with the box ticked when the row is unlisted; the script is a file of its own. */
add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		$screen = get_current_screen();
		if ( 'edit.php' !== $hook || ! $screen || ! in_array( $screen->post_type, tbe_unlist_post_types(), true ) ) {
			return;
		}
		wp_enqueue_script( 'tbe-unlist', TBE_URI . '/assets/js/unlist-admin.js', array( 'jquery', 'inline-edit-post' ), TBE_VERSION, true );
	}
);

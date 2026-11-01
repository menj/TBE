<?php
/**
 * Starter commentary packs: ready-made entries bundled in data/commentary/*.json, imported from
 * Appearance → TBE → Starter commentary. Entries land as drafts (or published, if you choose).
 * Once imported, a pack follows the theme: when an update changes an entry the new text overwrites the
 * old, new entries arrive as drafts, and entries the pack dropped go to the Trash. Tick "Keep my edits"
 * on an entry to exempt it.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

/** @return array<string,array> Packs keyed by id. */
function tbe_packs(): array {
	$packs = array();
	foreach ( glob( TBE_DIR . '/data/commentary/*.json' ) ?: array() as $file ) {
		$pack = json_decode( (string) file_get_contents( $file ), true );
		if ( is_array( $pack ) && ! empty( $pack['id'] ) && ! empty( $pack['entries'] ) ) {
			$packs[ (string) $pack['id'] ] = $pack;
		}
	}
	return $packs;
}

/** Post ID of an already-imported entry, or 0. */
function tbe_pack_existing( string $key ): int {
	$found = get_posts(
		array(
			'post_type'   => 'tbe_commentary',
			'post_status' => 'any',
			'meta_key'    => 'tbe_pack_key', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'  => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
			'numberposts' => 1,
			'fields'      => 'ids',
		)
	);
	return $found ? (int) $found[0] : 0;
}

/** Fingerprint of an entry's content, to tell when the theme's copy has changed. */
function tbe_pack_entry_hash( array $entry ): string {
	return md5( (string) wp_json_encode( array( $entry['title'], $entry['passage'], $entry['content'], $entry['themes'], $entry['section'] ) ) );
}

/**
 * Create or overwrite one entry from a pack. Status is only set when creating.
 *
 * @return int Post ID, or 0 on failure.
 */
function tbe_write_entry( int $existing_id, array $entry, string $status ): int {
	$ref = TBE_Bible::parse_reference( (string) $entry['passage'] );
	if ( ! $ref ) {
		return 0;
	}
	$post = array(
		'post_type'    => 'tbe_commentary',
		'post_title'   => wp_slash( (string) $entry['title'] ),
		'post_content' => wp_slash( (string) $entry['content'] ),
	);
	if ( $existing_id ) {
		$post['ID'] = $existing_id;
		$id         = wp_update_post( $post, true );
	} else {
		$post['post_status'] = 'publish' === $status ? 'publish' : 'draft';
		$post['post_author'] = get_current_user_id();
		$id                  = wp_insert_post( $post, true );
	}
	if ( ! $id || is_wp_error( $id ) ) {
		return 0;
	}
	update_post_meta( $id, 'tbe_pack_key', (string) $entry['key'] );
	update_post_meta( $id, 'tbe_pack_hash', tbe_pack_entry_hash( $entry ) );
	update_post_meta( $id, 'tbe_passage', tbe_passage_label( $ref['book'], $ref['chapter'], $ref['vstart'], $ref['vend'] ) );
	update_post_meta( $id, 'tbe_book', $ref['book'] );
	update_post_meta( $id, 'tbe_chapter', $ref['chapter'] );
	update_post_meta( $id, 'tbe_vstart', $ref['vstart'] );
	update_post_meta( $id, 'tbe_vend', $ref['vend'] );
	wp_set_object_terms( $id, array( (string) $entry['section'] ), 'tbe_section' );
	wp_set_post_tags( $id, array_map( 'strval', (array) $entry['themes'] ), false );
	return (int) $id;
}

/**
 * Create the entries of a pack that are not there yet.
 *
 * @return array{created:int,skipped:int,failed:string[]}
 */
function tbe_import_pack( array $pack, string $status ): array {
	$created = 0;
	$skipped = 0;
	$failed  = array();
	foreach ( $pack['entries'] as $entry ) {
		$key = (string) $entry['key'];
		if ( tbe_pack_existing( $key ) ) {
			++$skipped;
			continue;
		}
		if ( tbe_write_entry( 0, $entry, $status ) ) {
			++$created;
		} else {
			$failed[] = $key;
		}
	}
	return array( 'created' => $created, 'skipped' => $skipped, 'failed' => $failed );
}

/** Every post that came from a pack, keyed by its pack key (trash excluded). @return array<string,WP_Post> */
function tbe_pack_posts( string $pack_id ): array {
	$posts = get_posts(
		array(
			'post_type'    => 'tbe_commentary',
			'post_status'  => 'any',
			'meta_key'     => 'tbe_pack_key', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'   => $pack_id . ':', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_compare' => 'LIKE',
			'numberposts'  => -1,
		)
	);
	$by_key = array();
	foreach ( $posts as $post ) {
		$by_key[ (string) get_post_meta( $post->ID, 'tbe_pack_key', true ) ] = $post;
	}
	return $by_key;
}

/**
 * Keep a pack that was imported in step with the theme: overwrite entries whose text has changed,
 * add new ones as drafts, and move to the Trash the ones the pack no longer contains. An entry marked
 * "keep my edits" is never touched. A pack that was never imported is left alone.
 *
 * @return array{updated:int,created:int,removed:int}|null
 */
function tbe_sync_pack( array $pack ): ?array {
	$have = tbe_pack_posts( (string) $pack['id'] );
	if ( ! $have ) {
		return null;
	}
	$updated = 0;
	$created = 0;
	$removed = 0;
	$wanted  = array();
	foreach ( $pack['entries'] as $entry ) {
		$key            = (string) $entry['key'];
		$wanted[ $key ] = true;
		if ( ! isset( $have[ $key ] ) ) {
			$created += tbe_write_entry( 0, $entry, 'draft' ) ? 1 : 0;
			continue;
		}
		$post = $have[ $key ];
		if ( get_post_meta( $post->ID, 'tbe_pack_locked', true ) || get_post_meta( $post->ID, 'tbe_pack_hash', true ) === tbe_pack_entry_hash( $entry ) ) {
			continue;
		}
		$updated += tbe_write_entry( (int) $post->ID, $entry, 'draft' ) ? 1 : 0;
	}
	foreach ( $have as $key => $post ) {
		if ( isset( $wanted[ $key ] ) || get_post_meta( $post->ID, 'tbe_pack_locked', true ) ) {
			continue;
		}
		wp_trash_post( $post->ID );
		++$removed;
	}
	return array( 'updated' => $updated, 'created' => $created, 'removed' => $removed );
}

/**
 * Keep bundled packs in step with the theme. A pack that has never been handled is imported and
 * published as soon as the theme is activated (or first loaded in the admin after an upload).
 * A pack already imported follows the theme: changed entries are overwritten, new ones arrive as
 * drafts, dropped ones go to the Trash. Switch off under Appearance → TBE → General.
 */
function tbe_autosync_packs(): void {
	if ( ! current_user_can( 'manage_options' ) || ! tbe_setting( 'pack_sync' ) ) {
		return;
	}
	foreach ( tbe_packs() as $pack ) {
		$option      = 'tbe_pack_sync_' . $pack['id'];
		$seen        = get_option( $option );
		$fingerprint = md5( (string) wp_json_encode( $pack ) );
		if ( $seen === $fingerprint ) {
			continue;
		}
		if ( false === $seen && ! tbe_pack_posts( (string) $pack['id'] ) ) {
			$done   = tbe_import_pack( $pack, 'publish' );
			$result = array( 'updated' => 0, 'created' => $done['created'], 'removed' => 0 );
		} else {
			$result = tbe_sync_pack( $pack );
		}
		update_option( $option, $fingerprint, false );
		if ( $result && array_sum( $result ) ) {
			set_transient( 'tbe_sync_notice', $result + array( 'name' => (string) $pack['name'] ), 600 );
		}
	}
}
add_action( 'after_switch_theme', 'tbe_autosync_packs' );
add_action( 'admin_init', 'tbe_autosync_packs', 20 );

/** On an entry that came from a pack: a switch to keep the editor's own changes through theme updates. */
add_action(
	'add_meta_boxes_tbe_commentary',
	static function ( WP_Post $post ) {
		if ( ! get_post_meta( $post->ID, 'tbe_pack_key', true ) ) {
			return;
		}
		add_meta_box(
			'tbe-pack-lock',
			__( 'Starter commentary', 'tbe-child' ),
			static function ( WP_Post $post ) {
				wp_nonce_field( 'tbe_pack_lock', 'tbe_pack_lock_nonce' );
				printf(
					'<p><label><input type="checkbox" name="tbe_pack_locked" value="1" %s> %s</label></p><p class="description">%s</p>',
					checked( (bool) get_post_meta( $post->ID, 'tbe_pack_locked', true ), true, false ),
					esc_html__( 'Keep my edits', 'tbe-child' ),
					esc_html__( 'Without this, a theme update that changes this entry overwrites your version, and an update that drops it moves it to the Trash.', 'tbe-child' )
				);
			},
			'tbe_commentary',
			'side'
		);
	}
);

add_action(
	'save_post_tbe_commentary',
	static function ( int $post_id ) {
		if ( ! isset( $_POST['tbe_pack_lock_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tbe_pack_lock_nonce'] ) ), 'tbe_pack_lock' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( empty( $_POST['tbe_pack_locked'] ) ) {
			delete_post_meta( $post_id, 'tbe_pack_locked' );
		} else {
			update_post_meta( $post_id, 'tbe_pack_locked', 1 );
		}
	}
);

add_action(
	'admin_notices',
	static function () {
		$notice = get_transient( 'tbe_sync_notice' );
		if ( ! is_array( $notice ) ) {
			return;
		}
		delete_transient( 'tbe_sync_notice' );
		printf(
			'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: pack name, 2: updated, 3: added, 4: moved to Trash */
					__( 'TBE brought "%1$s" in step with the theme: %2$d updated, %3$d added, %4$d moved to the Trash.', 'tbe-child' ),
					$notice['name'],
					$notice['updated'],
					$notice['created'],
					$notice['removed']
				)
			)
		);
	}
);

add_action(
	'admin_post_tbe_import_pack',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'tbe-child' ), 403 );
		}
		check_admin_referer( 'tbe_import_pack' );
		$packs = tbe_packs();
		$id    = sanitize_key( wp_unslash( $_POST['pack'] ?? '' ) );
		if ( ! isset( $packs[ $id ] ) ) {
			wp_die( esc_html__( 'Unknown pack.', 'tbe-child' ), 400 );
		}
		$status = isset( $_POST['publish'] ) ? 'publish' : 'draft';
		$result = tbe_import_pack( $packs[ $id ], $status );
		update_option( 'tbe_pack_sync_' . $id, md5( (string) wp_json_encode( $packs[ $id ] ) ), false );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'tbe-settings',
					'tab'         => 'starter',
					'tbe_created' => $result['created'],
					'tbe_skipped' => $result['skipped'],
					'tbe_failed'  => count( $result['failed'] ),
					'tbe_status'  => $status,
				),
				admin_url( 'themes.php' )
			)
		);
		exit;
	}
);

/** Settings tab. */
function tbe_render_starter_tab(): void {
	// phpcs:disable WordPress.Security.NonceVerification
	if ( isset( $_GET['tbe_created'] ) ) {
		$created = absint( $_GET['tbe_created'] );
		$skipped = absint( $_GET['tbe_skipped'] ?? 0 );
		$failed  = absint( $_GET['tbe_failed'] ?? 0 );
		$status  = isset( $_GET['tbe_status'] ) && 'publish' === $_GET['tbe_status'] ? __( 'published', 'tbe-child' ) : __( 'saved as drafts', 'tbe-child' );
		printf(
			'<div class="notice notice-success inline"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: number created, 2: draft or published, 3: number skipped, 4: number failed */
					__( '%1$d entries %2$s. %3$d were already there and left alone. %4$d could not be imported.', 'tbe-child' ),
					$created,
					$status,
					$skipped,
					$failed
				)
			)
		);
	}
	// phpcs:enable

	$packs = tbe_packs();
	if ( ! $packs ) {
		echo '<p>' . esc_html__( 'No starter packs are bundled with this theme.', 'tbe-child' ) . '</p>';
		return;
	}
	foreach ( $packs as $pack ) {
		$existing = get_posts(
			array(
				'post_type'   => 'tbe_commentary',
				'post_status' => 'any',
				'meta_key'    => 'tbe_pack_key', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'  => $pack['id'] . ':', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_compare' => 'LIKE',
				'numberposts' => -1,
			)
		);
		$published = count( array_filter( $existing, static fn( $p ) => 'publish' === $p->post_status ) );
		?>
		<h2><?php echo esc_html( (string) $pack['name'] ); ?></h2>
		<table class="tbe-status">
			<tr><th><?php esc_html_e( 'Source', 'tbe-child' ); ?></th><td><?php echo esc_html( (string) $pack['source'] ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Permission', 'tbe-child' ); ?></th><td><?php echo esc_html( (string) $pack['licence'] ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Entries in the pack', 'tbe-child' ); ?></th><td><?php echo (int) count( $pack['entries'] ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Already imported', 'tbe-child' ); ?></th><td><?php echo esc_html( sprintf( /* translators: 1: imported, 2: published */ __( '%1$d (%2$d published)', 'tbe-child' ), count( $existing ), $published ) ); ?></td></tr>
		</table>
		<p class="description"><?php esc_html_e( 'Each entry is attached to the first passage the author cites, filed under Critical Commentary, tagged with its topic, and credited to the author. They quote the author as written so you can weigh his argument against the text; the ESV shows on hover. Edit, split or delete any of them.', 'tbe-child' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tbe-importer">
			<input type="hidden" name="action" value="tbe_import_pack">
			<input type="hidden" name="pack" value="<?php echo esc_attr( (string) $pack['id'] ); ?>">
			<?php wp_nonce_field( 'tbe_import_pack' ); ?>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Import as drafts (recommended)', 'tbe-child' ); ?></button>
			<button type="submit" name="publish" value="1" class="button"><?php esc_html_e( 'Import and publish', 'tbe-child' ); ?></button>
		</form>
		<?php if ( ! empty( $pack['held_back'] ) ) : ?>
			<h3><?php esc_html_e( 'Left out on purpose', 'tbe-child' ); ?></h3>
			<ul class="tbe-held">
				<?php foreach ( $pack['held_back'] as $held ) : ?>
					<li><strong><?php echo esc_html( sprintf( '%d. %s', (int) $held['topic'], (string) $held['title'] ) ); ?></strong> — <?php echo esc_html( (string) $held['reason'] ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php
	}
}

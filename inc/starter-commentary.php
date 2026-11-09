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
	// The stored body as WordPress filtered it, so a later edit can be told from the theme's own text.
	update_post_meta( $id, 'tbe_pack_body', tbe_pack_body_hash( get_post( $id ) ) );
	delete_post_meta( $id, 'tbe_pack_ack' );
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
 * With a deadline (a microtime value) it stops there and reports complete = false, so a big pack never
 * runs a dashboard request out of time; the next call carries on where it stopped.
 *
 * @return array{created:int,skipped:int,failed:string[],complete:bool}
 */
function tbe_import_pack( array $pack, string $status, float $deadline = 0.0 ): array {
	$created  = 0;
	$skipped  = 0;
	$failed   = array();
	$complete = true;
	foreach ( $pack['entries'] as $entry ) {
		if ( $deadline && microtime( true ) > $deadline ) {
			$complete = false;
			break;
		}
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
	return array( 'created' => $created, 'skipped' => $skipped, 'failed' => $failed, 'complete' => $complete );
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

/** Fingerprint of an entry as stored: title and body. Compared with the one recorded when the theme wrote it. */
function tbe_pack_body_hash( ?WP_Post $post ): string {
	return $post ? md5( $post->post_title . "\n" . $post->post_content ) : '';
}

/**
 * Has the owner changed this entry since the theme wrote it? True when the stored body differs from the
 * fingerprint the sync recorded, and also when none was recorded (an entry imported by an older version):
 * without proof that it is untouched, it counts as edited and is never overwritten or trashed.
 */
function tbe_pack_is_edited( WP_Post $post ): bool {
	$recorded = (string) get_post_meta( $post->ID, 'tbe_pack_body', true );
	return '' === $recorded || tbe_pack_body_hash( $post ) !== $recorded;
}

/**
 * What a sync does with one bundled entry. Pure: it reads nothing and writes nothing.
 *
 * create  not imported yet
 * current the stored copy came from this version of the entry
 * update  untouched by the owner, so it takes the new text
 * newer   edited by the owner and the theme has newer text: the owner decides
 * kept    edited and already decided for this version, or edited and marked "Do not ask me": the
 *         entry stays as it is and is left out of the notice
 */
function tbe_pack_decide( bool $exists, string $entry_hash, string $stored_hash, bool $edited, bool $locked, string $ack ): string {
	if ( ! $exists ) {
		return 'create';
	}
	if ( $stored_hash === $entry_hash ) {
		return 'current';
	}
	if ( ! $edited ) {
		return 'update';
	}
	return ( $locked || $ack === $entry_hash ) ? 'kept' : 'newer';
}

/** What a sync does with an imported entry the bundle no longer carries: trash (untouched), or ask. */
function tbe_pack_decide_removed( bool $edited, bool $locked, string $ack ): string {
	if ( ! $edited ) {
		return 'trash';
	}
	return ( $locked || 'removed' === $ack ) ? 'kept' : 'removed';
}

/**
 * The plan for one imported pack, without changing anything.
 *
 * @return array<int,array{key:string,title:string,post_id:int,action:string}>
 */
function tbe_pack_plan( array $pack ): array {
	$have   = tbe_pack_posts( (string) $pack['id'] );
	$plan   = array();
	$wanted = array();
	foreach ( $pack['entries'] as $entry ) {
		$key            = (string) $entry['key'];
		$wanted[ $key ] = true;
		$post           = $have[ $key ] ?? null;
		$action         = tbe_pack_decide(
			(bool) $post,
			tbe_pack_entry_hash( $entry ),
			$post ? (string) get_post_meta( $post->ID, 'tbe_pack_hash', true ) : '',
			$post ? tbe_pack_is_edited( $post ) : false,
			$post ? (bool) get_post_meta( $post->ID, 'tbe_pack_locked', true ) : false,
			$post ? (string) get_post_meta( $post->ID, 'tbe_pack_ack', true ) : ''
		);
		$plan[] = array( 'key' => $key, 'title' => (string) $entry['title'], 'post_id' => $post ? (int) $post->ID : 0, 'action' => $action );
	}
	foreach ( $have as $key => $post ) {
		if ( isset( $wanted[ $key ] ) ) {
			continue;
		}
		$plan[] = array(
			'key'     => $key,
			'title'   => $post->post_title,
			'post_id' => (int) $post->ID,
			'action'  => tbe_pack_decide_removed( tbe_pack_is_edited( $post ), (bool) get_post_meta( $post->ID, 'tbe_pack_locked', true ), (string) get_post_meta( $post->ID, 'tbe_pack_ack', true ) ),
		);
	}
	return $plan;
}

/** Append to the change log (the last 100 lines). */
function tbe_pack_log( string $pack_id, string $key, string $action ): void {
	$log   = (array) get_option( 'tbe_pack_log', array() );
	$log[] = array( 't' => time(), 'pack' => $pack_id, 'key' => $key, 'action' => $action );
	update_option( 'tbe_pack_log', array_slice( $log, -100 ), false );
}

/** Rebuild the list of entries that wait for the owner (newer text, removed from the bundle) for one pack. */
function tbe_pack_set_pending( string $pack_id, array $plan ): void {
	$pending = (array) get_option( 'tbe_pack_pending', array() );
	unset( $pending[ $pack_id ] );
	foreach ( $plan as $item ) {
		if ( in_array( $item['action'], array( 'newer', 'removed' ), true ) ) {
			$pending[ $pack_id ][ $item['key'] ] = array( 'action' => $item['action'], 'title' => $item['title'] );
		}
	}
	update_option( 'tbe_pack_pending', array_filter( $pending ), false );
}

/**
 * Keep a pack that was imported in step with the theme. New entries arrive as drafts. An entry the owner
 * has not touched takes the new text, and one the bundle dropped goes to the Trash (never deleted). An entry
 * the owner edited is never overwritten and never trashed: it is listed in one notice, and the owner takes
 * the bundled text or keeps their own, entry by entry. A pack that was never imported is left alone.
 *
 * @return array{updated:int,created:int,removed:int,newer:int,dropped:int}|null
 */
function tbe_sync_pack( array $pack ): ?array {
	$have = tbe_pack_posts( (string) $pack['id'] );
	if ( ! $have ) {
		return null;
	}
	$plan    = tbe_pack_plan( $pack );
	$entries = array();
	foreach ( $pack['entries'] as $entry ) {
		$entries[ (string) $entry['key'] ] = $entry;
	}
	$result = array( 'updated' => 0, 'created' => 0, 'removed' => 0, 'newer' => 0, 'dropped' => 0 );
	foreach ( $plan as $item ) {
		switch ( $item['action'] ) {
			case 'create':
				if ( tbe_write_entry( 0, $entries[ $item['key'] ], 'draft' ) ) {
					++$result['created'];
					tbe_pack_log( (string) $pack['id'], $item['key'], 'created' );
				}
				break;
			case 'update':
				if ( tbe_write_entry( $item['post_id'], $entries[ $item['key'] ], 'draft' ) ) {
					++$result['updated'];
					tbe_pack_log( (string) $pack['id'], $item['key'], 'updated' );
				}
				break;
			case 'trash':
				wp_trash_post( $item['post_id'] );
				++$result['removed'];
				tbe_pack_log( (string) $pack['id'], $item['key'], 'trashed' );
				break;
			case 'newer':
				++$result['newer'];
				break;
			case 'removed':
				++$result['dropped'];
				break;
		}
	}
	tbe_pack_set_pending( (string) $pack['id'], tbe_pack_plan( $pack ) );
	return $result;
}

/**
 * Keep bundled packs in step with the theme. A pack that has never been handled is imported and
 * published as soon as the theme is activated (or first loaded in the admin after an upload).
 * A pack already imported follows the theme: an untouched entry takes the new text, new ones arrive as
 * drafts, an untouched dropped one goes to the Trash, and an entry the owner edited is only ever listed
 * (see tbe_sync_pack()). Switch off under Appearance → TBE → General.
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
		// A pack marked "manual" is never first-imported by the automatic sync: its entries need a human check,
		// so they come in from the Starter commentary tab, where the editor chooses drafts or published.
		$first_import = 'partial' === $seen || ( false === $seen && ! tbe_pack_posts( (string) $pack['id'] ) );
		if ( ! empty( $pack['manual'] ) && $first_import ) {
			continue;
		}
		if ( $first_import ) {
			// First import: published, in batches of about fifteen seconds; 'partial' marks one still under way.
			$done   = tbe_import_pack( $pack, 'publish', microtime( true ) + 15 );
			$result = array( 'updated' => 0, 'created' => $done['created'], 'removed' => 0, 'newer' => 0, 'dropped' => 0 );
			if ( ! $done['complete'] ) {
				update_option( $option, 'partial', false );
				continue;
			}
		} else {
			$result = tbe_sync_pack( $pack );
		}
		update_option( $option, $fingerprint, false );
		if ( $result && ( $result['updated'] ?? 0 ) + ( $result['created'] ?? 0 ) + ( $result['removed'] ?? 0 ) ) {
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
					esc_html__( 'Do not ask me about newer versions', 'tbe-child' ),
					esc_html__( 'A theme update never overwrites or trashes an entry you have edited. It lists the entry in a notice instead. Tick this to leave this entry out of that notice as well.', 'tbe-child' )
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
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$notice = get_transient( 'tbe_sync_notice' );
		if ( is_array( $notice ) ) {
			delete_transient( 'tbe_sync_notice' );
			printf(
				'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: pack name, 2: updated, 3: added, 4: moved to Trash */
						__( 'TBE brought "%1$s" in step with the theme: %2$d updated, %3$d added, %4$d moved to the Trash. Entries you edited were left as they are.', 'tbe-child' ),
						$notice['name'],
						$notice['updated'],
						$notice['created'],
						$notice['removed']
					)
				)
			);
		}
		// One grouped notice for everything that waits for a decision.
		$pending = (array) get_option( 'tbe_pack_pending', array() );
		$newer   = array();
		$removed = array();
		foreach ( $pending as $items ) {
			foreach ( (array) $items as $item ) {
				if ( 'newer' === ( $item['action'] ?? '' ) ) {
					$newer[] = (string) $item['title'];
				} else {
					$removed[] = (string) $item['title'];
				}
			}
		}
		if ( ! $newer && ! $removed ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'Starter commentary has changes for entries you edited. Nothing was overwritten or trashed.', 'tbe-child' ) . '</p>';
		if ( $newer ) {
			echo '<p>' . esc_html__( 'Newer version available:', 'tbe-child' ) . ' ' . esc_html( implode( '; ', $newer ) ) . '</p>';
		}
		if ( $removed ) {
			echo '<p>' . esc_html__( 'Removed from the bundle:', 'tbe-child' ) . ' ' . esc_html( implode( '; ', $removed ) ) . '</p>';
		}
		printf(
			'<p><a href="%s">%s</a></p></div>',
			esc_url( add_query_arg( array( 'page' => 'tbe-settings', 'tab' => 'starter' ), admin_url( 'themes.php' ) ) ),
			esc_html__( 'Review them under Starter commentary', 'tbe-child' )
		);
	}
);

/** Take the bundled text for one entry, or keep the owner's own. Both are logged. */
add_action(
	'admin_post_tbe_pack_item',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'tbe-child' ), 403 );
		}
		check_admin_referer( 'tbe_pack_item' );
		$packs  = tbe_packs();
		$id     = sanitize_key( wp_unslash( $_POST['pack'] ?? '' ) );
		$key    = sanitize_text_field( wp_unslash( $_POST['key'] ?? '' ) );
		$choice = 'take' === ( $_POST['choice'] ?? '' ) ? 'take' : 'keep';
		if ( ! isset( $packs[ $id ] ) ) {
			wp_die( esc_html__( 'Unknown pack.', 'tbe-child' ), 400 );
		}
		$have = tbe_pack_posts( $id );
		$post = $have[ $key ] ?? null;
		if ( $post ) {
			$entry = null;
			foreach ( $packs[ $id ]['entries'] as $candidate ) {
				if ( (string) $candidate['key'] === $key ) {
					$entry = $candidate;
				}
			}
			if ( $entry ) {
				if ( 'take' === $choice ) {
					tbe_write_entry( (int) $post->ID, $entry, 'draft' );
				} else {
					update_post_meta( $post->ID, 'tbe_pack_ack', tbe_pack_entry_hash( $entry ) );
				}
			} elseif ( 'take' === $choice ) {
				wp_trash_post( $post->ID );
			} else {
				update_post_meta( $post->ID, 'tbe_pack_ack', 'removed' );
			}
			tbe_pack_log( $id, $key, 'take' === $choice ? 'took the bundled text' : 'kept the owner text' );
			tbe_pack_set_pending( $id, tbe_pack_plan( $packs[ $id ] ) );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'tbe-settings', 'tab' => 'starter' ), admin_url( 'themes.php' ) ) );
		exit;
	}
);

/** Run the sync now, for every imported pack: the same safe rules as the automatic one. */
add_action(
	'admin_post_tbe_pack_sync_now',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'tbe-child' ), 403 );
		}
		check_admin_referer( 'tbe_pack_sync_now' );
		foreach ( tbe_packs() as $pack ) {
			// A pack that was never imported returns null and is left alone, fingerprint included.
			if ( null !== tbe_sync_pack( $pack ) ) {
				update_option( 'tbe_pack_sync_' . $pack['id'], md5( (string) wp_json_encode( $pack ) ), false );
			}
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'tbe-settings', 'tab' => 'starter' ), admin_url( 'themes.php' ) ) );
		exit;
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
	tbe_render_pack_dry_run( $packs );
}

/** The dry run: what the next sync would do, per entry, with the owner's choices, and the log. */
function tbe_render_pack_dry_run( array $packs ): void {
	$labels = array(
		'create'  => __( 'Would add as a draft', 'tbe-child' ),
		'update'  => __( 'Would update (you have not edited it)', 'tbe-child' ),
		'trash'   => __( 'Would move to the Trash (you have not edited it)', 'tbe-child' ),
		'newer'   => __( 'Newer version available (you edited it, so it stays as it is)', 'tbe-child' ),
		'removed' => __( 'Removed from the bundle (you edited it, so it stays)', 'tbe-child' ),
		'kept'    => __( 'Kept as it is', 'tbe-child' ),
	);
	echo '<h2>' . esc_html__( 'Next sync, as a dry run', 'tbe-child' ) . '</h2><p class="description">' . esc_html__( 'Nothing here has been changed. An entry you edited is never overwritten or trashed by a sync. For those, choose the bundled text or keep yours.', 'tbe-child' ) . '</p>';
	$any = false;
	foreach ( $packs as $pack ) {
		if ( ! tbe_pack_posts( (string) $pack['id'] ) ) {
			continue;
		}
		$rows = array_filter( tbe_pack_plan( $pack ), static fn( $i ) => 'current' !== $i['action'] );
		if ( ! $rows ) {
			continue;
		}
		$any = true;
		echo '<h3>' . esc_html( (string) $pack['name'] ) . '</h3><table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Entry', 'tbe-child' ) . '</th><th scope="col">' . esc_html__( 'The next sync', 'tbe-child' ) . '</th><th scope="col">' . esc_html__( 'Your choice', 'tbe-child' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr><td>' . esc_html( $row['title'] ) . '</td><td>' . esc_html( $labels[ $row['action'] ] ?? $row['action'] ) . '</td><td>';
			if ( in_array( $row['action'], array( 'newer', 'removed', 'kept' ), true ) && $row['post_id'] ) {
				foreach ( array( 'take' => ( 'removed' === $row['action'] ? __( 'Move to the Trash', 'tbe-child' ) : __( 'Take the bundled text', 'tbe-child' ) ), 'keep' => __( 'Keep mine', 'tbe-child' ) ) as $choice => $text ) {
					printf(
						'<form method="post" action="%1$s" style="display:inline"><input type="hidden" name="action" value="tbe_pack_item"><input type="hidden" name="pack" value="%2$s"><input type="hidden" name="key" value="%3$s"><input type="hidden" name="choice" value="%4$s">%5$s<button type="submit" class="button button-small">%6$s</button></form> ',
						esc_url( admin_url( 'admin-post.php' ) ),
						esc_attr( (string) $pack['id'] ),
						esc_attr( $row['key'] ),
						esc_attr( $choice ),
						wp_nonce_field( 'tbe_pack_item', '_wpnonce', true, false ),
						esc_html( $text )
					);
				}
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}
	if ( ! $any ) {
		echo '<p>' . esc_html__( 'Every imported entry matches the bundle.', 'tbe-child' ) . '</p>';
	} else {
		printf(
			'<form method="post" action="%s"><input type="hidden" name="action" value="tbe_pack_sync_now">%s<button type="submit" class="button">%s</button></form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			wp_nonce_field( 'tbe_pack_sync_now', '_wpnonce', true, false ),
			esc_html__( 'Run the sync now', 'tbe-child' )
		);
	}
	$log = array_reverse( (array) get_option( 'tbe_pack_log', array() ) );
	echo '<h2>' . esc_html__( 'What changed', 'tbe-child' ) . '</h2>';
	if ( ! $log ) {
		echo '<p>' . esc_html__( 'Nothing yet.', 'tbe-child' ) . '</p>';
		return;
	}
	echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'When', 'tbe-child' ) . '</th><th scope="col">' . esc_html__( 'Entry', 'tbe-child' ) . '</th><th scope="col">' . esc_html__( 'What happened', 'tbe-child' ) . '</th></tr></thead><tbody>';
	foreach ( array_slice( $log, 0, 30 ) as $line ) {
		echo '<tr><td>' . esc_html( wp_date( 'Y-m-d H:i', (int) $line['t'] ) ) . '</td><td>' . esc_html( (string) $line['key'] ) . '</td><td>' . esc_html( (string) $line['action'] ) . '</td></tr>';
	}
	echo '</tbody></table>';
}

<?php
/**
 * Commentary entry fields: textual variants table and Qur'an cross-references.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

/** @return array<int,array{unit:string,reading:string,witnesses:string,note:string}> */
function tbe_get_variants( int $post_id ): array {
	$rows = get_post_meta( $post_id, 'tbe_variants', true );
	return is_array( $rows ) ? array_values( $rows ) : array();
}

/** @return array<int,array{ref:string,note:string}> */
function tbe_get_quran_refs( int $post_id ): array {
	$rows = get_post_meta( $post_id, 'tbe_quran', true );
	return is_array( $rows ) ? array_values( $rows ) : array();
}

/* Meta boxes -------------------------------------------------------------- */

add_action(
	'add_meta_boxes',
	static function () {
		add_meta_box( 'tbe_variants', __( 'Textual variants', 'tbe-child' ), 'tbe_variants_box', 'tbe_commentary', 'normal', 'default' );
		add_meta_box( 'tbe_quran', __( 'Qur\'an cross-references', 'tbe-child' ), 'tbe_quran_box', 'tbe_commentary', 'normal', 'default' );
	}
);

add_action(
	'admin_enqueue_scripts',
	static function ( $hook ) {
		$screen = get_current_screen();
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || ! $screen || 'tbe_commentary' !== $screen->post_type ) {
			return;
		}
		wp_enqueue_style( 'tbe-admin', TBE_URI . '/assets/css/admin.css', array(), TBE_VERSION );
		wp_enqueue_script( 'tbe-admin-entry', TBE_URI . '/assets/js/admin-entry.js', array(), TBE_VERSION, array( 'in_footer' => true ) );
	}
);

function tbe_variants_box( WP_Post $post ): void {
	wp_nonce_field( 'tbe_entry', 'tbe_entry_nonce' );
	$rows = tbe_get_variants( $post->ID );
	?>
	<p class="description"><?php esc_html_e( 'One row per reading. Rows with the same variation unit are grouped in the table.', 'tbe-child' ); ?></p>
	<div class="tbe-repeater" data-repeater="variants">
		<?php foreach ( $rows as $i => $row ) : ?>
			<?php tbe_variant_row( (string) $i, $row ); ?>
		<?php endforeach; ?>
	</div>
	<template id="tbe-variants-template"><?php tbe_variant_row( '__i__', array() ); ?></template>
	<p><button type="button" class="button" data-add="variants"><?php esc_html_e( 'Add reading', 'tbe-child' ); ?></button></p>
	<?php
}

function tbe_variant_row( string $i, array $row ): void {
	$row = wp_parse_args( $row, array( 'unit' => '', 'reading' => '', 'witnesses' => '', 'note' => '' ) );
	?>
	<div class="tbe-row">
		<input type="text" name="tbe_variants[<?php echo esc_attr( $i ); ?>][unit]" value="<?php echo esc_attr( $row['unit'] ); ?>" placeholder="<?php esc_attr_e( 'Variation unit, e.g. John 1:18', 'tbe-child' ); ?>">
		<input type="text" name="tbe_variants[<?php echo esc_attr( $i ); ?>][reading]" value="<?php echo esc_attr( $row['reading'] ); ?>" placeholder="<?php esc_attr_e( 'Reading', 'tbe-child' ); ?>">
		<input type="text" name="tbe_variants[<?php echo esc_attr( $i ); ?>][witnesses]" value="<?php echo esc_attr( $row['witnesses'] ); ?>" placeholder="<?php esc_attr_e( 'Witnesses (manuscripts, versions, fathers)', 'tbe-child' ); ?>">
		<textarea name="tbe_variants[<?php echo esc_attr( $i ); ?>][note]" rows="2" placeholder="<?php esc_attr_e( 'Notes', 'tbe-child' ); ?>"><?php echo esc_textarea( $row['note'] ); ?></textarea>
		<button type="button" class="button-link tbe-remove" aria-label="<?php esc_attr_e( 'Remove row', 'tbe-child' ); ?>">&times;</button>
	</div>
	<?php
}

function tbe_quran_box( WP_Post $post ): void {
	$rows = tbe_get_quran_refs( $post->ID );
	?>
	<p class="description"><?php esc_html_e( 'Reference only, e.g. 2:255, 112:1-4 or 19 for a whole surah. Each reference is checked against the 114 surahs and links to quran.com.', 'tbe-child' ); ?></p>
	<div class="tbe-repeater" data-repeater="quran">
		<?php foreach ( $rows as $i => $row ) : ?>
			<?php tbe_quran_row( (string) $i, $row ); ?>
		<?php endforeach; ?>
	</div>
	<template id="tbe-quran-template"><?php tbe_quran_row( '__i__', array() ); ?></template>
	<p><button type="button" class="button" data-add="quran"><?php esc_html_e( 'Add reference', 'tbe-child' ); ?></button></p>
	<?php
}

function tbe_quran_row( string $i, array $row ): void {
	$row = wp_parse_args( $row, array( 'ref' => '', 'note' => '' ) );
	?>
	<div class="tbe-row tbe-row--quran">
		<input type="text" name="tbe_quran[<?php echo esc_attr( $i ); ?>][ref]" value="<?php echo esc_attr( $row['ref'] ); ?>" placeholder="<?php esc_attr_e( '112:1-4', 'tbe-child' ); ?>">
		<input type="text" name="tbe_quran[<?php echo esc_attr( $i ); ?>][note]" value="<?php echo esc_attr( $row['note'] ); ?>" placeholder="<?php esc_attr_e( 'Why it matters here (optional)', 'tbe-child' ); ?>">
		<button type="button" class="button-link tbe-remove" aria-label="<?php esc_attr_e( 'Remove row', 'tbe-child' ); ?>">&times;</button>
	</div>
	<?php
}

/* Saving ------------------------------------------------------------------ */

add_action(
	'save_post_tbe_commentary',
	static function ( int $post_id ) {
		if ( ! isset( $_POST['tbe_entry_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tbe_entry_nonce'] ) ), 'tbe_entry' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$variants = array();
		foreach ( (array) wp_unslash( $_POST['tbe_variants'] ?? array() ) as $row ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$clean = array(
				'unit'      => sanitize_text_field( $row['unit'] ?? '' ),
				'reading'   => sanitize_text_field( $row['reading'] ?? '' ),
				'witnesses' => sanitize_text_field( $row['witnesses'] ?? '' ),
				'note'      => sanitize_textarea_field( $row['note'] ?? '' ),
			);
			if ( '' !== $clean['reading'] || '' !== $clean['witnesses'] ) {
				$variants[] = $clean;
			}
		}
		if ( $variants ) {
			update_post_meta( $post_id, 'tbe_variants', $variants );
		} else {
			delete_post_meta( $post_id, 'tbe_variants' );
		}

		$quran   = array();
		$invalid = array();
		foreach ( (array) wp_unslash( $_POST['tbe_quran'] ?? array() ) as $row ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$text = sanitize_text_field( $row['ref'] ?? '' );
			if ( '' === $text ) {
				continue;
			}
			$parsed = tbe_parse_quran_ref( $text );
			if ( $parsed ) {
				$quran[] = array(
					'ref'  => ( $parsed['from'] ? $parsed['surah'] . ':' . $parsed['from'] . ( $parsed['to'] > $parsed['from'] ? '-' . $parsed['to'] : '' ) : (string) $parsed['surah'] ),
					'note' => sanitize_text_field( $row['note'] ?? '' ),
				);
			} else {
				$invalid[] = $text;
			}
		}
		if ( $quran ) {
			update_post_meta( $post_id, 'tbe_quran', $quran );
		} else {
			delete_post_meta( $post_id, 'tbe_quran' );
		}
		if ( $invalid ) {
			set_transient( 'tbe_quran_invalid_' . get_current_user_id(), $invalid, 60 );
		}
	},
	20
);

add_action(
	'admin_notices',
	static function () {
		$key     = 'tbe_quran_invalid_' . get_current_user_id();
		$invalid = get_transient( $key );
		if ( ! $invalid ) {
			return;
		}
		delete_transient( $key );
		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s <code>%s</code></p></div>',
			esc_html__( 'These Qur\'an references were not recognised and were not saved:', 'tbe-child' ),
			esc_html( implode( ', ', $invalid ) )
		);
	}
);

/* Front-end rendering ----------------------------------------------------- */

function tbe_render_variants( int $post_id ): string {
	$rows = tbe_get_variants( $post_id );
	if ( ! $rows ) {
		return '';
	}
	$groups = array();
	foreach ( $rows as $row ) {
		$groups[ $row['unit'] ][] = $row;
	}
	$out  = '<section class="tbe-variants"><h4 class="tbe-sub">' . esc_html__( 'Textual variants', 'tbe-child' ) . '</h4>';
	$out .= '<div class="tbe-tablewrap" role="region" tabindex="0" aria-label="' . esc_attr__( 'Textual variants table', 'tbe-child' ) . '"><table class="tbe-table"><thead><tr>';
	$out .= '<th scope="col">' . esc_html__( 'Reading', 'tbe-child' ) . '</th><th scope="col">' . esc_html__( 'Witnesses', 'tbe-child' ) . '</th><th scope="col">' . esc_html__( 'Notes', 'tbe-child' ) . '</th></tr></thead><tbody>';
	foreach ( $groups as $unit => $items ) {
		if ( '' !== $unit ) {
			$out .= '<tr class="tbe-unit"><th colspan="3" scope="colgroup">' . esc_html( (string) $unit ) . '</th></tr>';
		}
		foreach ( $items as $row ) {
			$out .= '<tr><td class="tbe-reading">' . esc_html( $row['reading'] ) . '</td><td>' . tbe_annotate_terms( esc_html( $row['witnesses'] ) ) . '</td><td>' . tbe_enrich_commentary( esc_html( $row['note'] ) ) . '</td></tr>';
		}
	}
	return $out . '</tbody></table></div></section>';
}

function tbe_render_quran_refs( int $post_id ): string {
	$rows = tbe_get_quran_refs( $post_id );
	if ( ! $rows ) {
		return '';
	}
	$out = '<section class="tbe-quranrefs"><h4 class="tbe-sub">' . esc_html__( 'Qur\'an cross-references', 'tbe-child' ) . '</h4><ul>';
	foreach ( $rows as $row ) {
		$ref = tbe_parse_quran_ref( (string) $row['ref'] );
		if ( ! $ref ) {
			continue;
		}
		$out .= '<li><a class="tbe-ref tbe-ref--quran" href="' . esc_url( tbe_quran_url( $ref ) ) . '" target="_blank" rel="noopener">' . esc_html( tbe_quran_label( $ref ) ) . '</a>';
		if ( '' !== $row['note'] ) {
			$out .= ' <span class="tbe-muted">— ' . tbe_annotate_terms( esc_html( $row['note'] ) ) . '</span>';
		}
		$out .= '</li>';
	}
	return $out . '</ul></section>';
}

/** Variants table and Qur'an references for one entry. */
function tbe_render_entry_extras( int $post_id ): string {
	return tbe_render_variants( $post_id ) . tbe_render_quran_refs( $post_id );
}

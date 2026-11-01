<?php
/**
 * Theme settings: tabbed admin screen (Appearance → TBE).
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

/** Colour roles editable by the site owner. */
function tbe_color_roles(): array {
	return array(
		'paper'   => __( 'Page background', 'tbe-child' ),
		'surface' => __( 'Panels and cards', 'tbe-child' ),
		'ink'     => __( 'Text', 'tbe-child' ),
		'muted'   => __( 'Secondary text', 'tbe-child' ),
		'accent'  => __( 'Accent (links, buttons)', 'tbe-child' ),
		'rubric'  => __( 'Verse numbers and rubrics', 'tbe-child' ),
		'gold'    => __( 'Ornament', 'tbe-child' ),
		'line'    => __( 'Borders', 'tbe-child' ),
	);
}

/** Built-in colour schemes. */
function tbe_schemes(): array {
	return array(
		'manuscript' => array(
			'label'  => __( 'Manuscript — cream, burgundy and gold', 'tbe-child' ),
			'colors' => array(
				'paper'   => '#f4eedc',
				'surface' => '#fbf7ea',
				'ink'     => '#231d14',
				'muted'   => '#6b604f',
				'accent'  => '#6e1f2e',
				'rubric'  => '#9b2c2c',
				'gold'    => '#a67c2e',
				'line'    => '#d9cdb0',
			),
		),
		'emerald'    => array(
			'label'  => __( 'Emerald — cream and green', 'tbe-child' ),
			'colors' => array(
				'paper'   => '#f4eedc',
				'surface' => '#fbf7ea',
				'ink'     => '#231d14',
				'muted'   => '#6b604f',
				'accent'  => '#17604e',
				'rubric'  => '#8c2f23',
				'gold'    => '#a67c2e',
				'line'    => '#d9cdb0',
			),
		),
		'vellum'     => array(
			'label'  => __( 'Vellum — parchment and teal', 'tbe-child' ),
			'colors' => array(
				'paper'   => '#efe6cf',
				'surface' => '#f8f1dc',
				'ink'     => '#2a2118',
				'muted'   => '#6e6250',
				'accent'  => '#1d5a63',
				'rubric'  => '#8a3b2a',
				'gold'    => '#9c7a2b',
				'line'    => '#d3c6a6',
			),
		),
		'night'      => array(
			'label'  => __( 'Night study — dark', 'tbe-child' ),
			'colors' => tbe_dark_colors(),
		),
	);
}

/** Palette used for dark mode (toggle and the "Night study" scheme). */
function tbe_dark_colors(): array {
	return array(
		'paper'   => '#17120f',
		'surface' => '#201915',
		'ink'     => '#ece3cf',
		'muted'   => '#a79c88',
		'accent'  => '#d4a24c',
		'rubric'  => '#d9786b',
		'gold'    => '#d1a64c',
		'line'    => '#3a2f27',
	);
}

function tbe_defaults(): array {
	return array(
		// General.
		'tagline'          => __( 'Textual criticism, critical commentary and reflection on the Bible.', 'tbe-child' ),
		'allow_toggle'     => 1,
		'schema'           => 1,
		'pack_sync'        => 1,
		// Appearance.
		'scheme'           => 'manuscript',
		'colors'           => array(),
		// Reader and commentary.
		'font_scale'       => 100,
		'verse_numbers'    => 1,
		'layout'           => 'verse',
		'boxes_open'       => 1,
		'glossary_tooltips' => 1,
		'ref_cards'        => 1,
		'label_textual'    => __( 'Textual Criticism', 'tbe-child' ),
		'label_critical'   => __( 'Critical Commentary', 'tbe-child' ),
		'label_islamic'    => __( 'Islamic Perspective (Tafsir)', 'tbe-child' ),
		'label_reflection' => __( 'Reflection (Tadabbur)', 'tbe-child' ),
		// ESV and licence.
		'esv_live_fallback' => 1,
		'copyright'        => 'Scripture quotations are from the ESV® Bible (The Holy Bible, English Standard Version®), © 2001 by Crossway, a publishing ministry of Good News Publishers. Used by permission. All rights reserved. The ESV text may not be quoted in any publication made available to the public by a Creative Commons license. The ESV may not be translated into any other language.',
	);
}

/** All settings merged with defaults. */
function tbe_settings(): array {
	static $cache = null;
	if ( null === $cache ) {
		$saved = get_option( 'tbe_settings', array() );
		$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), tbe_defaults() );
	}
	return $cache;
}

function tbe_setting( string $key ) {
	$all = tbe_settings();
	return $all[ $key ] ?? null;
}

/** The active colour set: preset, or the owner's custom colours layered over the default. */
function tbe_active_colors(): array {
	$schemes = tbe_schemes();
	$scheme  = (string) tbe_setting( 'scheme' );
	if ( isset( $schemes[ $scheme ] ) ) {
		return $schemes[ $scheme ]['colors'];
	}
	$custom = (array) tbe_setting( 'colors' );
	return array_merge( $schemes['manuscript']['colors'], array_filter( $custom ) );
}

/** CSS custom properties for the active scheme (the only dynamic CSS the theme emits). */
function tbe_scheme_css(): string {
	$css = ':root{';
	foreach ( tbe_active_colors() as $role => $hex ) {
		$css .= '--tbe-' . $role . ':' . $hex . ';';
	}
	$scale = max( 80, min( 140, (int) tbe_setting( 'font_scale' ) ) );
	$css  .= '--tbe-scale:' . ( $scale / 100 ) . ';}';
	if ( 'night' === tbe_setting( 'scheme' ) ) {
		$css .= 'html:not([data-theme="light"]){color-scheme:dark;}';
		$css .= 'html:not([data-theme="light"]) .tbe-footer{--tbe-f-bg:color-mix(in srgb,var(--tbe-gold) 9%,#0b0807);}';
	}
	return $css;
}

/* -------------------------------------------------------------------------
 * Admin screen
 * ---------------------------------------------------------------------- */

add_action(
	'admin_menu',
	static function () {
		add_theme_page(
			__( 'TBE', 'tbe-child' ),
			__( 'TBE', 'tbe-child' ),
			'manage_options',
			'tbe-settings',
			'tbe_render_settings_page'
		);
	}
);

add_action(
	'admin_init',
	static function () {
		register_setting(
			'tbe_settings_group',
			'tbe_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => 'tbe_sanitize_settings',
				'default'           => array(),
			)
		);
	}
);

add_action(
	'admin_enqueue_scripts',
	static function ( $hook ) {
		if ( 'appearance_page_tbe-settings' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'tbe-admin', TBE_URI . '/assets/css/admin.css', array(), TBE_VERSION );
		wp_enqueue_script( 'tbe-admin', TBE_URI . '/assets/js/admin.js', array(), TBE_VERSION, array( 'in_footer' => true ) );
		wp_localize_script( 'tbe-admin', 'tbeAdmin', array( 'schemes' => tbe_schemes() ) );
	}
);

function tbe_settings_tabs(): array {
	return array(
		'general'    => __( 'General', 'tbe-child' ),
		'appearance' => __( 'Appearance', 'tbe-child' ),
		'reader'     => __( 'Reader and commentary', 'tbe-child' ),
		'esv'        => __( 'ESV and licence', 'tbe-child' ),
		'starter'    => __( 'Starter commentary', 'tbe-child' ),
	);
}

/**
 * Only the submitted tab is changed; everything else is preserved.
 *
 * @param mixed $input Raw submitted value.
 */
function tbe_sanitize_settings( $input ): array {
	$input = is_array( $input ) ? wp_unslash( $input ) : array();
	$new   = get_option( 'tbe_settings', array() );
	$new   = is_array( $new ) ? $new : array();
	$tab   = sanitize_key( $input['_tab'] ?? '' );
	$flag  = static function ( string $key ) use ( $input ): int {
		return empty( $input[ $key ] ) ? 0 : 1;
	};

	switch ( $tab ) {
		case 'general':
			$new['tagline']        = sanitize_text_field( $input['tagline'] ?? '' );
			$new['allow_toggle']   = $flag( 'allow_toggle' );
			$new['schema']         = $flag( 'schema' );
			$new['pack_sync']      = $flag( 'pack_sync' );
			break;

		case 'appearance':
			$scheme        = sanitize_key( $input['scheme'] ?? 'manuscript' );
			$new['scheme'] = ( 'custom' === $scheme || isset( tbe_schemes()[ $scheme ] ) ) ? $scheme : 'manuscript';
			$colors        = array();
			foreach ( array_keys( tbe_color_roles() ) as $role ) {
				$hex = sanitize_hex_color( $input['colors'][ $role ] ?? '' );
				if ( $hex ) {
					$colors[ $role ] = $hex;
				}
			}
			$new['colors'] = $colors;
			break;

		case 'reader':
			$new['font_scale']       = max( 80, min( 140, (int) ( $input['font_scale'] ?? 100 ) ) );
			$new['verse_numbers']    = $flag( 'verse_numbers' );
			$new['layout']           = 'flow' === ( $input['layout'] ?? '' ) ? 'flow' : 'verse';
			$new['boxes_open']       = $flag( 'boxes_open' );
			$new['glossary_tooltips'] = $flag( 'glossary_tooltips' );
			$new['ref_cards']        = $flag( 'ref_cards' );
			foreach ( array( 'label_textual', 'label_critical', 'label_islamic', 'label_reflection' ) as $key ) {
				$label        = sanitize_text_field( $input[ $key ] ?? '' );
				$new[ $key ] = '' !== $label ? $label : tbe_defaults()[ $key ];
			}
			break;

		case 'esv':
			$new['esv_live_fallback'] = $flag( 'esv_live_fallback' );
			$new['copyright']         = wp_kses_post( $input['copyright'] ?? '' );
			break;
	}

	return $new;
}

function tbe_render_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$tabs = tbe_settings_tabs();
	$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification
	$tab  = isset( $tabs[ $tab ] ) ? $tab : 'general';
	$s    = tbe_settings();
	?>
	<div class="wrap tbe-admin">
		<h1><?php esc_html_e( 'TBE settings', 'tbe-child' ); ?></h1>

		<nav class="tbe-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'tbe-child' ); ?>">
			<?php foreach ( $tabs as $slug => $label ) : ?>
				<a class="tbe-tab<?php echo $slug === $tab ? ' is-active' : ''; ?>"
					href="<?php echo esc_url( add_query_arg( array( 'page' => 'tbe-settings', 'tab' => $slug ), admin_url( 'themes.php' ) ) ); ?>"
					<?php echo $slug === $tab ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>

		<?php settings_errors(); ?>

		<?php if ( 'starter' === $tab ) : ?>
			<div class="tbe-panel"><?php tbe_render_starter_tab(); ?></div>
		<?php else : ?>
		<form method="post" action="options.php" class="tbe-panel">
			<?php settings_fields( 'tbe_settings_group' ); ?>
			<input type="hidden" name="tbe_settings[_tab]" value="<?php echo esc_attr( $tab ); ?>">

			<?php
			switch ( $tab ) {
				case 'general':
					tbe_field_text( 'tagline', __( 'Tagline', 'tbe-child' ), $s['tagline'], __( 'Shown on the home page hero.', 'tbe-child' ) );
					tbe_field_check( 'schema', __( 'Output schema.org structured data (JSON-LD) for search engines', 'tbe-child' ), $s['schema'] );
					tbe_field_check( 'allow_toggle', __( 'Show the light / dark toggle to visitors', 'tbe-child' ), $s['allow_toggle'] );
					tbe_field_check( 'pack_sync', __( 'Keep imported starter commentary in step with the theme: a changed entry overwrites the old one, and an entry the theme no longer includes goes to the Trash', 'tbe-child' ), $s['pack_sync'] );
					break;

				case 'appearance':
					tbe_render_appearance_tab( $s );
					break;

				case 'reader':
					tbe_field_number( 'font_scale', __( 'Reading size (%)', 'tbe-child' ), $s['font_scale'], 80, 140, __( '100 is the default. Visitors can adjust size themselves.', 'tbe-child' ) );
					tbe_field_check( 'verse_numbers', __( 'Show verse numbers', 'tbe-child' ), $s['verse_numbers'] );
					printf(
						'<div class="tbe-field"><label for="tbe-layout">%1$s</label><select id="tbe-layout" name="tbe_settings[layout]"><option value="verse" %2$s>%3$s</option><option value="flow" %4$s>%5$s</option></select></div>',
						esc_html__( 'Default text layout', 'tbe-child' ),
						selected( $s['layout'], 'verse', false ),
						esc_html__( 'Verse by verse, with notes under each verse', 'tbe-child' ),
						selected( $s['layout'], 'flow', false ),
						esc_html__( 'Continuous paragraphs', 'tbe-child' )
					);
					tbe_field_check( 'boxes_open', __( 'Show commentary boxes open by default', 'tbe-child' ), $s['boxes_open'] );
					tbe_field_check( 'glossary_tooltips', __( 'Underline glossary terms in commentary and show a tooltip', 'tbe-child' ), $s['glossary_tooltips'] );
					tbe_field_check( 'ref_cards', __( 'Turn scripture references in commentary into links with a verse preview', 'tbe-child' ), $s['ref_cards'] );
					echo '<h2>' . esc_html__( 'Commentary section names', 'tbe-child' ) . '</h2>';
					tbe_field_text( 'label_textual', __( 'Textual criticism', 'tbe-child' ), $s['label_textual'] );
					tbe_field_text( 'label_critical', __( 'Critical commentary', 'tbe-child' ), $s['label_critical'] );
					tbe_field_text( 'label_islamic', __( 'Islamic perspective', 'tbe-child' ), $s['label_islamic'] );
					tbe_field_text( 'label_reflection', __( 'Reflection', 'tbe-child' ), $s['label_reflection'], __( 'The Christian parallel to tadabbur has no exact name; "Reflection" or "Meditation" both work.', 'tbe-child' ) );
					break;

				case 'esv':
					tbe_render_esv_tab( $s );
					break;
			}
			submit_button();
			?>
		</form>
		<?php endif; ?>
	</div>
	<?php
}

function tbe_render_appearance_tab( array $s ): void {
	$schemes = tbe_schemes();
	$current = (string) $s['scheme'];
	$active  = tbe_active_colors();
	?>
	<fieldset class="tbe-schemes">
		<legend class="screen-reader-text"><?php esc_html_e( 'Colour scheme', 'tbe-child' ); ?></legend>
		<?php foreach ( $schemes + array( 'custom' => array( 'label' => __( 'Custom', 'tbe-child' ), 'colors' => $active ) ) as $slug => $scheme ) : ?>
			<label class="tbe-scheme">
				<input type="radio" name="tbe_settings[scheme]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $current, $slug ); ?>>
				<span class="tbe-scheme__name"><?php echo esc_html( $scheme['label'] ); ?></span>
				<span class="tbe-scheme__swatches" data-scheme="<?php echo esc_attr( $slug ); ?>">
					<?php foreach ( array( 'paper', 'surface', 'ink', 'accent', 'rubric', 'gold' ) as $role ) : ?>
						<i data-role="<?php echo esc_attr( $role ); ?>" style="background:<?php echo esc_attr( $scheme['colors'][ $role ] ); ?>"></i>
					<?php endforeach; ?>
				</span>
			</label>
		<?php endforeach; ?>
	</fieldset>

	<div class="tbe-custom" data-visible-when="custom">
		<h2><?php esc_html_e( 'Custom colours', 'tbe-child' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Used when "Custom" is selected. Every role can be changed independently.', 'tbe-child' ); ?></p>
		<div class="tbe-grid">
			<?php foreach ( tbe_color_roles() as $role => $label ) : ?>
				<label class="tbe-color">
					<span><?php echo esc_html( $label ); ?></span>
					<input type="color" name="tbe_settings[colors][<?php echo esc_attr( $role ); ?>]" data-role="<?php echo esc_attr( $role ); ?>" value="<?php echo esc_attr( $active[ $role ] ); ?>">
				</label>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}

function tbe_render_esv_tab( array $s ): void {
	$token = TBE_ESV_API::token_source();
	$have  = TBE_Bible::local_chapter_count();
	$total = TBE_Bible::total_chapters();
	?>
	<table class="tbe-status">
		<tr>
			<th><?php esc_html_e( 'API token', 'tbe-child' ); ?></th>
			<td><?php echo $token ? esc_html( sprintf( /* translators: %s: where the token comes from */ __( 'Found (%s)', 'tbe-child' ), $token ) ) : esc_html__( 'Not set. Define TBE_ESV_API_TOKEN in wp-config.php.', 'tbe-child' ); ?></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Local ESV text', 'tbe-child' ); ?></th>
			<td><?php echo esc_html( sprintf( /* translators: 1: chapters present, 2: total */ __( '%1$d of %2$d chapters', 'tbe-child' ), $have, $total ) ); ?></td>
		</tr>
	</table>
	<?php
	tbe_field_check( 'esv_live_fallback', __( 'Fetch a chapter live from the ESV API if it is missing locally', 'tbe-child' ), $s['esv_live_fallback'] );
	?>
	<div class="tbe-field">
		<label for="tbe-copyright"><?php esc_html_e( 'Copyright notice shown with Scripture', 'tbe-child' ); ?></label>
		<textarea id="tbe-copyright" name="tbe_settings[copyright]" rows="6" class="large-text"><?php echo esc_textarea( $s['copyright'] ); ?></textarea>
		<p class="description"><?php esc_html_e( 'Use the exact wording required by your Crossway licence.', 'tbe-child' ); ?></p>
	</div>
	<?php
}

/* Field helpers ----------------------------------------------------------- */

function tbe_field_text( string $key, string $label, $value, string $help = '', string $class = '' ): void {
	printf(
		'<div class="tbe-field"><label for="tbe-%1$s">%2$s</label><input type="text" id="tbe-%1$s" name="tbe_settings[%1$s]" value="%3$s" class="regular-text %5$s">%4$s</div>',
		esc_attr( $key ),
		esc_html( $label ),
		esc_attr( (string) $value ),
		$help ? '<p class="description">' . esc_html( $help ) . '</p>' : '',
		esc_attr( $class )
	);
}

function tbe_field_number( string $key, string $label, $value, int $min, int $max, string $help = '' ): void {
	printf(
		'<div class="tbe-field"><label for="tbe-%1$s">%2$s</label><input type="number" id="tbe-%1$s" name="tbe_settings[%1$s]" value="%3$d" min="%4$d" max="%5$d" class="small-text">%6$s</div>',
		esc_attr( $key ),
		esc_html( $label ),
		(int) $value,
		$min,
		$max,
		$help ? '<p class="description">' . esc_html( $help ) . '</p>' : ''
	);
}

function tbe_field_check( string $key, string $label, $value ): void {
	printf(
		'<div class="tbe-field"><label><input type="checkbox" name="tbe_settings[%1$s]" value="1" %3$s> %2$s</label></div>',
		esc_attr( $key ),
		esc_html( $label ),
		checked( ! empty( $value ), true, false )
	);
}

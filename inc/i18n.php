<?php
/**
 * Interface languages: English, Bahasa Melayu and Arabic (right-to-left).
 * The visitor's choice is kept in a cookie. Scripture stays English (ESV).
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

/** @return array<string,array{label:string,dir:string,short:string}> */
function tbe_languages(): array {
	return array(
		'en_US' => array( 'label' => 'English', 'dir' => 'ltr', 'short' => 'EN' ),
		'ms_MY' => array( 'label' => 'Bahasa Melayu', 'dir' => 'ltr', 'short' => 'BM' ),
		'ar'    => array( 'label' => 'العربية', 'dir' => 'rtl', 'short' => 'ع' ),
	);
}

/** The language the visitor asked for, or null to keep the site language. */
function tbe_requested_language(): ?string {
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || defined( 'WP_CLI' ) ) {
		return null;
	}
	$langs = tbe_languages();
	$code  = '';
	if ( isset( $_GET['tbe_lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$code = sanitize_text_field( wp_unslash( $_GET['tbe_lang'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	} elseif ( isset( $_COOKIE['tbe_lang'] ) ) {
		$code = sanitize_text_field( wp_unslash( $_COOKIE['tbe_lang'] ) );
	}
	return isset( $langs[ $code ] ) ? $code : null;
}

add_filter(
	'locale',
	static function ( $locale ) {
		return tbe_requested_language() ?: $locale;
	},
	20
);

add_action(
	'after_setup_theme',
	static function () {
		load_child_theme_textdomain( 'tbe-child', TBE_DIR . '/languages' );
	}
);

/** Remember the choice, and make WordPress treat Arabic as right-to-left. */
add_action(
	'init',
	static function () {
		$code = tbe_requested_language();
		if ( ! $code ) {
			return;
		}
		if ( isset( $_GET['tbe_lang'] ) && ! headers_sent() ) { // phpcs:ignore WordPress.Security.NonceVerification
			setcookie(
				'tbe_lang',
				$code,
				array(
					'expires'  => time() + YEAR_IN_SECONDS,
					'path'     => COOKIEPATH ?: '/',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}
		global $wp_locale;
		if ( $wp_locale instanceof WP_Locale ) {
			$wp_locale->text_direction = tbe_languages()[ $code ]['dir'];
		}
	},
	1
);

/** Language switcher for the header. */
function tbe_render_language_switcher(): string {
	if ( ! tbe_setting( 'lang_switcher' ) ) {
		return '';
	}
	$current = get_locale();
	$out     = '<nav class="tbe-lang" aria-label="' . esc_attr__( 'Language', 'tbe-child' ) . '"><ul>';
	foreach ( tbe_languages() as $code => $lang ) {
		$is_current = $current === $code || ( 'en_US' === $code && 0 === strpos( $current, 'en' ) && ! isset( tbe_languages()[ $current ] ) );
		$out       .= sprintf(
			'<li><a href="%1$s" lang="%2$s" hreflang="%2$s" title="%3$s"%4$s>%5$s</a></li>',
			esc_url( add_query_arg( 'tbe_lang', $code ) ),
			esc_attr( str_replace( '_', '-', $code ) ),
			esc_attr( $lang['label'] ),
			$is_current ? ' aria-current="true"' : '',
			esc_html( $lang['short'] )
		);
	}
	return $out . '</ul></nav>';
}

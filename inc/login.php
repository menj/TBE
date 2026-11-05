<?php
/**
 * The login screen in the site's dress: the broken-cross mark and the name in Angel, linking home,
 * on the paper and burgundy of the active colour scheme, light or dark as the visitor chose on the
 * site (theme.js reads the same saved choice). Covers every screen wp-login.php serves: log in,
 * lost password, reset password, registration and the interim login shown when a session expires.
 *
 * Appearance → TBE → Login screen: a switch (login_design, on by default), an optional logo URL
 * (login_logo) and an optional line above the form (login_message). While a plugin that styles the
 * login screen is active (LoginPress, Custom Login, Login Logo, Branda and the like), the theme steps
 * aside. The module builds no login address, so WPS Hide Login keeps moving it.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

/** Whether a plugin that styles the login screen is active. Filter: tbe_login_plugin_active. */
function tbe_login_plugin_active(): bool {
	$active = defined( 'LOGINPRESS_VERSION' ) || class_exists( 'LoginPress' )
		|| defined( 'CUSTOM_LOGIN_VERSION' ) || class_exists( 'CWS_Login_Logo_Plugin' )
		|| defined( 'BRANDA_VERSION' ) || class_exists( 'Branda' )
		|| defined( 'ACL_PLUGIN_URL' ) || class_exists( 'Login_Designer' )
		|| defined( 'ERIDENT_CUSTOM_LOGIN_VERSION' ) || function_exists( 'er_login_logo' );
	return (bool) apply_filters( 'tbe_login_plugin_active', $active );
}

/** Whether the theme dresses the login screen. */
function tbe_login_design_on(): bool {
	return ! empty( tbe_setting( 'login_design' ) ) && ! tbe_login_plugin_active();
}

/** Whether this request is the interim (session expired) login. */
function tbe_login_is_interim(): bool {
	return isset( $_REQUEST['interim-login'] ); // phpcs:ignore WordPress.Security.NonceVerification
}

/** Stylesheet, colour scheme and the light/dark script, on the login screen only. */
function tbe_login_assets(): void {
	if ( ! tbe_login_design_on() ) {
		return;
	}
	// In <head>, so a dark choice saved on the site applies before first paint.
	tbe_enqueue_theme_script( 'tbe-theme', 'assets/js/theme.js', array(), array( 'in_footer' => false ) );
	tbe_enqueue_theme_style( 'tbe-login', 'assets/css/login.css', array( 'login' ) );
	wp_add_inline_style( 'tbe-login', tbe_scheme_css() );
}
add_action( 'login_enqueue_scripts', 'tbe_login_assets' );

/** The mark links to the site. */
function tbe_login_header_url( $url ) {
	return tbe_login_design_on() ? home_url( '/' ) : $url;
}
add_filter( 'login_headerurl', 'tbe_login_header_url' );

/**
 * The mark and the site name. Core prints this inside the h1 link without escaping, so every part
 * is escaped here. A logo URL from the settings replaces the mark; its alt is empty because the
 * name follows it in text.
 */
function tbe_login_header_text( $text ) {
	if ( ! tbe_login_design_on() ) {
		return $text;
	}
	$logo = trim( (string) tbe_setting( 'login_logo' ) );
	$mark = '' !== $logo
		? '<img class="tbe-login-logo" src="' . esc_url( $logo ) . '" alt="" width="72" height="72">'
		: tbe_brandmark_svg();
	return $mark . '<span class="tbe-login-name">' . esc_html( wp_strip_all_tags( (string) get_bloginfo( 'name' ) ) ) . '</span>';
}
add_filter( 'login_headertext', 'tbe_login_header_text' );

/** Body classes that scope the stylesheet. */
function tbe_login_body_class( $classes ) {
	if ( ! tbe_login_design_on() ) {
		return $classes;
	}
	$classes   = (array) $classes;
	$classes[] = 'tbe-login';
	if ( tbe_login_is_interim() ) {
		$classes[] = 'tbe-login--interim';
	}
	return $classes;
}
add_filter( 'login_body_class', 'tbe_login_body_class' );

/**
 * The title tag: "<screen> | <site name>", under 60 characters. The screen part is cut at a word
 * when the two together would run longer.
 *
 * @param string $login_title Core's title.
 * @param string $title       The screen's own title ("Log In", "Lost Password").
 */
function tbe_login_title( $login_title, $title = '' ) {
	$site = trim( wp_strip_all_tags( (string) get_bloginfo( 'name' ) ) );
	$part = trim( wp_strip_all_tags( (string) $title ) );
	$part = '' !== $part ? $part : __( 'Log In', 'tbe-child' );
	$room = 59 - mb_strlen( ' | ' . $site );
	if ( $room < 8 ) {
		$site = mb_substr( $site, 0, 40 );
		$room = 59 - mb_strlen( ' | ' . $site );
	}
	if ( mb_strlen( $part ) > $room ) {
		$cut  = mb_substr( $part, 0, $room + 1 );
		$sp   = mb_strrpos( $cut, ' ' );
		$part = rtrim( false !== $sp && $sp > 0 ? mb_substr( $cut, 0, $sp ) : mb_substr( $part, 0, $room ), " \t:;,.-|" );
	}
	return $part . ' | ' . $site;
}
add_filter( 'login_title', 'tbe_login_title', 10, 2 );

/** The line from the settings above the log-in form; never on the other screens or the interim login. */
function tbe_login_message( $message ) {
	$note   = trim( (string) tbe_setting( 'login_message' ) );
	$action = isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login'; // phpcs:ignore WordPress.Security.NonceVerification
	if ( '' === $note || 'login' !== $action || tbe_login_is_interim() || ! tbe_login_design_on() ) {
		return $message;
	}
	return $message . '<p class="message tbe-login-note">' . esc_html( $note ) . '</p>';
}
add_filter( 'login_message', 'tbe_login_message' );

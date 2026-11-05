<?php
/**
 * Sitemaps (0.10.6).
 *
 * WordPress serves its own sitemap at /wp-sitemap.xml only while Settings, Reading, "Search engine visibility"
 * is unticked (the blog_public option). The live site had the box ticked: /sitemap.xml answered 301 to
 * /wp-sitemap.xml, which answered the theme's 404, robots.txt named no sitemap and every page carried
 * "noindex, nofollow". The theme now keeps WordPress's sitemaps on either way, so crawlers always find the list
 * of pages, and robots.txt names the sitemap that is live: an SEO plugin's while it serves one, else WordPress's.
 * The box itself is the owner's choice and stays as set; the dashboard and the theme's settings screen say when
 * it is ticked.
 *
 * A site that wants no sitemap at all adds add_filter( 'tbe_core_sitemaps', '__return_false' ); an SEO plugin
 * that turns WordPress's sitemaps off at the usual priority still does so.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * The address of the sitemap an SEO plugin serves in place of WordPress's: Yoast SEO, Rank Math, All in One
 * SEO, SEOPress and The SEO Framework, each while its sitemap is switched on. '' when none is. Filter:
 * tbe_plugin_sitemap_url.
 */
function tbe_plugin_sitemap_url(): string {
	$url = '';
	try {
		if ( defined( 'WPSEO_VERSION' ) && class_exists( 'WPSEO_Options' ) && WPSEO_Options::get( 'enable_xml_sitemap' ) ) {
			$url = home_url( '/sitemap_index.xml' );
		} elseif ( defined( 'RANK_MATH_VERSION' ) && class_exists( 'RankMath\Helper' ) && \RankMath\Helper::is_module_active( 'sitemap' ) ) {
			$url = home_url( '/sitemap_index.xml' );
		} elseif ( defined( 'AIOSEO_VERSION' ) && function_exists( 'aioseo' ) && aioseo()->options->sitemap->general->enable ) {
			$url = home_url( '/sitemap.xml' );
		} elseif ( defined( 'SEOPRESS_VERSION' ) && '1' === (string) ( ( (array) get_option( 'seopress_xml_sitemap_option_name', array() ) )['seopress_xml_sitemap_general_enable'] ?? '' ) ) {
			$url = home_url( '/sitemaps.xml' );
		} elseif ( defined( 'THE_SEO_FRAMEWORK_VERSION' ) && function_exists( 'tsf' ) && tsf()->get_option( 'sitemaps_output' ) ) {
			$url = home_url( '/sitemap.xml' );
		}
	} catch ( Throwable $e ) {
		$url = '';
	}
	return (string) apply_filters( 'tbe_plugin_sitemap_url', $url );
}

/**
 * Keep WordPress's sitemaps on while no SEO plugin serves its own, whatever "Search engine visibility" says.
 * Priority 1, so a plugin that turns them off at the usual 10 still wins.
 *
 * @param bool $enabled What WordPress decided from blog_public.
 */
function tbe_core_sitemaps_enabled( $enabled ): bool {
	if ( '' !== tbe_plugin_sitemap_url() ) {
		return (bool) $enabled;
	}
	return (bool) apply_filters( 'tbe_core_sitemaps', true );
}
add_filter( 'wp_sitemaps_enabled', 'tbe_core_sitemaps_enabled', 1 );

/** The sitemap that is live: the plugin's, else WordPress's while its sitemaps run; '' when there is none. */
function tbe_active_sitemap_url(): string {
	$plugin = tbe_plugin_sitemap_url();
	if ( '' !== $plugin ) {
		return $plugin;
	}
	if ( ! function_exists( 'wp_sitemaps_get_server' ) || ! wp_sitemaps_get_server()->sitemaps_enabled() ) {
		return '';
	}
	return (string) get_sitemap_url( 'index' );
}

/**
 * Name the live sitemap in robots.txt. WordPress adds its own line only while the site is public, and an SEO
 * plugin may add none; a line already there for the same address is not repeated.
 *
 * @param string $output The robots.txt text.
 */
function tbe_robots_sitemap( $output ): string {
	$output = (string) $output;
	$url    = tbe_active_sitemap_url();
	if ( '' === $url || false !== stripos( $output, 'Sitemap: ' . $url ) ) {
		return $output;
	}
	return rtrim( $output, "\n" ) . "\n\nSitemap: " . esc_url_raw( $url ) . "\n";
}
add_filter( 'robots_txt', 'tbe_robots_sitemap', 99 );

/** On the dashboard and the theme's settings screen: a word when search engines are asked to stay away. */
function tbe_search_visibility_notice(): void {
	if ( get_option( 'blog_public' ) || ! current_user_can( 'manage_options' ) || ! function_exists( 'get_current_screen' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'appearance_page_tbe-settings' ), true ) ) {
		return;
	}
	$map  = tbe_active_sitemap_url();
	$text = __( 'Search engines are asked not to index this site: every page carries "noindex, nofollow".', 'tbe-child' );
	if ( '' !== $map ) {
		/* translators: %s: sitemap address */
		$text .= ' ' . sprintf( __( 'The sitemap at %s stays open either way.', 'tbe-child' ), $map );
	}
	$text .= ' ' . __( 'Untick "Discourage search engines from indexing this site" when the site is ready.', 'tbe-child' );
	printf( '<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>', esc_html( $text ), esc_url( admin_url( 'options-reading.php' ) ), esc_html__( 'Settings, Reading', 'tbe-child' ) );
}
add_action( 'admin_notices', 'tbe_search_visibility_notice' );

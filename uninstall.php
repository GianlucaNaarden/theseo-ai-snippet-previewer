<?php
/**
 * Runs when the plugin is deleted from the plugin screen.
 *
 * Everything this plugin ever wrote is removed here: the settings including
 * the API key, the measurement history and the page context on every post,
 * and the cached copies of fetched pages. On a network install the same
 * happens for every site.
 *
 * @package TheSEO_AI_Snippet_Previewer
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove all data of this plugin for the current site.
 */
function theseo_ai_snippet_previewer_wipe_site() {
	global $wpdb;

	delete_option( 'theseo_ai_settings' );

	delete_post_meta_by_key( '_theseo_ai_history' );
	delete_post_meta_by_key( '_theseo_ai_context' );

	$like    = $wpdb->esc_like( '_transient_theseo_ai_page_' ) . '%';
	$timeout = $wpdb->esc_like( '_transient_timeout_theseo_ai_page_' ) . '%';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- uninstall cleanup, no cache to use.
	$names = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$like,
			$timeout
		)
	);

	foreach ( (array) $names as $name ) {
		delete_option( $name );
	}
}

if ( is_multisite() ) {
	$theseo_ai_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $theseo_ai_sites as $theseo_ai_site_id ) {
		switch_to_blog( $theseo_ai_site_id );
		theseo_ai_snippet_previewer_wipe_site();
		restore_current_blog();
	}

	unset( $theseo_ai_sites, $theseo_ai_site_id );
} else {
	theseo_ai_snippet_previewer_wipe_site();
}

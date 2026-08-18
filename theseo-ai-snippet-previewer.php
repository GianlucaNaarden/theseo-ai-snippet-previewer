<?php
/**
 * Plugin Name:       TheSEO AI Snippet Previewer
 * Plugin URI:        https://theseo.nl/tools-en-plugins/ai-snippet-previewer/
 * Description:       Measures per page which parts a language model can quote separately, shows the calculation behind the score, and builds a ready to use prompt. Optional connection to OpenAI with your own key.
 * Version:           2.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            TheSEO
 * Author URI:        https://theseo.nl/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       theseo-ai-snippet-previewer
 * Domain Path:       /languages
 * Update URI:        https://theseo.nl/tools-en-plugins/ai-snippet-previewer/
 *
 * @package TheSEO_AI_Snippet_Previewer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'THESEO_AI_SNIPPET_PREVIEWER_VERSION', '2.0.0' );
define( 'THESEO_AI_SNIPPET_PREVIEWER_FILE', __FILE__ );
define( 'THESEO_AI_SNIPPET_PREVIEWER_DIR', plugin_dir_path( __FILE__ ) );
define( 'THESEO_AI_SNIPPET_PREVIEWER_URL', plugin_dir_url( __FILE__ ) );

require_once THESEO_AI_SNIPPET_PREVIEWER_DIR . 'includes/class-theseo-ai-analyzer.php';
require_once THESEO_AI_SNIPPET_PREVIEWER_DIR . 'includes/class-theseo-ai-language-model.php';
require_once THESEO_AI_SNIPPET_PREVIEWER_DIR . 'includes/class-theseo-ai-admin.php';
require_once THESEO_AI_SNIPPET_PREVIEWER_DIR . 'includes/class-theseo-ai-i18n-nl.php';

/**
 * Load the translation files. On init, because WordPress 6.7 and newer warn
 * when a text domain is loaded before that point.
 */
function theseo_ai_snippet_previewer_load_textdomain() {
	load_plugin_textdomain(
		'theseo-ai-snippet-previewer',
		false,
		dirname( plugin_basename( THESEO_AI_SNIPPET_PREVIEWER_FILE ) ) . '/languages/'
	);

	TheSEO_AI_I18n_NL::register();
}
add_action( 'init', 'theseo_ai_snippet_previewer_load_textdomain' );

/**
 * Start the admin screen. Everything this plugin does lives in wp-admin,
 * including admin-ajax.php, so there is nothing to boot on the front end.
 */
function theseo_ai_snippet_previewer_boot() {
	if ( ! is_admin() ) {
		return;
	}

	$admin = new TheSEO_AI_Admin();
	$admin->register();
}
add_action( 'plugins_loaded', 'theseo_ai_snippet_previewer_boot' );

/**
 * Remove the cached copies of fetched pages when the plugin is switched off.
 * Settings and measurement history stay, so switching the plugin back on
 * costs nothing. Deleting the plugin removes those too, see uninstall.php.
 */
function theseo_ai_snippet_previewer_deactivate() {
	global $wpdb;

	$like    = $wpdb->esc_like( '_transient_theseo_ai_page_' ) . '%';
	$timeout = $wpdb->esc_like( '_transient_timeout_theseo_ai_page_' ) . '%';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one off cleanup, no cache to use.
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
register_deactivation_hook( __FILE__, 'theseo_ai_snippet_previewer_deactivate' );

<?php
/**
 * Plugin Name: Ayyash Studio
 * Plugin URI: https://themeoo.com/
 * Description: Create pixel-perfect website within a few clicks with “Ayyash Studio”. Import pre-built sites, tweak palette, font-pair, contents. You are ready to go!
 * Author: ThemeRox
 * Author URI: https://themerox.com/
 * Text Domain: ayyash-studio
 * Domain Path: /languages
 * Version: 1.0.3
 *
 * @package AyyashStudio
 *
 * [PHP]
 * Requires PHP: 7.1
 *
 * [WP]
 * Requires at least: 5.2
 * Tested up to: 6.0
 *
 * [WC]
 * WC requires at least: 5.0
 * WC tested up to: 6.8
 */

use AyyashStudio\AyyashStudio;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

if ( ! defined( 'AYYASH_STUDIO_VERSION' ) ) {
	define( 'AYYASH_STUDIO_VERSION', '1.0.3' );
}

if ( ! defined( 'AYYASH_STUDIO_PLUGIN_FILE' ) ) {
	define( 'AYYASH_STUDIO_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'AYYASH_STUDIO_PLUGIN_BASENAME' ) ) {
	define( 'AYYASH_STUDIO_PLUGIN_BASENAME', plugin_basename( AYYASH_STUDIO_PLUGIN_FILE ) );
}

if ( ! defined( 'AYYASH_STUDIO_PLUGIN_PATH' ) ) {
	/** @define "AYYASH_STUDIO_PLUGIN_PATH" "./" */
	define( 'AYYASH_STUDIO_PLUGIN_PATH', plugin_dir_path( AYYASH_STUDIO_PLUGIN_FILE ) );
}

if ( ! defined( 'AYYASH_STUDIO_PLUGIN_URL' ) ) {
	define( 'AYYASH_STUDIO_PLUGIN_URL', plugin_dir_url( AYYASH_STUDIO_PLUGIN_FILE ) );
}

$upload_dir = wp_upload_dir( null, false ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

if ( ! defined( 'AYYASH_STUDIO_UPLOADS_DIR' ) ) {
	/** @define "AYYASH_STUDIO_UPLOADS_DIR" "./../../uploads/ayyash-studio/" */
	define( 'AYYASH_STUDIO_UPLOADS_DIR', $upload_dir['basedir'] . '/ayyash-studio/' );
}

if ( ! defined( 'AYYASH_STUDIO_LOG_DIR' ) ) {
	/** @define "AYYASH_STUDIO_PLUGIN_PATH" "./../../uploads/ayyash-studio/" */
	define( 'AYYASH_STUDIO_LOG_DIR', AYYASH_STUDIO_UPLOADS_DIR );
}

if ( ! file_exists( AYYASH_STUDIO_PLUGIN_PATH . 'vendor/autoload.php' ) ) {
	/**
	 * Get Dependencies Notice.
	 *
	 * @return void
	 */
	function ayyash_studio_dependency_notice() {
		$install_dir = str_replace( ABSPATH, '', dirname( AYYASH_STUDIO_PLUGIN_FILE ) );
		?>
		<div class="notice notice-warning notice-alt">
			<p>
				<?php
				printf(
				/* translators: 1. Download link for production build, 2. composer install command, 3. Plugin installation path for running composer install.. */
					esc_html__( 'It seems that you have downloaded the development version of %1$s plugin from github or other sources. Please download it from %2$s or run %3$s command within %4$s directory.', 'ayyash-studio' ),
					'<strong>' . esc_html__( 'Ayyash Studio', 'ayyash-studio' ) . '</strong>',
					'<a href="https://wordpress.org/plugins/ayyash-studio/" target="_blank" rel="noopener">wordpress.org</a>',
					'<code>composer dump-autoload -o</code>',
					'<code>' . esc_html( $install_dir ) . '</code>'
				);
				?>
			</p>
		</div>
		<?php
	}
	add_action( 'admin_notices', 'ayyash_studio_dependency_notice' );
	return;
}

// load autoloader
require_once AYYASH_STUDIO_PLUGIN_PATH . 'vendor/autoload.php';

// Load helper functions.
require_once AYYASH_STUDIO_PLUGIN_PATH . 'includes/helpers.php';

/**
 * Initialize Main.
 *
 * @return AyyashStudio
 */
function ayyash_studio(): AyyashStudio {
	return AyyashStudio::get_instance();
}

// Exec Main.
ayyash_studio();

// End of file ayyash-importer.php.

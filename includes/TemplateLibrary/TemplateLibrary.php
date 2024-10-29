<?php
/**
 *
 *
 * @package Package
 * @author Name <email>
 * @version
 * @since
 * @license
 */


namespace AyyashStudio\TemplateLibrary;

use AyyashStudio\Importer\LibraryDataImporter;
use AyyashStudio\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class TemplateLibrary {

	use Singleton;

	const SLUG = 'ayyash-studio';

	/**
	 * List of hosting providers.
	 */
	private $free_hosting_providers = [
		'unaux',
		'epizy',
		'ezyro',
	];

	protected function __construct() {

		// Admin Menu.
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
	}

	public function register_menu() {
		$page_title = __( 'Ayyash Studio', 'ayyash-studio' );
		$hook       = add_theme_page( $page_title, $page_title, 'manage_options', self::SLUG, [ $this, 'render' ] );

		add_action( 'load-' . $hook, [ $this, 'init_page_hook' ] );
	}

	public function init_page_hook() {
		// Custom body class
		add_action( 'admin_body_class', [ $this, 'body_class' ] );

		// Assets loading.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	public function body_class() {
		//'appearance_page_' . TemplateLibrary::SLUG === $hook;
	}

	public function enqueue() {
		/** @define "AYYASH_STUDIO_PLUGIN_PATH" "./../../" */

		$admin_script_data = require_once AYYASH_STUDIO_PLUGIN_PATH . 'build/admin.asset.php';

		wp_enqueue_style( 'ayyash-studio-google-fonts', '//fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap', [], AYYASH_STUDIO_VERSION );

		wp_enqueue_style(
			'ayyash-studio-library',
			AYYASH_STUDIO_PLUGIN_URL . 'build/admin.css',
			[ 'ayyash-studio-google-fonts' ],
			$admin_script_data['version']
		);

		global $is_IE, $is_edge;

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'wp-api' );
		wp_enqueue_script( 'wp-util' );
		wp_enqueue_script( 'updates' );

		if ( $is_IE || $is_edge ) {
			wp_enqueue_script( 'ayyash-studio-eventsource', AYYASH_STUDIO_PLUGIN_URL . 'build/eventsource.min.js', [], $admin_script_data['version'], true );
		}

		wp_enqueue_media();

		wp_enqueue_script(
			'ayyash-studio-library',
			AYYASH_STUDIO_PLUGIN_URL . 'build/admin.js',
			$admin_script_data['dependencies'],
			$admin_script_data['version'],
			true
		);

		wp_localize_script( 'ayyash-studio-library', 'AyyashStudio', $this->script_options() );

		wp_localize_script( 'wp-api', 'wpApiSettings', [
			'root'  => esc_url_raw( rest_url() ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
		] );

		remove_all_actions( 'admin_notices' );
	}


	/**
	 * Get localized array for starter templates.
	 *
	 * @return array
	 */
	private function script_options() {
		$current_user = wp_get_current_user();

		$categories = get_site_option( 'ayyash_studio_categories_store' );
		$editors    = get_site_option( 'ayyash_studio_editors_store' );
		$favorites  = get_option( 'ayyash_studio_favorites_store' );

		$data = [
			'compatibilities' => [
				'wpDebug'    => defined( 'WP_DEBUG' ) && WP_DEBUG,
				'XMLReader'  => class_exists( '\XMLReader', false ),
				'curl'       => function_exists( '\curl_version' ),
				'phpVersion' => PHP_VERSION,
				'isLocal'    => ayyash_studio_is_localhost(),
			],
			'request'         => [
				'nonce' => wp_create_nonce( 'ayyash-studio' ),
				'url'   => esc_url( admin_url( 'admin-ajax.php' ) ),
			],
			'admin'           => [
				'url'         => esc_url( admin_url() ),
				'email'       => $current_user->user_email,
				'firstName'   => $current_user->first_name,
				'lastName'    => $current_user->last_name,
				'displayName' => $current_user->display_name,
			],
			'themeStatus'     => ayyash_studio_get_theme_status(),
			'siteUrl'         => get_site_url(),
			'isImportedOnce'  => get_option( 'ayyash_studio_import_finish', false ),
			'supportPortal'   => 'https://themeoo.com/?utm_source=ayyash-studio&utm_medium=support-cta&utm_content=support',
			'reportError'     => $this->should_report_error(),
			'categories'      => $categories && is_array( $categories ) ? $categories : [],
			'editors'         => $editors,
			'sites'           => ayyash_studio_get_sites(),
			'favorites'       => $favorites && is_array( $favorites ) ? $favorites : [],
		];

		return apply_filters( 'ayyash_studio_script_options', $data );
	}

	/**
	 * Skip error reporting for some free hosting providers.
	 */
	public function should_report_error() {
		foreach ( $this->free_hosting_providers as $provider ) {
			if ( strpos( ABSPATH, $provider ) !== false ) {
				return false;
			}
		}

		return true;
	}

	public function render() {
		?>
		<div class="wrap ayyash-studio">
			<div id="mount-ayyash-studio"></div>
		</div>
		<?php
	}
}

// End of file TemplateLibrary.php.

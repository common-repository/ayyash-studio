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

namespace AyyashStudio\Importer\Compatibility\Elementor;

use Elementor\Plugin;
use AyyashStudio\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class Elementor {

	/**
	 * Singleton instance ref.
	 *
	 * @var self
	 */
	protected static $instance;

	/**
	 * Create one instance of this class, stores and return that.
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Class constructor.
	 *
	 * Enforce singleton instance.
	 *
	 * @return void
	 */
	protected function __construct() {

		/**
		 * Add Slashes
		 *
		 * @TODO Elementor already have below code which works on defining the constant `WP_LOAD_IMPORTERS`.
		 *       After defining the constant `WP_LOAD_IMPORTERS` in WP CLI it was not works.
		 *       Try to remove below duplicate code in the future.
		 */
		if ( ! wp_doing_ajax() || ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.0.0', '>=' ) ) ) {
			remove_filter( 'wp_import_post_meta', [ 'Elementor\Compatibility', 'on_wp_import_post_meta' ] );
			remove_filter( 'wxr_importer.pre_process.post_meta', [ 'Elementor\Compatibility', 'on_wxr_importer_pre_process_post_meta' ] );
		}

		add_filter( 'wp_import_post_meta', [ $this, 'on_wp_import_post_meta' ] );
		add_filter( 'wxr_importer.pre_process.post_meta', [ $this, 'on_wxr_importer_pre_process_post_meta' ] );

		// in-case of deleting old content.
		add_action( 'ayyash_studio_delete_post', [ $this, 'force_delete_kit' ], 10, 2 );
		add_action( 'ayyash_studio_init_sse_import', [ $this, 'disable_attachment_metadata' ] );

		add_action( 'init', [ $this, 'init' ] );
	}

	public static function elementor_detected(): bool {
		return defined( 'ELEMENTOR_VERSION' ) && class_exists( '\Elementor\Plugin' );
	}

	/**
	 * Remove the transient update check for plugins callback from Elementor.
	 * This reduces the extra code execution for Elementor.
	 */
	public function init() {
		if ( ayyash_studio_is_importing() && self::elementor_detected() && null !== Plugin::$instance->admin ) {
			remove_filter( 'pre_set_site_transient_update_plugins', [
				Plugin::$instance->admin->get_component( 'canary-deployment' ),
				'check_version',
			] );
		}
	}

	/**
	 * Disable the attachment metadata
	 */
	public function disable_attachment_metadata() {
		if ( self::elementor_detected() ) {
			remove_filter( 'wp_update_attachment_metadata', [
				Plugin::$instance->uploads_manager->get_file_type_handlers( 'svg' ),
				'set_svg_meta_data',
			], 10 );
		}
	}

	/**
	 * Force Delete Elementor Kit
	 *
	 * Delete the previously imported Elementor kit.
	 *
	 * @param int $post_id Post name.
	 * @param string $post_type Post type.
	 */
	public function force_delete_kit( int $post_id = 0, string $post_type = '' ) {
		if ( $post_id && 'elementor_library' === $post_type ) {
			$_GET['force_delete_kit'] = true;
		}
	}

	/**
	 * Process post meta before WP importer.
	 *
	 * Normalize Elementor post meta on import, We need the `wp_slash` in order
	 * to avoid the unslashing during the `add_post_meta`.
	 *
	 * Fired by `wp_import_post_meta` filter.
	 *
	 * @param array $post_meta Post meta.
	 *
	 * @return array Updated post meta.
	 */
	public function on_wp_import_post_meta( $post_metas ) {
		foreach ( $post_metas as &$meta ) {
			if ( '_elementor_data' === $meta['key'] ) {
				ayyash_studio_log_info( 'Processing Elementor data' );
				$meta['value'] = wp_slash( $meta['value'] );
				break;
			}
		}

		return $post_metas;
	}

	/**
	 * Process post meta before WXR importer.
	 *
	 * Normalize Elementor post meta on import with the new WP_importer, We need
	 * the `wp_slash` in order to avoid the unslashing during the `add_post_meta`.
	 *
	 * Fired by `wxr_importer.pre_process.post_meta` filter.
	 *
	 * @param array $post_meta Post meta.
	 *
	 * @return array Updated post meta.
	 */
	public function on_wxr_importer_pre_process_post_meta( $post_meta ) {
		if ( '_elementor_data' === $post_meta['key'] ) {
			ayyash_studio_log_info( 'Processing Elementor data' );
			$post_meta['value'] = wp_slash( $post_meta['value'] );
		}

		return $post_meta;
	}
}

// End of file Elementor.php.

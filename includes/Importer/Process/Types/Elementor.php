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

namespace AyyashStudio\Importer\Process\Types;

use AyyashStudio\Importer\Traits\ID_Mappings;
use AyyashStudio\Importer\Traits\Post_Query;
use AyyashStudio\Importer\Traits\Site_Data;

use Elementor\TemplateLibrary\Source_Local;
use Elementor\Plugin;
use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class Elementor extends Source_Local {

	use ID_Mappings;
	use Site_Data;
	use Post_Query;

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

	public function run() {
		$post_types = self::get_supporting_post_types( 'elementor' );
		if ( empty( $post_types ) ) {
			return;
		}

		if ( defined( 'WP_CLI' ) ) {
			WP_CLI::line( 'Processing "Elementor" Batch Import' );
		}

		ayyash_studio_log_info( 'Processing WordPress Posts / Pages - for "Elementor"' );

		if ( defined( 'WP_CLI' ) ) {
			WP_CLI::line( 'For post types: ' . implode( ', ', $post_types ) );
		}

		// Preload data.
		self::load_mapping();
		self::get_site_data();

		$post_ids = self::get_post_ids( $post_types, [
			'relation' => 'AND',
			[
				'key'   => '_ayyash_studio_need_processing',
				'value' => 1,
			],
			[
				'key'     => '_elementor_version',
				'compare' => 'EXISTS',
			],
		] );

		ayyash_studio_log_info( 'Processing ' . count( $post_ids ) . ' Elementor post' );

		foreach ( $post_ids as $post_id ) {
			$this->import_single_post( $post_id );
		}
	}

	/**
	 * Update post meta.
	 *
	 * @param  int|string $post_id Post ID.
	 * @return void
	 */
	public function import_single_post( $post_id = 0 ) {

		// @TODO remove. may be not needed, as meta-query contains both _ayyash_studio_need_processing && _elementor_version

		$is_elementor_post = get_post_meta( $post_id, '_elementor_version', true );
		if ( ! $is_elementor_post ) {
			return;
		}

		// Is page imported with Studio? If not then skip batch process.
		$imported_from_demo_site = get_post_meta( $post_id, '_ayyash_studio_need_processing', true );
		if ( ! $imported_from_demo_site ) {
			return;
		}

		if ( defined( 'WP_CLI' ) ) {
			WP_CLI::line( 'Elementor - Processing page: ' . $post_id );
		}

		ayyash_studio_log_info( 'Elementor - Processing page: ' . $post_id );

		$data = get_post_meta( $post_id, '_elementor_data', true );

		if ( ! empty( $data ) ) {

			// Update WP form IDs.
			$data = self::replace_ids( $data );

			if ( ! is_array( $data ) ) {
				$data = json_decode( $data, true );
			}

			$document = Plugin::$instance->documents->get( $post_id );
			if ( $document ) {
				try {
					$data = $document->get_elements_raw_data( $data, true );
				} catch ( \Exception $e ) {
					ayyash_studio_log_info( 'Error Processing Elementor Data "' . ( $e->getMessage() ) . '"' );
				}
			}

			// Import the data.
			$data = $this->process_export_import_content( $data, 'on_import' );

			// Replace the site urls.
			// escape url for json data
			$demo_url = str_replace( '/', '\/', self::$site_data->siteUrl ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$site_url = str_replace( '/', '\/', self::$current_site_url );

			// @TODO options should be int, we should be using JSON_HEX_TAG, verify and update.
			$data = wp_json_encode( $data, true );
			$data = str_replace( $demo_url, $site_url, $data );
			$data = json_decode( $data, true );

			// Update processed meta.
			update_metadata( 'post', $post_id, '_elementor_data', $data );

			// !important, Clear the cache after images import.
			Plugin::$instance->files_manager->clear_cache();
		}

		// Clean the post excerpt.
		wp_update_post( [
			'ID'           => $post_id,
			'post_excerpt' => '',
		] );

		// post should not be process 2nd time.
		delete_post_meta( $post_id, '_ayyash_studio_need_processing' );
	}
}

// End of file Elementor.php.

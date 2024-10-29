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

namespace AyyashStudio\Importer\Types;

use AyyashStudio\Importer\Wxr\StudioImporter;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class XMLContentImporter extends BaseImporter {

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
		add_action( 'wp_ajax_ayyash_studio_prepare-xml-content', [ $this, 'ajax_download_xml' ] );
		add_action( 'wp_ajax_ayyash_studio_' . $this->get_hook_slug(), [ $this, 'ajax_import' ] );
	}

	public function get_hook_slug() {
		return 'import-xml-content';
	}

	public function get_capability() {
		return 'manage_options';
	}

	public function ajax_download_xml() {
		ayyash_studio_verify_if_ajax( 'customize' );

		$data = $this->download_xml();

		if ( is_wp_error( $data ) ) {
			wp_send_json_error( [ 'errorMessage' => $data->get_error_message() ] );
		}

		wp_send_json_success( $data );
	}

	public function download_xml() {
		$data = get_site_transient( 'ayyash_studio_current_site_data' );

		if ( ! class_exists( 'XMLReader' ) ) {
			return new WP_Error( 'xml-reader', __( 'The XMLReader library is not available. This library is required to import the content for the website.', 'ayyash-studio' ) );
		}

		if ( isset( $data->content ) && $data->content ) {
			// Time to run the import!
			set_time_limit( 0 );

			// Download XML file.
			$xml_path = ayyash_studio_download_file( $data->content, [ 'wp_handle_sideload' => 'upload' ] );

			if ( $xml_path['success'] ) {
				$post = [
					'post_title'     => basename( $data->content ),
					'guid'           => $xml_path['data']['url'],
					'post_mime_type' => $xml_path['data']['type'],
				];

				$attachment_id = wp_insert_attachment( $post, $xml_path['data']['file'], 0, true );

				if ( is_wp_error( $attachment_id ) ) {
					return $attachment_id;
				} else {
					update_site_option( 'ayyash_studio_wxr_id', $attachment_id, 'no' );
					$attachment_metadata = wp_generate_attachment_metadata( $attachment_id, $xml_path['data']['file'] );
					wp_update_attachment_metadata( $attachment_id, $attachment_metadata );
					$data = StudioImporter::get_instance()->get_xml_data( $xml_path['data']['file'], $attachment_id );
					if ( is_wp_error( $data ) ) {
						return $data;
					}
					$data['xml'] = $xml_path['data'];

					return $data;
				}
			} else {
				return new WP_Error( 'xml-download-error', $xml_path['data'] );
			}
		}

		return new WP_Error( 'no-content', __( 'Error Reading Site Data.', 'ayyash-studio' ) );
	}

	public function ajax_import() {
		ayyash_studio_verify_if_ajax( $this->get_capability() );

		StudioImporter::get_instance()->sse_import();
	}

	public function import() {
	}
}

// End of file XMLContentImporter.php.

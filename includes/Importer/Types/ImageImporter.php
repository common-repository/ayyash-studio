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

class ImageImporter {

	/**
	 * Singleton instance ref.
	 *
	 * @var self
	 */
	protected static $instance;

	protected $cache = [];

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

	protected function __construct() {
		add_filter( 'ayyash_studio_importer_skip_image', [ $this, 'maybe_skip_image' ], 10, 2 );

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		WP_Filesystem();

		add_action( 'init', array( $this, 'defer_image_processing' ) );
	}

	public function get_hook_slug(): string {
		return 'import-image';
	}

	public function get_capability(): string {
		return 'manage_options';
	}

	public function ajax_import() {
		ayyash_studio_verify_if_ajax( $this->get_capability() );

		$status = $this->import();

		if ( is_wp_error( $status ) ) {
			wp_send_json_error( $status->get_error_message() );
		}

		wp_send_json_success( $status );
	}

	public function import( array $attachment ) {
		if ( isset( $attachment['url'] ) && ! ayyash_studio_is_valid_url( $attachment['url'] ) ) {
			return $attachment;
		}

		ayyash_studio_log_info( 'Source - ' . $attachment['url'] );
		$saved_image = $this->get_saved_image( $attachment );
		ayyash_studio_log_info( 'Log - ' . wp_json_encode( $saved_image['attachment'] ) );

		if ( $saved_image['status'] ) {
			return $saved_image['attachment'];
		}

		$file_content = wp_remote_retrieve_body(
			wp_safe_remote_get(
				$attachment['url'],
				array(
					'timeout'   => '60', // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
					'sslverify' => false,
				)
			)
		);

		// Empty file content?
		if ( empty( $file_content ) ) {
			ayyash_studio_log_info( 'Image Import Failed {Error: Failed wp_remote_retrieve_body} - ' . $attachment['url'] );

			return $attachment;
		}

		// Extract the file name and extension from the URL.
		$filename = basename( $attachment['url'] );

		$upload = wp_upload_bits( $filename, null, $file_content );

		ayyash_studio_log_info( $filename );
		ayyash_studio_log_info( wp_json_encode( $upload ) );

		$post = [
			'post_title' => $filename,
			'guid'       => $upload['url'],
		];
		ayyash_studio_log_info( wp_json_encode( $post ) );

		$info = wp_check_filetype( $upload['file'] );
		if ( $info ) {
			$post['post_mime_type'] = $info['type'];
		} else {
			// For now just return the origin attachment.
			return $attachment;
		}

		$post_id  = wp_insert_attachment( $post, $upload['file'] );
		$metadata = wp_generate_attachment_metadata( $post_id, $upload['file'] );
		wp_update_attachment_metadata( $post_id, $metadata );

		StudioImporter::get_instance()->track_post( $post_id, [
			'post_type' => 'attachment',
			'guid'      => $upload['url'],
		] );

		ayyash_studio_log_info( 'BATCH - SUCCESS Image {Imported} - ' . $upload['url'] );

		$this->cache[] = $post_id;

		return [
			'id'  => $post_id,
			'url' => $upload['url'],
		];
	}

	public function defer_image_processing() {
		if ( ayyash_studio_is_importing() && ! get_site_option( 'defer_attachment_processing' ) ) {
			add_filter( 'intermediate_image_sizes_advanced', [ $this, 'maybe_defer_attachment_processing' ], 10, 3 );
		}
	}

	/**
	 * Force attachment size re-generate in the background.
	 *
	 * @param array $new_sizes Array of image sizes.
	 * @param array $image_meta Metadata of the image.
	 * @param integer $attachment_id Attachment id.
	 *
	 * @return array
	 */
	public function maybe_defer_attachment_processing( $new_sizes, $image_meta, $attachment_id ): array {
		$deferred_attachments = get_site_option( 'ayyash_studio_deferred_attachments', [] );

		// If the cron job is already scheduled, bail.
		if ( in_array( $attachment_id, $deferred_attachments, true ) ) {
			return $new_sizes;
		}

		$deferred_attachments[] = $attachment_id;

		update_site_option( 'ayyash_studio_deferred_attachments', $deferred_attachments );

		// Return blank array of sizes to not generate any sizes in this request.
		return [];
	}

	/**
	 * Get Hash Image.
	 *
	 * @param string $attachment_url Attachment URL.
	 *
	 * @return string                 Hash string.
	 */
	public function get_hash_image( string $attachment_url ): string {
		return sha1( $attachment_url );
	}

	public function maybe_skip_image( $can_process, $attachment ): bool {
		if ( isset( $attachment['url'] ) && ! empty( $attachment['url'] ) ) {

			// If image URL contain current site URL? then return true to skip that image from import.
			if ( strpos( $attachment['url'], site_url() ) !== false ) {
				return true;
			}

			return ! ayyash_studio_is_valid_url( $attachment['url'] ); // reverse value.
		}

		return true;
	}

	/**
	 * Get Saved Image.
	 *
	 * @param array $attachment Attachment Data.
	 *
	 * @return array                 Hash string.
	 */
	private function get_saved_image( array $attachment ): array {
		if ( apply_filters( 'ayyash_studio_importer_skip_image', false, $attachment ) ) {
			ayyash_studio_log_info( 'SKIP Image - {from filter} - ' . $attachment['url'] . ' - Filter name `ayyash_studio_importer_skip_image`.' );

			return [
				'status'     => true,
				'attachment' => $attachment,
			];
		}

		global $wpdb;

		// @codingStandardsIgnoreStart
		// 1. Is already imported in Batch Import Process?
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT `post_id` FROM `' . $wpdb->postmeta . '` WHERE `meta_key` = \'_ayyash_studio_image_hash\' AND `meta_value` = %s;',
				$this->get_hash_image( $attachment['url'] )
			)
		);
		// @codingStandardsIgnoreEnd

		// 2. Is image already imported though XML?
		if ( empty( $post_id ) ) {

			// Get file name without extension.
			// To check it exist in attachment.
			$filename = basename( $attachment['url'] );

			// @codingStandardsIgnoreStart
			// Find the attachment by meta value.
			// Code reused from Elementor plugin.
			$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s", '%/' . $filename . '%' ) );
			// @codingStandardsIgnoreEnd

			ayyash_studio_log_info( 'SKIP Image {already imported from xml} - ' . $attachment['url'] );
		}

		if ( $post_id ) {
			$new_attachment = [
				'id'  => $post_id,
				'url' => wp_get_attachment_url( $post_id ),
			];
			$this->cache[]  = $post_id;

			return [
				'status'     => true,
				'attachment' => $new_attachment,
			];
		}

		return [
			'status'     => false,
			'attachment' => $attachment,
		];
	}

	/**
	 * Is Image URL
	 *
	 * @param string|mixed $url URL.
	 *
	 * @return boolean
	 */
	public function is_image_url( $url = '' ): bool {
		if ( empty( $url ) || ! is_string( $url ) ) {
			return false;
		}

		return ayyash_studio_is_valid_image( $url );
	}
}

// End of file ImageImporter.php.

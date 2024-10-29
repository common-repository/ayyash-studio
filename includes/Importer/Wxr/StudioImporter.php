<?php
/**
 * The Importer
 * This initializes hooks and import the xml file
 */

namespace AyyashStudio\Importer\Wxr;

use AyyashStudio\Importer\Traits\ID_Mappings;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class StudioImporter WXR Importer
 */
class StudioImporter {

	use ID_Mappings;

	private static $instance = null;

	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	protected function __construct() {
		add_filter( 'upload_mimes', [ $this, 'custom_upload_mimes' ] ); // phpcs:ignore WordPressVIPMinimum.Hooks.RestrictedHooks.upload_mimes
		add_filter( 'wxr_importer.pre_process.user', '__return_null' );
		add_filter( 'wp_import_post_data_processed', [ $this, 'pre_post_data' ], 10, 2 );
		add_filter( 'wxr_importer.pre_process.post', [ $this, 'pre_process_post' ], 10, 4 );
		if ( version_compare( get_bloginfo( 'version' ), '5.1.0', '>=' ) ) {
			add_filter( 'wp_check_filetype_and_ext', [ $this, 'real_mime_types_5_1_0' ], 10, 5 );
		} else {
			add_filter( 'wp_check_filetype_and_ext', [ $this, 'real_mime_types' ], 10, 4 );
		}
	}

	/**
	 * Track Imported Post
	 *
	 * @param int|string $post_id Post ID.
	 * @param array $data Raw data imported for the post.
	 *
	 * @return void
	 */
	public function track_post( $post_id = 0, array $data = [] ) {
		ayyash_studio_log_info( 'Inserted - Post ' . $post_id . ' - ' . get_post_type( $post_id ) . ' - ' . get_the_title( $post_id ) );

		update_post_meta( $post_id, '_ayyash_studio_imported_post', true );
		update_post_meta( $post_id, '_ayyash_studio_need_processing', true );
		update_post_meta( $post_id, '_ayyash_studio_enable_for_batch', true );

		if ( isset( $data['post_type'] ) && self::$mc4wp_post_type === $data['post_type'] ) {
			self::add_mc4wp_id( $data['post_id'], $post_id );
		}

		if ( isset( $data['post_type'] ) && self::$wpcf7_post_type === $data['post_type'] ) {
			self::add_wpcf7_id( $data['post_id'], $post_id );
		}

		if ( isset( $data['post_type'] ) && self::$wpForms_post_type === $data['post_type'] ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			self::add_wpForms_id( $data['post_id'], $post_id );
		}

		// Set the full width template for the pages.
		if ( isset( $data['post_type'] ) && 'page' === $data['post_type'] ) {
			$is_elementor_page = get_post_meta( $post_id, '_elementor_version', true );
			$theme_status      = ayyash_studio_get_theme_status();
			if ( \AyyashStudio\Importer\Importer::THEME_ACTIVE !== $theme_status && $is_elementor_page ) {
				update_post_meta( $post_id, '_wp_page_template', 'elementor_header_footer' );
			}
		} elseif ( isset( $data['post_type'] ) && 'attachment' === $data['post_type'] ) {
			$remote_url          = $data['guid'] ?? '';
			$attachment_hash_url = sha1( $remote_url );
			if ( ! empty( $attachment_hash_url ) ) {
				update_post_meta( $post_id, '_ayyash_studio_image_hash', $attachment_hash_url );
				update_post_meta( $post_id, '_elementor_source_image_hash', $attachment_hash_url );
			}
		}
	}

	/**
	 * Track Imported Term
	 *
	 * @param int $term_id Term ID.
	 *
	 * @return void
	 */
	public function track_term( $term_id ) {
		$term = get_term( $term_id );
		if ( $term ) {
			ayyash_studio_log_info( 'Inserted - Term ' . $term_id . ' - ' . wp_json_encode( $term ) );
		}
		update_term_meta( $term_id, '_ayyash_studio_imported_term', true );
	}

	/**
	 * Pre Post Data
	 *
	 * @param array $postdata Post data.
	 * @param array $data Post data.
	 *
	 * @return array           Post data.
	 */
	public function pre_post_data( $postdata, $data ) {

		// SKIP GUID. It is pointed to the demo server.
		$postdata['guid'] = '';

		return $postdata;
	}

	/**
	 * Pre Process Post
	 *
	 * @param array $data Post data. (Return empty to skip.).
	 * @param array $meta Meta data.
	 * @param array $comments Comments on the post.
	 * @param array $terms Terms on the post.
	 */
	public function pre_process_post( $data, $meta, $comments, $terms ) {
		if ( isset( $data['post_content'] ) ) {
			$meta_data = wp_list_pluck( $meta, 'key' );

			$is_attachment          = 'attachment' === $data['post_type'];
			$is_elementor_page      = in_array( '_elementor_version', $meta_data, true );
			$is_beaver_builder_page = in_array( '_fl_builder_enabled', $meta_data, true );
			$is_brizy_page          = in_array( 'brizy_post_uid', $meta_data, true );

			$disable_post_content = apply_filters( 'ayyash_studio_pre_process_post_disable_content', ( $is_attachment || $is_elementor_page || $is_beaver_builder_page || $is_brizy_page ) );

			// If post type is `attachment OR
			// If page contain Elementor, Brizy or Beaver Builder meta then skip this page.
			if ( $disable_post_content ) {
				$data['post_content'] = '';
			}
		}

		return $data;
	}

	/**
	 * Different MIME type of different PHP version
	 *
	 * Filters the "real" file type of the given file.
	 *
	 * @param array $defaults File data array containing 'ext', 'type', and
	 *                                          'proper_filename' keys.
	 * @param string $file Full path to the file.
	 * @param string $filename The name of the file (may differ from $file due to
	 *                                          $file being in a tmp directory).
	 * @param array $mimes Key is the file extension with value as the mime type.
	 * @param string $real_mime Real MIME type of the uploaded file.
	 */
	public function real_mime_types_5_1_0( $defaults, $file, $filename, $mimes, $real_mime ) {
		return $this->real_mimes( $defaults, $filename );
	}

	/**
	 * Different MIME type of different PHP version
	 *
	 * Filters the "real" file type of the given file.
	 *
	 * @param array $defaults File data array containing 'ext', 'type', and
	 *                                          'proper_filename' keys.
	 * @param string $file Full path to the file.
	 * @param string $filename The name of the file (may differ from $file due to
	 *                                          $file being in a tmp directory).
	 * @param array $mimes Key is the file extension with value as the mime type.
	 */
	public function real_mime_types( $defaults, $file, $filename, $mimes ) {
		return $this->real_mimes( $defaults, $filename );
	}

	/**
	 * Real Mime Type
	 *
	 * @param array $defaults File data array containing 'ext', 'type', and
	 *                                          'proper_filename' keys.
	 * @param string $filename The name of the file (may differ from $file due to
	 *                                          $file being in a tmp directory).
	 */
	public function real_mimes( $defaults, $filename ) {

		// Set EXT and real MIME type only for the file name `wxr.xml`.
		if ( false !== strpos( $filename, 'wxr' ) ) {
			$defaults['ext']  = 'xml';
			$defaults['type'] = 'text/xml';
		}

		// Set EXT and real MIME type only for the file name `wpforms.json` or `wpforms-{page-id}.json`.
		if ( false !== strpos( $filename, 'wpforms' ) || false !== strpos( $filename, 'cartflows' ) ) {
			$defaults['ext']  = 'json';
			$defaults['type'] = 'text/plain';
		}

		return $defaults;
	}

	/**
	 * Set GUID as per the attachment URL which avoid duplicate images issue due to the different GUID.
	 *
	 * @param array $data Post data. (Return empty to skip).
	 * @param array $meta Meta data.
	 * @param array $comments Comments on the post.
	 * @param array $terms Terms on the post.
	 */
	public function fix_duplicate_image_import( $data, $meta, $comments, $terms ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$remote_url   = ! empty( $data['attachment_url'] ) ? $data['attachment_url'] : $data['guid'];
		$data['guid'] = $remote_url;

		return $data;
	}

	/**
	 * Enable the WP_Image_Editor_GD library.
	 *
	 * @param array $editors Image editors library list.
	 *
	 * @return array
	 */
	public function enable_wp_image_editor_gd( $editors ) {
		$gd_editor = 'WP_Image_Editor_GD';
		$editors   = array_diff( $editors, [ $gd_editor ] );
		array_unshift( $editors, $gd_editor );

		return $editors;
	}

	/**
	 * Constructor.
	 *
	 * @param string $xml_url XML file URL.
	 */
	public function sse_import( $xml_url = '' ) {
		if ( wp_doing_ajax() ) {
			// phpcs:disable

			// Start the event stream.
			header( 'Content-Type: text/event-stream, charset=UTF-8' );

			$previous = error_reporting( error_reporting() ^ E_WARNING );

			// Turn off PHP output compression.
			ini_set( 'output_buffering', 'off' );
			ini_set( 'zlib.output_compression', false );

			error_reporting( $previous );

			if ( $GLOBALS['is_nginx'] ) {
				// Setting this header instructs Nginx to disable fast-cgi buffering
				// and disable gzip for this request.
				header( 'X-Accel-Buffering: no' );
				header( 'Content-Encoding: none' );
			}

			// phpcs:enable

			echo esc_html( ':' . str_repeat( ' ', 2048 ) . "\n\n" ); // 2KB padding for IE.
		}

		// Nonce verified by caller method.
		$xml_id = isset( $_REQUEST['xml_id'] ) ? absint( $_REQUEST['xml_id'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! empty( $xml_id ) ) {
			$xml_url = get_attached_file( $xml_id );
		}

		if ( empty( $xml_url ) ) {
			// No xml. We're done.
			$this->emit_sse_message( [
				'action' => 'complete',
				'error'  => __( 'WXR File missing.', 'ayyash-studio' ),
			] );
			exit;
		}

		// Time to run the import!
		set_time_limit( 0 );

		// Ensure we're not buffered.
		wp_ob_end_flush_all();
		flush();

		do_action( 'ayyash_studio_init_sse_import' );

		// Enable default GD library.
		add_filter( 'wp_image_editors', [ $this, 'enable_wp_image_editor_gd' ] );

		// Change GUID image URL.
		add_filter( 'wxr_importer.pre_process.post', [ $this, 'fix_duplicate_image_import' ], 10, 4 );

		// Are we allowed to create users?
		add_filter( 'wxr_importer.pre_process.user', '__return_null' );

		// Keep track of our progress.
		add_action( 'wxr_importer.processed.post', [ $this, 'imported_post' ], 10, 2 );
		add_action( 'wxr_importer.process_failed.post', [ $this, 'imported_post' ], 10, 2 );
		add_action( 'wxr_importer.process_already_imported.post', [ $this, 'already_imported_post' ], 10, 2 );
		add_action( 'wxr_importer.process_skipped.post', [ $this, 'already_imported_post' ], 10, 2 );
		add_action( 'wxr_importer.processed.comment', [ $this, 'imported_comment' ] );
		add_action( 'wxr_importer.process_already_imported.comment', [ $this, 'imported_comment' ] );
		add_action( 'wxr_importer.processed.term', [ $this, 'imported_term' ] );
		add_action( 'wxr_importer.process_failed.term', [ $this, 'imported_term' ] );
		add_action( 'wxr_importer.process_already_imported.term', [ $this, 'imported_term' ] );
		add_action( 'wxr_importer.processed.user', [ $this, 'imported_user' ] );
		add_action( 'wxr_importer.process_failed.user', [ $this, 'imported_user' ] );

		// Keep track of our progress.
		add_action( 'wxr_importer.processed.post', [ $this, 'track_post' ], 10, 2 );
		add_action( 'wxr_importer.processed.term', [ $this, 'track_term' ] );

		// Flush once more.
		flush();

		$importer = $this->get_importer();
		add_filter( 'user_can_richedit', '__return_true' );
		$response = $importer->import( $xml_url );
		remove_filter( 'user_can_richedit', '__return_true' );

		// Let the browser know we're done.
		$complete = [
			'action' => 'complete',
			'error'  => false,
		];
		if ( is_wp_error( $response ) ) {
			$complete['error'] = $response->get_error_message();
		}

		$this->emit_sse_message( $complete );
		if ( wp_doing_ajax() ) {
			exit;
		}
	}

	/**
	 * Add .xml files as supported format in the uploader.
	 *
	 * @param array $mimes Already supported mime types.
	 */
	public function custom_upload_mimes( $mimes ) {

		// Allow SVG files.
		$mimes['svg']  = 'image/svg+xml';
		$mimes['svgz'] = 'image/svg+xml';

		// Allow XML files.
		$mimes['xml'] = 'text/xml';

		// Allow JSON files.
		$mimes['json'] = 'application/json';

		return $mimes;
	}

	/**
	 * Start the xml import.
	 *
	 * @param string $path Absolute path to the XML file.
	 * @param int $post_id Uploaded XML file ID.
	 */
	public function get_xml_data( $path, $post_id ) {
		$args = [
			'id'          => '1',
			'xml_id'      => $post_id,
			'action'      => 'ayyash_studio_import-xml-content',
			'_ajax_nonce' => wp_create_nonce( 'ayyash-studio' ),
		];
		$url  = add_query_arg( urlencode_deep( $args ), admin_url( 'admin-ajax.php', 'relative' ) );
		$data = $this->get_data( $path );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return [
			'count'   => [
				'posts'    => $data->post_count,
				'media'    => $data->media_count,
				'users'    => count( $data->users ),
				'comments' => $data->comment_count,
				'terms'    => $data->term_count,
			],
			'url'     => $url,
			'strings' => [
				'complete' => __( 'Import complete!', 'ayyash-studio' ),
			],
		];
	}

	/**
	 * Get XML data.
	 *
	 * @param string $url Downloaded XML file absolute URL.
	 *
	 * @return ImportInfo|WP_Error  XML file data.
	 */
	public function get_data( $url ) {
		return $this->get_importer()->get_preliminary_information( $url );
	}

	/**
	 * Get Importer
	 *
	 * @return object   Importer object.
	 */
	public function get_importer() {
		$options = apply_filters(
			'ayyash_studio_xml_import_options',
			[
				'update_attachment_guids' => true,
				'fetch_attachments'       => true,
				'default_author'          => get_current_user_id(),
			]
		);

		$importer = new WXR_Importer( $options );
		$logger   = new ServerSentEvents();

		$importer->set_logger( $logger );

		return $importer;
	}

	/**
	 * Send message when a post has been imported.
	 *
	 * @param int $id Post ID.
	 * @param array $data Post data saved to the DB.
	 */
	public function imported_post( $id, $data ) {
		$this->emit_sse_message(
			[
				'action' => 'updateDelta',
				'type'   => ( 'attachment' === $data['post_type'] ) ? 'media' : 'posts',
				'delta'  => 1,
				'objId'  => $id,
			]
		);
	}

	/**
	 * Send message when a post is marked as already imported.
	 *
	 * @param array $data Post data saved to the DB.
	 */
	public function already_imported_post( $data ) {
		$this->emit_sse_message(
			[
				'action' => 'updateDelta',
				'type'   => ( 'attachment' === $data['post_type'] ) ? 'media' : 'posts',
				'delta'  => 1,
			]
		);
	}

	/**
	 * Send message when a comment has been imported.
	 */
	public function imported_comment() {
		$this->emit_sse_message(
			[
				'action' => 'updateDelta',
				'type'   => 'comments',
				'delta'  => 1,
			]
		);
	}

	/**
	 * Send message when a term has been imported.
	 */
	public function imported_term() {
		$this->emit_sse_message(
			[
				'action' => 'updateDelta',
				'type'   => 'terms',
				'delta'  => 1,
			]
		);
	}

	/**
	 * Send message when a user has been imported.
	 */
	public function imported_user() {
		$this->emit_sse_message(
			[
				'action' => 'updateDelta',
				'type'   => 'users',
				'delta'  => 1,
			]
		);
	}

	/**
	 * Emit a Server-Sent Events message.
	 *
	 * @param mixed $data Data to be JSON-encoded and sent in the message.
	 */
	public function emit_sse_message( $data ) {
		if ( wp_doing_ajax() ) {
			echo "event: message\n";
			echo 'data: ' . wp_json_encode( $data ) . "\n\n";

			// Extra padding.
			echo esc_html( ':' . str_repeat( ' ', 2048 ) . "\n\n" );
		}

		flush();
	}

}

// End of file StudioImporter.php

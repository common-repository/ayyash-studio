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

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class ImageMetadata {

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

	/**
	 * Class constructor.
	 *
	 * Enforce singleton instance.
	 *
	 * @return void
	 */
	protected function __construct() {
	}

	public function run() {
		$deferred_attachments = get_site_option( 'ayyash_studio_deferred_attachments', [] );

		update_site_option( 'defer_attachment_processing', true );
		// stop deferring

		if ( empty( $deferred_attachments ) ) {
			return;
		}

		ayyash_studio_log_info( 'Processing ' . count( $deferred_attachments ) . ' Deferred Attachments' );

		foreach ( $deferred_attachments as $attachment_id ) {
			$file = get_attached_file( $attachment_id );
			if ( false !== $file ) {
				ayyash_studio_log_info( 'Processing Attachment: ' . $attachment_id );
				wp_generate_attachment_metadata( $attachment_id, $file );
			}
		}

		ayyash_studio_log_info( 'Completed Processing Deferred Attachments' );
	}

}

// End of file ImageMetadata.php.

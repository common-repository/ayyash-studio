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
use AyyashStudio\Importer\Types\ImageImporter;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class Widgets {

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
	public function __construct() {
	}

	public function run() {
		if ( defined( 'WP_CLI' ) ) {
			\WP_CLI::line( 'Importing Widgets Data' );
		}

		ayyash_studio_log_info( 'Processing Widgets.' );

		if ( 'yes' === get_site_option( 'ayyash_studio_need_widget_processing' ) ) {
			// Preload data.
			self::load_mapping();
			self::get_site_data();

			// @TODO replace direct links for static content widget (eg. text, block, etc)

			// Process text widget data.
			$this->widget_text();

			$this->widget_block();

			// Process image widget data.
			$this->widget_media_image();
		}

		ayyash_studio_log_info( 'Completed Processing Widgets.' );
	}

	/**
	 * Widget Text
	 *
	 * @return void
	 */
	public function widget_text() {
		if ( empty( $wpcf7 ) && empty( $mc4wp ) ) {
			return;
		}

		$data = get_option( 'widget_text', null );

		if ( empty( $data ) ) {
			return;
		}


		ayyash_studio_log_info( 'Processing Contact Form Mapping from Text Widgets' );

		foreach ( $data as $idx => $value ) {
			if ( isset( $value['text'] ) && ! empty( $value['text'] ) ) {
				$data[ $idx ]['text'] = self::replace_ids( $value['text'] );
				if ( defined( 'WP_CLI' ) ) {
					\WP_CLI::line( 'Updating Contact Form Mapping from Text Widgets' );
				}
			}
		}

		update_option( 'widget_text', $data );
	}

	/**
	 * Widget Text
	 *
	 * @return void
	 */
	public function widget_block() {
		if ( empty( $wpcf7 ) && empty( $mc4wp ) ) {
			return;
		}

		$data = get_option( 'widget_block', null );

		if ( empty( $data ) ) {
			return;
		}


		ayyash_studio_log_info( 'Processing Contact Form Mapping from Text Widgets' );

		foreach ( $data as $idx => $value ) {
			if ( isset( $value['content'] ) && ! empty( $value['content'] ) ) {
				$data[ $idx ]['content'] = self::replace_ids( $value['content'] );
				if ( defined( 'WP_CLI' ) ) {
					\WP_CLI::line( 'Updating Contact Form Mapping from Text Widgets' );
				}
			}
		}

		update_option( 'widget_block', $data );
	}

	/**
	 * Widget Media Image
	 *
	 * @return void
	 */
	public function widget_media_image() {
		$data = get_option( 'widget_media_image', null );

		if ( empty( $data ) ) {
			return;
		}

		ayyash_studio_log_info( 'Processing Media Image Widgets' );

		foreach ( $data as $idx => $value ) {
			if ( isset( $value['url'] ) && isset( $value['attachment_id'] ) ) {
				$image = ImageImporter::get_instance()->import( [
					'url' => $value['url'],
					'id'  => $value['attachment_id'],
				] );

				$data[ $idx ]['url']           = $image['url'];
				$data[ $idx ]['attachment_id'] = $image['id'];

				if ( defined( 'WP_CLI' ) ) {
					\WP_CLI::line( 'Importing Widgets Image: ' . $value['url'] . ' | New Image ' . $image['url'] );
				}
			}
		}

		update_option( 'widget_media_image', $data );
	}

}

// End of file Widgets.php.

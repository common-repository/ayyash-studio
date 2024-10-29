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

class Customizer {

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
		if ( defined( 'WP_CLI' ) ) {
			\WP_CLI::line( 'Processing "Customizer" Data' );
		}

		ayyash_studio_log_info( 'Processing "Customizer" Data' );

		$theme_slug = get_option( 'stylesheet' );

		self::get_site_data();

		$options = get_option( "theme_mods_$theme_slug", [] );

		// Update button url.
		$options['layout_header_button_url'] = esc_url_raw(
			str_replace(
				self::$site_data->siteUrl, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				self::$current_site_url,
				$options['layout_header_button_url']
			)
		);

		array_walk_recursive( $options, function ( &$value ) {
			if ( ! is_array( $value ) && ayyash_studio_is_valid_image( $value ) ) {
				$image = ImageImporter::get_instance()->import( [
					'url' => $value['url'],
					'id'  => $value['attachment_id'],
				] );
				$value = $image['url'];
			}
		} );

		// Updated settings.
		update_option( "theme_mods_$theme_slug", $options );

		ayyash_studio_log_info( 'Completed Processing "Customizer" Data' );
	}
}

// End of file Customizer.php.

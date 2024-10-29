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
use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class NavMenu {

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
			WP_CLI::line( 'Processing Nav Menu Items' );
		}

		ayyash_studio_log_info( 'Processing Nav Menu Items' );

		$post_ids = self::get_posts_need_processing( 'nav_menu_item' );

		// Preload data.
		self::load_mapping();
		self::get_site_data();

		foreach ( $post_ids as $post_id ) {
			if ( defined( 'WP_CLI' ) ) {
				WP_CLI::line( 'Processing Nav Menu Item: ' . $post_id );
			}

			ayyash_studio_log_info( 'Processing Nav Menu Item: ' . $post_id );

			$menu_url = get_post_meta( $post_id, '_menu_item_url', true );

			if ( $menu_url ) {
				$menu_url = str_replace( self::$site_data->siteUrl, self::$current_site_url, $menu_url ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				update_post_meta( $post_id, '_menu_item_url', $menu_url );
			}

			// post should not be process 2nd time.
			delete_post_meta( $post_id, '_ayyash_studio_need_processing' );
		}
	}

}

// End of file NavMenu.php.

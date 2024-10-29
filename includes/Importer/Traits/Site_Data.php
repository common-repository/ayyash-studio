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

namespace AyyashStudio\Importer\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

trait Site_Data {

	protected static $current_site_url;

	/**
	 * @var Object
	 */
	protected static $site_data;

	public static function get_site_data() {
		if ( null === self::$current_site_url ) {
			self::$current_site_url = untrailingslashit( site_url() );
		}
		if ( null === self::$site_data ) {
			self::$site_data = get_site_transient( 'ayyash_studio_current_site_data' );

			if ( isset( self::$site_data->siteUrl ) ) {
				self::$site_data->siteUrl = untrailingslashit( self::$site_data->siteUrl );
			}
		}

		return self::$site_data;
	}

}

// End of file Site_Data.php.

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

namespace AyyashStudio\Importer\Compatibility;

use AyyashStudio\Client\Client;
use AyyashStudio\Importer\Compatibility\Elementor\Elementor;
use AyyashStudio\Importer\Compatibility\WooCommerce\WooCommerce;
use AyyashStudio\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class Studio_Compatibility {

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
		if ( version_compare( get_bloginfo( 'version' ), '5.1.0', '>=' ) ) {
			add_filter( 'http_request_timeout', [ $this, 'extend_image_download_timeout' ], 10, 2 ); // phpcs:ignore WordPressVIPMinimum.Hooks.RestrictedHooks.http_request_timeout
		}

		add_filter( 'upgrader_package_options', [ $this, 'clear_plugin_installation_directory' ] );

		WooCommerce::get_instance();
		Elementor::get_instance();
	}

	/**
	 * @param $timeout_value
	 * @param $url
	 *
	 * @return string|int
	 */
	public function extend_image_download_timeout( $timeout_value, $url ) {
		if ( strpos( $url, Client::get_instance()->get_host() ) !== false && ayyash_studio_is_valid_image( $url ) ) {
			return apply_filters( 'ayyash_studio_image_download_timeout', 300, $url, $timeout_value );
		}

		return $timeout_value;
	}

	public function clear_plugin_installation_directory( $options ) {
		if ( isset( $_REQUEST['clear_destination'] ) && 'true' === $_REQUEST['clear_destination'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$options['clear_destination'] = true;
		}

		return $options;
	}
}

// End of file Studio_Compatibility.php.

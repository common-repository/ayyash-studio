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

namespace AyyashStudio\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

/**
 * The RequestFor trait to determine what we request; used to determine
 * which classes we instantiate in the main class
 */
trait Requester {

	/**
	 * What type of request is this?
	 *
	 * @param  string $type admin, ajax, cron or frontend.
	 * @param  bool $is_logged_in also check if user is logged in.
	 * @return bool
	 */
	public function is_request( $type, $is_logged_in = false ) {
		$status = false;
		switch ( $type ) {
			case 'wp-installing':
				$status = $this->is_installing_wp();
				break;
			case 'backend':
			case 'admin':
				$status = $this->is_admin();
				break;
			case 'cron':
				$status = $this->is_cron();
				break;
			case 'frontend':
				$status = $this->is_frontend();
				break;
			case 'ajax':
				$status = $this->is_ajax();
				break;
			case 'rest':
			case 'api':
				$status = $this->is_rest_api_request();
				break;
			case 'cli':
				$status = $this->is_cli();
				break;
		}

		return ! $is_logged_in ? $status : is_user_logged_in() && $status;
	}

	public function is_installing_wp() {
		return defined( 'WP_INSTALLING' );
	}

	/**
	 * Is frontend
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_frontend() {
		return ( ! $this->is_admin() || $this->is_ajax() ) && ! $this->is_cron() && ! $this->is_rest();
	}

	public function is_ajax() {
		return defined( 'DOING_AJAX' ) && DOING_AJAX;
	}

	/**
	 * Is admin
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_admin() {
		return is_admin();
	}

	/**
	 * Is rest
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_rest_api_request() {
		return $this->is_rest_api_request();
	}

	/**
	 * Returns true if the request is a non-legacy REST API request.
	 *
	 * Legacy REST requests should still run some extra code for backwards compatibility.
	 *
	 * @todo: replace this function once core WP function is available: https://core.trac.wordpress.org/ticket/42061.
	 *
	 * @return bool
	 */
	public function is_rest() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		$rest_prefix         = trailingslashit( rest_get_url_prefix() );
		$is_rest_api_request = ( false !== strpos( $_SERVER['REQUEST_URI'], $rest_prefix ) ); // phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return apply_filters( 'ayyash_studio_is_rest_api_request', $is_rest_api_request );
	}

	/**
	 * Is cron
	 * with back compact before 4.8.0
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_cron() {
		return ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) || defined( 'DOING_CRON' );
	}

	/**
	 * Is cli
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_cli() {
		return defined( 'WP_CLI' ) && WP_CLI; // phpcs:disable ImportDetection.Imports.RequireImports.Symbol -- this constant is global
	}

}

// End of file RequestFor.php.

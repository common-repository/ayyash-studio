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

namespace AyyashStudio\Client;

use AyyashStudio\Traits\Singleton;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

final class Client {
	use Singleton;

	/**
	 * API Client Version.
	 *
	 * @var string
	 */
	private $version = '1.0.0';

	/**
	 * API Host
	 *
	 * @var string
	 */
	private $host = 'https://demo.themeoo.com/';

	/**
	 * API Version.
	 *
	 * @var string
	 */
	private $api_version = 'v1';

	/**
	 * Get API Host.
	 *
	 * @return string
	 */
	public function get_host(): string {
		return defined( 'AYYASH_STUDIO_API_HOST' ) ? trailingslashit( AYYASH_STUDIO_API_HOST ) : $this->host;
	}

	/**
	 * Get API Path.
	 *
	 * @return string
	 */
	public function get_api_path(): string {
		return $this->get_host() . 'wp-json/ayyash-studio/' . $this->api_version . '/';
	}

	public function get_endpoint( $endpoint ): string {
		$endpoint = rtrim( $endpoint, '/\\' );
		$endpoint = ltrim( $endpoint, '/\\' );

		return $this->get_api_path() . $endpoint;
	}

	/**
	 * Client UserAgent String.
	 *
	 * @return string
	 */
	public function get_user_agent(): string {
		return 'Ayyash Studio/' . AYYASH_STUDIO_VERSION . ' (WordPress/' . get_bloginfo( 'version' ) . ') Client/' . $this->version;
	}

	/**
	 *
	 * @param string $endpoint
	 * @param array $data
	 * @param array $args
	 * @param string $method
	 *
	 * @return array|object|object[]|mixed|WP_Error
	 */
	public function request( string $endpoint, array $data = null, array $args = [], string $method = 'GET' ) {
		$method      = strtoupper( $method );
		$defaults    = [
			'method'             => $method,
			'timeout'            => 300, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
			'user-agent'         => $this->get_user_agent(),
			'reject_unsafe_urls' => false,
			'redirection'        => 5,
			'httpversion'        => '1.0',
			'blocking'           => true,
			'sslverify'          => false,
			'headers'            => [ 'Accept' => 'application/json' ],
		];
		$args        = wp_parse_args( $args, $defaults );
		$request_url = $this->get_endpoint( $endpoint );

		if ( ! in_array( $method, [ 'GET', 'DELETE' ], true ) ) {
			$args['headers']['Content Type'] = 'application/json; charset=UTF-8';
		}

		if ( defined( 'AYYASH_STUDIO_DEV_AUTH' ) && AYYASH_STUDIO_DEV_AUTH ) {
			if ( ! $data ) {
				$data = [];
			}

			$data['dev_auth'] = AYYASH_STUDIO_DEV_AUTH;
		}

		if ( ! isset( $args['body'] ) && $data ) {
			$args['body'] = $data;
		}

		do_action( 'ayyash_studio_before_api_request', $request_url, $args );

		$response = wp_remote_request( $request_url, $args );

		do_action( 'ayyash_studio_after_api_request', $request_url, $response, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$contents = wp_remote_retrieve_body( $response );

		if ( $contents ) {
			$contents = json_decode( $contents );

			if ( $contents ) {
				return $contents;
			}
		}

		return new WP_Error( 'invalid-api-response', __( 'Invalid API Response from Ayyash Studio', 'ayyash-studio' ), [ 'raw_response' => $response ] );
	}

	public function check_updates() {
		return $this->request( 'check-updates' );
	}

	/**
	 * Get Sites.
	 *
	 * @param int $page Optional, page number. Will affect only if limit is set.
	 *
	 * @return array|mixed|WP_Error
	 */
	public function get_sites( int $page = 1 ) {
		return $this->request( 'sites', [
			'page'     => $page,
			'per_page' => 15,
		] );
	}

	/**
	 * Get single site data.
	 *
	 * @param int $site
	 *
	 * @return array|mixed|WP_Error
	 */
	public function get_site( int $site ) {
		$site = absint( $site );

		if ( $site ) {
			return $this->request( 'sites/' . $site );
		}

		return new WP_Error( 'invalid_site_id', esc_html__( 'Invalid Site ID', 'ayyash-studio' ), [ 'site' => $site ] );
	}

	public function import_site_data( int $site ) {
		$site = absint( $site );

		if ( $site ) {
			return $this->request( 'sites/' . $site . '/data' );
		}

		return new WP_Error( 'invalid_site_id', esc_html__( 'Invalid Site ID', 'ayyash-studio' ), [ 'site' => $site ] );
	}

	/**
	 * Get Editors.
	 *
	 * @return array|mixed|WP_Error
	 */
	public function get_editors() {
		return $this->request( 'editors' );
	}

	/**
	 * Get Categories.
	 *
	 * @return array|mixed|WP_Error
	 */
	public function get_categories( $parent = '' ) {
		return $this->request( 'categories', [ 'parent' => $parent ] );
	}

	/**
	 * Get Tags.
	 *
	 * @return array|mixed|WP_Error
	 */
	public function get_tags() {
		return $this->request( 'tags' );
	}

}

// End of file Client.php.

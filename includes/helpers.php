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

use AyyashStudio\Importer\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

/** @define "AYYASH_STUDIO_PLUGIN_PATH" "./../" */

require_once AYYASH_STUDIO_PLUGIN_PATH . 'includes/helpers/deprecated-functions.php';
require_once AYYASH_STUDIO_PLUGIN_PATH . 'includes/helpers/logger.php';

function ayyash_studio_detect_user_ip() {
	$lookup = [
		'HTTP_X_REAL_IP',
		'HTTP_CF_CONNECTING_IP', // CloudFlare
		'HTTP_TRUE_CLIENT_IP', // CloudFlare Enterprise header
		'HTTP_CLIENT_IP',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_FORWARDED',
		'HTTP_X_CLUSTER_CLIENT_IP',
		'HTTP_FORWARDED_FOR',
		'HTTP_FORWARDED',
		'REMOTE_ADDR',
	];
	$ip     = '';
	foreach ( $lookup as $item ) {
		if ( isset( $_SERVER[ $item ] ) && ! empty( $_SERVER[ $item ] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER[ $item ] ) );

			if ( strpos( $ip, ',' ) ) {
				$ip = (string) rest_is_ip_address( trim( current( preg_split( '/,/', $ip ) ) ) );
			}
			break;
		}
	}

	return filter_var( $ip, FILTER_VALIDATE_IP );
}

function ayyash_studio_is_localhost() {
	return in_array( ayyash_studio_detect_user_ip(), [ '127.0.0.1', '::1' ] );
}

/**
 * @return bool|void
 */
function is_ayyash_studio_supported() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	if ( ! doing_action( 'after_setup_theme' ) && ! did_action( 'after_setup_theme' ) ) {
		ayyash_studio_doing_it_wrong(
			__FUNCTION__,
			sprintf(
			/* translators: 1: is_ayyash_studio_supported 2: after_setup_theme */
				__( '%1$s should not be called before the %2$s action.', 'ayyash-studio' ),
				'is_ayyash_studio_supported',
				'after_setup_theme'
			),
			'1.0.0'
		);

		return;
	}

	return current_theme_supports( 'ayyash-studio' );
}

/**
 * Checks if current request is an ajax request not from cli.
 *
 * @return bool
 */
function ayyash_studio_is_ajax_action() {
	return ! defined( 'WP_CLI' ) && wp_doing_ajax();
}

/**
 * Verify Ajax Request.
 *
 * @return false|int|mixed|void
 */
function ayyash_studio_verify_ajax_request() {
	return check_ajax_referer( 'ayyash-studio', '_ajax_nonce' );
}

/**
 * @param string $capability
 * @param string|null $action
 *
 * @return void
 */
function ayyash_studio_verify_if_ajax( string $capability = '', string $action = null ) {
	if ( ayyash_studio_is_ajax_action() ) {
		ayyash_studio_verify_ajax_request();

		if ( ! $capability ) {
			$capability = 'manage_options';
		}

		if ( $capability && ! current_user_can( $capability ) ) {
			if ( ! $action ) {
				wp_send_json_error( __( 'You are not allowed to perform this action.', 'ayyash-studio' ) );
			} else {
				wp_send_json_error( sprintf(
					/* translators: %s: User invoked ajax action name/label. */
					__( 'You are not allowed to %s.', 'ayyash-studio' ),
					esc_html( $action )
				) );
			}
			wp_die();
		}
	}
}

function ayyash_studio_calculate_pagination( $total, $output = 'range' ) {
	$limit = 15;
	$total = (int) ceil( $total / $limit );
	if ( 'range' !== $output ) {
		return $total;
	}

	return range(1, $total );
}

/**
 * Checks if the importer is running.
 *
 * @return bool
 */
function ayyash_studio_is_importing(): bool {
	return 'yes' === get_site_transient( 'ayyash_studio_init_import' );
}

/**
 * Get an instance of WP_Filesystem_Direct.
 *
 * @return WP_Filesystem_Base|false file system instance
 */
function ayyash_studio_get_filesystem() {
	global $wp_filesystem;

	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . '/wp-admin/includes/file.php';
	}

	WP_Filesystem();

	return $wp_filesystem ? $wp_filesystem : false;
}

/**
 * Checks if supported (ayyash) theme is installed.
 * Do not try to check for theme update here, it's already handled in importer script.
 *
 * @return int
 */
function ayyash_studio_get_theme_status() {
	$theme = wp_get_theme();

	// Theme installed and activate.
	if ( 'Ayyash' === $theme->name || 'Ayyash' === $theme->parent_theme ) {
		return Importer::THEME_ACTIVE;
	}

	// Theme installed but not activate.
	foreach ( wp_get_themes() as $theme_dir => $theme ) {
		if ( 'Ayyash' === $theme->name || 'Ayyash' === $theme->parent_theme ) {
			return Importer::THEME_INACTIVE;
		}
	}

	return Importer::THEME_404;
}

add_filter( 'wie_import_data', 'ayyash_studio_custom_menu_widget' );
add_filter( 'wp_prepare_attachment_for_js', 'ayyash_studio_wp_media_svg_support', 10, 2 );

/**
 * Custom Menu Widget
 *
 * In widget export we set the nav menu slug instead of ID.
 * So, In import process we check get menu id by slug and set
 * it in import widget process.
 *
 * @param object $all_sidebars Widget data.
 *
 * @return object Set custom menu id by slug.
 */
function ayyash_studio_custom_menu_widget( $all_sidebars ) {

	// Get current menu ID & Slugs.
	$menu_locations = [];
	$nav_menus      = (object) wp_get_nav_menus();
	if ( isset( $nav_menus ) ) {
		foreach ( $nav_menus as $menu_key => $menu ) {
			if ( is_object( $menu ) ) {
				$menu_locations[ $menu->term_id ] = $menu->slug;
			}
		}
	}

	// Import widget data.
	$all_sidebars = (object) $all_sidebars;
	foreach ( $all_sidebars as $widgets_key => $widgets ) {
		foreach ( $widgets as $widget_key => $widget ) {

			// Found slug in current menu list.
			if ( isset( $widget->nav_menu ) ) {
				$menu_id = array_search( $widget->nav_menu, $menu_locations, true );
				if ( ! empty( $menu_id ) ) {
					$all_sidebars->$widgets_key->$widget_key->nav_menu = $menu_id;
				}
			}
		}
	}

	return $all_sidebars;
}

/**
 * Add svg image support for WP Media Modal JS
 *
 * @param array $response Attachment response.
 * @param object $attachment Attachment object.
 *
 * @return array
 */
function ayyash_studio_wp_media_svg_support( $response, $attachment ) {
	if ( ! function_exists( 'simplexml_load_file' ) ) {
		return $response;
	}

	if ( ! empty( $response['sizes'] ) ) {
		return $response;
	}

	if ( 'image/svg+xml' !== $response['mime'] ) {
		return $response;
	}

	$svg_path = get_attached_file( $attachment->ID );

	$dimensions = ayyash_studio_get_svg_dimensions( $svg_path );

	$response['sizes'] = [
		'full' => [
			'url'         => $response['url'],
			'width'       => $dimensions['width'],
			'height'      => $dimensions['height'],
			'orientation' => $dimensions['width'] > $dimensions['height'] ? 'landscape' : 'portrait',
		],
	];

	return $response;
}

/**
 * Get SVG Dimensions
 *
 * @param string $svg SVG file path.
 *
 * @return array Return SVG file height & width for valid SVG file.
 */
function ayyash_studio_get_svg_dimensions( $svg ) {
	$svg = simplexml_load_file( $svg );

	if ( false === $svg ) {
		$width  = '0';
		$height = '0';
	} else {
		$attributes = $svg->attributes();
		$width      = (string) $attributes->width;
		$height     = (string) $attributes->height;
	}

	return [
		'width'  => $width,
		'height' => $height,
	];
}

/**
 * Download File Into Uploads Directory.
 *
 * @param string $file Download File URL.
 * @param array $overrides Upload file arguments.
 * @param int $timeout_seconds Timeout in downloading the XML file in seconds.
 *
 * @return array        Downloaded file data.
 */
function ayyash_studio_download_file( $file = '', $overrides = array(), $timeout_seconds = 300 ) {

	// Gives us access to the download_url() and wp_handle_sideload() functions.
	require_once ABSPATH . 'wp-admin/includes/file.php';

	// Download file to temp dir.
	$temp_file = download_url( $file, $timeout_seconds );

	// WP Error.
	if ( is_wp_error( $temp_file ) ) {
		return array(
			'success' => false,
			'data'    => $temp_file->get_error_message(),
		);
	}

	// Array based on $_FILE as seen in PHP file uploads.
	$file_args = [
		'name'     => basename( $file ),
		'tmp_name' => $temp_file,
		'error'    => 0,
		'size'     => filesize( $temp_file ),
	];

	$defaults = [

		// Tells WordPress to not look for the POST form
		// fields that would normally be present as
		// we downloaded the file from a remote server, so there
		// will be no form fields
		// Default is true.
		'test_form'   => false,
		// Setting this to false lets WordPress allow empty files, not recommended.
		// Default is true.
		'test_size'   => true,
		// A properly uploaded file will pass this test. There should be no reason to override this one.
		'test_upload' => true,
		'mimes'       => [
			'xml'  => 'text/xml',
			'json' => 'text/plain',
			'svg'  => 'image/svg',
		],
	];

	$overrides = wp_parse_args( $overrides, $defaults );

	// Move the temporary file into the uploads' directory.
	$results = wp_handle_sideload( $file_args, $overrides );

	if ( isset( $results['error'] ) ) {
		return array(
			'success' => false,
			'data'    => $results,
		);
	}

	// Success.
	return [
		'success' => true,
		'data'    => $results,
	];
}

/**
 * @param string $content
 *
 * @return array
 */
function ayyash_studio_extract_links( string $content ): array {
	// Extract all links.
	preg_match_all( '#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $content, $match );

	return array_unique( $match[0] );
}

/**
 * Check for the valid image
 *
 * @param string $link  The Image link.
 *
 * @return bool
 */
function ayyash_studio_is_valid_image( $link ) {
	return ! ! preg_match( '/^((https?:\/\/)|(www\.))([a-z0-9-].?)+(:[0-9]+)?\/[\w\-\@]+\.(jpg|png|gif|jpeg|svg)\/?$/i', $link );
}

/**
 * Check is valid URL
 *
 * @param string $url  The site URL.
 *
 * @return bool
 */
function ayyash_studio_is_valid_url( string $url = '' ): bool {
	if ( empty( $url ) ) {
		return false;
	}

	$parse_url = wp_parse_url( $url );
	if ( empty( $parse_url ) || ! is_array( $parse_url ) ) {
		return false;
	}

	$valid_hosts = [
		'lh3.googleusercontent.com',
		'pixabay.com',
		'themeoo.com',
		'demo.themeoo.com',
		'lib.absoluteplugins.com',
	];

	// Validate host.
	if ( in_array( $parse_url['host'], $valid_hosts, true ) ) {
		return true;
	}

	return false;
}

/**
 * @param string $option
 * @param string $wildcardPosition
 *
 * @return bool
 */
function ayyash_studio_delete_site_option( $option, $wildcardPosition = 'both' ) {
	global $wpdb;

	switch ( $wildcardPosition ) {
		case 'left':
			$option = '%' . $option;
			break;
		case 'right':
			$option = $option . '%';
			break;
		case 'both':
		default:
			$option = '%' . $option . '%';
			break;
	}

	if ( ! is_multisite() ) {
		$options = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s", $option ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( ! empty( $options ) ) {
			$status = (bool) $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE  option_name LIKE %s", $option ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			array_map(function ( $opt ) {
				wp_cache_delete( $opt, 'options' );
			}, $options );

			return $status;
		}
	} else {
		$network_id = get_current_network_id();

		if ( ! $network_id ) {
			return false;
		}

		$options = $wpdb->get_row( $wpdb->prepare( "SELECT meta_key FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s AND site_id = %d;", $option, $network_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! empty( $options ) ) {
			$status = (bool) $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->sitemeta WHERE  meta_key LIKE %s AND site_id = %d;", $option, $network_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			array_map( function ( $opt ) use ( $network_id ) {
				wp_cache_delete( "$network_id:$opt", 'site-options' );
			}, $options );

			return $status;
		}
	}

	return false;
}

function ayyash_studio_get_sites(): array {
	global $wpdb;
	$sites = [];
	if ( ! is_multisite() ) {
		$options = $wpdb->get_col( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'ayyash_studio_sites_store_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	} else {
		$options = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT meta_key FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s AND site_id = %d;",
				'ayyash_studio_sites_store_%',
				get_current_network_id()
			)
		);
	}

	foreach ( $options as $option ) {
		$values = get_site_option( $option );
		if ( ! empty( $values ) && is_array( $values ) ) {
			$sites = array_merge( $sites, $values );
		}
	}

	return $sites;
}

function ayyash_studio_importer_errors() {
	$errors = get_site_transient( 'ayyash_studio_importer_errors' );

	if ( ! $errors ) {
		$errors = [];
	}

	return $errors;
}

function ayyash_studio_add_errors( $message, $type = 'warning', $data = [] ) {
	$errors   = ayyash_studio_importer_errors();
	$errors[] = [
		'type'    => esc_attr( $type ),
		'message' => esc_html( $message ),
		'data'    => $data,
	];

	return set_site_transient( 'ayyash_studio_importer_errors', $errors, HOUR_IN_SECONDS );
}

function ayyash_studio_clear_errors() {
	return delete_site_transient( 'ayyash_studio_importer_errors' );
}

function ayyash_studio_get_requested_ids( string $key ): array {
	$ids = [];

	if ( ! empty( $_REQUEST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$ids = array_map( 'absint', explode( ',', $_REQUEST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$ids = array_filter( $ids );
	}

	return $ids;
}

function ayyash_studio_clean_ids( array $ids ): array {
	if ( ! empty( $ids ) ) {
		$ids = array_map( 'absint', $ids );
		$ids = array_filter( $ids );
	}

	return $ids;
}

/**
 * Get imported post ids
 *
 * @return int[]
 */
function ayyash_studio_get_imported_posts(): array {
	global $wpdb;

	$post_ids = $wpdb->get_col( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_ayyash_studio_imported_post'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	return ayyash_studio_clean_ids( $post_ids );
}

function ayyash_studio_get_imported_terms(): array {
	global $wpdb;

	$term_ids = $wpdb->get_col( "SELECT term_id FROM $wpdb->termmeta WHERE meta_key='_ayyash_studio_imported_term'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	return ayyash_studio_clean_ids( $term_ids );
}

function ayyash_studio_get_imported_forms(): array {
	global $wpdb;

	$form_ids = $wpdb->get_col( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_ayyash_studio_imported_wp_forms'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	return ayyash_studio_clean_ids( $form_ids );
}

/**
 * Get json data file path.
 *
 * @param string $filename
 *
 * @return string
 */
function ayyash_studio_get_json_path( string $filename ): string {
	if ( false === strpos( $filename, '.json' ) ) {
		$filename .= '.json';
	}

	return AYYASH_STUDIO_PLUGIN_PATH . 'build/data/' . ltrim( $filename, '\\/' );
}

/**
 * Read Json Data from file.
 *
 * @param string $filename
 * @param bool $associative
 *
 * @return mixed|null
 */
function ayyash_studio_read_json( string $filename, bool $associative = false ) {
	$filename = ayyash_studio_get_json_path( $filename );
	$filename = wp_normalize_path( realpath( $filename ) );

	if ( ! file_exists( $filename ) ) {
		return null;
	}

	if ( function_exists( 'wp_json_file_decode' ) ) {
		$data = wp_json_file_decode( $filename, [ 'associative' => $associative ] );
	} else {
		$data = json_decode( file_get_contents( $filename ), $associative ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
	}

	return $data;
}

// End of file helpers.php.

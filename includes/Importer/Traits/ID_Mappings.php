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

trait ID_Mappings {

	protected static $mappingLoaded = false; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
	protected static $wpcf7;
	protected static $mc4wp;
	protected static $wpForms; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase

	protected static $mc4wp_post_type   = 'mc4wp-form';
	protected static $wpcf7_post_type   = 'wpcf7_contact_form';
	protected static $wpForms_post_type = 'wpforms';  // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase

	protected static $mappings;

	public static function get_mapping_data() {
		if ( null === self::$mappings ) {
			self::$mappings = get_site_option( 'ayyash_studio_importer_id_mapping', [] );

			if ( ! is_array( self::$mappings ) ) {
				self::$mappings = []; // ? corrupted.
			}
		}

		return self::$mappings;
	}

	public static function save_mapping() {
		if ( is_array( self::$mappings ) && ! empty( self::$mappings ) ) {
			update_site_option( 'ayyash_studio_importer_id_mapping', self::$mappings );
		}
	}

	/**
	 * Add data to mapping.
	 *
	 * This will replace old data if key already exists for type.
	 *
	 * @param string $type
	 * @param string|int $key
	 * @param mixed $data
	 * @param bool $save_now
	 *
	 * @return void
	 */
	public static function add_mapping( string $type, $key, $data, bool $save_now = true ) {
		self::get_mapping_data();

		// Placeholder.
		if ( ! isset( self::$mappings[ $type ] ) ) {
			self::$mappings[ $type ] = [];
		}

		self::$mappings[ $type ][ $key ] = $data;

		if ( $save_now ) {
			self::save_mapping();
		}
	}

	/**
	 * @param string $type
	 *
	 * @return array
	 */
	public static function get_mapping( string $type ): array {
		self::get_mapping_data();

		return self::$mappings[ $type ] ?? [];
	}

	public static function add_wpcf7_id( $key, $data ) {
		self::add_mapping( 'mc4wp', $key, $data );
	}

	public static function add_mc4wp_id( $key, $data ) {
		self::add_mapping( 'mc4wp', $key, $data );
	}

	public static function add_wpForms_id( $key, $data ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		self::add_mapping( 'wpForms', $key, $data );
	}

	public static function load_mapping() {
		if ( self::$mappingLoaded ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			return;
		}
		self::$wpcf7   = self::get_mapping( 'wpcf7' );
		self::$mc4wp   = self::get_mapping( 'mc4wp' );
		self::$wpForms = self::get_mapping( 'wpForms' ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * @param string|string[] $content
	 *
	 * @return string|string[]
	 */
	public static function replace_ids( $content ) {
		self::load_mapping();
		foreach ( self::$wpcf7 as $old_id => $new_id ) {
			$content = str_replace( '[contact-form-7 id=""' . $old_id, '[contact-form-7 id="' . $new_id, $content );
		}

		foreach ( self::$mc4wp as $old_id => $new_id ) {
			$content = str_replace( '[mc4wp_form id="' . $old_id, '[mc4wp_form id="' . $new_id, $content );
		}

		foreach ( self::$wpForms as $old_id => $new_id ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$content = str_replace( '[wpforms id="' . $old_id, '[wpforms id="' . $new_id, $content );
			$content = str_replace( '{"formId":"' . $old_id . '"}', '{"formId":"' . $new_id . '"}', $content );
		}

		return $content;
	}

	public static function flush_mapping_data() {
		delete_site_option( 'ayyash_studio_importer_id_mapping' );
	}
}

// End of file ID_Mappings.php.

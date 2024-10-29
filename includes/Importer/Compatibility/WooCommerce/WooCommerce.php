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

namespace AyyashStudio\Importer\Compatibility\WooCommerce;

use AyyashStudio\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class WooCommerce {
	use Singleton;

	/**
	 * Constructor
	 *
	 * @since 1.1.4
	 */
	protected function __construct() {
		add_action( 'init', [ $this, 'disable_pages_creation' ], 2 );
		add_action( 'ayyash_studio_after_plugin_activation', [ $this, 'install_wc' ], 10, 2 );

		// WooCommerce product attributes registration.
		if ( class_exists( '\WooCommerce' ) ) {
			add_filter( 'wxr_importer.pre_process.term', [ $this, 'import_attribute_taxonomies' ], 10, 1 );
			add_action( 'ayyash_studio_import_finish', [ $this, 'finalize_import' ] );
		}
	}

	public function disable_pages_creation() {
		if ( ayyash_studio_is_importing() ) {
			add_filter( 'woocommerce_create_pages', '__return_empty_array' );
		}
	}

	/**
	 * Create default WooCommerce tables
	 *
	 * @param string $plugin Plugin file which is activated.
	 *
	 * @return void
	 */
	public function install_wc( string $plugin ) {
		if ( 'woocommerce/woocommerce.php' !== $plugin ) {
			return;
		}

		// From WC 6.5 core installer handles admin install routines.
		// Running admin installer for older versions.
		if ( is_callable( '\Automattic\WooCommerce\Admin\Install::create_tables' ) ) {
			\Automattic\WooCommerce\Admin\Install::create_tables();
			\Automattic\WooCommerce\Admin\Install::create_events();
		}

		if ( is_callable( '\WC_Install::check_version' ) ) {
			\WC_Install::check_version();
		}
	}

	/**
	 * Hook into the pre-process term filter of the content import and register the
	 * custom WooCommerce product attributes, so that the terms can then be imported normally.
	 *
	 * This should probably be removed once the WP importer 2.0 support is added in WooCommerce.
	 *
	 * Fixes: [WARNING] Failed to import pa_size L warnings in content import.
	 * Code from: woocommerce/includes/admin/class-wc-admin-importers.php (ver 2.6.9).
	 *
	 * Github issue: https://github.com/proteusthemes/one-click-demo-import/issues/71
	 *
	 * @param  array|mixed $data The term data to import.
	 * @return array|mixed       The unchanged term data.
	 */
	public function import_attribute_taxonomies( $data ) {
		global $wpdb;

		if ( empty( $data ) || ! isset( $data['taxonomy'] ) ) {
			return $data;
		}

		if ( strstr( $data['taxonomy'], 'pa_' ) ) {
			if ( ! taxonomy_exists( $data['taxonomy'] ) ) {
				$attribute_name = wc_sanitize_taxonomy_name( str_replace( 'pa_', '', $data['taxonomy'] ) );

				// Create the taxonomy.
				if ( ! in_array( $attribute_name, wc_get_attribute_taxonomies(), true ) ) {
					$attribute = [
						'attribute_label'   => $attribute_name,
						'attribute_name'    => $attribute_name,
						'attribute_type'    => 'select', // @TODO read from import data.
						'attribute_orderby' => 'menu_order',
						'attribute_public'  => 0,
					];
					$wpdb->insert( $wpdb->prefix . 'woocommerce_attribute_taxonomies', $attribute ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					// @TODO keep track of attributes we importing, so it can be reset.
					// transient and caches will be cleared after import completed.
				}

				// Register the taxonomy now so that the import works!
				register_taxonomy(
					$data['taxonomy'],
					apply_filters( 'woocommerce_taxonomy_objects_' . $data['taxonomy'], [ 'product' ] ),
					apply_filters(
						'woocommerce_taxonomy_args_' . $data['taxonomy'], [
							'hierarchical' => true,
							'show_ui'      => false,
							'query_var'    => true,
							'rewrite'      => false,
						]
					)
				);
			}
		}

		return $data;
	}

	/**
	 * Finalize import.
	 *
	 * Update WooCommerce Lookup Table, clear caches, etc.
	 *
	 * @TODO detect if wc is installed in single if statement
	 *
	 * @return void
	 */
	public function finalize_import() {
		if ( function_exists( 'wc_update_product_lookup_tables' ) ) {
			if ( ! wc_update_product_lookup_tables_is_running() ) {
				wc_update_product_lookup_tables();
			}
		}

		if ( is_callable( '\WC_Cache_Helper::invalidate_cache_group' ) ) {
			delete_transient( 'wc_attribute_taxonomies' );
			\WC_Cache_Helper::invalidate_cache_group( 'woocommerce-attributes' );
		}
	}
}

// End of file WooCommerce.php.

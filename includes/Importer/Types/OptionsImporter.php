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

namespace AyyashStudio\Importer\Types;

use AyyashStudio\Importer\Traits\ID_Mappings;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class OptionsImporter extends BaseImporter {

	use ID_Mappings;

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

	public function get_hook_slug() {
		return 'import-options';
	}

	public function get_capability() {
		return 'manage_options';
	}

	public function ajax_import() {
		ayyash_studio_verify_if_ajax( $this->get_capability() );

		$status = $this->import();

		if ( is_wp_error( $status ) ) {
			wp_send_json_error( $status->get_error_message() );
		}

		wp_send_json_success();
	}

	public function import() {
		$data = get_site_transient( 'ayyash_studio_current_site_data' );

		if ( is_object( $data ) && isset( $data->options ) ) {

			// convert to array.
			$data->options = json_decode( wp_json_encode( $data->options ), true );

			update_option( '_ayyash_studio_old_site_options', $data->options, 'no' );

			foreach ( $data->options as $option_name => $option_value ) {
				if ( null === $option_value || ! in_array( $option_name, self::site_options(), true ) ) {
					continue;
				}

				switch ( $option_name ) {

					// Set WooCommerce page ID by page Title.
					case 'woocommerce_shop_page_title':
					case 'woocommerce_cart_page_title':
					case 'woocommerce_checkout_page_title':
					case 'woocommerce_myaccount_page_title':
					case 'woocommerce_edit_address_page_title':
					case 'woocommerce_view_order_page_title':
					case 'woocommerce_change_password_page_title':
					case 'woocommerce_logout_page_title':
						$this->update_wp_page_id( $option_name, $option_value );
						break;

					case 'page_for_posts':
					case 'page_on_front':
						$this->update_page_id( $option_name, $option_value );
						break;

					// nav menu locations.
					case 'nav_menu_locations':
						$this->set_nav_menu_locations( $option_value );
						break;

					// import WooCommerce category images.
					case 'woocommerce_product_cat':
						$this->set_woocommerce_product_cat( $option_value );
						break;

					// insert logo.
					case 'custom_logo':
						$this->insert_logo( $option_value );
						break;

					case 'elementor_active_kit':
						if ( '' !== $option_value ) {
							$this->set_elementor_kit();
						}
						break;


					case 'mc4wp_default_form_id':
						self::$mc4wp = self::get_mapping( 'mc4wp' );
						if ( ! empty( self::$mc4wp ) ) {
							$newIds = array_values( self::$mc4wp );
							update_option( 'mc4wp_default_form_id', $newIds[0] );
						}
						break;

					default:
						update_option( $option_name, $option_value );
						break;
				}
			}

			return true;
		}

		return new WP_Error( 'site-data-missing', __( 'Missing Current Site Data.', 'ayyash-studio' ) );
	}

	/**
	 * Site Options
	 *
	 * @return array    List of defined array.
	 */
	public static function site_options(): array {
		return [
			'custom_logo',
			'nav_menu_locations',
			'show_on_front',
			'page_on_front',
			'page_for_posts',

			// Plugin: Elementor.
			'elementor_container_width',
			'elementor_cpt_support',
			'elementor_css_print_method',
			'elementor_default_generic_fonts',
			'elementor_disable_color_schemes',
			'elementor_disable_typography_schemes',
			'elementor_editor_break_lines',
			'elementor_exclude_user_roles',
			'elementor_global_image_lightbox',
			'elementor_page_title_selector',
			'elementor_scheme_color',
			'elementor_scheme_color-picker',
			'elementor_scheme_typography',
			'elementor_space_between_widgets',
			'elementor_stretched_section_container',
			'elementor_load_fa4_shim',
			'elementor_active_kit',

			// Plugin: WooCommerce.
			// Pages.
			'woocommerce_shop_page_title',
			'woocommerce_cart_page_title',
			'woocommerce_checkout_page_title',
			'woocommerce_myaccount_page_title',
			'woocommerce_edit_address_page_title',
			'woocommerce_view_order_page_title',
			'woocommerce_change_password_page_title',
			'woocommerce_logout_page_title',

			// Account & Privacy.
			'woocommerce_enable_guest_checkout',
			'woocommerce_enable_checkout_login_reminder',
			'woocommerce_enable_signup_and_login_from_checkout',
			'woocommerce_enable_myaccount_registration',
			'woocommerce_registration_generate_username',

			// Plugin: Easy Digital Downloads - EDD.
			'edd_settings',

			// Plugin: WPForms.
			'wpforms_settings',

			// Categories.
			'woocommerce_product_cat',

			// Plugin: LearnDash LMS.
			'learndash_settings_theme_ld30',
			'learndash_settings_courses_themes',

			'mc4wp_default_form_id',
		];
	}

	/**
	 * Create a fresh copy of elementor kit.
	 *
	 * @return void
	 */
	private function set_elementor_kit() {

		// Update Elementor Theme Kit Option.
		$query = get_posts( [
			'post_type'   => 'elementor_library',
			'post_status' => 'publish',
			'numberposts' => 1,
			'meta_query'  => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'   => '_ayyash_studio_imported_post',
					'value' => '1',
				],
				[
					'key'   => '_elementor_template_type',
					'value' => 'kit',
				],
			],
		] );

		if ( ! empty( $query ) && isset( $query[0] ) && isset( $query[0]->ID ) ) {
			update_option( 'elementor_active_kit', $query[0]->ID );
		}
	}

	/**
	 * Update post option
	 *
	 * @param string $option_name Option name.
	 * @param mixed $option_value Option value.
	 */
	private function update_page_id( string $option_name, $option_value ) {
		$page = function_exists( 'wpcom_vip_get_page_by_title' ) ? wpcom_vip_get_page_by_title( $option_value ) : get_page_by_title( $option_value ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_page_by_title_get_page_by_title
		if ( is_object( $page ) ) {
			update_option( $option_name, $page->ID );
		}
	}

	/**
	 * Update WooCommerce page ids.
	 *
	 * @param string $option_name Option name.
	 * @param mixed $option_value Option value.
	 */
	private function update_wp_page_id( string $option_name, $option_value ) {
		$option_name = str_replace( '_title', '_id', $option_name );
		$this->update_page_id( $option_name, $option_value );
	}

	/**
	 * In WP nav menu is stored as ( 'menu_location' => 'menu_id' );
	 * In export we send 'menu_slug' like ( 'menu_location' => 'menu_slug' );
	 * In import we set 'menu_id' from menu slug like ( 'menu_location' => 'menu_id' );
	 *
	 * @param array $nav_menu_locations Array of nav menu locations.
	 */
	private function set_nav_menu_locations( array $nav_menu_locations = array() ) {
		$menu_locations = [];

		// Update menu locations.
		if ( isset( $nav_menu_locations ) ) {
			foreach ( $nav_menu_locations as $menu => $value ) {
				$term = get_term_by( 'slug', $value, 'nav_menu' );

				if ( is_object( $term ) ) {
					$menu_locations[ $menu ] = $term->term_id;
				}
			}

			set_theme_mod( 'nav_menu_locations', $menu_locations );
		}
	}

	/**
	 * Set WooCommerce category images.
	 *
	 * @param array $cats Array of categories.
	 */
	private function set_woocommerce_product_cat( array $cats = array() ) {
		if ( isset( $cats ) ) {
			foreach ( $cats as $cat ) {
				if ( ! empty( $cat['slug'] ) && ! empty( $cat['thumb'] ) ) {
					$term = get_term_by( 'slug', $cat['slug'], 'product_cat' );
					if ( ! is_wp_error( $term ) ) {
						$image = [
							'url' => $cat['thumb'],
							'id'  => 0,
						];
						$image = ImageImporter::get_instance()->import( $image );
						if ( $image['id'] ) {
							update_term_meta( $term->term_id, 'thumbnail_id', $image['id'] );
						}
					}
				}
			}
		}
	}

	/**
	 * Insert Logo By URL
	 *
	 * @param string $image_url Logo URL.
	 */
	private function insert_logo( string $image_url = '' ) {
		$downloaded_image = ImageImporter::get_instance()->import( [
			'url' => $image_url,
			'id'  => 0,
		] );
		if ( $downloaded_image['id'] ) {
			set_theme_mod( 'custom_logo', $downloaded_image['id'] );
		}
	}
}

// End of file OptionsImporter.php.

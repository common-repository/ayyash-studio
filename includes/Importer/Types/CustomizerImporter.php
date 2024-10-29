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

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

final class CustomizerImporter extends BaseImporter {

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

	protected function __construct() {
		parent::__construct();

		add_action( 'wp_ajax_ayyash_studio_apply-customization', [ $this, 'apply_customization' ] );
	}

	public function get_hook_slug() {
		return 'import-customizer';
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

		if ( is_object( $data ) && isset( $data->customizer ) ) {
			// Save Mods.
			$mods = wp_json_encode( $data->customizer->mods );
			$mods = json_decode( $mods, true );
			$this->update_theme_mods( $mods );

			// Update custom css if needed.
			if ( $data->customizer->css ) {
				$css         = $data->customizer->css;
				$image_links = ayyash_studio_extract_links( $css );
				$image_links = array_filter( $image_links, 'ayyash_studio_is_valid_image' );
				foreach ( $image_links as $image_url ) {
					// Download remote image.
					$image = ImageImporter::get_instance()->import( [
						'url' => $image_url,
						'id'  => 0,
					] );

					$css = str_replace( $image_url, $image['url'], $css );
				}

				wp_update_custom_css_post( $css );
			}

			return true;
		}

		return new WP_Error( 'site-data-missing', __( 'Missing Current Site Data.', 'ayyash-studio' ) );
	}

	protected function update_theme_mods( $mods = [], $theme_slug = '' ) {
		if ( ! $theme_slug ) {
			$theme_slug = get_option( 'stylesheet' );
		}

		update_option( "theme_mods_$theme_slug", $mods );
	}

	public function apply_customization() {
		ayyash_studio_verify_if_ajax( $this->get_capability() );

		// color, typography, logo
		$palette = isset( $_REQUEST['palette'] ) ? sanitize_text_field( $_REQUEST['palette'] ) : 'false'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$typo    = isset( $_REQUEST['typography'] ) ? sanitize_text_field( $_REQUEST['typography'] ) : 'false'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$logo    = isset( $_REQUEST['logo'] ) ? sanitize_text_field( $_REQUEST['logo'] ) : 'false'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $palette && 'false' !== $palette ) {
			$palette = wp_unslash( $palette );
			$palette = json_decode( $palette, true );

			if ( is_array( $palette ) && ! empty( $palette['colors'] ) ) {
				ayyash_studio_log_info( 'Applying custom color palette: ' . print_r( $palette, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				$mods = [
					'colors_global_accent',       //  1. accent color
					'colors_global_accent_shade', //  2. accent hover (shade) color
					'colors_global_heading',      //  3. header (h1...6) color
					'colors_global_text',         //  4. text color
					'colors_global_content_bg',   //  5. content bg
					'colors_global_site_bg',      //  6. site bg
					'colors_global_border',       //  7. border
					'colors_footer_sc_text',      //  8. credit text
					'colors_footer_sc_bg',        //  9. credit bg
					'colors_global_gradient',     // 10. gradient (extra)
				];

				foreach ( $palette['colors'] as $idx => $value ) {
					if ( isset( $mods[ $idx ] ) && $mods[ $idx ] && $value ) {
						ayyash_studio_log_info( 'Applying ' . $mods[ $idx ] . ' : ' . $value );
						set_theme_mod( $mods[ $idx ], $value );
					}
				}

				ayyash_studio_log_info( 'Custom color palette applied.' );
			}
		}

		if ( $typo && 'false' !== $typo ) {
			$typo = wp_unslash( $typo );
			$typo = json_decode( $typo, true );

			if ( is_array( $typo ) && ! empty( $typo['global'] ) && ! empty( $typo['heading'] ) ) {
				$this->set_typography( 'global', $typo['global'] );

				$this->set_typography( 'heading', $typo['heading'] );

				foreach ( range( 1, 6 ) as $level ) {
					if ( ! isset( $typo[ 'h' . $level ] ) ) {
						continue;
					}

					if ( isset( $typo[ 'h' . $level ]['font'] ) ) {
						unset( $typo[ 'h' . $level ]['font'] );
					}

					$this->set_typography( 'heading_h' . $level, $typo[ 'h' . $level ] );
				}

				ayyash_studio_log_info( 'Custom typography applied.' );
			}
		}

		if ( $logo && 'false' !== $logo ) {
			$logo = wp_unslash( $logo );
			$logo = json_decode( $logo, true );
			if ( isset( $logo['id'] ) && $logo['id'] ) {
				$width = isset( $logo['width'] ) && $logo['width'] ? absint( $logo['width'] ) : false;
				$logo  = absint( $logo['id'] );
				if ( $logo && 'attachment' === get_post_type( $logo ) ) {
					set_theme_mod( 'custom_logo', $logo );
					// Apply width only if logo is applied.
					if ( $width ) {
						set_theme_mod( 'logo_width', $width );
					}
				}
			}
		}

		wp_send_json_success();
	}

	protected $defaults = [
		'font'   => '',
		'weight' => '',
		'size'   => '',
		'line'   => '',
		'letter' => '',
		'word'   => '',
	];
	protected $mapping  = [
		'font'   => 'typography_{type}_font_family',
		'weight' => 'typography_{type}_font_variant',
		'size'   => 'typography_{type}_font_size',
		'line'   => 'typography_{type}_line_height',
		'letter' => 'typography_{type}_letter_spacing',
		'word'   => 'typography_{type}_word_spacing',
	];

	protected function set_typography( $type, $values ) {
		$values = wp_parse_args( $values, $this->defaults );

		foreach ( $this->mapping as $key => $mod ) {
			$value = sanitize_text_field( $values[ $key ] );

			if ( '' === $values[ $key ] ) {
				continue;
			}

			if ( 'font' === $key ) {
				$value = $this->get_font_name( $value );
			} else {
				$value = floatval( $values );
			}

			$mod = str_replace( '{type}', $type, $mod );
			ayyash_studio_log_info( 'Applying ' . $mod . ': ' . $value );
			set_theme_mod( $mod, $value );
		}
	}

	private function get_font_name( $input ) {
		if ( preg_match( "/'([^']+)'/", $input, $matches ) ) {
			return $matches[1];
		}

		return $input;
	}
}

// End of file CustomizerImporter.php.

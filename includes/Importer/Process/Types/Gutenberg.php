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
use AyyashStudio\Importer\Types\ImageImporter;
use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class Gutenberg {

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
			WP_CLI::line( 'Processing "Gutenberg" posts.' );
		}

		ayyash_studio_log_info( 'Processing WordPress Posts / Pages - for "Gutenberg"' );

		$post_types = apply_filters( 'ayyash_studio_process_gutenberg_post_types', [ 'page', 'wp_block' ] );

		if ( defined( 'WP_CLI' ) ) {
			WP_CLI::line( 'For post types: ' . implode( ', ', $post_types ) );
		}

		$post_ids = self::get_posts_need_processing( $post_types );

		// Preload data.
		self::load_mapping();
		self::get_site_data();

		// Allow the SVG tags in batch update process.
		add_filter( 'wp_kses_allowed_html', [ __CLASS__, 'allow_svg' ], 10, 2 );

		foreach ( $post_ids as $post_id ) {
			$this->import_single_post( $post_id );
		}

		remove_filter( 'wp_kses_allowed_html', [ __CLASS__, 'allow_svg' ] );
	}

	/**
	 * Skip page/post build with page-builders.
	 *
	 * @param $post_id
	 *
	 * @return bool
	 */
	private static function maybe_skip_post( $post_id ): bool {
		// @TODO Exclude elementor and other page-builder post with meta query.
		//       Or make a way of detecting gutenberg only post.
		//       May be we can add a custom meta field in studio server while developing the post/page for gutenberg.
		$is_elementor_page      = get_post_meta( $post_id, '_elementor_version', true );
		$is_beaver_builder_page = get_post_meta( $post_id, '_fl_builder_enabled', true );
		$is_brizy_page          = get_post_meta( $post_id, 'brizy_post_uid', true );

		return ! ( $is_elementor_page || $is_beaver_builder_page || $is_brizy_page );
	}

	/**
	 * Update post meta.
	 *
	 * @param  int|string $post_id Post ID.
	 * @return void
	 */
	public function import_single_post( $post_id = 0 ) {
		if ( self::maybe_skip_post( $post_id ) ) {
			return;
		}

		if ( defined( 'WP_CLI' ) ) {
			WP_CLI::line( 'Gutenberg - Processing page: ' . $post_id );
		}

		ayyash_studio_log_info( 'Gutenberg - Processing page: ' . $post_id );

		// Post content.
		$content = get_post_field( 'post_content', $post_id );

		$content = self::replace_ids( $content );

		// @TODO Replaces the category ID in used Post blocks.

		// @XXX
		//       Gutenberg break block markup from render. Because the '&' is updated in database with '&amp;' and it
		//       expects as 'u0026amp;'. So, Converted '&amp;' with 'u0026amp;'.
		//
		// @TODO This affect for normal page content too. Detect only Gutenberg pages and process only on it.
		// $content = str_replace( '&amp;', "\u0026amp;", $content );

		$content = $this->get_content( $content );

		// Update content.
		wp_update_post( [
			'ID'           => $post_id,
			'post_content' => $content,
			'post_excerpt' => '',
		] );

		// post should not be process 2nd time.
		delete_post_meta( $post_id, '_ayyash_studio_need_processing' );
	}

	/**
	 * Download and Replace hotlink images
	 *
	 * @param string $content Mixed post content.
	 *
	 * @return string
	 */
	public function get_content( string $content = '' ): string {

		// Extract all links.
		$all_links = ayyash_studio_extract_links( $content );

		// Not have any link.
		if ( empty( $all_links ) ) {
			return $content;
		}

		$link_mapping = [];
		$image_links  = [];
		$other_links  = [];

		// Extract normal and image links.
		foreach ( $all_links as $link ) {
			if ( ayyash_studio_is_valid_image( $link ) ) {

				// Get all image links.
				// Avoid *-150x, *-300x and *-1024x images.
				if (
					false === strpos( $link, '-150x' ) &&
					false === strpos( $link, '-300x' ) &&
					false === strpos( $link, '-1024x' )
				) {
					$image_links[] = $link;
				}
			} else {
				// Collect other links.
				$other_links[] = $link;
			}
		}

		// Step 1: Download images.
		if ( ! empty( $image_links ) ) {
			foreach ( $image_links as $image_url ) {
				// Download remote image.
				$image = ImageImporter::get_instance()->import( [
					'url' => $image_url,
					'id'  => 0,
				] );

				// Old and New image mapping links.
				$link_mapping[ $image_url ] = $image['url'];
			}
		}

		// Step 2: Replace the demo site URL with live site URL.
		if ( ! empty( $other_links ) ) {
			foreach ( $other_links as $link ) {
				$link_mapping[ $link ] = str_replace( self::$site_data->siteUrl, self::$current_site_url, $link ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			}
		}

		// Step 3: Replace mapping links.
		foreach ( $link_mapping as $old_url => $new_url ) {
			$content = str_replace( $old_url, $new_url, $content );

			// Replace the slashed URLs if any exist.
			$old_url = str_replace( '/', '/\\', $old_url );
			$new_url = str_replace( '/', '/\\', $new_url );
			$content = str_replace( $old_url, $new_url, $content );
		}

		return $content;
	}

	/**
	 * Allowed tags for the batch update process.
	 *
	 * @param array $html_tags   Array of default allowable HTML tags.
	 * @param  string|array $context    The context for which to retrieve tags. Allowed values are 'post',
	 *                                  'strip', 'data', 'entities', or the name of a field filter such as
	 *                                  'pre_user_description'.
	 *
	 * @return array Array of allowed HTML tags and their allowed attributes.
	 */
	public static function allow_svg( array $html_tags, $context ): array {

		// Keep only for 'post' context.
		if ( 'post' === $context ) {
			$common_atts = [
				'class'  => true,
				'id'     => true,
				'width'  => true,
				'height' => true,
				'fill'   => true,
				'style'  => true,
				'stroke' => true,
			];

			$html_tags['svg']  = array_merge( [
				'xmlns'   => true,
				'viewbox' => true,
			], $common_atts );
			$html_tags['path'] = array_merge( [ 'd' => true ], $common_atts );
			$html_tags['g']    = $common_atts;
		}

		return $html_tags;
	}
}

// End of file Gutenberg.php.

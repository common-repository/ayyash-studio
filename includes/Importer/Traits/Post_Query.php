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

use WP_Post;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

trait Post_Query {

	/**
	 * @param string[]|string $post_types
	 * @param array|null $meta_query
	 *
	 * @return int[]|WP_Post[]
	 */
	public static function get_post_ids( $post_types, array $meta_query = null ): array {
		if ( $post_types ) {
			$args = [
				'post_type'      => $post_types,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'post_status'    => 'publish',
				'posts_per_page' => - 1,
			];

			// Other plugin can cause potential issues by filtering results, do not interfere with process.
			$args['suppress_filters'] = true; // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFiltersTrue

			if ( $meta_query ) {
				$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			}

			$query = new WP_Query( $args );

			if ( $query->have_posts() ) {
				return $query->posts;
			}
		}

		return [];
	}

	/**
	 * Get post ids
	 *
	 * @param string|string[] $post_types
	 *
	 * @return int[]
	 */
	public static function get_posts_need_processing( $post_types ): array {
		return self::get_post_ids( $post_types, [
			'relation' => 'AND',
			[
				'key'   => '_ayyash_studio_need_processing',
				'value' => 1,
			],
		] );
	}

	/**
	 * Get Supporting Post Types..
	 *
	 * @param string $feature Feature.
	 *
	 * @return array
	 */
	public static function get_supporting_post_types( string $feature ): array {
		global $_wp_post_type_features;

		return array_keys( wp_filter_object_list( $_wp_post_type_features, [ $feature => true ] ) );
	}
}

// End of file Post_Query.php.

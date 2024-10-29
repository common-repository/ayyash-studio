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

use AyyashStudio\AyyashStudioErrorHandler;
use AyyashStudio\Importer\Types\OptionsImporter;
use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

trait Reset {

	protected function init_importer_reset_hooks() {
		add_action( 'wp_ajax_ayyash_studio_init_reset', [ $this, 'init_reset' ] );

		add_action( 'wp_ajax_ayyash_studio_delete_customizer_data', [ $this, 'delete_customizer_data' ] );
		add_action( 'wp_ajax_ayyash_studio_delete_site_options', [ $this, 'delete_site_options' ] );
		add_action( 'wp_ajax_ayyash_studio_delete_widgets_data', [ $this, 'delete_widgets_data' ] );

		add_action( 'wp_ajax_ayyash_studio_delete_posts', [ $this, 'delete_posts' ] );
		add_action( 'wp_ajax_ayyash_studio_delete_terms', [ $this, 'delete_terms' ] );
		add_action( 'wp_ajax_ayyash_studio_delete_wp_forms', [ $this, 'delete_wp_forms' ] );
	}

	public function init_reset() {
		ayyash_studio_verify_if_ajax();

		wp_send_json_success( [
			'posts'    => array_chunk( ayyash_studio_get_imported_posts(), 10 ),
			'terms'    => array_chunk( ayyash_studio_get_imported_terms(), 10 ),
			'wp_forms' => array_chunk( ayyash_studio_get_imported_forms(), 10 ),
			// @TODO Collect imported WooCommerce Attributes for removal.
		] );
	}

	public function delete_customizer_data() {
		ayyash_studio_verify_if_ajax();

		$theme_slug = get_option( 'stylesheet' );

		ayyash_studio_log_info( 'Deleted customizer Settings:' . wp_json_encode( get_option( "theme_mods_$theme_slug", [] ) ) );

		delete_option( "theme_mods_$theme_slug" );

		if ( defined( 'WP_CLI' ) ) {
			WP_CLI::line( 'Deleted Customizer Settings!' );
		} elseif ( wp_doing_ajax() ) {
			wp_send_json_success();
		}
	}

	public function delete_site_options() {
		ayyash_studio_verify_if_ajax();

		$options = get_option( '_ayyash_studio_old_site_options', [] );

		ayyash_studio_log_info( 'Deleted - Site Options ' . wp_json_encode( $options ) );

		$options = array_keys( $options );

		foreach ( $options as $option_name ) {
			delete_option( $option_name );
		}

		if ( defined( 'WP_CLI' ) ) {
			WP_CLI::line( 'Deleted Site Options!' );
		} elseif ( wp_doing_ajax() ) {
			wp_send_json_success();
		}
	}

	public function delete_widgets_data() {
		ayyash_studio_verify_if_ajax();

		// Get all old widget ids.
		$old_widgets_data = (array) get_option( '_ayyash_studio_old_widgets_data', [] );
		$old_widget_ids   = [];
		foreach ( $old_widgets_data as $old_sidebar_key => $old_widgets ) {
			if ( ! empty( $old_widgets ) && is_array( $old_widgets ) ) {
				$old_widget_ids = array_merge( $old_widget_ids, $old_widgets );
			}
		}

		// Process if not empty.
		$sidebars_widgets = get_option( 'sidebars_widgets', [] );
		if ( ! empty( $old_widget_ids ) && ! empty( $sidebars_widgets ) ) {
			ayyash_studio_log_info( 'DELETED - WIDGETS ' . wp_json_encode( $old_widget_ids ) );

			foreach ( $sidebars_widgets as $sidebar_id => $widgets ) {
				$widgets = (array) $widgets;

				if ( ! empty( $widgets ) ) {
					foreach ( $widgets as $widget_id ) {
						if ( in_array( $widget_id, $old_widget_ids, true ) ) {
							ayyash_studio_log_info( 'DELETED - WIDGET ' . $widget_id );

							// Move old widget to inactive list.
							$sidebars_widgets['wp_inactive_widgets'][] = $widget_id;

							// Remove old widget from sidebar.
							$sidebars_widgets[ $sidebar_id ] = array_diff( $sidebars_widgets[ $sidebar_id ], array( $widget_id ) );
						}
					}
				}
			}

			update_option( 'sidebars_widgets', $sidebars_widgets );
		}

		if ( defined( 'WP_CLI' ) ) {
			WP_CLI::line( 'Deleted Widgets!' );
		} elseif ( wp_doing_ajax() ) {
			wp_send_json_success();
		}
	}

	public function delete_posts() {
		ayyash_studio_verify_if_ajax();

		AyyashStudioErrorHandler::get_instance()->start_error_handler();

		// Suspend bunches of stuff in WP core.
		wp_defer_term_counting( true );
		wp_defer_comment_counting( true );
		wp_suspend_cache_invalidation( true );

		if ( ! empty( $_REQUEST['posts'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$posts = ayyash_studio_get_requested_ids( 'posts' );
			foreach ( $posts as $post_id ) {
				$post_type = get_post_type( $post_id );
				$title     = get_the_title( $post_id );
				do_action( 'ayyash_studio_delete_post', $post_id, $post_type );
				wp_delete_post( $post_id, true );
				ayyash_studio_log_info( 'Deleted - Post ID ' . $post_id . ' - ' . $post_type . ' - ' . $title );
				do_action( 'ayyash_studio_deleted_post', $post_id, $post_type );
			}
		}

		// Re-enable stuff in core.
		wp_suspend_cache_invalidation( false );
		wp_cache_flush();

		// @TODO defer taxonomy term children hierarchy cache building.
		//       This should be done after resetting imported terms.
		//       May be we can do this after import..
		foreach ( get_taxonomies() as $tax ) {
			delete_option( "{$tax}_children" );
			_get_term_hierarchy( $tax );
		}

		wp_defer_term_counting( false );
		wp_defer_comment_counting( false );

		AyyashStudioErrorHandler::get_instance()->stop_error_handler();

		if ( wp_doing_ajax() ) {
			wp_send_json_success();
		}
	}

	public function delete_terms() {
		ayyash_studio_verify_if_ajax();

		AyyashStudioErrorHandler::get_instance()->start_error_handler();

		if ( ! empty( $_REQUEST['terms'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$terms = ayyash_studio_get_requested_ids( 'terms' );
			foreach ( $terms as $term_id ) {
				$term = get_term( $term_id );
				if ( $term && ! is_wp_error( $term ) ) {
					do_action( 'ayyash_studio_delete_term', $term_id, $term );
					wp_delete_term( $term_id, $term->taxonomy );
					ayyash_studio_log_info( 'Deleted - Term ' . $term_id . ' - ' . $term->name . ' ' . $term->taxonomy );
					do_action( 'ayyash_studio_deleted_term', $term_id, $term );
				}
			}
		}

		AyyashStudioErrorHandler::get_instance()->stop_error_handler();

		if ( wp_doing_ajax() ) {
			wp_send_json_success();
		}
	}

	public function delete_wp_forms() {
		ayyash_studio_verify_if_ajax();

		AyyashStudioErrorHandler::get_instance()->start_error_handler();

		if ( ! empty( $_REQUEST['wp_forms'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$posts = ayyash_studio_get_requested_ids( 'wp_forms' );
			foreach ( $posts as $post_id ) {
				$post_type = get_post_type( $post_id );
				$title     = get_the_title( $post_id );
				do_action( 'ayyash_studio_delete_wp_form', $post_id, $post_type );
				wp_delete_post( $post_id, true );
				ayyash_studio_log_info( 'Deleted - Form ID ' . $post_id . ' - ' . $post_type . ' - ' . $title );
				do_action( 'ayyash_studio_deleted_wp_form', $post_id, $post_type );
			}
		}

		AyyashStudioErrorHandler::get_instance()->stop_error_handler();

		if ( wp_doing_ajax() ) {
			wp_send_json_success();
		}
	}
}

// End of file Reset.php.

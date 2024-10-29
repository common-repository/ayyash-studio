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

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

trait Plugin {

	protected function init_plugin_hooks() {
		add_action( 'wp_ajax_ayyash_studio_activate_plugin', [ $this, 'activate_plugin' ] );
		add_action( 'wp_ajax_ayyash_studio_deactivate_plugins', [ $this, 'deactivate_plugins' ] );
	}

	/**
	 * Required Plugin Activate
	 *
	 * @return void
	 */
	public function activate_plugin() {
		ayyash_studio_verify_if_ajax( 'install_plugins' );

		$plugin = ( isset( $_POST['plugin'] ) ) ? sanitize_text_field( $_POST['plugin'] ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $plugin ) {
			$activate = $this->do_activate_plugin( $plugin );

			if ( is_wp_error( $activate ) ) {
				if ( defined( 'WP_CLI' ) ) {
					\WP_CLI::error( 'Plugin Activation Error: ' . $activate->get_error_message() );
				} elseif ( wp_doing_ajax() ) {
					wp_send_json_error( [
						'slug'         => $plugin,
						'errorMessage' => $activate->get_error_message(),
					] );
				}
			}

			if ( defined( 'WP_CLI' ) ) {
				\WP_CLI::line( 'Plugin Activated!' );
			} elseif ( wp_doing_ajax() ) {
				wp_send_json_success(
					array(
						'success' => true,
						'message' => __( 'Plugin Activated', 'ayyash-studio' ),
					)
				);
			}
		} else {
			wp_send_json_error( [ 'errorMessage' => __( 'Invalid Request', 'ayyash-studio' ) ] );
		}
	}

	public function deactivate_plugins() {
		ayyash_studio_verify_if_ajax( 'install_plugins' );

		$plugins = ( isset( $_POST['plugins'] ) ) ? sanitize_text_field( $_POST['plugins'] ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $plugins ) {
			$plugins = explode( ',', $plugins );
			deactivate_plugins( $plugins );
			$statuses = [];
			foreach ( $plugins as $plugin ) {
				$statuses[ $plugin ] = is_plugin_inactive( $plugin );
			}
			wp_send_json_success( [
				'status' => $statuses,
				'list'   => get_option( 'active_plugins', array() ),
			] );
		} else {
			wp_send_json_error( [ 'errorMessage' => __( 'Invalid Request', 'ayyash-studio' ) ] );
		}
	}

	/**
	 * @param string $plugin
	 *
	 * @return bool|\WP_Error Null on success, WP_Error on invalid file.
	 */
	protected function do_activate_plugin( string $plugin ) {
		do_action( 'ayyash_studio_before_plugin_activation', $plugin );

		AyyashStudioErrorHandler::get_instance()->start_error_handler();

		$activate = activate_plugin( $plugin, '', false, false );

		AyyashStudioErrorHandler::get_instance()->stop_error_handler();

		if ( ! is_wp_error( $activate ) ) {
			do_action( 'ayyash_studio_after_plugin_activation', $plugin, [] );

			return true;
		}

		return $activate;
	}
}

// End of file Plugin.php.

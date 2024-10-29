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

trait Theme {

	protected function init_theme_hooks() {
		add_action( 'wp_ajax_ayyash_studio_activate_theme', [ $this, 'activate_theme' ] );
	}

	public function activate_theme() {
		ayyash_studio_verify_if_ajax( 'customize' );

		do_action( 'ayyash_studio_activating_theme' );

		AyyashStudioErrorHandler::get_instance()->start_error_handler();

		switch_theme( 'ayyash' );

		AyyashStudioErrorHandler::get_instance()->stop_error_handler();

		do_action( 'ayyash_studio_theme_activated' );

		wp_send_json_success( __( 'Theme Activated.', 'ayyash-studio' ) );
	}
}

// End of file Theme.php.

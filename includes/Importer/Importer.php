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

namespace AyyashStudio\Importer;

use AyyashStudio\Importer\Traits\Plugin;
use AyyashStudio\Importer\Traits\Reset;
use AyyashStudio\Importer\Traits\Theme;
use AyyashStudio\Importer\Wxr\StudioImporter;
use AyyashStudio\Traits\Singleton;
use AyyashStudio\Importer\Compatibility\Studio_Compatibility;
use AyyashStudio\AyyashStudioErrorHandler;
use AyyashStudio\Client\Client;
use AyyashStudio\Importer\Types\ImageImporter;
use AyyashStudio\Importer\Types\CustomizerImporter;
use AyyashStudio\Importer\Types\XMLContentImporter;
use AyyashStudio\Importer\Types\OptionsImporter;
use AyyashStudio\Importer\Types\WidgetsImporter;
use AyyashStudio\Importer\Process\ProcessImported;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

final class Importer {

	const THEME_ACTIVE   = 1; // installed-and-active
	const THEME_INACTIVE = 0; // installed-but-inactive
	const THEME_404      = -1; // not-installed

	use Singleton;

	use Reset;

	use Theme;

	use Plugin;

	protected function __construct() {

		// Load compatibilities

		ProcessImported::get_instance();
		Studio_Compatibility::get_instance();

		$this->init_hooks();
	}

	protected function init_hooks() {

		// Image importer instance should be the first, so the cache works as expected.
		ImageImporter::get_instance();

		add_action( 'wp_ajax_ayyash_studio_init_import', [ $this, 'init_import' ] );

		$this->init_importer_reset_hooks();
		$this->init_theme_hooks();
		$this->init_plugin_hooks();

		StudioImporter::get_instance();
		CustomizerImporter::get_instance();
		XMLContentImporter::get_instance();
		OptionsImporter::get_instance(); // options importer must be called after wxr being imported.
		WidgetsImporter::get_instance();

		add_action( 'wp_ajax_ayyash_studio_end_import', [ $this, 'end_import' ] );
	}

	public function init_import() {
		ayyash_studio_verify_if_ajax();

		do_action( 'ayyash_studio_init_import' );
		delete_site_transient( 'ayyash_studio_init_import' );

		if ( isset( $_POST['site'] ) && $_POST['site'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			set_site_transient( 'ayyash_studio_init_import', 'yes', HOUR_IN_SECONDS );
			$response = Client::get_instance()->import_site_data( absint( $_POST['site'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( is_wp_error( $response ) ) {
				wp_send_json_error( $response->get_error_message() );
			} else {
				set_site_transient( 'ayyash_studio_current_site_data', $response, HOUR_IN_SECONDS );
				wp_send_json_success( $response );
			}
		} else {
			wp_send_json_error( esc_html__( 'Invalid Site', 'ayyash-studio' ) );
		}

		wp_die();
	}

	public function end_import() {
		ayyash_studio_verify_if_ajax();

		$demo_data = get_site_transient( 'ayyash_studio_current_site_data' );

		// Flush permalinks.
		flush_rewrite_rules(); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules

		do_action( 'ayyash_studio_import_finish', $demo_data );

		// @TODO trigger clear cache action, handle third party caching plugin, nginx fast-cgi cache etc.

		// Ensure finish timestamp is set after all actions.
		update_site_option( 'ayyash_studio_import_finish', current_time( 'mysql' ) );

		if ( wp_doing_ajax() ) {
			wp_send_json_success();
			wp_die();
		}
	}
}

// End of file Importer.php.

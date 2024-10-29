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

namespace AyyashStudio\Importer\Process;

use AyyashStudio\AsyncProcess\AsyncProcess;
use AyyashStudio\Importer\Process\Types\Customizer;
use AyyashStudio\Importer\Process\Types\Elementor;
use AyyashStudio\Importer\Process\Types\Gutenberg;
use AyyashStudio\Importer\Process\Types\ImageMetadata;
use AyyashStudio\Importer\Process\Types\NavMenu;
use AyyashStudio\Importer\Process\Types\Widgets;
use AyyashStudio\Importer\Process\Types\WPCF7;
use AyyashStudio\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class ProcessImported {
	use Singleton;

	/**
	 * @var AsyncProcess
	 */
	protected static $asyncProcess;

	protected function __construct() {
		self::prepare();

		add_action( 'ayyash_studio_import_finish', [ __CLASS__, 'process' ] );
		add_action( 'ayyash_studio_async_process_complete', [ __CLASS__, 'process_complete' ] );
	}

	protected static function prepare() {
		if ( null === self::$asyncProcess ) {
			self::$asyncProcess = new AsyncProcess();
		}

		/** WordPress Plugin Administration API */
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! function_exists( 'get_core_checksums' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		if ( ! function_exists( 'wp_crop_image' ) ) {
			// Core Helpers - Image.
			// @TODO This file is required for Elementor. Once we implement our logic for updating elementor data then we'll delete this file.
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
	}

	public static function process() {
		self::prepare();
		self::cleanup();

		ayyash_studio_log_info( 'Async Process Started...' );

		$processors = [
			Widgets::get_instance(),
			Elementor::get_instance(), // Process elementor before gutenberg.
			Gutenberg::get_instance(), // Gutenberg should be the last to process as we can't detect editor type for it.
			NavMenu::get_instance(),
			ImageMetadata::get_instance(),
			Customizer::get_instance(),
			WPCF7::get_instance(),
		];

		foreach ( $processors as $instance ) {
			self::$asyncProcess->push_to_queue( $instance );
		}

		// Dispatch Queue.
		self::$asyncProcess->save()->dispatch();
	}

	public static function cleanup() {
		$wxr_id = get_site_option( 'ayyash_studio_wxr_id' );
		if ( $wxr_id ) {
			wp_delete_attachment( $wxr_id, true );
			ayyash_studio_log_info( 'Deleted Temporary WXR file ' . $wxr_id );
			delete_option( 'ayyash_studio_wxr_id' );
			ayyash_studio_log_info( 'Option `ayyash_studio_wxr_id` Deleted.' );
		}
	}

	public static function process_complete() {
		delete_site_option( 'ayyash_studio_importer_id_mapping' );
		delete_site_option( 'defer_attachment_processing' );
		delete_site_option( 'ayyash_studio_deferred_attachments' );
		delete_site_option( 'ayyash_studio_need_widget_processing' );
		ayyash_studio_log_info( 'Async Process Ended...' );
		delete_site_transient( 'ayyash_studio_current_site_data' );
		delete_site_transient( 'ayyash_studio_init_import' );
		flush_rewrite_rules(); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules
	}
}

// End of file ProcessImported.php.

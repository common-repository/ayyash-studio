<?php /** @noinspection DuplicatedCode */

/**
 * Installer
 *
 * @package AyyashStudio
 */

namespace AyyashStudio;

use AyyashStudio\Scheduler\Scheduler;
use AyyashStudio\TemplateLibrary\TemplateLibrary;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

final class Installer {

	/**
	 * Hook in tabs.
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'check_version' ], 5 );
		add_action( 'init', [ __CLASS__, 'schedule_update_check' ] );
		add_filter( 'plugin_action_links_' . AYYASH_STUDIO_PLUGIN_BASENAME, [ __CLASS__, 'plugin_action_links' ] );
		add_filter( 'plugin_row_meta', [ __CLASS__, 'plugin_row_meta' ], 10, 2 );
	}

	/**
	 * Check version and run the updater is required.
	 *
	 * This check is done on all requests and runs if the versions do not match.
	 */
	public static function check_version() {
		if ( get_site_transient( 'ayyash_studio_activation_redirect' ) ) {
			delete_site_transient( 'ayyash_studio_activation_redirect' );
			wp_safe_redirect( admin_url( 'themes.php?page=' . TemplateLibrary::SLUG ) );
			die();
		}

		if ( ! defined( 'IFRAME_REQUEST' ) && version_compare( get_option( 'ayyash_studio_version' ), ayyash_studio()->version, '<' ) ) {
			if ( ! defined( 'AYYASH_STUDIO_UPDATING' ) ) {
				define( 'AYYASH_STUDIO_UPDATING', true );
			}
			self::install();
			do_action( 'ayyash_studio_updated' );
		}
	}

	public static function schedule_update_check() {
		// Start Scheduler.
		Scheduler::start();
	}

	/**
	 * Install.
	 */
	public static function install() {
		if ( ! is_blog_installed() ) {
			return;
		}

		// Check if we are not already running this routine.
		if ( 'yes' === get_transient( 'ayyash_studio_installing' ) ) {
			return;
		}

		// If we made it till here nothing is running yet, lets set the transient now.
		set_transient( 'ayyash_studio_installing', 'yes', MINUTE_IN_SECONDS * 10 );
		if ( ! defined( 'AYYASH_STUDIO_INSTALLING' ) ) {
			define( 'AYYASH_STUDIO_INSTALLING', true );
		}

		self::create_cron_jobs();
		self::create_options();
		self::create_files();
		self::maybe_set_activation_transients();
		self::migrate();
		self::update_version();

		delete_transient( 'ayyash_studio_installing' );

		do_action( 'ayyash_studio_installed' );
	}

	public static function migrate() {
		delete_site_option( 'ayyash_studio_editor_store' ); // old store

		$last_updates   = get_site_option( 'ayyash_studio_last_updates', false );
		$cached_updates = ayyash_studio_read_json( 'last_updates', false );
		$last_timestamp = $last_updates['timestamp'] ?? false;
		$last_timestamp = is_numeric( $last_timestamp ) ? gmdate( 'Y-m-d H:i:s', $last_timestamp ) : $last_timestamp;

		if ( ! $last_timestamp || ( $cached_updates && $last_timestamp < $cached_updates->timestamp ) ) {
			$cached_assets = ayyash_studio_read_json( 'cached_assets', true );
			foreach ( $cached_assets as $asset ) {
				$cached = ayyash_studio_read_json( $asset, false );
				if ( $cached ) {
					update_site_option( 'ayyash_studio_' . $asset, $cached );
				}
			}

			update_site_option( 'ayyash_studio_last_updates', [
				'sites'      => $cached_updates->sites->hash,
				'editors'    => $cached_updates->editors->hash,
				'categories' => $cached_updates->categories->hash,
				'timestamp'  => $cached_updates->timestamp,
			] );
		}

		Scheduler::check_update();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'ayyash_studio_cleanup_logs' );
		Scheduler::stop();
	}


	/**
	 * Is this a brand-new installation?
	 *
	 * A brand-new installation has no version yet. Also treat empty installs as 'new'.
	 *
	 * @return bool
	 */
	public static function is_new_install() {
		return is_null( get_option( 'ayyash_studio_version', null ) );
	}

	/**
	 * See if we need to set redirect transients for activation or not.
	 */
	private static function maybe_set_activation_transients() {
		if ( self::is_new_install() ) {
			set_site_transient( 'ayyash_studio_activation_redirect', 1, 30 );
		}
	}

	/**
	 * Update WC version to current.
	 */
	private static function update_version() {
		update_site_option( 'ayyash_studio_version', AYYASH_STUDIO_VERSION );
	}

	/**
	 * Create cron jobs (clear them first).
	 */
	private static function create_cron_jobs() {
		wp_clear_scheduled_hook( 'ayyash_studio_cleanup_logs' );

		wp_schedule_event( time() + MINUTE_IN_SECONDS, 'daily', 'ayyash_studio_cleanup_logs' );
	}

	/**
	 * Default options.
	 *
	 * Sets up the default options used on the settings page.
	 */
	private static function create_options() {
	}

	/**
	 * Create files/directories.
	 */
	private static function create_files() {
		// Bypass if filesystem is read-only and/or non-standard upload system is used.
		if ( apply_filters( 'ayyash_studio_install_skip_create_files', false ) ) {
			return;
		}

		// Install files and folders for uploading files and prevent hot-linking.

		$files = [
			[
				'base'    => AYYASH_STUDIO_UPLOADS_DIR,
				'file'    => 'index.html',
				'content' => '',
			],
			[
				'base'    => AYYASH_STUDIO_UPLOADS_DIR,
				'file'    => '.htaccess',
				'content' => 'deny from all',
			],
		];

		foreach ( $files as $file ) {
			if ( wp_mkdir_p( $file['base'] ) && ! file_exists( trailingslashit( $file['base'] ) . $file['file'] ) ) {
				$file_handle = @fopen( trailingslashit( $file['base'] ) . $file['file'], 'wb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_read_fopen
				if ( $file_handle ) {
					fwrite( $file_handle, $file['content'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fwrite, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite
					fclose( $file_handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
				}
			}
		}
	}

	/**
	 * Show action links on the plugin screen.
	 *
	 * @param array $links Plugin Action links.
	 *
	 * @return array
	 */
	public static function plugin_action_links( $links ) {
		return array_merge( [
			'library' => '<a href="' . esc_url( admin_url( 'themes.php?page=' . TemplateLibrary::SLUG ) ) . '" aria-label="' . esc_attr__( 'See Ayyash Theme Prebuilt Templates', 'ayyash-studio' ) . '">' . esc_html__( 'Template Library', 'ayyash-studio' ) . '</a>',
		], $links );
	}

	/**
	 * Show row meta on the plugin screen.
	 *
	 * @param string[] $meta Plugin Row Meta.
	 * @param string $file Plugin Base file.
	 *
	 * @return array
	 */
	public static function plugin_row_meta( $meta, $file ) {
		if ( AYYASH_STUDIO_PLUGIN_BASENAME !== $file ) {
			return $meta;
		}

		$meta['docs']    = '<a href="' . esc_url( apply_filters( 'ayyash_studio_docs_url', 'https://docs.themeoo.com/docs/ayyash-studio/' ) ) . '" aria-label="' . esc_attr__( 'View documentation', 'ayyash-studio' ) . '">' . esc_html__( 'Docs', 'ayyash-studio' ) . '</a>';
		$meta['support'] = '<a href="' . esc_url( apply_filters( 'ayyash_studio_community_support_url', 'https://wordpress.org/support/plugin/ayyash-studio/' ) ) . '" aria-label="' . esc_attr__( 'Visit community forums', 'ayyash-studio' ) . '">' . esc_html__( 'Community support', 'ayyash-studio' ) . '</a>';

		return $meta;
	}
}

// End of file Installer.php.

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

namespace AyyashStudio;

use AyyashStudio\Cli\Cli;
use AyyashStudio\Importer\LibraryDataImporter;
use AyyashStudio\Traits\Singleton;
use AyyashStudio\Traits\Requester;
use AyyashStudio\Client\Client;
use AyyashStudio\Queue\Queue_Interface;
use AyyashStudio\Queue\Queue;
use AyyashStudio\Scheduler\Scheduler;
use AyyashStudio\Importer\Importer;
use AyyashStudio\TemplateLibrary\TemplateLibrary;

/** @define "AYYASH_STUDIO_PLUGIN_PATH" "./../" */


if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

final class AyyashStudio {

	use Singleton;

	public $version = AYYASH_STUDIO_VERSION;

	protected function __construct() {
		$this->include_packages();
		$this->init_hooks();
	}

	/**
	 * When WP has loaded all plugins, trigger the `ayyash_studio_loaded` hook.
	 *
	 * This ensures `ayyash_studio_loaded` is called only after all other plugins
	 * are loaded, to avoid issues caused by plugin directory naming changing
	 * the load order. See #21524 for details.
	 */
	public function on_plugins_loaded() {
		do_action( 'ayyash_studio_loaded' );
	}

	/**
	 * Hook into actions and filters.
	 */
	private function init_hooks() {
		register_activation_hook( AYYASH_STUDIO_PLUGIN_FILE, [ Installer::class, 'install' ] );
		register_deactivation_hook( AYYASH_STUDIO_PLUGIN_FILE, [ Installer::class, 'deactivate' ] );

		AyyashStudioErrorHandler::get_instance();

		register_shutdown_function( [ $this, 'log_errors' ] );

		Installer::init();
		Importer::get_instance();
		TemplateLibrary::get_instance();
		LibraryDataImporter::get_instance();
		Scheduler::get_instance();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			Cli::get_instance();
		}

		add_action( 'plugins_loaded', [ $this, 'on_plugins_loaded' ], - 1 );
		add_action( 'init', [ $this, 'init' ], 0 );

		// Cache purge support for “Nginx Cache” plugin (https://wordpress.org/plugins/nginx-cache/).
		add_filter( 'nginx_cache_purge_actions', [ $this, 'add_nginx_purge_cache_action' ] );
	}

	/**
	 * Get queue instance.
	 *
	 * @return Queue_Interface
	 */
	public function queue(): Queue_Interface {
		return Queue::get_instance();
	}

	/**
	 * @return Client
	 */
	public function client(): Client {
		return Client::get_instance();
	}

	/**
	 * @param $actions
	 *
	 * @return mixed
	 */
	public function add_nginx_purge_cache_action( $actions ) {
		$actions[] = 'ayyash_studio_import_finish';
		$actions[] = 'ayyash_studio_async_process_complete';
		return $actions;
	}

	/**
	 * Ensures fatal errors are logged, so they can be picked up in the status report.
	 */
	public function log_errors() {
		$error     = error_get_last();
		$trackable = [ E_ERROR, E_PARSE, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ];
		if ( $error && in_array( $error['type'], $trackable, true ) ) {
			ayyash_studio_log_critical(
				sprintf(
					/* translators: 1: error message 2: file name and path 3: line number */
					__( '%1$s in %2$s on line %3$s', 'ayyash-studio' ),
					$error['message'],
					$error['file'],
					$error['line']
				) . PHP_EOL,
				'fatal-errors'
			);

			do_action( 'ayyash_studio_shutdown_error', $error );
		}
	}

	/**
	 * Include 3rd party packages.
	 *
	 * @return void
	 */
	private function include_packages() {
		require_once AYYASH_STUDIO_PLUGIN_PATH . 'packages/action-scheduler/action-scheduler.php';
	}

	/**
	 * Initialize.
	 */
	public function init() {
		// Before init action.
		do_action( 'before_ayyash_studio_init' );

		// Set up localisation.
		$this->load_plugin_textdomain();

		// Init action.
		do_action( 'ayyash_studio_init' );
	}

	public function unsupported_theme_notice() {
		if ( current_theme_supports( 'ayyash-studio' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! isset( $screen->id ) ) {
			return;
		}

		if ( 'plugins' === $screen->id || 'themes' === $screen->id ) {
			return;
		}

		?>
		<div class="notice notice-warning notice-alt">
			<p><?php printf(
				/* translators: %1$s Ayyash theme name, %2$s Ayyash Studio plugin name. */
					esc_html__( 'Your current theme (%1$s) does not support %2$s. To use  %2$s please install and activate %1$s or any of it\'s supported theme.', 'ayyash-studio' ),
					'<strong>' . esc_html__( 'Ayyash', 'ayyash-studio' ) . '</strong>',
					'<strong>' . esc_html__( 'Ayyash Studio', 'ayyash-studio' ) . '</strong>'
				); ?></p>
			<p class="actions">
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'theme-install.php?theme=ayyash' ) ); ?>">
					<?php printf(
					/* translators: %s Ayyash theme name. */
						esc_html__( 'Install & Activate %s', 'ayyash-studio' ),
						'Ayyash'
					);
					?>
				</a>
			</p>
		</div>
		<?php
	}

	public function load_plugin_textdomain() {
		$locale = determine_locale();
		$locale = apply_filters( 'plugin_locale', $locale, 'ayyash-studio' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		unload_textdomain( 'ayyash-studio' );

		load_textdomain( 'ayyash-studio', WP_LANG_DIR . '/ayyash-studio/ayyash-studio-' . $locale . '.mo' ); // @phpstan-ignore-line
		load_plugin_textdomain( 'ayyash-studio', false, plugin_basename( dirname( AYYASH_STUDIO_PLUGIN_FILE ) ) . '/languages' );

		// Script translation.
		wp_set_script_translations( 'ayyash-studio-library', 'ayyash-studio' );
	}


}

// End of file AyyashStudio.php.

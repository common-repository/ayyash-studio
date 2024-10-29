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

use Exception;
use AyyashStudio\Traits\Singleton;
use Throwable;

/** @define "AYYASH_STUDIO_PLUGIN_PATH" "./../" */

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

define( 'AYYASH_STUDIO_TRACKABLE', E_ERROR | E_PARSE | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR );

class AyyashStudioErrorHandler {
	use Singleton;

	protected function __construct() {

		// @XXX maybe we should track other errors too.
		if ( true === ayyash_studio_is_importing() ) {
			$this->start_error_handler();
		}

		add_action( 'shutdown', array( $this, 'stop_handler' ) );
	}


	/**
	 * Stop the shutdown handlers.
	 *
	 * @return void
	 */
	public function stop_handler() {
		if ( true === ayyash_studio_is_importing() ) {
			$this->stop_error_handler();
		}
	}

	/**
	 * Start the error handling.
	 */
	public function start_error_handler() {
		if ( ! interface_exists( 'Throwable' ) ) {
			// Fatal error handler for PHP < 7.
			register_shutdown_function( array( $this, 'shutdown_handler' ) );
		}

		// Fatal error handler for PHP >= 7, and uncaught exception handler for all PHP versions.
		set_exception_handler( array( $this, 'exception_handler' ) );
	}

	/**
	 * Stop and restore the error handlers.
	 */
	public function stop_error_handler() {
		// Restore the error handlers.
		restore_error_handler();
		restore_exception_handler();
	}

	/**
	 * Uncaught exception handler.
	 *
	 * In PHP >= 7 this will receive a Throwable object.
	 * In PHP < 7 it will receive an Exception object.
	 *
	 * @param Throwable|Exception $e The error or exception.
	 *
	 * @throws Exception|Throwable Exception that is catched.
	 */
	public function exception_handler( $e ) {
		if ( is_a( $e, 'Exception' ) ) {
			$error = 'Uncaught Exception';
		} else {
			$error = 'Uncaught Error';
		}

		ayyash_studio_log_critical(
			sprintf(
			/* translators: 1: error message 2: file name and path 3: line number 4: Backtrace Label (Backtrace:) 5: Backtrace data. */
				__( '%1$s in %2$s on line %3$s. %4$s %5$s', 'ayyash-studio' ),
				$e->getMessage(),
				$e->getFile(),
				$e->getLine(),
				__( 'Backtrace:', 'ayyash-studio' ),
				wp_debug_backtrace_summary( __CLASS__ ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_wp_debug_backtrace_summary
			) . PHP_EOL,
			'fatal-errors'
		);

		if ( wp_doing_ajax() ) {
			wp_send_json_error(
				[
					'message' => __( 'Error while processing your request.', 'ayyash-studio' ),
					'stack'   => [
						'error-message' => sprintf( '%s: %s', $error, $e->getMessage() ),
						'file'          => $e->getFile(),
						'line'          => $e->getLine(),
						'trace'         => $e->getTrace(),
					],
				]
			);
		}

		throw $e;
	}

	/**
	 * Displays fatal error output for sites running PHP < 7.
	 */
	public function shutdown_handler() {
		$e = error_get_last();

		if ( empty( $e ) || ! ( $e['type'] & AYYASH_STUDIO_TRACKABLE ) ) {
			return;
		}

		if ( $e['type'] & E_RECOVERABLE_ERROR ) {
			$error = 'Catchable fatal error';
		} else {
			$error = 'Fatal error';
		}

		ayyash_studio_log_info( $error, 'fatal-errors' );

		ayyash_studio_log_critical(
			sprintf(
			/* translators: 1: error message 2: file name and path 3: line number */
				__( '%1$s in %2$s on line %3$s', 'ayyash-studio' ),
				$e['message'],
				$e['file'],
				$e['line']
			) . PHP_EOL,
			'fatal-errors'
		);

		if ( wp_doing_ajax() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Error while processing your request.', 'ayyash-studio' ),
					'stack'   => array(
						'error-message' => $error . ': ' . $e['message'],
						'error'         => $e,
					),
				)
			);
		}
	}
}

// End of file AyyashStudioErrorHandler.php.

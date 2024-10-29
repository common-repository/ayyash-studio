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

use AyyashStudio\Logger\Log_Handler_WP_Cli;
use AyyashStudio\Logger\Logger;
use AyyashStudio\Logger\Logger_Interface;
use AyyashStudio\Logger\Log_Levels;
use AyyashStudio\Logger\Log_Handler_File;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

function ayyash_studio_cli_log( $message, $level = Log_Levels::INFO ) {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		// 'emergency|alert|critical|error|warning|notice|info|debug|success'
		switch ( $level ) {
			case 'emergency':
			case 'critical':
			case 'error':
				\WP_CLI::error( $message );
				break;
			case 'warning':
			case 'alert':
				\WP_CLI::warning( $message );
				break;
			case 'debug':
				\WP_CLI::debug( $message );
				break;
			case 'success':
				\WP_CLI::success( $message );
				break;
			default:
				\WP_CLI::log( $message );
				break;
		}
	}
}

/**
 * Get a shared logger instance.
 *
 * Use the ayyash_studio_logging_class filter to change the logging class. You may provide one of the following:
 *     - a class name which will be instantiated as `new $class` with no arguments
 *     - an instance which will be used directly as the logger
 * In either case, the class or instance *must* implement Logger_Interface.
 *
 * @return Logger
 * @see Logger_Interface
 */
function ayyash_studio_get_logger() {
	static $logger = null;

	$class = apply_filters( 'ayyash_studio_logging_class', Logger::class );

	if ( null !== $logger && is_string( $class ) && is_a( $logger, $class ) ) {
		return $logger;
	}

	$implements = class_implements( $class );

	if ( is_array( $implements ) && in_array( Logger_Interface::class, $implements, true ) ) {
		$logger = is_object( $class ) ? $class : new $class();
	} else {
		ayyash_studio_doing_it_wrong(
			__FUNCTION__,
			sprintf(
			/* translators: 1: class name 2: ayyash_studio_logging_class 3: Logger_Interface */
				__( 'The class %1$s provided by %2$s filter must implement %3$s.', 'ayyash-studio' ),
				'<code>' . esc_html( is_object( $class ) ? get_class( $class ) : $class ) . '</code>',
				'<code>ayyash_studio_logging_class</code>',
				'<code>' . Logger_Interface::class . '</code>'
			),
			'1.0.0'
		);

		$logger = is_a( $logger, Logger::class ) ? $logger : new Logger();
	}

	return $logger;
}

function ayyash_studio_log( $message, $level = Log_Levels::INFO, $source = null ) {
	$logger = ayyash_studio_get_logger();
	if ( ! $source ) {
		$source = 'ayyash-studio-log';
	}
	$logger->log( $level, $message, [ 'source' => $source ] );
}

function ayyash_studio_log_info( $message, $source = null ) {
	ayyash_studio_log( $message, Log_Levels::INFO, $source );
}

function ayyash_studio_log_debug( $message, $source = null ) {
	ayyash_studio_log( $message, Log_Levels::DEBUG, $source );
}

function ayyash_studio_log_notice( $message, $source = null ) {
	ayyash_studio_log( $message, Log_Levels::NOTICE, $source );
}

function ayyash_studio_log_warning( $message, $source = null ) {
	ayyash_studio_log( $message, Log_Levels::WARNING, $source );
}

function ayyash_studio_log_error( $message, $source = null ) {
	ayyash_studio_log( $message, Log_Levels::ERROR, $source );
}

function ayyash_studio_log_critical( $message, $source = null ) {
	ayyash_studio_log( $message, Log_Levels::CRITICAL, $source );
}

function ayyash_studio_log_alert( $message, $source = null ) {
	ayyash_studio_log( $message, Log_Levels::ALERT, $source );
}

function ayyash_studio_log_emergency( $message, $source = null ) {
	ayyash_studio_log( $message, Log_Levels::EMERGENCY, $source );
}

function ayyash_studio_log_db_error( $whatsDoing = '', $source = null ) {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		global $wpdb;
		if ( $wpdb->last_error ) {
			if ( $whatsDoing ) {
				ayyash_studio_log_emergency(
					sprintf( 'Encountered with database error while %s', $whatsDoing ),
					$source
				);
			}
			ayyash_studio_log_debug( $wpdb->last_error, $source );
		}
	}
}

/**
 * Trigger logging cleanup using the logging class.
 */
function ayyash_studio_cleanup_logs() {
	$logger = ayyash_studio_get_logger();

	if ( is_callable( array( $logger, 'clear_expired_logs' ) ) ) {
		$logger->clear_expired_logs();
	}
}

add_action( 'ayyash_studio_cleanup_logs', 'ayyash_studio_cleanup_logs' );

/**
 * Get a log file path.
 *
 * @param string $handle name.
 *
 * @return string the log file path.
 * @since 2.2
 */
function ayyash_studio_get_log_file_path( $handle ) {
	return Log_Handler_File::get_log_file_path( $handle );
}

/**
 * Get a log file name.
 *
 * @param string $handle Name.
 *
 * @return string The log file name.
 */
function ayyash_studio_get_log_file_name( $handle ) {
	return Log_Handler_File::get_log_file_name( $handle );
}

/**
 * Registers the default log handler.
 *
 * @param array $handlers Handlers.
 *
 * @return array
 */
function ayyash_studio_register_default_log_handler( $handlers ) {
	$handler_class = defined( 'AYYASH_STUDIO_LOG_HANDLER' ) ? AYYASH_STUDIO_LOG_HANDLER : null;
	if ( ! class_exists( $handler_class ) ) {
		$handler_class = Log_Handler_File::class;
	}

	$handlers[] = new $handler_class();

	if ( defined( 'WP_CLI' ) && WP_CLI && ! defined( 'AYYASH_STUDIO_INSTALLING') ) {
		$handlers[] = new Log_Handler_WP_Cli();
	}


	return $handlers;
}

add_filter( 'ayyash_studio_register_log_handlers', 'ayyash_studio_register_default_log_handler' );


// End of file log.php.

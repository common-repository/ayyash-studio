<?php
/**
 * Provides logging capabilities for debugging purposes.
 */

namespace AyyashStudio\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

/**
 * Logger class.
 */
class Logger implements Logger_Interface {

	/**
	 * Stores registered log handlers.
	 *
	 * @var array
	 */
	protected $handlers;

	/**
	 * Minimum log level this handler will process.
	 *
	 * @var int Integer representation of minimum log level to handle.
	 */
	protected $threshold;

	/**
	 * Constructor for the logger.
	 *
	 * @param array|null $handlers Optional. Array of log handlers. If $handlers is not provided, the filter 'ayyash_studio_register_log_handlers' will be used to define the handlers. If $handlers is provided, the filter will not be applied and the handlers will be used directly.
	 * @param string|null $threshold Optional. Define an explicit threshold. May be configured via  LOG_THRESHOLD. By default, all logs will be processed.
	 */
	public function __construct( array $handlers = null, string $threshold = null ) {
		if ( null === $handlers ) {
			$handlers = apply_filters( 'ayyash_studio_register_log_handlers', [] );
		}

		$register_handlers = [];

		if ( ! empty( $handlers ) && is_array( $handlers ) ) {
			foreach ( $handlers as $handler ) {
				$implements = class_implements( $handler );
				if ( is_object( $handler ) && is_array( $implements ) && in_array( Log_Handler_Interface::class, $implements, true ) ) {
					$register_handlers[] = $handler;
				} else {
					ayyash_studio_doing_it_wrong(
						__METHOD__,
						sprintf(
						/* translators: 1: class name 2: Log_Handler_Interface */
							__( 'The provided handler %1$s does not implement %2$s.', 'ayyash-studio' ),
							'<code>' . esc_html( is_object( $handler ) ? get_class( $handler ) : $handler ) . '</code>',
							'<code>' . Log_Handler_Interface::class . '</code>'
						),
						'1.0.0'
					);
				}
			}
		}

		if ( null !== $threshold && ! Log_Levels::is_valid_level( $threshold ) ) {
			$threshold = null;
		}

		if ( null !== $threshold ) {
			$threshold = Log_Levels::get_level_severity( $threshold );
		}

		$this->handlers  = $register_handlers;
		$this->threshold = $threshold;
	}

	/**
	 * Determine whether to handle or ignore log.
	 *
	 * @param string $level emergency|alert|critical|error|warning|notice|info|debug.
	 *
	 * @return bool True if the log should be handled.
	 */
	protected function should_handle( $level ) {
		if ( null === $this->threshold ) {
			return true;
		}

		return $this->threshold <= Log_Levels::get_level_severity( $level );
	}

	/**
	 * Add a log entry.
	 *
	 * This is not the preferred method for adding log messages. Please use log() or any one of
	 * the level methods (debug(), info(), etc.). This method may be deprecated in the future.
	 *
	 * @param string $handle File handle.
	 * @param string $message Message to log.
	 * @param string $level Logging level.
	 *
	 * @return bool
	 */
	public function add( $handle, $message, $level = Log_Levels::NOTICE ) {
		$message = apply_filters( 'ayyash_studio_logger_add_message', $message, $handle );
		$this->log(
			$level,
			$message,
			[
				'source'  => $handle,
				'_legacy' => true,
			]
		);

		return true;
	}

	/**
	 * Add a log entry.
	 *
	 * @param string $level One of the following:
	 *     'emergency': System is unusable.
	 *     'alert': Action must be taken immediately.
	 *     'critical': Critical conditions.
	 *     'error': Error conditions.
	 *     'warning': Warning conditions.
	 *     'notice': Normal but significant condition.
	 *     'info': Informational messages.
	 *     'debug': Debug-level messages.
	 * @param string $message Log message.
	 * @param array $context Optional. Additional information for log handlers.
	 */
	public function log( $level, $message, $context = [] ) {
		if ( ! Log_Levels::is_valid_level( $level ) ) {
			/* translators: 1: Logger::log 2: level */
			ayyash_studio_doing_it_wrong( __METHOD__, sprintf( __( '%1$s was called with an invalid level "%2$s".', 'ayyash-studio' ), '<code>Logger::log</code>', $level ), '1.0.0' );
		}

		if ( $this->should_handle( $level ) ) {
			$timestamp = time();

			foreach ( $this->handlers as $handler ) {
				/**
				 * Filter the logging message. Returning null will prevent logging from occurring
				 *
				 * @param string $message Log message.
				 * @param string $level One of: emergency, alert, critical, error, warning, notice, info, or debug.
				 * @param array $context Additional information for log handlers.
				 * @param object $handler The handler object, such as Log_Handler_File.
				 */
				$message = apply_filters( 'ayyash_studio_logger_log_message', $message, $level, $context, $handler );

				if ( null !== $message ) {
					$handler->handle( $timestamp, $level, $message, $context );
				}
			}
		}
	}

	/**
	 * Adds an emergency level message.
	 *
	 * System is unusable.
	 *
	 * @param string $message Message to log.
	 * @param array $context Log context.
	 *
	 * @see Logger::log
	 */
	public function emergency( $message, $context = [] ) {
		$this->log( Log_Levels::EMERGENCY, $message, $context );
	}

	/**
	 * Adds an alert level message.
	 *
	 * Action must be taken immediately.
	 * Example: Entire website down, database unavailable, etc.
	 *
	 * @param string $message Message to log.
	 * @param array $context Log context.
	 *
	 * @see Logger::log
	 */
	public function alert( $message, $context = [] ) {
		$this->log( Log_Levels::ALERT, $message, $context );
	}

	/**
	 * Adds a critical level message.
	 *
	 * Critical conditions.
	 * Example: Application component unavailable, unexpected exception.
	 *
	 * @param string $message Message to log.
	 * @param array $context Log context.
	 *
	 * @see Logger::log
	 */
	public function critical( $message, $context = [] ) {
		$this->log( Log_Levels::CRITICAL, $message, $context );
	}

	/**
	 * Adds an error level message.
	 *
	 * Runtime errors that do not require immediate action but should typically be logged
	 * and monitored.
	 *
	 * @param string $message Message to log.
	 * @param array $context Log context.
	 *
	 * @see Logger::log
	 */
	public function error( $message, $context = [] ) {
		$this->log( Log_Levels::ERROR, $message, $context );
	}

	/**
	 * Adds a warning level message.
	 *
	 * Exceptional occurrences that are not errors.
	 *
	 * Example: Use of deprecated APIs, poor use of an API, undesirable things that are not
	 * necessarily wrong.
	 *
	 * @param string $message Message to log.
	 * @param array $context Log context.
	 *
	 * @see Logger::log
	 */
	public function warning( $message, $context = [] ) {
		$this->log( Log_Levels::WARNING, $message, $context );
	}

	/**
	 * Adds a notice level message.
	 *
	 * Normal but significant events.
	 *
	 * @param string $message Message to log.
	 * @param array $context Log context.
	 *
	 * @see Logger::log
	 */
	public function notice( $message, $context = [] ) {
		$this->log( Log_Levels::NOTICE, $message, $context );
	}

	/**
	 * Adds an info level message.
	 *
	 * Interesting events.
	 * Example: User logs in, SQL logs.
	 *
	 * @param string $message Message to log.
	 * @param array $context Log context.
	 *
	 * @see Logger::log
	 */
	public function info( $message, $context = [] ) {
		$this->log( Log_Levels::INFO, $message, $context );
	}

	/**
	 * Adds a debug level message.
	 *
	 * Detailed debug information.
	 *
	 * @param string $message Message to log.
	 * @param array $context Log context.
	 *
	 * @see Logger::log
	 */
	public function debug( $message, $context = [] ) {
		$this->log( Log_Levels::DEBUG, $message, $context );
	}

	/**
	 * Clear entries for a chosen file/source.
	 *
	 * @param string $source Source/handle to clear.
	 *
	 * @return bool
	 */
	public function clear( $source = '' ) {
		if ( ! $source ) {
			return false;
		}
		foreach ( $this->handlers as $handler ) {
			if ( is_callable( array( $handler, 'clear' ) ) ) {
				$handler->clear( $source );
			}
		}

		return true;
	}

	/**
	 * Clear all logs older than a defined number of days. Defaults to 30 days.
	 *
	 * @since 3.4.0
	 */
	public function clear_expired_logs() {
		$days      = absint( apply_filters( 'ayyash_studio_logger_days_to_retain_logs', 30 ) );
		$timestamp = strtotime( "-$days days" );

		foreach ( $this->handlers as $handler ) {
			if ( is_callable( array( $handler, 'delete_logs_before_timestamp' ) ) ) {
				$handler->delete_logs_before_timestamp( $timestamp );
			}
		}
	}
}

// End of file Logger.php.

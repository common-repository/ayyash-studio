<?php /** @noinspection DuplicatedCode */

/**
 * Handles log entries by writing to a file.
 */

namespace AyyashStudio\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class Log_Handler_WP_Cli extends Log_Handler {

	/**
	 * Constructor for the logger.
	 *
	 * @param int $log_size_limit Optional. Size limit for log files. Default 5mb.
	 */
	public function __construct( $log_size_limit = null ) {
	}

	/**
	 * Handle a log entry.
	 *
	 * @param int    $timestamp Log timestamp.
	 * @param string $level emergency|alert|critical|error|warning|notice|info|debug|success.
	 * @param string $message Log message.
	 * @param array  $context {
	 *      Additional information for log handlers.
	 *
	 *     @type string $source Optional. Determines log file to write to. Default 'log'.
	 *     @type bool $_legacy Optional. Default false. True to use outdated log format
	 *         originally used in deprecated WC_Logger::add calls.
	 * }
	 *
	 * @return bool False if value was not handled and true if value was handled.
	 */
	public function handle( $timestamp, $level, $message, $context ) {
		ayyash_studio_cli_log( $message, $level );

		return true;
	}

	/**
	 * Builds a log entry text from timestamp, level and message.
	 *
	 * @param int    $timestamp Log timestamp.
	 * @param string $level emergency|alert|critical|error|warning|notice|info|debug.
	 * @param string $message Log message.
	 * @param array  $context Additional information for log handlers.
	 *
	 * @return string Formatted log entry.
	 */
	protected static function format_entry( $timestamp, $level, $message, $context ) {
		return '';
	}
}

// End of file Log_Handler_WP_Cli.php.

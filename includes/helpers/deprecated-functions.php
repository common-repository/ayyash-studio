<?php
/**
 * Deprecated functions
 *
 * Where functions come to die, and functions help that help dying.
 *
 * @package AyyashStudio
 * @version 1.0.0
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

/**
 * Runs a deprecated action with notice only if used.
 *
 * @param string $tag The name of the action hook.
 * @param array $args Array of additional function arguments to be passed to do_action().
 * @param string $version The version of WooCommerce that deprecated the hook.
 * @param string $replacement The hook that should have been used.
 * @param string $message A message regarding the change.
 */
function ayyash_studio_do_deprecated_action( $tag, $args, $version, $replacement = null, $message = null ) {
	if ( ! has_action( $tag ) ) {
		return;
	}

	ayyash_studio_deprecated_hook( $tag, $version, $replacement, $message );
	do_action_ref_array( $tag, $args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
}

/**
 * Wrapper for deprecated functions so we can apply some extra logic.
 *
 * @param string $function Function used.
 * @param string $version Version the message was added in.
 * @param string $replacement Replacement for the called function.
 */
function ayyash_studio_deprecated_function( $function, $version, $replacement = null ) {
	// @codingStandardsIgnoreStart
	if ( wp_doing_ajax() || ayyash_studio()->is_rest_api_request() ) {
		do_action( 'deprecated_function_run', $function, $replacement, $version );
		$log_string = "The {$function} function is deprecated since version {$version}.";
		$log_string .= $replacement ? " Replace with {$replacement}." : '';
		error_log( $log_string );
	} else {
		_deprecated_function( $function, $version, $replacement );
	}
	// @codingStandardsIgnoreEnd
}

/**
 * Wrapper for deprecated hook so we can apply some extra logic.
 *
 * @param string $hook The hook that was used.
 * @param string $version The version of WordPress that deprecated the hook.
 * @param string $replacement The hook that should have been used.
 * @param string $message A message regarding the change.
 */
function ayyash_studio_deprecated_hook( $hook, $version, $replacement = null, $message = null ) {
	// @codingStandardsIgnoreStart
	if ( wp_doing_ajax() || ayyash_studio()->is_rest_api_request() ) {
		do_action( 'deprecated_hook_run', $hook, $replacement, $version, $message );

		$message    = empty( $message ) ? '' : ' ' . $message;
		$log_string = "{$hook} is deprecated since version {$version}";
		$log_string .= $replacement ? "! Use {$replacement} instead." : ' with no alternative available.';

		error_log( $log_string . $message );
	} else {
		_deprecated_hook( $hook, $version, $replacement, $message );
	}
	// @codingStandardsIgnoreEnd
}

/**
 * When catching an exception, this allows us to log it if unexpected.
 *
 * @param Exception $exception_object The exception object.
 * @param string $function The function which threw exception.
 * @param array $args The args passed to the function.
 */
function ayyash_studio_caught_exception( $exception_object, $function = '', $args = [] ) {
	// @codingStandardsIgnoreStart
	$message = $exception_object->getMessage();
	$message .= '. Args: ' . print_r( $args, true ) . '.';

	do_action( 'woocommerce_caught_exception', $exception_object, $function, $args );
	error_log( "Exception caught in {$function}. {$message}." );
	// @codingStandardsIgnoreEnd
}

/**
 * Wrapper for _doing_it_wrong().
 *
 * @param string $function Function used.
 * @param string $message Message to log.
 * @param string $version Version the message was added in.
 */
function ayyash_studio_doing_it_wrong( $function, $message, $version ) {
	// @codingStandardsIgnoreStart
	$message .= PHP_EOL . ' Backtrace: ' . wp_debug_backtrace_summary() . PHP_EOL;

	if ( wp_doing_ajax() || ayyash_studio()->is_rest_api_request() ) {
		do_action( 'doing_it_wrong_run', $function, $message, $version );
		if ( function_exists( '__' ) ) {
			if ( $version ) {
				/* translators: %s: Version number. */
				$version = sprintf( __( '(This message was added in version %s.)' ), $version );
			}
			/* translators: Developer debugging message. 1: PHP function name, 2: Explanatory message, 3: WordPress version number. */
			$error = sprintf( __( '%1$s was called <strong>incorrectly</strong>. %2$s %3$s' ), $function, $message, $version );
		} else {
			if ( $version ) {
				$version = sprintf( '(This message was added in version %s.)', $version );
			}

			$error = sprintf( '%1$s was called <strong>incorrectly</strong>. %2$s %3$s', $function, $message, $version );
		}
		error_log( $error );
	} else {
		_doing_it_wrong( $function, $message, $version );
	}
	// @codingStandardsIgnoreEnd
}

/**
 * Wrapper for deprecated arguments so we can apply some extra logic.
 *
 * @param string $argument
 * @param string $version
 * @param string $replacement
 */
function ayyash_studio_deprecated_argument( $argument, $version, $message = null ) {
	// @codingStandardsIgnoreStart
	if ( wp_doing_ajax() || ayyash_studio()->is_rest_api_request() ) {
		do_action( 'deprecated_argument_run', $argument, $message, $version );
		error_log( "The {$argument} argument is deprecated since version {$version}. {$message}" );
	} else {
		_deprecated_argument( $argument, $version, $message );
	}
	// @codingStandardsIgnoreEnd
}

// End of file deprecated-functions.php.

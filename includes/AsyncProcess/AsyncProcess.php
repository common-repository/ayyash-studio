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

namespace AyyashStudio\AsyncProcess;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class AsyncProcess extends WP_Background_Process {

	/**
	 * Image Process
	 *
	 * @var string
	 */
	protected $action = 'ayyash_studio_async_queue';

	protected $cron_interval = 1;

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param object|mixed $item Queue item object.
	 * @return mixed
	 */
	protected function task( $item ) {
		if ( $item ) {
			ayyash_studio_log_info( 'Running:: ' . get_class( $item ) );

			if ( method_exists( $item, 'run' ) ) {
				return $item->run();
			}
		}

		return false;
	}

	/**
	 * Complete
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		parent::complete();

		do_action( 'ayyash_studio_async_process_complete' );
	}

}

// End of file AsyncProcess.php.

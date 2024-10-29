<?php
/**
 * @TODO need doc.
 */

namespace AyyashStudio\Queue;

use AyyashStudio\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

final class Queue {

	use Singleton;

	/**
	 * The single instance of the queue.
	 *
	 * @var Queue_Interface|null
	 */
	protected static $instance = null;

	/**
	 * The default queue class to initialize
	 *
	 * @var string
	 */
	protected static $default_cass = Action_Queue::class;

	/**
	 * Single instance of WC_Queue_Interface
	 *
	 * @return Queue_Interface
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			$class          = self::get_class();
			self::$instance = new $class();
			self::$instance = self::validate_instance( self::$instance );
		}
		return self::$instance;
	}

	/**
	 * Get class to instantiate
	 *
	 * And make sure 3rd party code has the chance to attach a custom queue class.
	 *
	 * @return string
	 */
	protected static function get_class(): string {
		if ( ! did_action( 'plugins_loaded' ) ) {
			ayyash_studio_doing_it_wrong( __FUNCTION__, __( 'This function should not be called before plugins_loaded.', 'ayyash-studio' ), '1.0.0' );
		}

		return apply_filters( 'ayyash_studio_queue_class', self::$default_cass );
	}

	/**
	 * Enforce a WC_Queue_Interface
	 *
	 * @param Queue_Interface|mixed $instance Instance class.
	 * @return Queue_Interface
	 */
	protected static function validate_instance( $instance ): Queue_Interface {
		if ( false === ( $instance instanceof Queue_Interface ) ) {
			$default_class = self::$default_cass;
			/* translators: %s: Default class name */
			ayyash_studio_doing_it_wrong( __FUNCTION__, sprintf( __( 'The class attached to the "ayyash_studio_queue_class" does not implement the AyyashStudio\Queue\Queue_Interface interface. The default %s class will be used instead.', 'ayyash-studio' ), $default_class ), '1.0.0' );
			$instance = new $default_class();
		}

		return $instance;
	}
}

// End of file Queue.php.

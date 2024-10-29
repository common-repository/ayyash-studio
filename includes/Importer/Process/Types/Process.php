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

namespace AyyashStudio\Importer\Process\Types;

use RuntimeException;
use AyyashStudio\Importer\Traits\ID_Mappings;
use AyyashStudio\Importer\Traits\Post_Query;
use AyyashStudio\Importer\Traits\Site_Data;
use WP_Post;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class Process implements ProcessInterface {

	use ID_Mappings;
	use Site_Data;
	use Post_Query;

	/**
	 * Singleton instance ref.
	 *
	 * @var self
	 */
	protected static $instance;

	/**
	 * Create one instance of this class, stores and return that.
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Class constructor.
	 *
	 * Enforce singleton instance.
	 *
	 * @return void
	 */
	protected function __construct() {
	}

	/**
	 * Process run method.
	 * This child class must override this method.
	 *
	 * This class/method could be declared as abstract, but then it would not be
	 * possible to implement the get_instance method. As abstract method can't be instantiated (new self()).
	 * Also we can't use the singleton trait, as async process runner will serialize and unserialize
	 * these process classes.
	 *
	 * @return mixed
	 */
	public function run() {
		throw new RuntimeException( sprintf( '%1$s must be override by sub class. Called in %2$s', __METHOD__, get_called_class() ) );
	}
}

// End of file Process.php.

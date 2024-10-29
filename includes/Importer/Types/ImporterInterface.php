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

namespace AyyashStudio\Importer\Types;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

interface ImporterInterface {

	public static function get_instance();

	public function get_hook_slug();

	public function get_capability();

	public function ajax_import();

	public function import();
}

// End of file ImporterInterface.php.

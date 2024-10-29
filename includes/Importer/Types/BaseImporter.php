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

use function add_action;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

abstract class BaseImporter implements ImporterInterface {

	protected function __construct() {
		add_action( 'wp_ajax_ayyash_studio_' . $this->get_hook_slug(), [ $this, 'ajax_import' ] );
	}

	public function get_capability() {
		return 'manage_options';
	}
}

// End of file BaseImporter.php.

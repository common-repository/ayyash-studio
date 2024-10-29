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

namespace AyyashStudio\Cli;

use AyyashStudio\Cli\Commands\Cache;
use AyyashStudio\Cli\Commands\Site;
use AyyashStudio\Traits\Singleton;
use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

final class Cli {
	use Singleton;

	protected function __construct() {
		WP_CLI::add_command( 'ayyash-studio site', Site::class );
		WP_CLI::add_command( 'ayyash-studio cache', Cache::class );
	}
}

// End of file Cli.php.

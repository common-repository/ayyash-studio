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

namespace AyyashStudio\Cli\Commands;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class Site extends Cli_Base {

	public function list( $args, $assoc_args = [] ) {
		$types = [ 'sites', 'site', 'categories', 'category', 'editors', 'editor' ];
		$type  = $args[0] ?? 'sites';
		$type  = in_array( $type, $types, true ) ? $type : 'sites';

		if ( 'site' === $type ) {
			$type = 'sites';
		}
		if ( 'category' === $type ) {
			$type = 'categories';
		}
		if ( 'editor' === $type ) {
			$type = 'editors';
		}

		switch ( $type ) {
			case 'categories':
			case 'editors':
				$data = get_site_option( 'ayyash_studio_' . $type . '_store' );
				break;
			default:
				$data = ayyash_studio_get_sites();
				break;
		}

		$this->display_data( $assoc_args, $data, $type, true );
	}
}

// End of file Site.php.

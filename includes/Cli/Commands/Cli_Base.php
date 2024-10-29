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

use AyyashStudio\Client\Client;
use WP_CLI\Formatter;
use WP_CLI_Command;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class Cli_Base extends WP_CLI_Command {

	protected static $client;

	protected function get_client(): Client {
		if ( null === self::$client ) {
			self::$client = Client::get_instance();
		}

		return self::$client;
	}

	protected function write_json( $filename, $contents ) {
		if ( ! is_string( $contents ) ) {
			$contents = wp_json_encode( $contents );
		}

		return file_put_contents( ayyash_studio_get_json_path( $filename ), $contents );
	}

	protected function read_json( $filename, $associative = false ) {
		return ayyash_studio_read_json( $filename, $associative );
	}

	protected function display_data( &$assoc_args, $data, $fields, $make_assoc = false ) {
		if ( $make_assoc ) {
			$data = json_decode( json_encode( $data ), true );
		}

		if ( ! is_array( $data ) || empty( $data ) ) {
			\WP_CLI::log( \WP_CLI:: colorize( '%yNothing to show.%n' ) );
			return;
		}



		$footer = 'Total ' . count( $data );

		if ( is_string( $fields ) ) {
			$fields = str_replace( '_store', '', $fields );

			switch ( $fields ) {
				case 'categories':
					$footer .= ' Categories.';
					$fields = [ 'label', 'items' ];
					$data = array_map( [ $this, 'formatCategoryData' ], $data );
					break;
				case 'editors':
					$footer .= ' Editors.';
					$fields = [ 'Editor', 'Status' ];
					$data = array_map( [ $this, 'formatEditorData' ], array_keys( $data ), $data );
					break;
				default:
					if ( false === strpos( $fields, 'sites' ) ) {
						return;
					}
					$footer .= ' Sites.';
					$fields = [ 'ID', 'name', 'editor', 'downloads', 'views', 'preview' ];
					$data = array_map( [ $this, 'formatSiteData' ], $data );
					break;
			}
		}

		$formatter = $this->get_formatter( $assoc_args, $fields );
		$formatter->display_items( $data );
		\WP_CLI::log( \WP_CLI::colorize( '%c' . $footer . '%n' ) . PHP_EOL );
	}

	protected function formatCategoryData( $category ) {

		$category['items'] = isset( $category['items'] ) && is_array( $category['items'] ) ? array_map( [ $this, 'map_child_cats' ], $category['items'] ) : [];

		$category['items'] = implode( ', ', $category['items'] );

		$category['label'] = html_entity_decode( $category['label'] );

		return $category;
	}

	protected function map_child_cats( $cat ): string {
		return html_entity_decode( $cat['label'] ?? '' );
	}

	protected function formatEditorData( $editor, $status ): array {
		return [
			'Editor' => ucfirst( $editor ),
			'Status' => $status ? 'Enabled' : 'Disabled',
		];
	}

	protected function formatSiteData( $site ) {

		$site['name']      = html_entity_decode( $site['name'] );
		$site['editor']    = ucfirst( $site['editor'] );
		$site['downloads'] = number_format_i18n( $site['downloads'] );
		$site['views']     = number_format_i18n( $site['views'] );
		$site['preview']   = explode( '?', $site['preview'] )[0];

		return $site;
	}

	protected function get_formatter( &$assoc_args, $fields = null, $prefix = false ): Formatter {
		return new Formatter( $assoc_args, $fields, $prefix );
	}
}

// End of file Cli_Base.php.

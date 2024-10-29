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

use WP_CLI;
use function WP_CLI\Utils\make_progress_bar;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class Cache extends Cli_Base {

	public function make( $args, $assoc_args = [] ) {

		$path = AYYASH_STUDIO_PLUGIN_PATH . 'build/data/';

		if ( ! is_dir( $path ) ) {
			if ( ! wp_mkdir_p( $path ) ) {
				WP_CLI::error( 'Unable to create data directory [' . $path . ']' );
				return;
			}
		}

		WP_CLI::log( 'Making json cache for initial data.' );
		WP_CLI::log( 'Do not interrupt the process.' );

		$progress = make_progress_bar( 'Preparing...', 0 );

		$updates = $this->get_client()->check_updates();

		// Update timestamp.
		$updates->timestamp = current_time( 'mysql' );

		$siteCount = $updates->sites->count;

		$total = ayyash_studio_calculate_pagination( $siteCount, 'total' );

		$progress->setTotal( $total + 6 );

		if ( is_wp_error( $updates ) ) {
			$progress->finish();
			WP_CLI::error( $updates->get_error_message() );
			return;
		}

		$assets = [
			'categories_store',
			'editors_store',
		];

		$this->write_json( 'last_updates', $updates );

		$progress->tick( 1, 'Processing Category List' );
		$categories = $this->get_client()->get_categories( 0 );

		if ( is_wp_error( $categories ) ) {
			$progress->finish();
			WP_CLI::error( $categories->get_error_message() );
			return;
		}

		$this->write_json( 'categories_store', $categories );
		$progress->tick( 1, 'Processing Category List' );

		sleep( 1 );

		$progress->tick( 1, 'Processing Editor List' );

		$editors = $this->get_client()->get_editors();

		if ( is_wp_error( $editors ) ) {
			$progress->finish();
			WP_CLI::error( $editors->get_error_message() );
			return;
		}

		$this->write_json( 'editors_store', $editors );

		$progress->tick( 1, 'Processing Editor List' );
		sleep( 1 );

		$progress->tick( 1, 'Downloading ' . $siteCount . ' sites' );
		sleep( 1 );

		for ( $page = 1; $page <= $total; $page++ ) {

			$completed = ( 15 * $page );

			if ( $completed > $siteCount ) {
				$completed = $siteCount;
			}

			$progress->tick( 1, $completed . '/' . $siteCount . ' Sites Downloaded' );

			$sites = $this->get_client()->get_sites( $page );
			if ( is_wp_error( $sites ) ) {
				$progress->finish();
				WP_CLI::error( $editors->get_error_message() );
				break;
			}

			$this->write_json( 'sites_store_' . $page, $sites );
			$assets[] = 'sites_store_' . $page;
		}

		$progress->tick( 1, 'Saving asset list' );

		$this->write_json( 'cached_assets', $assets );

		$progress->finish();
		sleep( 1 );

		WP_CLI::success( 'Caching completed.' );
	}

	public function verify( $args, $assoc_args = [] ) {
		$assets = $this->read_json( 'cached_assets', true );
		$sites  = [];

		foreach ( $assets as $asset_type ) {
			$data = $this->read_json( $asset_type, true );
			if ( false === strpos( $asset_type, 'sites' ) ) {
				$this->display_data( $assoc_args, $data, $asset_type );
			} else {
				$sites = array_merge( $sites, $data );
			}
		}

		if ( ! empty( $sites ) ) {
			$this->display_data( $assoc_args, $sites, 'sites' );
		}
	}
}

// End of file Cache.php.

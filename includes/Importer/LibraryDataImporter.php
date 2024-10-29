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

namespace AyyashStudio\Importer;

use AyyashStudio\Client\Client;
use AyyashStudio\Traits\Singleton;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class LibraryDataImporter {

	use Singleton;

	protected static $stockStatus;

	protected static $currentStatus;

	protected function __construct() {
		add_action( 'wp_ajax_ayyash_studio_check_update', [ $this, 'ajax_check_update' ] );
		add_action( 'wp_ajax_ayyash_studio_clean_sites_store', [ $this, 'ajax_clean_sites_store' ] );
		add_action( 'wp_ajax_ayyash_studio_import_sites', [ $this, 'ajax_import_sites' ] );
		add_action( 'wp_ajax_ayyash_studio_import_editors', [ $this, 'ajax_import_editors' ] );
		add_action( 'wp_ajax_ayyash_studio_import_categories', [ $this, 'ajax_import_categories' ] );
		add_action( 'wp_ajax_ayyash_studio_finalize_sync', [ $this, 'ajax_finalize_sync' ] );
		add_action( 'wp_ajax_ayyash_studio_update_favorites', [ $this, 'ajax_update_favorites' ] );
	}

	public function ajax_clean_sites_store() {
		ayyash_studio_verify_ajax_request();

		if ( ayyash_studio_delete_site_option( 'ayyash_studio_sites_store_', 'right' ) ) {
			wp_send_json_success();
		} else {
			wp_send_json_error();
		}

		wp_die();
	}

	/**
	 * @return array|mixed|WP_Error
	 */
	public function check_update( $force_update = false ) {
		if ( null === self::$stockStatus ) {
			self::$stockStatus = get_site_option( 'ayyash_studio_last_updates', false );
		}

		if ( null === self::$currentStatus ) {
			self::$currentStatus = Client::get_instance()->check_updates();

			if ( is_wp_error( self::$currentStatus ) ) {
				$errors = implode( PHP_EOL, self::$currentStatus->get_error_messages() );
				ayyash_studio_log_critical( 'Unable to check update. Error: ' . $errors );

				return self::$currentStatus;
			}
		}

		// if new site or doing a force update.
		// we need data count so, can't return earlier.

		if ( ! self::$stockStatus || $force_update ) {
			self::$currentStatus->sites->need_update      = true;
			self::$currentStatus->editors->need_update    = true;
			self::$currentStatus->categories->need_update = true;
		} else {
			self::$currentStatus->sites->need_update      = self::$stockStatus['sites'] !== self::$currentStatus->sites->hash;
			self::$currentStatus->editors->need_update    = self::$stockStatus['editors'] !== self::$currentStatus->editors->hash;
			self::$currentStatus->categories->need_update = self::$stockStatus['categories'] !== self::$currentStatus->categories->hash;
		}

		return self::$currentStatus;
	}

	public function ajax_check_update() {
		ayyash_studio_verify_ajax_request();

		wp_send_json_success( $this->check_update( isset( $_REQUEST['force'] ) && 'true' === $_REQUEST['force'] ) );  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	public function saveCurrentStatus() {
		if ( null === self::$currentStatus ) {
			self::$currentStatus = $this->check_update();
		}

		update_site_option( 'ayyash_studio_last_updates', [
			'sites'      => self::$currentStatus->sites->hash,
			'editors'    => self::$currentStatus->editors->hash,
			'categories' => self::$currentStatus->categories->hash,
			'timestamp'  => current_time( 'mysql' ),
		] );
	}

	public function updateCurrentStatus( $type, $hash ) {
		if ( ! in_array( $type, [ 'sites', 'editors', 'categories' ] ) ) {
			return;
		}

		$lastStatus = get_site_option( 'ayyash_studio_last_updates', false );

		if ( ! is_array( $lastStatus ) ) {
			$lastStatus = [];
		}

		$lastStatus[ $type ]     = $hash;
		$lastStatus['timestamp'] = current_time( 'mysql' );

		update_site_option( 'ayyash_studio_last_updates', $lastStatus );
	}

	public function ajax_finalize_sync() {
		ayyash_studio_verify_ajax_request();

		$this->saveCurrentStatus();

		wp_send_json_success();

		wp_die();
	}

	public function ajax_update_favorites() {
		ayyash_studio_verify_ajax_request();

		if ( isset( $_POST['favorites'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$favorites = sanitize_text_field( $_POST['favorites'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! empty( $favorites ) ) {
				$favorites = explode( ',', $favorites );
				$favorites = array_map( 'absint', $favorites );
				$favorites = array_filter( $favorites );
				$favorites = array_unique( $favorites );
			} else {
				$favorites = [];
			}

			update_site_option( 'ayyash_studio_favorites_store', $favorites );
			wp_send_json_success();
		} else {
			wp_send_json_error();
		}

		wp_die();
	}

	public function import_sites_data( $hash, $page = 1, $lastPage = 1 ) {
		$page     = absint( $page );
		$lastPage = absint( $lastPage );

		if ( ! $page || ! $lastPage ) {
			return new WP_Error( 'invalid-page', __( 'Page number is required for importing sites.', 'ayyash-studio' ) );
		}

		$sites = Client::get_instance()->get_sites( $page );

		if ( ! is_wp_error( $sites ) && ! empty( $sites ) ) {
			update_site_option( 'ayyash_studio_sites_store_' . $page, $sites );
		}

		if ( $lastPage === $page ) {
			do_action( 'ayyash_studio_sites_sync_completed' );

			$this->updateCurrentStatus( 'sites', $hash );
		}

		return $sites;
	}

	public function import_sites( $hash, $page = 1, $lastPage = 1 ) {
		$status = $this->import_sites_data( $hash, $page, $lastPage );

		return ! is_wp_error( $status );
	}

	public function ajax_import_sites() {
		ayyash_studio_verify_ajax_request();

		$page     = isset( $_REQUEST['page'] ) ? absint( $_REQUEST['page'] ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$lastPage = isset( $_REQUEST['lastPage'] ) ? absint( $_REQUEST['lastPage'] ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$hash     = isset( $_REQUEST['hash'] ) ? sanitize_text_field( $_REQUEST['hash'] ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $page && $lastPage && $hash ) {
			$status = $this->import_sites_data( $hash, $page, $lastPage );

			if ( ! is_wp_error( $status ) ) {
				wp_send_json_success( $status );
				wp_die();
			}
		}

		wp_send_json_error();
		wp_die();
	}

	public function import_editors( $hash ) {
		$editors = Client::get_instance()->get_editors();

		if ( is_wp_error( $editors ) ) {
			ayyash_studio_log_error( 'Error while Synchronizing site editor list. Error: ' . $editors->get_error_message() );
			$editors = [];
		}

		update_site_option( 'ayyash_studio_editors_store', $editors );

		$this->updateCurrentStatus( 'editors', $hash );

		do_action( 'ayyash_studio_editors_sync_completed' );

		return $editors;
	}

	public function ajax_import_editors() {
		ayyash_studio_verify_ajax_request();

		$hash = isset( $_REQUEST['hash'] ) ? sanitize_text_field( $_REQUEST['hash'] ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $hash ) {
			$status = $this->import_editors( $hash );

			if ( ! is_wp_error( $status ) ) {
				wp_send_json_success( $status );
				wp_die();
			}
		}

		wp_send_json_error();
		wp_die();
	}

	public function import_categories( $hash ) {
		$categories = Client::get_instance()->get_categories( 0 );

		if ( is_wp_error( $categories ) ) {
			ayyash_studio_log_error( 'Error while Synchronizing site category list. Error: ' . $categories->get_error_message() );
			$categories = [];
		}

		update_site_option( 'ayyash_studio_categories_store', $categories );

		$this->updateCurrentStatus( 'categories', $hash );

		do_action( 'ayyash_studio_categories_sync_completed' );

		return $categories;
	}

	public function ajax_import_categories() {
		ayyash_studio_verify_ajax_request();

		$hash = isset( $_REQUEST['hash'] ) ? sanitize_text_field( $_REQUEST['hash'] ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $hash ) {
			$status = $this->import_categories( $hash );

			if ( ! is_wp_error( $status ) ) {
				wp_send_json_success( $status );
				wp_die();
			}
		}

		wp_send_json_error();
		wp_die();
	}
}

// End of file LibraryDataImporter.php.

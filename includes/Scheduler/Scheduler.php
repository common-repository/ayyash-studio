<?php
/**
 * Action Scheduler.
 *
 * Hooks the actions.
 */

namespace AyyashStudio\Scheduler;

use AyyashStudio\Client\Client;
use AyyashStudio\Importer\LibraryDataImporter;
use AyyashStudio\Queue\Queue;
use AyyashStudio\Traits\Singleton;
use ActionScheduler;
use ActionScheduler_FinishedAction;
use CronExpression;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class Scheduler {

	use Singleton;

	protected static $hookPrefix = 'ayyash_studio_';

	protected static $group = 'AyyashStudio';

	protected static $cronHooks;

	protected function __construct() {

		/**
		 * @var callable[]
		 */
		self::$cronHooks = [
			'check_update',
			'sync_sites',
			'sync_editors',
			'sync_categories',
			'run_gc',
		];

		self::init_hooks();
	}

	protected static function init_hooks() {
		foreach ( self::$cronHooks as $hook ) {
			add_action( self::$hookPrefix . $hook, [ __CLASS__, $hook ] );
		}
	}

	public static function increase_time_limit( $time_limit ) {
		$maxExeTime = (int) ini_get( 'max_execution_time' ) - 100;

		return max( $maxExeTime, $time_limit );
	}

	/**
	 * Check if the master cron scheduled.
	 *
	 * @param string|null $hook
	 * @param array|null $args
	 * @param string|null $group
	 *
	 * @return bool
	 */
	public static function isScheduled( string $hook, array $args = null, string $group = null ): bool {
		if ( null === $group ) {
			$group = self::$group;
		}

		return ! ! Queue::get_instance()->get_next( $hook, $args, $group );
	}

	public static function start( $updateNow = false, $force_update = false ) {
		if ( self::isScheduled( self::$hookPrefix . 'check_update', [], self::$group ) ) {
			return;
		}

		if ( ! apply_filters( 'check_update', true ) ) {
			return;
		}

		self::stop( true );

		ayyash_studio_log_info( 'Starting Cron Schedules.' );

		$next = strtotime( '+1 week' );

		Queue::get_instance()->schedule_recurring(
			$next,
			WEEK_IN_SECONDS,
			self::$hookPrefix . 'check_update',
			[],
			self::$group
		);

		ayyash_studio_log_info( sprintf( 'Main Cron Scheduled & next run will be %s.', gmdate( 'Y-m-d H:i:s T', $next ) ) );

		// For Initial Sync.
		if ( $updateNow ) {
			ayyash_studio_log_info( 'Scheduling initial sync.' );
			self::check_update( $force_update );
		}
	}

	/**
	 * Stop All Scheduler actions.
	 *
	 * @param bool $log
	 */
	public static function stop( bool $log = false ) {
		if ( ! $log ) {
			ayyash_studio_log_info( 'Stopping All Scheduled Actions' );
		}

		if ( class_exists( '\ActionScheduler_Store' ) ) {
			\ActionScheduler_Store::instance()->cancel_actions_by_group( self::$group );
		}

		if ( ! $log ) {
			ayyash_studio_log_info( 'All Scheduled Actions are stopped.' );
		}
	}

	/**
	 * @return bool
	 */
	protected static function isGCEnabled(): bool {
		return (bool) apply_filters( 'ayyash_studio_scheduler_use_gc', false );
	}

	public static function gcStart() {
		$next = strtotime( '+1 week' );

		Queue::get_instance()->schedule_recurring(
			$next,
			WEEK_IN_SECONDS,
			self::$hookPrefix . 'run_gc',
			[],
			self::$group
		);

		ayyash_studio_log_info( sprintf( '[GC] Scheduled & next run will be %s.', gmdate( 'Y-m-d H:i:s T', $next ) ) );
	}

	public static function run_gc() {
		if ( ! self::isGCEnabled() ) {
			return;
		}

		ayyash_studio_log_info( '[GC] Collecting failed process.' );

		$queue = Queue::get_instance();

		/**
		 * @var ActionScheduler_FinishedAction[] $failed
		 */
		$failed = $queue->search( [
			'status'   => 'failed',
			'group'    => self::$group,
			'per_page' => - 1,
		] );


		if ( empty( $failed ) ) {
			ayyash_studio_log_info( '[GC] No failed jobs. Exiting GC.' );

			return;
		}

		$total = count( $failed );

		ayyash_studio_log_info( sprintf( '[GC] %d items found', $total ) );

		$store  = ActionScheduler::store();
		$delay  = apply_filters( 'ayyash_studio_gc_delay', MINUTE_IN_SECONDS );
		$queued = 1;

		foreach ( $failed as $_id => $item ) {
			if ( ! self::isScheduled( $item->get_hook(), $item->get_args(), $item->get_group() ) ) {
				ayyash_studio_log_info( sprintf(
					'Re-queued For Synchronization. Hook : %s, Args: %s',
					$item->get_hook(),
					wp_json_encode( $item->get_args() )
				) );

				$queue->schedule_single(
					time() + ( $delay + $queued ),
					$item->get_hook(),
					$item->get_args(),
					$item->get_group()
				);

				$queued ++;
			}

			$store->delete_action( $_id );
		}

		ayyash_studio_log_info( sprintf( '[GC] %d rescheduled out of %d', $queued, $total ) );
	}

	public static function check_update( $force_update = false ) {
		if ( ! apply_filters( 'check_update', true ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			return;
		}

		ayyash_studio_log_info( 'Checking for update.' );

		$status = LibraryDataImporter::get_instance()->check_update( $force_update );

		if ( is_wp_error( $status ) ) {
			ayyash_studio_log_warning( 'Update checking failed. Error: ' . PHP_EOL . implode( PHP_EOL, $status->get_error_messages() ) );

			if ( (bool) apply_filters( 'ayyash_studio_reschedule_check_update', true ) ) {
				$delay = absint( apply_filters( 'ayyash_studio_update_checker_reschedule_delay', 5 * MINUTE_IN_SECONDS ) );
				ayyash_studio_log_info( sprintf( 'Rescheduling update checking after %s minutes.', intval( $delay / MINUTE_IN_SECONDS ) ) );

				Queue::get_instance()->schedule_single(
					time() + $delay,
					self::$hookPrefix . 'check_update', // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					[],
					self::$group
				);
			}

			return;
		}

		$idx = 0;

		foreach ( $status as $type => $data ) {
			if ( $data->count && $data->need_update ) {
				if ( ! $idx ) {
					ayyash_studio_log_info( 'Preparing for update sync' );
				}

				if ( ! method_exists( __CLASS__, 'sync_' . $type ) ) {
					ayyash_studio_log_alert( 'No sync method found for ' . $type . '. may be updating “Ayyash Studio” plugin can resolve this.' );
					continue;
				}

				$label = strtoupper( $type );
				if ( 'sites' === $type ) {
					ayyash_studio_log_info( sprintf( 'Queueing %s data for synchronization. Total %s found.', $label, $data->count ) );
					$pagination = ayyash_studio_calculate_pagination( $data->count );
					$last       = end( $pagination );
					reset( $pagination );
					foreach ( $pagination as $page ) {
						// Let Action Scheduler Handle the queue.
						Queue::get_instance()->schedule_single(
							time() + $idx,
							self::$hookPrefix . 'sync_' . $type, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
							[
								'hash' => $data->hash,
								'page' => $page,
								'last' => $last,
							],
							self::$group
						);
						$idx ++;
					}
				} else {
					Queue::get_instance()->schedule_single(
						time() + $idx,
						self::$hookPrefix . 'sync_' . $type, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						[ 'hash' => $data->hash ],
						self::$group
					);
					$idx ++;
				}
			}
		}

		if ( ! $idx ) {
			ayyash_studio_log_info( 'Nothing to sync, library already has latest updates.' );
		}

		LibraryDataImporter::get_instance()->saveCurrentStatus();
	}

	public static function sync_sites( $hash, $page = 1, $lastPage = 1 ) {
		LibraryDataImporter::get_instance()->import_sites( $hash, $page, $lastPage );
	}

	public static function sync_editors( $hash ) {
		LibraryDataImporter::get_instance()->import_editors( $hash );
	}

	public static function sync_categories( $hash ) {
		LibraryDataImporter::get_instance()->import_categories( $hash );
	}
}

// End of file Scheduler.php.

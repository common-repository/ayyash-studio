<?php
/**
 * Ayyash Studio Uninstallation Script.
 *
 * Uninstalling Ayyash Studio deletes cached sites, site-categories,
 * tags and other plugin specific options and transient.
 *
 * @package AyyashStudio\Uninstaller
 * @version 1.0.0
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb, $wp_version;

/*
 * Only remove ALL product and page data if AYYASH_STUDIO_REMOVE_ALL_DATA
 * constant is set to true in WordPress's wp-config.php. This is to prevent
 * data loss when deleting the plugin from the backend and to ensure only the
 * site owner can perform this action.
 */

if ( defined( 'AYYASH_STUDIO_REMOVE_ALL_DATA' ) && true === AYYASH_STUDIO_REMOVE_ALL_DATA ) {

	// Load system.

	include_once dirname( __FILE__ ) . '/vendor/autoload.php';
	include_once dirname( __FILE__ ) . '/includes/helpers.php';
	include_once dirname( __FILE__ ) . '/includes/helpers/logger.php';

	// Uninstall
	\AyyashStudio\Installer::deactivate();

	// Drop options & transients.

	delete_site_transient( 'ayyash_studio_activation_redirect' );
	delete_site_transient( 'ayyash_studio_init_import' );
	delete_site_transient( 'ayyash_studio_import_finish' );
	delete_site_transient( 'ayyash_studio_importer_errors' );

	delete_site_option( 'ayyash_studio_version' );
	delete_site_option( 'ayyash_studio_importer_data' );
	delete_site_option( 'ayyash_studio_favorites_store' );
	delete_site_option( 'ayyash_studio_import_finish' );
	ayyash_studio_delete_site_option( 'ayyash_studio_sites_store_', 'right' );
	delete_site_option( 'ayyash_studio_editors_store' );
	delete_site_option( 'ayyash_studio_categories_store' );
	delete_site_option( 'ayyash_studio_tags_store' );

	delete_site_option( 'ayyash-studio-last-updates' );
	delete_site_option( 'ayyash_studio_last_updates' );

	$fs = ayyash_studio_get_filesystem(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	if ( $fs ) {
		$upload_dir = wp_upload_dir( null, false ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

		$fs->rmdir( $upload_dir['basedir'] . '/ayyash-studio/' );
	}

	wp_clear_scheduled_hook( 'ayyash_studio_cleanup_logs' );

	// Clear any cached data that has been removed.
	wp_cache_flush();

	// Flush permalink.
	flush_rewrite_rules(); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules
}

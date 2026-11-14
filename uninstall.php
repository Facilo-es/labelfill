<?php
/**
 * Removes LabelFill's data when the plugin is deleted, only if the shop asked for it in Settings.
 *
 * @package LabelFill
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Cleans one site.
 */
function labelfill_uninstall_site() {
	global $wpdb;
	$settings = get_option( 'labelfill_settings', array() );
	if ( empty( $settings['delete_data'] ) ) {
		return;
	}
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( 'labelfill_daily', array(), 'labelfill' );
		as_unschedule_all_actions( 'labelfill_refresh_one', null, 'labelfill' );
	}
	// phpcs:disable WordPress.DB.DirectDatabaseQuery -- One-off cleanup on uninstall; no API deletes meta by prefix.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $wpdb->esc_like( '_labelfill_' ) . '%' ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_labelfill_' ) . '%', $wpdb->esc_like( '_transient_timeout_labelfill_' ) . '%' ) );
	// phpcs:enable
	delete_option( 'labelfill_settings' );
	wp_cache_flush();
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $labelfill_site ) {
		switch_to_blog( $labelfill_site );
		labelfill_uninstall_site();
		restore_current_blog();
	}
} else {
	labelfill_uninstall_site();
}

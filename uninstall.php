<?php
/**
 * X-Fields uninstall.
 *
 * Data is KEPT by default, so reinstalling never loses content.
 * To remove everything on uninstall, add to wp-config.php:
 *     define( 'XFIELDS_REMOVE_ALL_DATA', true );
 * (Tools → X-Fields Tools → Reset does the same from the admin.)
 *
 * @package X-Fields
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

wp_clear_scheduled_hook( 'xfields_cleanup_sessions' );

if ( ! defined( 'XFIELDS_REMOVE_ALL_DATA' ) || true !== XFIELDS_REMOVE_ALL_DATA ) {
    return;
}

global $wpdb;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- uninstall: remove our own data and tables
$wpdb->query( $wpdb->prepare( 'DELETE v, m FROM %i v LEFT JOIN %i m ON m.meta_id = v.meta_id', $wpdb->prefix . 'xfields_values', $wpdb->postmeta ) );
$wpdb->query( $wpdb->prepare( 'DELETE p, m FROM %i p LEFT JOIN %i m ON m.post_id = p.ID WHERE p.post_type = %s', $wpdb->posts, $wpdb->postmeta, 'xfields_group' ) );
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'xfields_values' ) );
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'xfields_sessions' ) );
// phpcs:enable WordPress.DB.DirectDatabaseQuery

foreach ( [ 'xfields_version', 'xfields_next_field_id', 'xfields_cfs_migration', 'xfields_v1_migrated' ] as $xfields_option ) {
    delete_option( $xfields_option );
}

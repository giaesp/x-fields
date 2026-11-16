<?php
/**
 * X-Fields uninstall.
 *
 * Data is KEPT by default, so reinstalling never loses content.
 * To remove everything on uninstall, add to wp-config.php:
 *     define( 'XF_REMOVE_ALL_DATA', true );
 * (Tools → X-Fields Tools → Reset does the same from the admin.)
 *
 * @package X-Fields
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

wp_clear_scheduled_hook( 'xf_cleanup_sessions' );

if ( ! defined( 'XF_REMOVE_ALL_DATA' ) || true !== XF_REMOVE_ALL_DATA ) {
    return;
}

global $wpdb;

$wpdb->query( "DELETE v, m FROM {$wpdb->prefix}xf_values v LEFT JOIN {$wpdb->postmeta} m ON m.meta_id = v.meta_id" );
$wpdb->query( "DELETE p, m FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID WHERE p.post_type = 'xf_group'" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}xf_values" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}xf_sessions" );

foreach ( [ 'xf_version', 'xf_next_field_id', 'xf_cfs_migration' ] as $option ) {
    delete_option( $option );
}

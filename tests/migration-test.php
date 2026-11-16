<?php
/**
 * Migration test: X-Fields 1.x ("xf_" prefix) -> 2.x ("xfields_" prefix).
 * Run with: wp eval-file wp-content/plugins/x-fields/tests/migration-test.php
 * Builds a fake 1.x install (tables, field group, values, options), migrates it and checks the result.
 */

global $wpdb;

$GLOBALS['xfields_mig_pass'] = 0;
$GLOBALS['xfields_mig_fail'] = 0;
function xfields_mig_t( $name, $cond ) {
    if ( $cond ) { $GLOBALS['xfields_mig_pass']++; echo "  OK   $name\n"; }
    else { $GLOBALS['xfields_mig_fail']++; echo "  FAIL $name\n"; }
}

$old    = 'x' . 'f_';
$values = $wpdb->prefix . 'xfields_values';
$legacy = $wpdb->prefix . $old . 'values';

// The 2.x table must be empty for the migration to take over (fresh install scenario)
if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$values}" ) > 0 ) {
    echo "SKIP: {$values} is not empty\n";
    return;
}

// ---- Fake 1.x install --------------------------------------------------------
$wpdb->query( "CREATE TABLE {$legacy} LIKE {$values}" );
$wpdb->query( "CREATE TABLE {$wpdb->prefix}{$old}sessions LIKE {$wpdb->prefix}xfields_sessions" );

$group = wp_insert_post( [ 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Legacy 1.x group' ] );
$wpdb->update( $wpdb->posts, [ 'post_type' => $old . 'group' ], [ 'ID' => $group ] );
clean_post_cache( $group );
add_post_meta( $group, $old . 'fields', [ [ 'id' => 7, 'name' => 'legacy_v1', 'label' => 'Legacy', 'type' => 'text', 'notes' => '', 'parent_id' => 0, 'weight' => 0, 'options' => [] ] ] );
add_post_meta( $group, $old . 'rules', [ 'post_types' => [ 'operator' => '==', 'values' => [ 'page' ] ] ] );
add_post_meta( $group, $old . 'extras', [ 'order' => 0, 'context' => 'normal', 'hide_editor' => 0 ] );

$page = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Legacy 1.x page' ] );
$wpdb->insert( $wpdb->postmeta, [ 'post_id' => $page, 'meta_key' => 'legacy_v1', 'meta_value' => 'value from 1.x' ] );
$wpdb->insert( $legacy, [ 'field_id' => 7, 'meta_id' => $wpdb->insert_id, 'post_id' => $page, 'hierarchy' => '' ] );

$backup = [
    'version' => get_option( 'xfields_version' ),
    'next'    => get_option( 'xfields_next_field_id' ),
    'flag'    => get_option( 'xfields_v1_migrated' ),
];
update_option( $old . 'version', '1.1.0' );
update_option( $old . 'next_field_id', 8 );
delete_option( 'xfields_v1_migrated' );
delete_option( 'xfields_next_field_id' );

// ---- Migrate -----------------------------------------------------------------
xfields_migration::maybe_migrate_v1();
XFields()->field_group->cache = null;
XFields()->api->cache = [];

xfields_mig_t( 'post type renamed', 'xfields_group' === get_post_type( $group ) );
xfields_mig_t( 'group meta renamed', is_array( get_post_meta( $group, 'xfields_fields', true ) ) && '' === get_post_meta( $group, $old . 'fields', true ) );
xfields_mig_t( 'legacy table renamed', $legacy !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy ) ) );
xfields_mig_t( 'value readable through XFields()', 'value from 1.x' === XFields()->get( 'legacy_v1', $page ) );
xfields_mig_t( 'field ID counter carried over', 8 === (int) get_option( 'xfields_next_field_id' ) );
xfields_mig_t( 'old options removed', false === get_option( $old . 'version' ) && false === get_option( $old . 'next_field_id' ) );

// Second run is a no-op
update_option( $old . 'version', '1.1.0' );
xfields_mig_t( 'second run is a no-op', null === xfields_migration::maybe_migrate_v1() && 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$values} WHERE post_id = %d", $page ) ) );
delete_option( $old . 'version' );

// ---- Cleanup -------------------------------------------------------------------
wp_delete_post( $group, true );
wp_delete_post( $page, true );
$wpdb->query( "DROP TABLE IF EXISTS {$legacy}, {$wpdb->prefix}{$old}sessions" );
update_option( 'xfields_version', $backup['version'] );
update_option( 'xfields_next_field_id', max( (int) $backup['next'], (int) get_option( 'xfields_next_field_id' ) ) );
update_option( 'xfields_v1_migrated', $backup['flag'] ? $backup['flag'] : time(), false );

// ---- Custom Field Suite -> X-Fields -----------------------------------------------
$cfs_table = $wpdb->prefix . 'cfs_values';
$wpdb->query( "CREATE TABLE {$cfs_table} LIKE {$values}" );
$cg = wp_insert_post( [ 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'CFS group' ] );
$wpdb->update( $wpdb->posts, [ 'post_type' => 'cfs' ], [ 'ID' => $cg ] );
clean_post_cache( $cg );
add_post_meta( $cg, 'cfs_fields', [ [ 'id' => 3, 'name' => 'cfs_text', 'label' => 'T', 'type' => 'text', 'notes' => '', 'parent_id' => 0, 'weight' => 0, 'options' => [] ] ] );
add_post_meta( $cg, 'cfs_rules', [ 'post_types' => [ 'operator' => '==', 'values' => [ 'page' ] ] ] );
add_post_meta( $cg, 'cfs_extras', [] );
$auto = wp_insert_post( [ 'post_type' => 'xfields_group', 'post_status' => 'auto-draft', 'post_title' => '' ] );
$cp = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'CFS page' ] );
$wpdb->insert( $wpdb->postmeta, [ 'post_id' => $cp, 'meta_key' => 'cfs_text', 'meta_value' => 'from cfs' ] );
$wpdb->insert( $cfs_table, [ 'field_id' => 3, 'meta_id' => $wpdb->insert_id, 'post_id' => $cp, 'hierarchy' => '' ] );
$cfs_bak = get_option( 'xfields_cfs_migration' );
delete_option( 'xfields_cfs_migration' );

xfields_migration::maybe_migrate();
XFields()->field_group->cache = null;
XFields()->api->cache = [];
$m = (array) get_option( 'xfields_cfs_migration' );
xfields_mig_t( 'CFS: migrated even with an auto-draft group present', isset( $m['status'] ) && 'migrated' === $m['status'] );
xfields_mig_t( 'CFS: group converted', 'xfields_group' === get_post_type( $cg ) );
xfields_mig_t( 'CFS: value readable', 'from cfs' === XFields()->get( 'cfs_text', $cp ) );

wp_delete_post( $cg, true );
wp_delete_post( $cp, true );
wp_delete_post( $auto, true );
$wpdb->query( "DROP TABLE IF EXISTS {$cfs_table}" );
update_option( 'xfields_cfs_migration', $cfs_bak ? $cfs_bak : [ 'status' => 'nothing' ], false );
delete_transient( 'xfields_migration_notice' );

echo "\nResult: {$GLOBALS['xfields_mig_pass']} passed, {$GLOBALS['xfields_mig_fail']} failed\n";

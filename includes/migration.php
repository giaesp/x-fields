<?php
/**
 * One-time migration from Custom Field Suite (CFS) to X-Fields.
 *
 * - Field groups (post type "cfs") become "xf_group"
 * - Group meta cfs_fields / cfs_rules / cfs_extras become xf_*
 * - Rows of {prefix}cfs_values are copied into {prefix}xf_values
 * - The field ID counter is carried over
 *
 * Field values live in postmeta (keyed by field name) and are shared, so they are not touched.
 * The CFS tables are left in place as a backup; drop them manually once you're happy.
 *
 * @package X-Fields
 */

defined( 'ABSPATH' ) || exit;

class xf_migration
{
    const OPTION = 'xf_cfs_migration';


    /**
     * Activation hook: make sure tables exist, then migrate.
     */
    public static function activate() {
        include_once XF_DIR . '/includes/upgrade.php';
        self::maybe_migrate();
    }


    /**
     * Run the migration once, only when there is CFS data and X-Fields is still empty.
     */
    public static function maybe_migrate() {
        global $wpdb;

        if ( false !== get_option( self::OPTION, false ) ) {
            return;
        }

        $cfs_table = $wpdb->prefix . 'cfs_values';
        $xf_table  = $wpdb->prefix . 'xf_values';

        $has_cfs_table  = ( $cfs_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $cfs_table ) ) ) );
        $has_cfs_groups = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'cfs'" );

        // Nothing to migrate
        if ( ! $has_cfs_table && 0 === $has_cfs_groups ) {
            update_option( self::OPTION, [ 'status' => 'nothing', 'time' => time() ], false );
            return;
        }

        // Never merge into an X-Fields install that already has data (field IDs would collide)
        $xf_groups = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'xf_group'" );
        $xf_rows   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$xf_table}" );

        if ( 0 < $xf_groups || 0 < $xf_rows ) {
            update_option( self::OPTION, [ 'status' => 'skipped', 'time' => time() ], false );
            return;
        }

        $copied = 0;
        if ( $has_cfs_table ) {
            $copied = (int) $wpdb->query(
                "INSERT INTO {$xf_table} (id, field_id, meta_id, post_id, base_field_id, hierarchy, depth, weight, sub_weight)
                 SELECT id, field_id, meta_id, post_id, base_field_id, hierarchy, depth, weight, sub_weight FROM {$cfs_table}"
            );
        }

        // Rename group meta first (scoped to CFS groups), then convert the post type
        foreach ( [ 'fields', 'rules', 'extras' ] as $key ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->postmeta} m
                 INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id AND p.post_type = 'cfs'
                 SET m.meta_key = %s
                 WHERE m.meta_key = %s",
                'xf_' . $key, 'cfs_' . $key
            ) );
        }

        $group_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'cfs'" );
        $wpdb->query( "UPDATE {$wpdb->posts} SET post_type = 'xf_group' WHERE post_type = 'cfs'" );

        // Direct SQL bypasses the object cache
        foreach ( $group_ids as $group_id ) {
            clean_post_cache( (int) $group_id );
        }

        $next_id = (int) get_option( 'cfs_next_field_id', 1 );
        update_option( 'xf_next_field_id', max( 1, $next_id, (int) get_option( 'xf_next_field_id', 1 ) ) );

        update_option( self::OPTION, [
            'status' => 'migrated',
            'time'   => time(),
            'groups' => $has_cfs_groups,
            'rows'   => $copied,
        ], false );

        set_transient( 'xf_migration_notice', 1, DAY_IN_SECONDS );
        add_action( 'admin_notices', [ __CLASS__, 'notice' ] );
    }


    /**
     * Admin notice after a successful migration.
     */
    public static function notice() {
        if ( ! current_user_can( 'manage_options' ) || ! get_transient( 'xf_migration_notice' ) ) {
            return;
        }

        delete_transient( 'xf_migration_notice' );
        $info = (array) get_option( self::OPTION, [] );

        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            esc_html( sprintf(
                /* translators: 1: number of field groups, 2: number of value rows */
                __( 'X-Fields imported %1$d field groups and %2$d values from Custom Field Suite. The old CFS tables were kept as a backup.', 'x-fields' ),
                isset( $info['groups'] ) ? (int) $info['groups'] : 0,
                isset( $info['rows'] ) ? (int) $info['rows'] : 0
            ) )
        );
    }
}

add_action( 'admin_notices', [ 'xf_migration', 'notice' ] );

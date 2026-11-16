<?php
/**
 * One-time migration from Custom Field Suite (CFS) to X-Fields.
 *
 * - Field groups (post type "cfs") become "xfields_group"
 * - Group meta cfs_fields / cfs_rules / cfs_extras become xfields_*
 * - Rows of {prefix}cfs_values are copied into {prefix}xfields_values
 * - The field ID counter is carried over
 *
 * Field values live in postmeta (keyed by field name) and are shared, so they are not touched.
 * The CFS tables are left in place as a backup; drop them manually once you're happy.
 *
 * @package X-Fields
 */

defined( 'ABSPATH' ) || exit;

// This file reads/writes X-Fields' own tables ({prefix}xfields_values, {prefix}xfields_sessions) and the
// group/value rows they reference; WordPress has no API for them. All queries are prepared or use integer/constant values.
// phpcs:disable WordPress.DB.DirectDatabaseQuery

class xfields_migration
{
    const OPTION = 'xfields_cfs_migration';


    /**
     * Activation hook: make sure tables exist, then migrate.
     */
    public static function activate() {
        self::maybe_migrate_v1();
        include_once XFIELDS_DIR . '/includes/upgrade.php';
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
        $xfields_table  = $wpdb->prefix . 'xfields_values';

        $has_cfs_table  = ( $cfs_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $cfs_table ) ) ) );
        $has_cfs_groups = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'cfs'" );

        // Nothing to migrate
        if ( ! $has_cfs_table && 0 === $has_cfs_groups ) {
            update_option( self::OPTION, [ 'status' => 'nothing', 'time' => time() ], false );
            return;
        }

        // Never merge into an X-Fields install that already has data (field IDs would collide)
        $xfields_groups = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'xfields_group' AND post_status NOT IN ('auto-draft', 'trash')" );
        $xfields_rows   = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $xfields_table ) );

        if ( 0 < $xfields_groups || 0 < $xfields_rows ) {
            update_option( self::OPTION, [ 'status' => 'skipped', 'time' => time() ], false );
            return;
        }

        $copied = 0;
        if ( $has_cfs_table ) {
            $copied = (int) $wpdb->query( $wpdb->prepare(
                'INSERT INTO %i (id, field_id, meta_id, post_id, base_field_id, hierarchy, depth, weight, sub_weight)
                 SELECT id, field_id, meta_id, post_id, base_field_id, hierarchy, depth, weight, sub_weight FROM %i',
                $xfields_table, $cfs_table
            ) );
        }

        // Rename group meta first (scoped to CFS groups), then convert the post type
        foreach ( [ 'fields', 'rules', 'extras' ] as $key ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->postmeta} m
                 INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id AND p.post_type = 'cfs'
                 SET m.meta_key = %s
                 WHERE m.meta_key = %s",
                'xfields_' . $key, 'cfs_' . $key
            ) );
        }

        $group_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'cfs'" );
        $wpdb->query( "UPDATE {$wpdb->posts} SET post_type = 'xfields_group' WHERE post_type = 'cfs'" );

        // Direct SQL bypasses the object cache
        foreach ( $group_ids as $group_id ) {
            clean_post_cache( (int) $group_id );
        }

        $next_id = (int) get_option( 'cfs_next_field_id', 1 );
        update_option( 'xfields_next_field_id', max( 1, $next_id, (int) get_option( 'xfields_next_field_id', 1 ) ) );

        update_option( self::OPTION, [
            'status' => 'migrated',
            'time'   => time(),
            'groups' => $has_cfs_groups,
            'rows'   => $copied,
        ], false );

        set_transient( 'xfields_migration_notice', 1, DAY_IN_SECONDS );
        add_action( 'admin_notices', [ __CLASS__, 'notice' ] );
    }


    /**
     * X-Fields 1.x used the short "xf_" prefix; 2.0 uses "xfields_" (WordPress.org requirement).
     * Renames tables, post type, group meta, options and cron. Must run BEFORE the 2.0 tables are created.
     * Old names are built at runtime so automated renames never touch them.
     * @since 2.0.0
     */
    public static function maybe_migrate_v1() {
        global $wpdb;

        $old = 'x' . 'f_';

        if ( false === get_option( $old . 'version', false ) || get_option( 'xfields_v1_migrated' ) ) {
            return;
        }

        $tables = [ 'values', 'sessions' ];
        foreach ( $tables as $t ) {
            $old_table = $wpdb->prefix . $old . $t;
            $new_table = $wpdb->prefix . 'xfields_' . $t;
            $has_old = ( $old_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $old_table ) ) ) );
            $has_new = ( $new_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $new_table ) ) ) );

            if ( ! $has_old ) {
                continue;
            }

            // Table names are built from $wpdb->prefix and constants only
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange -- one-time rename of our own tables
            if ( $has_new && 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $new_table ) ) ) {
                $wpdb->query( $wpdb->prepare( 'DROP TABLE %i', $new_table ) );
                $has_new = false;
            }
            if ( ! $has_new ) {
                $wpdb->query( $wpdb->prepare( 'RENAME TABLE %i TO %i', $old_table, $new_table ) );
            }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange
        }

        $group_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", $old . 'group' ) );

        foreach ( [ 'fields', 'rules', 'extras' ] as $key ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->postmeta} m
                 INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id AND p.post_type = %s
                 SET m.meta_key = %s
                 WHERE m.meta_key = %s",
                $old . 'group', 'xfields_' . $key, $old . $key
            ) );
        }

        $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_type = %s WHERE post_type = %s", 'xfields_group', $old . 'group' ) );

        foreach ( $group_ids as $group_id ) {
            clean_post_cache( (int) $group_id );
        }

        foreach ( [ 'next_field_id', 'cfs_migration' ] as $opt ) {
            $value = get_option( $old . $opt, null );
            if ( null !== $value && false === get_option( 'xfields_' . $opt, false ) ) {
                update_option( 'xfields_' . $opt, $value, false );
            }
            delete_option( $old . $opt );
        }

        // Tables already exist: let the 2.0 upgrader only record the new version
        update_option( 'xfields_version', get_option( $old . 'version' ) );
        delete_option( $old . 'version' );
        wp_clear_scheduled_hook( $old . 'cleanup_sessions' );

        update_option( 'xfields_v1_migrated', time(), false );
    }


    /**
     * Admin notice after a successful migration.
     */
    public static function notice() {
        if ( ! current_user_can( 'manage_options' ) || ! get_transient( 'xfields_migration_notice' ) ) {
            return;
        }

        delete_transient( 'xfields_migration_notice' );
        $info = (array) get_option( self::OPTION, [] );

        // Only after a real migration
        if ( ! isset( $info['status'] ) || 'migrated' !== $info['status'] || empty( $info['groups'] ) ) {
            return;
        }

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

add_action( 'admin_notices', [ 'xfields_migration', 'notice' ] );

<?php

defined( 'ABSPATH' ) || exit;

// This file reads/writes X-Fields' own tables ({prefix}xfields_values, {prefix}xfields_sessions) and the
// group/value rows they reference; WordPress has no API for them. All queries are prepared or use integer/constant values.
// phpcs:disable WordPress.DB.DirectDatabaseQuery

class xfields_ajax
{
    /**
     * Search posts (in the Placement Rules area)
     * @param array $options
     * @return string A JSON results object
     */
    public function search_posts( $options ) {
        global $wpdb;

        $q = isset( $options['q'] ) && is_string( $options['q'] ) ? wp_unslash( $options['q'] ) : '';

        $sql = $wpdb->prepare("
        SELECT ID, post_type, post_title, post_parent
        FROM $wpdb->posts
        WHERE
            post_status IN ('publish', 'private') AND
            post_type NOT IN ('xfields_group', 'attachment', 'revision', 'nav_menu_item') AND
            post_title LIKE %s
        ORDER BY post_type, post_title
        LIMIT 10",
        '%' . $wpdb->esc_like( $q ) . '%' );

        // phpcs:disable WordPress.DB.PreparedSQL -- only table names, constants or integer IDs are interpolated
        $results = $wpdb->get_results( $sql );
        // phpcs:enable WordPress.DB.PreparedSQL

        $output = [];
        foreach ( $results as $result ) {
            $parent = '';

            if (
                isset( $result->post_parent ) &&
                absint( $result->post_parent ) > 0 &&
                $parent = get_post( $result->post_parent )
            ) {
                $parent = "$parent->post_title >";
            }

            $output[] = [
                'id' => $result->ID,
                'text' => "($result->post_type) $parent $result->post_title (#$result->ID)"
            ];
        }
        return wp_json_encode( $output );
    }


    /**
     * Remove all traces of XFields
     */
    public function reset() {
        global $wpdb;

        // Drop field groups
        $sql = "
        DELETE p, m FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
        WHERE p.post_type = 'xfields_group'";
        // phpcs:disable WordPress.DB.PreparedSQL -- only table names, constants or integer IDs are interpolated
        $wpdb->query( $sql );
        // phpcs:enable WordPress.DB.PreparedSQL

        // Drop custom field values
        $sql = "
        DELETE v, m FROM {$wpdb->prefix}xfields_values v
        LEFT JOIN {$wpdb->postmeta} m ON m.meta_id = v.meta_id";
        // phpcs:disable WordPress.DB.PreparedSQL -- only table names, constants or integer IDs are interpolated
        $wpdb->query( $sql );
        // phpcs:enable WordPress.DB.PreparedSQL

        // Drop tables
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}xfields_values" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}xfields_sessions" );
        delete_option( 'xfields_version' );
        delete_option( 'xfields_next_field_id' );
    }
}

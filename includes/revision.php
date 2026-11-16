<?php

defined( 'ABSPATH' ) || exit;

// This file reads/writes X-Fields' own tables ({prefix}xfields_values, {prefix}xfields_sessions) and the
// group/value rows they reference; WordPress has no API for them. All queries are prepared or use integer/constant values.
// phpcs:disable WordPress.DB.DirectDatabaseQuery

if ( defined( 'XFIELDS_REVISIONS' ) && XFIELDS_REVISIONS ) {
    new xfields_revision();
}

class xfields_revision
{

    function __construct() {
        add_action( 'save_post', [ $this, 'save_post' ] );
        add_action( 'wp_restore_post_revision', [ $this, 'wp_restore_post_revision' ], 10, 2 );
        add_action( 'wp_delete_post_revision', [ $this, 'wp_delete_post_revision' ] );

        add_filter( '_wp_post_revision_fields', [ $this, '_wp_post_revision_fields' ] );
        add_filter( '_wp_post_revision_field_xfields_postmeta', [ $this, '_wp_post_revision_field_postmeta' ], 10, 3 );
        add_filter( 'wp_save_post_revision_check_for_changes', [ $this, 'check_for_changes' ], 10, 3 );
    }


    /**
     * Register the revision variable
     * @see wp-includes/revision.php - wp_save_post_revision()
     */
    function _wp_post_revision_fields( $fields ) {
        $fields[ 'xfields_postmeta' ] = __( 'Post Meta', 'x-fields' );
        return $fields;
    }


    /**
     * Generate the data for the "xfields_postmeta" variable
     * @see wp-admin/includes/ajax-actions - wp_ajax_revisions_data()
     */
    function _wp_post_revision_field_postmeta( $value, $column, $post ) {
        $output = '';
        $fields = XFields()->get( false, $post->ID );
        $field_info = XFields()->get_field_info( false, $post->ID );

        foreach ( (array) $fields as $field_name => $field_data ) {
            $output .= '[' . $field_name . "]\n";

            if ( is_array( $field_data ) ) {
                $props = isset( $field_info[ $field_name ] ) ? $field_info[ $field_name ] : [ 'type' => '' ];
                if ( 'relationship' == $props['type'] ) {
                    $values = [];
                    if ( ! empty( $field_data ) ) {
                        foreach ( $field_data as $item_id ) {
                            $item = get_post( $item_id );
                            $values[] = $item ? $item->post_title : '#' . (int) $item_id;
                        }
                    }
                    $output .= wp_json_encode( $values ) . "\n";
                }
                else {
                    $output .= wp_json_encode( $field_data ) . "\n";
                }
            }
            else {
                $output .= $field_data . "\n";
            }
        }
        return $output;
    }


    /**
     * Determine whether the data changed
     * @see wp-includes/revision.php -> wp_save_post_revision()
     */
    function check_for_changes( $default, $last_revision, $post ) {
        $revision_data = XFields()->get( false, $last_revision->ID );
        $post_data = XFields()->get( false, $post->ID );

        if ( serialize( $revision_data ) != serialize( $post_data ) ) {
            return false;
        }

        return true;
    }


    /**
     * Save revision custom fields
     */
    function save_post( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        $original_post_id = wp_is_post_revision( $post_id );

        if ( $original_post_id ) {
            $field_data = XFields()->get( false, $original_post_id, [ 'format' => 'raw' ] );
            XFields()->save( $field_data, [ 'ID' => $post_id ] );
        }
    }


    /**
     * Restore revision custom fields
     * @see wp-includes/revision.php -> wp_restore_post_revision()
     */
    function wp_restore_post_revision( $post_id, $revision_id ) {
        $field_data = XFields()->get( false, $revision_id, [ 'format' => 'raw' ] );
        XFields()->save( $field_data, [ 'ID' => $post_id ] );
    }


    /**
     * Delete revision custom fields
     * @see wp-includes/revision.php -> wp_delete_post_revision()
     */
    function wp_delete_post_revision( $revision_id ) {
        global $wpdb;

        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}xfields_values WHERE post_id = %d", $revision_id ) );
    }
}

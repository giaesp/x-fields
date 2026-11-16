<?php

defined( 'ABSPATH' ) || exit;

global $post;

$xfields_options_html = [];

/*---------------------------------------------------------------------------------------------
    Field management screen
---------------------------------------------------------------------------------------------*/

if ( 'xfields_group' == $screen->post_type ) {
    foreach ( XFields()->fields as $xfields_field_name => $xfields_field_data ) {
        ob_start();
        // Pass an empty field object (passing the field type object caused dynamic properties on PHP 8.2+)
        XFields()->fields[ $xfields_field_name ]->options_html( 'clone', (object) [ 'options' => [] ] );
        $xfields_options_html[ $xfields_field_name ] = ob_get_clean();
    }

    $xfields_field_count = get_post_meta( $post->ID, 'xfields_fields', true );
    $xfields_field_count = is_array( $xfields_field_count ) ? count( $xfields_field_count ) : 0;

    // Build clone HTML
    $xfields_field = (object) [
        'id'            => 0,
        'parent_id'     => 0,
        'name'          => 'new_field',
        'label'         => __( 'New Field', 'x-fields' ),
        'type'          => 'text',
        'notes'         => '',
        'weight'        => 'clone',
    ];

    ob_start();
    XFields()->field_html( $xfields_field );
    $xfields_field_clone = ob_get_clean();
    wp_add_inline_script( 'xfields-fields', 'window.XFields = Object.assign( window.XFields || {}, ' . wp_json_encode( [
        'field_index'  => (int) $xfields_field_count,
        'field_clone'  => $xfields_field_clone,
        'options_html' => $xfields_options_html,
        'i18n'         => [ 'confirm_delete' => __( 'Delete this field and its saved values?', 'x-fields' ) ],
    ], JSON_HEX_TAG | JSON_HEX_AMP ) . ' );', 'before' );

}

/*---------------------------------------------------------------------------------------------
    Field input
---------------------------------------------------------------------------------------------*/

else {
    $xfields_hide_editor = false;
    $xfields_field_groups = XFields()->api->get_matching_groups( $post->ID );

    if ( ! empty( $xfields_field_groups ) ) {

        // Store field group IDs as an array for front-end forms
        XFields()->group_ids = array_keys( $xfields_field_groups );

        // Support for multiple metaboxes
        foreach ( $xfields_field_groups as $xfields_group_id => $xfields_title ) {

            // Get field group options
            $xfields_extras = (array) get_post_meta( $xfields_group_id, 'xfields_extras', true );
            $xfields_context = ( isset( $xfields_extras['context'] ) && 'side' === $xfields_extras['context'] ) ? 'side' : 'normal';
            $xfields_priority = ( 'normal' == $xfields_context ) ? 'high' : 'core';

            if ( isset( $xfields_extras['hide_editor'] ) && 0 < (int) $xfields_extras['hide_editor'] ) {
                $xfields_hide_editor = true;
            }

            $xfields_args = [ 'box' => 'input', 'group_id' => $xfields_group_id ];
            add_meta_box( "xfields_input_$xfields_group_id", $xfields_title, [ $this, 'meta_box' ], $post->post_type, $xfields_context, $xfields_priority, $xfields_args );
            add_filter( "postbox_classes_{$post->post_type}_xfields_input_{$xfields_group_id}", 'xfields_postbox_classes' );
        }

        // Force editor support
        $xfields_has_editor = post_type_supports( $post->post_type, 'editor' );
        add_post_type_support( $post->post_type, 'editor' );

        if ( ! $xfields_has_editor || $xfields_hide_editor ) {
            echo '<style type="text/css">#poststuff .postarea { display: none; }</style>';
        }
    }
}

if ( ! function_exists( 'xfields_postbox_classes' ) ) {
    function xfields_postbox_classes( $xfields_classes ) {
        $xfields_classes[] = 'xfields_input';
        return $xfields_classes;
    }
}

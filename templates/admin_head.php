<?php

defined( 'ABSPATH' ) || exit;

global $post;

$options_html = [];

/*---------------------------------------------------------------------------------------------
    Field management screen
---------------------------------------------------------------------------------------------*/

if ( 'xf_group' == $screen->post_type ) {
    foreach ( XF()->fields as $field_name => $field_data ) {
        ob_start();
        // Pass an empty field object (passing the field type object caused dynamic properties on PHP 8.2+)
        XF()->fields[ $field_name ]->options_html( 'clone', (object) [ 'options' => [] ] );
        $options_html[ $field_name ] = ob_get_clean();
    }

    $field_count = get_post_meta( $post->ID, 'xf_fields', true );
    $field_count = is_array( $field_count ) ? count( $field_count ) : 0;

    // Build clone HTML
    $field = (object) [
        'id'            => 0,
        'parent_id'     => 0,
        'name'          => 'new_field',
        'label'         => __( 'New Field', 'x-fields' ),
        'type'          => 'text',
        'notes'         => '',
        'weight'        => 'clone',
    ];

    ob_start();
    XF()->field_html( $field );
    $field_clone = ob_get_clean();
?>

<script>
var XF = XF || {};
XF['field_index'] = <?php echo (int) $field_count; ?>;
XF['field_clone'] = <?php echo json_encode( $field_clone ); ?>;
XF['options_html'] = <?php echo json_encode( $options_html ); ?>;
XF['i18n'] = <?php echo wp_json_encode( [ 'confirm_delete' => __( 'Delete this field and its saved values?', 'x-fields' ) ] ); ?>;
</script>
<script src="<?php echo esc_url( XF_URL . '/assets/js/fields.js?ver=' . XF_VERSION ); ?>"></script>
<script src="<?php echo esc_url( XF_URL . '/assets/js/select2/select2.min.js?ver=' . XF_VERSION ); ?>"></script>
<script src="<?php echo esc_url( XF_URL . '/assets/js/jquery-powertip/jquery.powertip.min.js?ver=' . XF_VERSION ); ?>"></script>
<link rel="stylesheet" type="text/css" href="<?php echo esc_url( XF_URL . '/assets/css/fields.css?ver=' . XF_VERSION ); ?>" />
<link rel="stylesheet" type="text/css" href="<?php echo esc_url( XF_URL . '/assets/js/select2/select2.css?ver=' . XF_VERSION ); ?>" />
<link rel="stylesheet" type="text/css" href="<?php echo esc_url( XF_URL . '/assets/js/jquery-powertip/jquery.powertip.css?ver=' . XF_VERSION ); ?>" />

<?php
}

/*---------------------------------------------------------------------------------------------
    Field input
---------------------------------------------------------------------------------------------*/

else {
    $hide_editor = false;
    $field_groups = XF()->api->get_matching_groups( $post->ID );

    if ( ! empty( $field_groups ) ) {

        // Store field group IDs as an array for front-end forms
        XF()->group_ids = array_keys( $field_groups );

        // Support for multiple metaboxes
        foreach ( $field_groups as $group_id => $title ) {

            // Get field group options
            $extras = (array) get_post_meta( $group_id, 'xf_extras', true );
            $context = ( isset( $extras['context'] ) && 'side' === $extras['context'] ) ? 'side' : 'normal';
            $priority = ( 'normal' == $context ) ? 'high' : 'core';

            if ( isset( $extras['hide_editor'] ) && 0 < (int) $extras['hide_editor'] ) {
                $hide_editor = true;
            }

            $args = [ 'box' => 'input', 'group_id' => $group_id ];
            add_meta_box( "xf_input_$group_id", $title, [ $this, 'meta_box' ], $post->post_type, $context, $priority, $args );
            add_filter( "postbox_classes_{$post->post_type}_xf_input_{$group_id}", 'xf_postbox_classes' );
        }

        // Force editor support
        $has_editor = post_type_supports( $post->post_type, 'editor' );
        add_post_type_support( $post->post_type, 'editor' );

        if ( ! $has_editor || $hide_editor ) {
            echo '<style type="text/css">#poststuff .postarea { display: none; }</style>';
        }
    }
}

if ( ! function_exists( 'xf_postbox_classes' ) ) {
    function xf_postbox_classes( $classes ) {
        $classes[] = 'xf_input';
        return $classes;
    }
}

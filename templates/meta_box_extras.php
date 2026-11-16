<?php

defined( 'ABSPATH' ) || exit;

global $wpdb;

// Post types
$post_types = [];
$types = get_post_types( [ 'public' => true ] );

foreach ( $types as $post_type ) {
    if ( ! in_array( $post_type, [ 'xf_group', 'attachment' ] ) ) {
        $post_types[] = $post_type;
    }
}

$extras = (array) get_post_meta( $post->ID, 'xf_extras', true );

if ( ! isset( $extras['hide_editor'] ) ) {
    $extras['hide_editor'] = '';
}
if ( ! isset( $extras['order'] ) ) {
    $extras['order'] = 0;
}
if ( ! isset( $extras['context'] ) ) {
    $extras['context'] = 'normal';
}

?>

<table>
    <tr>
        <td class="label">
            <label>
                <?php _e( 'Order', 'x-fields' ); ?>
                <div class="xf_tooltip">?
                    <div class="tooltip_inner"><?php _e( 'The field group with the lowest order will appear first.', 'x-fields' ); ?></div>
                </div>
            </label>
        </td>
        <td style="vertical-align:top">
            <input type="text" name="xf[extras][order]" value="<?php echo (int) $extras['order']; ?>" style="width:80px" />
        </td>
    </tr>
    <tr>
        <td class="label">
            <label><?php _e( 'Position', 'x-fields' ); ?></label>
        </td>
        <td style="vertical-align:top">
            <input type="radio" name="xf[extras][context]" value="normal"<?php echo ( $extras['context'] == 'normal' ) ? ' checked' : ''; ?> /> <?php _e( 'Normal', 'x-fields' ); ?> &nbsp; &nbsp;
            <input type="radio" name="xf[extras][context]" value="side"<?php echo ( $extras['context'] == 'side' ) ? ' checked' : ''; ?> /> <?php _e( 'Side', 'x-fields' ); ?>
        </td>
    </tr>
    <tr>
        <td class="label">
            <label><?php _e( 'Display Settings', 'x-fields' ); ?></label>
        </td>
        <td style="vertical-align:top">
            <div>
                <?php
                    XF()->create_field( [
                        'type'          => 'true_false',
                        'input_name'    => "xf[extras][hide_editor]",
                        'input_class'   => 'true_false',
                        'value'         => $extras['hide_editor'],
                        'options'       => [ 'message' => __( 'Hide the content editor', 'x-fields' ) ],
                    ] );
                ?>
            </div>
        </td>
    </tr>

</table>

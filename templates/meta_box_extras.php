<?php

defined( 'ABSPATH' ) || exit;

global $wpdb;

// Post types
$xfields_post_types = [];
$xfields_types = get_post_types( [ 'public' => true ] );

foreach ( $xfields_types as $xfields_post_type ) {
    if ( ! in_array( $xfields_post_type, [ 'xfields_group', 'attachment' ] ) ) {
        $xfields_post_types[] = $xfields_post_type;
    }
}

$xfields_extras = (array) get_post_meta( $post->ID, 'xfields_extras', true );

if ( ! isset( $xfields_extras['hide_editor'] ) ) {
    $xfields_extras['hide_editor'] = '';
}
if ( ! isset( $xfields_extras['order'] ) ) {
    $xfields_extras['order'] = 0;
}
if ( ! isset( $xfields_extras['context'] ) ) {
    $xfields_extras['context'] = 'normal';
}

?>

<table>
    <tr>
        <td class="label">
            <label>
                <?php esc_html_e( 'Order', 'x-fields' ); ?></label>
                                    <p class="description"><?php esc_html_e( 'The field group with the lowest order will appear first.', 'x-fields' ); ?></p>
        </td>
        <td style="vertical-align:top">
            <input type="text" name="xfields[extras][order]" value="<?php echo (int) $xfields_extras['order']; ?>" style="width:80px" />
        </td>
    </tr>
    <tr>
        <td class="label">
            <label><?php esc_html_e( 'Position', 'x-fields' ); ?></label>
        </td>
        <td style="vertical-align:top">
            <input type="radio" name="xfields[extras][context]" value="normal"<?php echo ( $xfields_extras['context'] == 'normal' ) ? ' checked' : ''; ?> /> <?php esc_html_e( 'Normal', 'x-fields' ); ?> &nbsp; &nbsp;
            <input type="radio" name="xfields[extras][context]" value="side"<?php echo ( $xfields_extras['context'] == 'side' ) ? ' checked' : ''; ?> /> <?php esc_html_e( 'Side', 'x-fields' ); ?>
        </td>
    </tr>
    <tr>
        <td class="label">
            <label><?php esc_html_e( 'Display Settings', 'x-fields' ); ?></label>
        </td>
        <td style="vertical-align:top">
            <div>
                <?php
                    XFields()->create_field( [
                        'type'          => 'true_false',
                        'input_name'    => "xfields[extras][hide_editor]",
                        'input_class'   => 'true_false',
                        'value'         => $xfields_extras['hide_editor'],
                        'options'       => [ 'message' => __( 'Hide the content editor', 'x-fields' ) ],
                    ] );
                ?>
            </div>
        </td>
    </tr>

</table>

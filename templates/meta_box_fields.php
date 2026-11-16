<?php defined( 'ABSPATH' ) || exit; ?>
<input type="hidden" name="xfields[save]" value="<?php echo esc_attr( wp_create_nonce( 'xfields_save_fields' ) ); ?>" />

<div class="xfields-fields-header" aria-hidden="true">
    <span class="field_order"></span>
    <span class="field_label"><?php esc_html_e( 'Label', 'x-fields' ); ?></span>
    <span class="field_name"><?php esc_html_e( 'Name', 'x-fields' ); ?></span>
    <span class="field_type"><?php esc_html_e( 'Type', 'x-fields' ); ?></span>
    <span class="field_tools"></span>
</div>

<div class="xfields-empty-state"<?php echo empty( array_filter( (array) get_post_meta( $post->ID, 'xfields_fields', true ) ) ) ? '' : ' hidden'; ?>>
    <span class="dashicons dashicons-forms" aria-hidden="true"></span>
    <p><strong><?php esc_html_e( 'No fields yet', 'x-fields' ); ?></strong></p>
    <p><?php esc_html_e( 'Add your first field, then choose where the group appears in “Placement Rules”.', 'x-fields' ); ?></p>
</div>

<ul class="fields">
<?php

global $post;

$xfields_results = XFields()->api->get_input_fields( [ 'group_id' => $post->ID, 'post_id_values' => 0 ] );

/*---------------------------------------------------------------------------------------------
    Create <ul> based on field structure
---------------------------------------------------------------------------------------------*/

$xfields_level = 0;
$xfields_levels = [];
$xfields_last_level = $xfields_diff = 0;

foreach ( $xfields_results as $xfields_field ) {

    // Skip missing field types
    if ( ! isset( XFields()->fields[ $xfields_field->type ] ) ) {
        continue;
    }

    $xfields_level = 0;
    if ( 0 < (int) $xfields_field->parent_id ) {
        $xfields_level = isset( $xfields_levels[ $xfields_field->parent_id ] ) ? $xfields_levels[ $xfields_field->parent_id ] + 1 : 1;
        $xfields_levels[ $xfields_field->id ] = (int) $xfields_level;
    }
    $xfields_diff = ( $xfields_level - $xfields_last_level );
    $xfields_last_level = $xfields_level;

    if ( 0 < $xfields_diff ) {
        for ( $xfields_i = 0; $xfields_i < ( $xfields_diff - 1 ); $xfields_i++ ) {
            echo '<ul><li>';
        }
        echo '<ul>';
    }
    elseif ( 0 > $xfields_diff ) {
        for ( $xfields_i = 0; $xfields_i < abs( $xfields_diff ); $xfields_i++ ) {
            echo '</li></ul>';
        }
    }

    echo ( 'loop' == $xfields_field->type ) ? '<li class="loop">' : '<li>';

    XFields()->field_html( $xfields_field );
}

for ( $xfields_i = 0; $xfields_i < abs($xfields_level); $xfields_i++ ) {
    echo '</li></ul>';
}

echo '</li>';

?>
</ul>

<div class="table_footer">
    <button type="button" class="button button-primary xfields_add_field"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> <?php esc_html_e( 'Add field', 'x-fields' ); ?></button>
</div>

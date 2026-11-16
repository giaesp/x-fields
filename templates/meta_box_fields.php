<?php defined( 'ABSPATH' ) || exit; ?>
<input type="hidden" name="xf[save]" value="<?php echo esc_attr( wp_create_nonce( 'xf_save_fields' ) ); ?>" />

<div class="xf-fields-header" aria-hidden="true">
    <span class="field_order"></span>
    <span class="field_label"><?php esc_html_e( 'Label', 'x-fields' ); ?></span>
    <span class="field_name"><?php esc_html_e( 'Name', 'x-fields' ); ?></span>
    <span class="field_type"><?php esc_html_e( 'Type', 'x-fields' ); ?></span>
    <span class="field_tools"></span>
</div>

<div class="xf-empty-state"<?php echo empty( array_filter( (array) get_post_meta( $post->ID, 'xf_fields', true ) ) ) ? '' : ' hidden'; ?>>
    <span class="dashicons dashicons-forms" aria-hidden="true"></span>
    <p><strong><?php esc_html_e( 'No fields yet', 'x-fields' ); ?></strong></p>
    <p><?php esc_html_e( 'Add your first field, then choose where the group appears in “Placement Rules”.', 'x-fields' ); ?></p>
</div>

<ul class="fields">
<?php

global $post;

$results = XF()->api->get_input_fields( [ 'group_id' => $post->ID, 'post_id_values' => 0 ] );

/*---------------------------------------------------------------------------------------------
    Create <ul> based on field structure
---------------------------------------------------------------------------------------------*/

$level = 0;
$levels = [];
$last_level = $diff = 0;

foreach ( $results as $field ) {

    // Skip missing field types
    if ( ! isset( XF()->fields[ $field->type ] ) ) {
        continue;
    }

    $level = 0;
    if ( 0 < (int) $field->parent_id ) {
        $level = isset( $levels[ $field->parent_id ] ) ? $levels[ $field->parent_id ] + 1 : 1;
        $levels[ $field->id ] = (int) $level;
    }
    $diff = ( $level - $last_level );
    $last_level = $level;

    if ( 0 < $diff ) {
        for ( $i = 0; $i < ( $diff - 1 ); $i++ ) {
            echo '<ul><li>';
        }
        echo '<ul>';
    }
    elseif ( 0 > $diff ) {
        for ( $i = 0; $i < abs( $diff ); $i++ ) {
            echo '</li></ul>';
        }
    }

    echo ( 'loop' == $field->type ) ? '<li class="loop">' : '<li>';

    XF()->field_html( $field );
}

for ( $i = 0; $i < abs($level); $i++ ) {
    echo '</li></ul>';
}

echo '</li>';

?>
</ul>

<div class="table_footer">
    <button type="button" class="button button-primary xf_add_field"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> <?php esc_html_e( 'Add field', 'x-fields' ); ?></button>
</div>

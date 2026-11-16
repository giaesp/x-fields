<?php defined( 'ABSPATH' ) || exit; ?>
<div class="field">
    <?php $xfields_type_label = isset( XFields()->fields[ $field->type ] ) ? XFields()->fields[ $field->type ]->label : $field->type; ?>
    <div class="field_meta">
        <span class="field_order" title="<?php esc_attr_e( 'Drag to reorder', 'x-fields' ); ?>" aria-hidden="true"></span>
        <span class="field_label">
            <a class="xfields_edit_field row-title" href="#" role="button"><?php echo esc_html( $field->label ); ?></a>
        </span>
        <code class="field_name"><?php echo esc_html( $field->name ); ?></code>
        <span class="field_type xfields-type-<?php echo esc_attr( $field->type ); ?>"><?php echo esc_html( $xfields_type_label ); ?></span>
        <span class="field_tools">
            <button type="button" class="xfields-icon-button xfields_edit_field" title="<?php esc_attr_e( 'Edit', 'x-fields' ); ?>"><span class="dashicons dashicons-edit" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Edit', 'x-fields' ); ?></span></button>
            <button type="button" class="xfields-icon-button xfields_delete_field" title="<?php esc_attr_e( 'Delete', 'x-fields' ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Delete', 'x-fields' ); ?></span></button>
        </span>
    </div>

    <div class="field_form">
        <table class="widefat">
            <tbody>
                <tr class="field_basics">
                    <td colspan="2">
                        <table>
                            <tr>
                                <td class="field_label">
                                    <label>
                                        <?php esc_html_e( 'Label', 'x-fields' ); ?></label>
                                    <input type="text" name="xfields[fields][<?php echo esc_attr( $field->weight ); ?>][label]" value="<?php echo empty( $field->id ) ? '' : esc_attr( $field->label ); ?>" />
                                    <p class="description"><?php esc_html_e( 'The field label that editors will see.', 'x-fields' ); ?></p>
                                </td>
                                <td class="field_name">
                                    <label>
                                        <?php esc_html_e( 'Name', 'x-fields' ); ?></label>
                                    <input type="text" name="xfields[fields][<?php echo esc_attr( $field->weight ); ?>][name]" value="<?php echo empty( $field->id ) ? '' : esc_attr( $field->name ); ?>" />
                                    <p class="description"><?php esc_html_e( 'The field name is passed into get() to retrieve values. Use only lowercase letters, numbers, and underscores.', 'x-fields' ); ?></p>
                                </td>
                                <td class="field_type">
                                    <label><?php esc_html_e( 'Field Type', 'x-fields' ); ?></label>
                                    <select name="xfields[fields][<?php echo esc_attr( $field->weight ); ?>][type]">
                                        <?php foreach ( XFields()->fields as $xfields_type ) : ?>
                                        <option value="<?php echo esc_attr( $xfields_type->name ); ?>"<?php selected( $xfields_type->name, $field->type ); ?>><?php echo esc_html( $xfields_type->label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <?php if ( isset( XFields()->fields[ $field->type ] ) ) { XFields()->fields[ $field->type ]->options_html( $field->weight, $field ); } ?>

                <tr class="field_notes">
                    <td class="label">
                        <label>
                            <?php esc_html_e( 'Notes', 'x-fields' ); ?></label>
                                    <p class="description"><?php esc_html_e( 'Notes for editors during data entry', 'x-fields' ); ?></p>
                    </td>
                    <td>
                        <textarea name="xfields[fields][<?php echo esc_attr( $field->weight ); ?>][notes]"><?php echo esc_textarea( $field->notes ); ?></textarea>
                    </td>
                </tr>
                <tr class="field_actions">
                    <td class="label"></td>
                    <td style="vertical-align:middle">
                        <input type="hidden" name="xfields[fields][<?php echo esc_attr( $field->weight ); ?>][id]" class="field_id" value="<?php echo (int) $field->id; ?>" />
                        <input type="hidden" name="xfields[fields][<?php echo esc_attr( $field->weight ); ?>][parent_id]" class="parent_id" value="<?php echo (int) $field->parent_id; ?>" />
                        <button type="button" class="button xfields_edit_field"><?php esc_html_e( 'Done', 'x-fields' ); ?></button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
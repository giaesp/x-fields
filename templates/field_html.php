<?php defined( 'ABSPATH' ) || exit; ?>
<div class="field">
    <?php $type_label = isset( XF()->fields[ $field->type ] ) ? XF()->fields[ $field->type ]->label : $field->type; ?>
    <div class="field_meta">
        <span class="field_order" title="<?php esc_attr_e( 'Drag to reorder', 'x-fields' ); ?>" aria-hidden="true"></span>
        <span class="field_label">
            <a class="xf_edit_field row-title" href="#" role="button"><?php echo esc_html( $field->label ); ?></a>
        </span>
        <code class="field_name"><?php echo esc_html( $field->name ); ?></code>
        <span class="field_type xf-type-<?php echo esc_attr( $field->type ); ?>"><?php echo esc_html( $type_label ); ?></span>
        <span class="field_tools">
            <button type="button" class="xf-icon-button xf_edit_field" title="<?php esc_attr_e( 'Edit', 'x-fields' ); ?>"><span class="dashicons dashicons-edit" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Edit', 'x-fields' ); ?></span></button>
            <button type="button" class="xf-icon-button xf_delete_field" title="<?php esc_attr_e( 'Delete', 'x-fields' ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Delete', 'x-fields' ); ?></span></button>
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
                                        <?php esc_html_e( 'Label', 'x-fields' ); ?>
                                        <div class="xf_tooltip">?
                                            <div class="tooltip_inner"><?php esc_html_e( 'The field label that editors will see.', 'x-fields' ); ?></div>
                                        </div>
                                    </label>
                                    <input type="text" name="xf[fields][<?php echo esc_attr( $field->weight ); ?>][label]" value="<?php echo empty( $field->id ) ? '' : esc_attr( $field->label ); ?>" />
                                </td>
                                <td class="field_name">
                                    <label>
                                        <?php esc_html_e( 'Name', 'x-fields' ); ?>
                                        <div class="xf_tooltip">?
                                            <div class="tooltip_inner">
                                                <?php esc_html_e( 'The field name is passed into get() to retrieve values. Use only lowercase letters, numbers, and underscores.', 'x-fields' ); ?>
                                            </div>
                                        </div>
                                    </label>
                                    <input type="text" name="xf[fields][<?php echo esc_attr( $field->weight ); ?>][name]" value="<?php echo empty( $field->id ) ? '' : esc_attr( $field->name ); ?>" />
                                </td>
                                <td class="field_type">
                                    <label><?php esc_html_e( 'Field Type', 'x-fields' ); ?></label>
                                    <select name="xf[fields][<?php echo esc_attr( $field->weight ); ?>][type]">
                                        <?php foreach ( XF()->fields as $type ) : ?>
                                        <option value="<?php echo esc_attr( $type->name ); ?>"<?php selected( $type->name, $field->type ); ?>><?php echo esc_html( $type->label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <?php if ( isset( XF()->fields[ $field->type ] ) ) { XF()->fields[ $field->type ]->options_html( $field->weight, $field ); } ?>

                <tr class="field_notes">
                    <td class="label">
                        <label>
                            <?php esc_html_e( 'Notes', 'x-fields' ); ?>
                            <div class="xf_tooltip">?
                                <div class="tooltip_inner"><?php esc_html_e( 'Notes for editors during data entry', 'x-fields' ); ?></div>
                            </div>
                        </label>
                    </td>
                    <td>
                        <textarea name="xf[fields][<?php echo esc_attr( $field->weight ); ?>][notes]"><?php echo esc_textarea( $field->notes ); ?></textarea>
                    </td>
                </tr>
                <tr class="field_actions">
                    <td class="label"></td>
                    <td style="vertical-align:middle">
                        <input type="hidden" name="xf[fields][<?php echo esc_attr( $field->weight ); ?>][id]" class="field_id" value="<?php echo (int) $field->id; ?>" />
                        <input type="hidden" name="xf[fields][<?php echo esc_attr( $field->weight ); ?>][parent_id]" class="parent_id" value="<?php echo (int) $field->parent_id; ?>" />
                        <button type="button" class="button xf_edit_field"><?php esc_html_e( 'Done', 'x-fields' ); ?></button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
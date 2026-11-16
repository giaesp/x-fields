<?php

defined( 'ABSPATH' ) || exit;

/**
 * Color field.
 *
 * Uses the WordPress core color picker (wp-color-picker / Iris), the same one used
 * across the admin. Values are stored as hex strings (#rgb, #rrggbb, #rrggbbaa).
 */
class xfields_color extends xfields_field
{

    function __construct() {
        $this->name = 'color';
        $this->label = __( 'Color', 'x-fields' );
    }


    function html( $field ) {
        $value   = is_scalar( $field->value ) ? (string) $field->value : '';
        $default = $this->get_option( $field, 'default_value' );
    ?>
        <input type="text" name="<?php echo esc_attr( $field->input_name ); ?>" class="<?php echo esc_attr( $field->input_class ); ?> xfields-color-picker" value="<?php echo esc_attr( $value ); ?>" data-default-color="<?php echo esc_attr( is_scalar( $default ) ? $default : '' ); ?>" />
    <?php
    }


    function options_html( $key, $field ) {
    ?>
        <tr class="field_option field_option_<?php echo esc_attr( $this->name ); ?>">
            <td class="label">
                <label><?php esc_html_e( 'Default Value', 'x-fields' ); ?></label>
                <p class="description"><?php esc_html_e( 'Hex color, e.g. #2271b1', 'x-fields' ); ?></p>
            </td>
            <td>
                <?php
                    XFields()->create_field( [
                        'type'       => 'text',
                        'input_name' => "xfields[fields][$key][options][default_value]",
                        'value'      => $this->get_option( $field, 'default_value' ),
                    ] );
                ?>
            </td>
        </tr>
        <tr class="field_option field_option_<?php echo esc_attr( $this->name ); ?>">
            <td class="label">
                <label><?php esc_html_e( 'Validation', 'x-fields' ); ?></label>
            </td>
            <td>
                <?php
                    XFields()->create_field( [
                        'type'        => 'true_false',
                        'input_name'  => "xfields[fields][$key][options][required]",
                        'input_class' => 'true_false',
                        'value'       => $this->get_option( $field, 'required' ),
                        'options'     => [ 'message' => __( 'This is a required field', 'x-fields' ) ],
                    ] );
                ?>
            </td>
        </tr>
    <?php
    }


    // Hex colors only (#rgb, #rrggbb, #rrggbbaa)
    function pre_save( $value, $field = null ) {
        $value = is_array( $value ) ? reset( $value ) : $value;
        $value = is_scalar( $value ) ? trim( (string) $value ) : '';
        return preg_match( '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value ) ? $value : '';
    }


    function input_head( $field = null ) {
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
    ?>
        <script>
        (function($) {
            function init_color(context) {
                if (!$.fn.wpColorPicker) {
                    return;
                }
                $(context).find('.xfields_color input.xfields-color-picker').each(function() {
                    // Skip loop templates and already initialised pickers
                    if ($(this).closest('.wp-picker-container').length || /\[clone\]/.test(this.name)) {
                        return;
                    }
                    $(this).wpColorPicker();
                });
            }

            $(function() {
                init_color(document);
                $(document).on('xfields/ready', '.xfields_add_field', function() {
                    init_color(document);
                });
            });
        })(jQuery);
        </script>
    <?php
    }
}

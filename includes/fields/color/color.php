<?php

defined( 'ABSPATH' ) || exit;

class xf_color extends xf_field
{

    function __construct() {
        $this->name = 'color';
        $this->label = __( 'Color', 'x-fields' );
    }


    function options_html( $key, $field ) {
    ?>
        <tr class="field_option field_option_<?php echo esc_attr( $this->name ); ?>">
            <td class="label">
                <label><?php esc_html_e( 'Default Value', 'x-fields' ); ?></label>
            </td>
            <td>
                <?php
                    XF()->create_field( [
                        'type' => 'text',
                        'input_name' => "xf[fields][$key][options][default_value]",
                        'value' => $this->get_option( $field, 'default_value' ),
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
        wp_register_script( 'miniColors', XF_URL . '/includes/fields/color/jquery.miniColors.min.js' );
        wp_enqueue_script( 'miniColors' );
    ?>
        <link rel="stylesheet" type="text/css" href="<?php echo esc_url( XF_URL . '/includes/fields/color/color.css' ); ?>" />
        <script>
        (function($) {
            $(document).on('focus', '.xf_color input.color', function() {
                if (!$(this).hasClass('ready')) {
                    $(this).addClass('ready').minicolors();
                }
            });

            $(function() {
                $('.xf_color input.color').addClass('ready').minicolors();
            });
        })(jQuery);
        </script>
    <?php
    }
}

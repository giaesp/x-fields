<?php

defined( 'ABSPATH' ) || exit;

class xf_text extends xf_field
{

    function __construct() {
        $this->name = 'text';
        $this->label = __( 'Text', 'x-fields' );
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
        <tr class="field_option field_option_<?php echo esc_attr( $this->name ); ?>">
            <td class="label">
                <label><?php esc_html_e( 'Validation', 'x-fields' ); ?></label>
            </td>
            <td>
                <?php
                    XF()->create_field( [
                        'type' => 'true_false',
                        'input_name' => "xf[fields][$key][options][required]",
                        'input_class' => 'true_false',
                        'value' => $this->get_option( $field, 'required' ),
                        'options' => [ 'message' => __( 'This is a required field', 'x-fields' ) ],
                    ] );
                ?>
            </td>
        </tr>
    <?php
    }


    // Escaping happens in html() via esc_attr(); escaping here too caused double-encoding
    function format_value_for_input( $value, $field = null ) {
        return is_scalar( $value ) ? (string) $value : '';
    }
}

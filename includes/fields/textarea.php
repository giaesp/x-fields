<?php

defined( 'ABSPATH' ) || exit;

class xfields_textarea extends xfields_field
{

    function __construct() {
        $this->name = 'textarea';
        $this->label = __( 'Textarea', 'x-fields' );
    }


    function html( $field ) {
    ?>
        <textarea name="<?php echo esc_attr( $field->input_name ); ?>" class="<?php echo esc_attr( $field->input_class ); ?>" rows="4"><?php echo esc_textarea( is_scalar( $field->value ) ? (string) $field->value : '' ); ?></textarea>
    <?php
    }


    function options_html($key, $field) {
    ?>
        <tr class="field_option field_option_<?php echo esc_attr( $this->name ); ?>">
            <td class="label">
                <label><?php esc_html_e( 'Default Value', 'x-fields' ); ?></label>
            </td>
            <td>
                <?php
                    XFields()->create_field( [
                        'type' => 'textarea',
                        'input_name' => "xfields[fields][$key][options][default_value]",
                        'value' => $this->get_option( $field, 'default_value' ),
                    ] );
                ?>
            </td>
        </tr>
        <tr class="field_option field_option_<?php echo esc_attr( $this->name ); ?>">
            <td class="label">
                <label><?php esc_html_e( 'Formatting', 'x-fields' ); ?></label>
            </td>
            <td>
                <?php
                    XFields()->create_field( [
                        'type' => 'select',
                        'input_name' => "xfields[fields][$key][options][formatting]",
                        'options' => [
                            'choices' => [
                                'none' => __( 'None', 'x-fields' ),
                                'auto_br' => __( 'Convert newlines to <br />', 'x-fields' )
                            ],
                            'force_single' => true,
                        ],
                        'value' => $this->get_option( $field, 'formatting', 'auto_br' ),
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
                        'type' => 'true_false',
                        'input_name' => "xfields[fields][$key][options][required]",
                        'input_class' => 'true_false',
                        'value' => $this->get_option( $field, 'required' ),
                        'options' => [ 'message' => __( 'This is a required field', 'x-fields' ) ],
                    ] );
                ?>
            </td>
        </tr>
    <?php
    }


    // Escaping happens in html() via esc_textarea()
    function format_value_for_input( $value, $field = null ) {
        return is_scalar( $value ) ? (string) $value : '';
    }


    function format_value_for_api( $value, $field = null ) {
        $formatting = $this->get_option( $field, 'formatting', 'none' );
        return ( 'none' == $formatting ) ? $value : nl2br( (string) $value );
    }
}

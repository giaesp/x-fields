<?php

defined( 'ABSPATH' ) || exit;

class xfields_true_false extends xfields_field
{

    function __construct() {
        $this->name = 'true_false';
        $this->label = __('True / False', 'x-fields' );
    }




    function html( $field ) {
        $field->value = ( is_scalar( $field->value ) && 0 < (int) $field->value ) ? 1 : 0;
    ?>
		<label>
			<input type="checkbox" <?php echo $field->value ? ' checked' : ''; ?>>
			<span><?php echo isset( $field->options['message'] ) ? esc_html( $field->options['message'] ) : ''; ?></span>
			<input type="hidden" name="<?php echo esc_attr( $field->input_name ); ?>" class="<?php echo esc_attr( $field->input_class ); ?>" value="<?php echo esc_attr( $field->value ); ?>" />
		</label>
    <?php
    }




    function options_html( $key, $field ) {
    ?>
        <tr class="field_option field_option_<?php echo esc_attr( $this->name ); ?>">
            <td class="label">
                <label><?php esc_html_e( 'Message', 'x-fields' ); ?></label>
                <p class="description"><?php esc_html_e( 'The text beside the checkbox', 'x-fields' ); ?></p>
            </td>
            <td>
                <?php
                    XFields()->create_field( [
                        'type' => 'text',
                        'input_name' => "xfields[fields][$key][options][message]",
                        'value' => $this->get_option( $field, 'message' ),
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




    function input_head( $field = null ) {
    ?>
        <script>
        (function($) {
            $(function() {
                $(document).on('xfields/ready', '.xfields_add_field', function() {
                    $('.xfields_true_false:not(.ready)').init_true_false();
                });
                $('.xfields_true_false').init_true_false();
            });

            $.fn.init_true_false = function() {
                this.each(function() {
                    var $this = $(this);
                    $this.addClass('ready');

                    // handle click
                    $this.find('input[type="checkbox"]').on('change click', function() {
                        var val = $(this).prop('checked') ? 1 : 0;
                        $(this).siblings('.true_false').val(val);
                    });
                });
            }
        })(jQuery);
        </script>
    <?php
    }




    function pre_save( $value, $field = null ) {
        $value = is_array( $value ) ? reset( $value ) : $value;
        return ( is_scalar( $value ) && 0 < (int) $value ) ? 1 : 0;
    }


    function format_value_for_api( $value, $field = null ) {
        return ( is_scalar( $value ) && 0 < (int) $value ) ? 1 : 0;
    }
}

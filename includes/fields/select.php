<?php

defined( 'ABSPATH' ) || exit;

class xf_select extends xf_field
{
    public $select2_inserted;

    function __construct() {
        $this->name = 'select';
        $this->label = __( 'Select', 'x-fields' );
        $this->select2_inserted = false;
    }

    function html( $field ) {
        $multiple = '';
        $field->input_class = empty( $field->input_class ) ? '' : $field->input_class;

        // Multi-select
        if ( isset( $field->options['multiple'] ) && '1' == $field->options['multiple'] ) {
            $multiple = ' multiple';
            $field->input_class .= ' multiple';
        }

        // Select2
        if ( isset( $field->options['select2'] ) && '1' == $field->options['select2'] ) {
            $field->input_class .= ' select2';

            add_action( 'admin_footer', [ $this, 'select2_code' ] );
        }

        // Select boxes should return arrays (unless "force_single" is true)
        if ( '[]' != substr( $field->input_name, -2 ) && empty( $field->options['force_single'] ) ) {
            $field->input_name .= '[]';
        }
    ?>
        <select name="<?php echo esc_attr( $field->input_name ); ?>" class="<?php echo esc_attr( $field->input_class ); ?>"<?php echo $multiple ? ' multiple' : ''; ?>>
        <?php foreach ( ( isset( $field->options['choices'] ) ? (array) $field->options['choices'] : [] ) as $val => $label ) : ?>
            <?php $val = ( '{empty}' == $val ) ? '' : $val; ?>
            <option value="<?php echo esc_attr( $val ); ?>"<?php selected( in_array( (string) $val, array_map( 'strval', (array) $field->value ), true ) ); ?>><?php echo esc_html( $label ); ?></option>
        <?php endforeach; ?>
        </select>
    <?php
    }

    function select2_code() {

        // Exit early if the select2 code has already been inserted
        if ( $this->select2_inserted ) {
            return;
        }

        echo '<script src="' . esc_url( XF_URL . '/assets/js/select2/select2.min.js' ) . '"></script>';
        echo '<link rel="stylesheet" type="text/css" href="' . esc_url( XF_URL . '/assets/js/select2/select2.css' ) . '" />';

        // Don't insert select2 code twice
        $this->select2_inserted = true;
    }

    function input_head( $field = null ) {
    ?>
        <script>
        (function($) {
            $(function() {
                $(document).on('xf/ready', '.xf_add_field', function() {
                    $('.xf_select:not(.ready)').init_select();
                });
                $('.xf_select').init_select();
            });

            $.fn.init_select = function() {
                this.each(function() {
                    var $this = $(this);
                    $this.addClass('ready');

                    if ( $this.find( 'select' ).hasClass( 'select2' ) ) {
                        $this.find( 'select' ).select2();
                    }
                });
            }
        })(jQuery);
        </script>
    <?php
    }


    function options_html( $key, $field ) {

        // Convert choices to textarea-friendly format
        if ( isset( $field->options['choices'] ) && is_array( $field->options['choices'] ) ) {
            foreach ( $field->options['choices'] as $choice_key => $choice_val ) {
                $field->options['choices'][ $choice_key ] = "$choice_key : $choice_val";
            }

            $field->options['choices'] = implode( "\n", $field->options['choices'] );
        }
        else {
            $field->options['choices'] = '';
        }
    ?>
        <tr class="field_option field_option_<?php echo esc_attr( $this->name ); ?>">
            <td class="label">
                <label><?php esc_html_e( 'Choices', 'x-fields' ); ?></label>
                <p class="description"><?php esc_html_e( 'Enter one choice per line', 'x-fields' ); ?></p>
            </td>
            <td>
                <?php
                    XF()->create_field( [
                        'type' => 'textarea',
                        'input_name' => "xf[fields][$key][options][choices]",
                        'value' => $this->get_option( $field, 'choices' ),
                    ] );
                ?>
            </td>
        </tr>
        <tr class="field_option field_option_<?php echo esc_attr( $this->name ); ?>">
            <td class="label">
                <label><?php esc_html_e( 'Multi-select?', 'x-fields' ); ?></label>
            </td>
            <td>
                <?php
                    XF()->create_field( [
                        'type' => 'true_false',
                        'input_name' => "xf[fields][$key][options][multiple]",
                        'input_class' => 'true_false',
                        'value' => $this->get_option( $field, 'multiple' ),
                        'options' => [ 'message' => __( 'This is a multi-select field', 'x-fields' ) ],
                     ] );
                ?>
            </td>
        </tr>
        <tr class="field_option field_option_<?php echo esc_attr( $this->name ); ?>">
            <td class="label">
                <label><?php esc_html_e('Select2', 'x-fields' ); ?></label>
            </td>
            <td>
                <?php
                    XF()->create_field( [
                        'type' => 'true_false',
                        'input_name' => "xf[fields][$key][options][select2]",
                        'input_class' => 'true_false',
                        'value' => $this->get_option($field, 'select2'),
                        'options' => [ 'message' => __('Render this field with Select2', 'x-fields' ) ],
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


    function format_value_for_api( $value, $field = null ) {
        $value_array = [];
        $choices = isset( $field->options['choices'] ) ? (array) $field->options['choices'] : [];

        // Return an associative array (value, label)
        if ( is_array( $value ) ) {
            foreach ( $value as $val ) {
                if ( ! is_scalar( $val ) ) {
                    continue;
                }
                $value_array[ $val ] = isset( $choices[ $val ] ) ? $choices[ $val ] : $val;
            }
        }

        return $value_array;
    }


    function prepare_value( $value, $field = null ) {
        return $value;
    }


    // Only accept values that exist in the configured choices
    function pre_save( $value, $field = null ) {
        $choices = ( is_object( $field ) && isset( $field->options['choices'] ) ) ? (array) $field->options['choices'] : [];
        $value = array_filter( (array) $value, 'is_scalar' );

        if ( empty( $choices ) ) {
            return array_values( $value );
        }

        $allowed = array_map( 'strval', array_keys( $choices ) );
        $allowed[] = ''; // "{empty}" choice
        return array_values( array_filter( $value, function( $v ) use ( $allowed ) {
            return in_array( (string) $v, $allowed, true );
        } ) );
    }


    function pre_save_field( $field ) {
        $new_choices = [];

        // Already parsed (e.g. saved programmatically or re-saved): keep scalar pairs only
        if ( isset( $field['options']['choices'] ) && is_array( $field['options']['choices'] ) ) {
            foreach ( $field['options']['choices'] as $k => $v ) {
                if ( is_scalar( $v ) ) {
                    $new_choices[ $k ] = (string) $v;
                }
            }
            $field['options']['choices'] = $new_choices;
            return $field;
        }

        $choices = isset( $field['options']['choices'] ) && is_string( $field['options']['choices'] ) ? trim( $field['options']['choices'] ) : '';

        if ( ! empty( $choices ) ) {
            $choices = str_replace( "\r\n", "\n", $choices );
            $choices = str_replace( "\r", "\n", $choices );
            $choices = ( false !== strpos( $choices, "\n" ) ) ? explode( "\n", $choices ) : (array) $choices;

            foreach ( $choices as $choice ) {
                $choice = trim( $choice );
                if ( false !== ( $pos = strpos( $choice, ' : ' ) ) ) {
                    $array_key = substr( $choice, 0, $pos );
                    $array_value = substr( $choice, $pos + 3 );
                    $new_choices[ $array_key ] = $array_value;
                }
                else {
                    $new_choices[ $choice ] = $choice;
                }
            }
        }

        $field['options']['choices'] = $new_choices;

        return $field;
    }
}

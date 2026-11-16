<?php

defined( 'ABSPATH' ) || exit;

/**
 * Date field.
 *
 * Uses the browser's native date / datetime-local picker: no JavaScript library,
 * accessible, keyboard and mobile friendly. Values are stored as ISO strings:
 * "YYYY-MM-DD" or "YYYY-MM-DD HH:MM".
 */
class xfields_date extends xfields_field
{

    function __construct() {
        $this->name = 'date';
        $this->label = __( 'Date', 'x-fields' );
    }


    function html( $field ) {
        $with_time = ! empty( $field->options['include_time'] );
        $value     = is_scalar( $field->value ) ? trim( (string) $field->value ) : '';

        if ( $with_time ) {
            // datetime-local expects "YYYY-MM-DDTHH:MM"
            $value = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value . 'T00:00' : str_replace( ' ', 'T', substr( $value, 0, 16 ) );
        }
        else {
            $value = substr( $value, 0, 10 );
        }
    ?>
        <input type="<?php echo $with_time ? 'datetime-local' : 'date'; ?>" name="<?php echo esc_attr( $field->input_name ); ?>" class="<?php echo esc_attr( $field->input_class ); ?>" value="<?php echo esc_attr( $value ); ?>" />
    <?php
    }


    function options_html( $key, $field ) {
    ?>
        <tr class="field_option field_option_<?php echo esc_attr( $this->name ); ?>">
            <td class="label">
                <label><?php esc_html_e( 'Time', 'x-fields' ); ?></label>
            </td>
            <td>
                <?php
                    XFields()->create_field( [
                        'type'        => 'true_false',
                        'input_name'  => "xfields[fields][$key][options][include_time]",
                        'input_class' => 'true_false',
                        'value'       => $this->get_option( $field, 'include_time' ),
                        'options'     => [ 'message' => __( 'Include the time (hours and minutes)', 'x-fields' ) ],
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


    // YYYY-MM-DD with an optional time; anything else is dropped
    function pre_save( $value, $field = null ) {
        $value = is_array( $value ) ? reset( $value ) : $value;
        $value = is_scalar( $value ) ? trim( (string) $value ) : '';
        $value = str_replace( 'T', ' ', $value ); // datetime-local separator

        if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})(?: (\d{2}):(\d{2})(?::\d{2})?)?$/', $value, $m ) ) {
            return '';
        }

        // Reject impossible dates/times (e.g. 2026-02-31, 25:00)
        if ( ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
            return '';
        }
        if ( isset( $m[4] ) && ( (int) $m[4] > 23 || (int) $m[5] > 59 ) ) {
            return '';
        }

        return isset( $m[4] ) ? sprintf( '%s-%s-%s %s:%s', $m[1], $m[2], $m[3], $m[4], $m[5] ) : sprintf( '%s-%s-%s', $m[1], $m[2], $m[3] );
    }
}

<?php

defined( 'ABSPATH' ) || exit;

class xf_hyperlink extends xf_field
{


    function __construct() {
        $this->name = 'hyperlink';
        $this->label = __( 'Hyperlink', 'x-fields' );
    }


    function html( $field ) {
        $field->value = is_array( $field->value ) ? $field->value : [];
        $field->value = [
            'url'    => isset( $field->value['url'] ) ? $field->value['url'] : '',
            'text'   => isset( $field->value['text'] ) ? $field->value['text'] : '',
            'class'  => isset( $field->value['class'] ) ? $field->value['class'] : '',
            'target' => isset( $field->value['target'] ) ? $field->value['target'] : '',
        ];
    ?>
        <div class="xf-hyperlink" style="overflow:hidden;">
            <div class="xf-hyperlink-url" style="width:39%;float:left;">
                <div><?php _e( 'URL', 'x-fields' ); ?></div>
                <input type="text" name="<?php echo esc_attr( $field->input_name ); ?>[url]" class="link-url" value="<?php echo esc_url( $field->value['url'] ); ?>" placeholder="http://" />
            </div>
            <div class="xf-hyperlink-text" style="width:39%;float:left;margin-left:1%;">
                <div><?php _e( 'Link Text', 'x-fields' ); ?></div>
                <input type="text" name="<?php echo esc_attr( $field->input_name ); ?>[text]" class="link-text" value="<?php echo esc_attr( $field->value['text'] ); ?>" />
            </div>
            <div class="xf-hyperlink-target" style="width:19%;float:left;margin-left:1%;">
                <div><?php _e( 'Link Target', 'x-fields' ); ?></div>
                <select class="link-target widefat" name="<?php echo esc_attr( $field->input_name ); ?>[target]">
                    <option value="none" <?php selected( 'none', esc_attr( $field->value['target'] ) ); ?>>None</option>
                    <option value="_blank" <?php selected( '_blank', esc_attr( $field->value['target'] ) ); ?>>_blank</option>
                    <option value="_self" <?php selected( '_self', esc_attr( $field->value['target'] ) ); ?>>_self</option>
                    <option value="_top" <?php selected( '_top', esc_attr( $field->value['target'] ) ); ?>>_top</option>
                </select>
            </div>
        </div>
    <?php
    }


    function options_html( $key, $field = null ) {
    ?>
        <tr class="field_option field_option_<?php echo esc_attr( $this->name ); ?>">
            <td class="label">
                <label><?php _e('Output format', 'x-fields' ); ?></label>
            </td>
            <td>
                <?php
                    XF()->create_field( [
                        'type' => 'select',
                        'input_name' => "xf[fields][$key][options][format]",
                        'options' => [
                            'choices' => [
                                'html' => __( 'HTML', 'x-fields' ),
                                'php' => __( 'PHP Array', 'x-fields' )
                            ],
                            'force_single' => true,
                        ],
                        'value' => $this->get_option( $field, 'format', 'html' ),
                    ] );
                ?>
            </td>
        </tr>
    <?php
    }


    function pre_save( $value, $field = null ) {
        // convert to a proper associative array when inside a Loop
        if ( isset( $value[0]['url'], $value[1]['text'], $value[2]['target'] ) ) {
            $value = [
                'url'    => $value[0]['url'],
                'text'   => $value[1]['text'],
                'target' => $value[2]['target'],
            ];
        }
        // Keep only known string keys
        $clean = [];
        foreach ( [ 'url', 'text', 'class', 'target' ] as $k ) {
            if ( isset( $value[ $k ] ) && is_scalar( $value[ $k ] ) ) {
                $clean[ $k ] = (string) $value[ $k ];
            }
        }
        if ( isset( $clean['url'] ) ) {
            $clean['url'] = esc_url_raw( $clean['url'] );
        }
        if ( isset( $clean['target'] ) && ! in_array( $clean['target'], [ 'none', '_blank', '_self', '_top' ], true ) ) {
            $clean['target'] = '';
        }

        return serialize( $clean );
    }


    function prepare_value( $value, $field = null ) {
        $raw = is_array( $value ) ? ( isset( $value[0] ) ? $value[0] : '' ) : $value;

        if ( ! is_string( $raw ) || ! is_serialized( $raw ) ) {
            return [];
        }

        // Never instantiate objects from stored data
        $data = @unserialize( $raw, [ 'allowed_classes' => false ] );
        return is_array( $data ) ? $data : [];
    }


    function format_value_for_api( $value, $field = null ) {
        $value  = is_array( $value ) ? $value : [];
        $url    = isset( $value['url'] ) ? $value['url'] : '';
        $text   = ( isset( $value['text'] ) && '' !== $value['text'] ) ? $value['text'] : $url;
        $target = isset( $value['target'] ) ? $value['target'] : '';
        $format = $this->get_option( $field, 'format', 'html' );
        $output = '';

        // target="none" (sometimes?) opens a new tab
        if ( 'none' == $target ) {
            $target = '';
        }

        // Return an HTML string
        if ( 'html' == $format ) {
            $output = '';
            if ( ! empty( $url ) ) {
                $target_attr = empty( $target ) ? '' : ' target="' . esc_attr( $target ) . '"';
                $rel_attr = ( '_blank' === $target ) ? ' rel="noopener noreferrer"' : '';
                $output = '<a class="xf-hyperlink" href="' . esc_url( $url ) . '"' . $target_attr . $rel_attr . '><span class="text">' . esc_html( $text ) . '</span></a>';
            }
        }

        // Return an associative array
        elseif ( 'php' == $format ) {
            $output = $value;
        }

        return $output;
    }
}

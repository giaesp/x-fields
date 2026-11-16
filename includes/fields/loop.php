<?php

defined( 'ABSPATH' ) || exit;

class xfields_loop extends xfields_field
{
    public $values;

    function __construct() {
        $this->name = 'loop';
        $this->label = __( 'Loop', 'x-fields' );
        $this->values = [];
    }


    /*
    ================================================================
        html
    ================================================================
    */
    function html( $field ) {
        global $post;

        $this->values = empty( $post->ID ) ? [] : XFields()->api->get_fields( $post->ID, [ 'format' => 'input' ] );
        $this->recursive_clone( $field->group_id, $field->id );
        $this->recursive_html( $field->group_id, $field->id );
    }


    /*
    ================================================================
        options_html
    ================================================================
    */
    function options_html( $key, $field ) {
    ?>
        <tr class="field_option field_option_<?php echo esc_attr( $this->name ); ?>">
            <td class="label">
                <label><?php esc_html_e( 'Row Display', 'x-fields' ); ?></label>
            </td>
            <td>
                <?php
                    XFields()->create_field( [
                        'type' => 'true_false',
                        'input_name' => "xfields[fields][$key][options][row_display]",
                        'input_class' => 'true_false',
                        'value' => $this->get_option( $field, 'row_display' ),
                        'options' => [ 'message' => __( 'Show the values by default', 'x-fields' ) ],
                    ] );
                ?>
            </td>
        </tr>
        <tr class="field_option field_option_<?php echo esc_attr( $this->name ); ?>">
            <td class="label">
                <label><?php esc_html_e( 'Row Label', 'x-fields' ); ?></label>
            </td>
            <td>
                <?php
                    XFields()->create_field( [
                        'type' => 'text',
                        'input_name' => "xfields[fields][$key][options][row_label]",
                        'value' => $this->get_option( $field, 'row_label', __( 'Loop Row', 'x-fields' ) ),
                    ] );
                ?>
            </td>
        </tr>
        <tr class="field_option field_option_<?php echo esc_attr( $this->name ); ?>">
            <td class="label">
                <label><?php esc_html_e( 'Button Label', 'x-fields' ); ?></label>
            </td>
            <td>
                <?php
                    XFields()->create_field( [
                        'type' => 'text',
                        'input_name' => "xfields[fields][$key][options][button_label]",
                        'value' => $this->get_option( $field, 'button_label', __( 'Add Row', 'x-fields' ) ),
                    ] );
                ?>
            </td>
        </tr>

        <tr class="field_option field_option_<?php echo esc_attr( $this->name ); ?>">
            <td class="label">
                <label><?php esc_html_e( 'Limits', 'x-fields' ); ?></label>
            </td>
            <td>
                <input type="text" name="xfields[fields][<?php echo esc_attr( $key ); ?>][options][limit_min]" value="<?php echo esc_attr( $this->get_option( $field, 'limit_min' ) ); ?>" placeholder="min" style="width:60px" />
                <input type="text" name="xfields[fields][<?php echo esc_attr( $key ); ?>][options][limit_max]" value="<?php echo esc_attr( $this->get_option( $field, 'limit_max' ) ); ?>" placeholder="max" style="width:60px" />
            </td>
        </tr>

    <?php
    }


    /*
    ================================================================
        recursive_clone
    ================================================================
    */
    function recursive_clone( $group_id, $field_id ) {
        $loop_field_ids = [];

        // Get loop field
        $loop_field = XFields()->api->get_input_fields( [
            'field_id' => $field_id
        ] );

        // Get sub-fields
        $results = XFields()->api->get_input_fields( [
            'group_id' => $group_id,
            'parent_id' => $field_id
        ] );

        $loop_field_obj = isset( $loop_field[ $field_id ] ) ? $loop_field[ $field_id ] : null;

        $row_label = $this->dynamic_label(
            $this->get_option( $loop_field_obj, 'row_label', __( 'Loop Row', 'x-fields' ) )
        );

        ob_start();
    ?>
        <div class="loop_wrapper">
            <div class="xfields_loop_head open">
                <a class="xfields_delete_field" href="javascript:;"></a>
                <a class="xfields_toggle_field" href="javascript:;"></a>
                <a class="xfields_insert_field" href="javascript:;"></a>
                <span class="label"><?php echo esc_html( $row_label ); ?></span>
            </div>
            <div class="xfields_loop_body open">
            <?php foreach ( $results as $field ) : ?>
                <label><?php echo esc_html( $field->label ); ?></label>

                <?php if ( ! empty( $field->notes ) ) : ?>
                <p class="notes"><?php echo esc_html( $field->notes ); ?></p>
                <?php endif; ?>

                <div class="field field-<?php echo esc_attr( $field->name ); ?> xfields_<?php echo esc_attr( $field->type ); ?>">
                <?php
                if ( 'loop' == $field->type ) :
                    $loop_field_ids[] = $field->id;
                ?>
                    <div class="table_footer">
                        <input type="button" class="button-primary xfields_add_field" value="<?php echo esc_attr( $this->get_option( $field, 'button_label', __( 'Add Row', 'x-fields' ) ) ); ?>" data-loop-tag="[clone][<?php echo (int) $field->id; ?>]" data-rows="0" />
                    </div>
                <?php else : ?>
                <?php
                    XFields()->create_field( [
                        'type' => $field->type,
                        'input_name' => "xfields[input][clone][$field->id][value][]",
                        'input_class' => $field->type,
                        'options' => $field->options,
                        'value' => $this->get_option( $field, 'default_value' ),
                    ] );
                ?>
                <?php endif; ?>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    <?php
        $buffer = ob_get_clean();
    ?>

        <script>
        XFields.loop_buffer[<?php echo (int) $field_id; ?>] = <?php echo wp_json_encode( $buffer, JSON_HEX_TAG | JSON_HEX_AMP ); ?>;
        </script>

    <?php
        foreach ( $loop_field_ids as $loop_field_id ) {
            $this->recursive_clone( $group_id, $loop_field_id );
        }
    }


    /*
    ================================================================
        recursive_html
    ================================================================
    */
    function recursive_html( $group_id, $field_id, $parent_tag = '', $parent_weight = 0 ) {

        // Get loop field
        $loop_field = XFields()->api->get_input_fields( [
            'field_id' => $field_id
        ] );

        // Get sub-fields
        $results = XFields()->api->get_input_fields( [
            'group_id' => $group_id,
            'parent_id' => $field_id
        ] );

        // Dynamically build the $values array
        // (previously done with eval(), which allowed code execution through crafted row keys)
        $field_id = (int) $field_id;
        $parent_tag = empty( $parent_tag ) ? "[$field_id]" : $parent_tag;
        $values = $this->get_nested_value( $this->values, $parent_tag );

        // Row options
        $loop_field_obj = isset( $loop_field[ $field_id ] ) ? $loop_field[ $field_id ] : null;
        $row_display = $this->get_option( $loop_field_obj, 'row_display', 0 );
        $row_label = $this->get_option( $loop_field_obj, 'row_label', __( 'Loop Row', 'x-fields' ) );
        $button_label = $this->get_option( $loop_field_obj, 'button_label', __( 'Add Row', 'x-fields' ) );
        $css_class = ( 0 < (int) $row_display ) ? ' open' : '';

        // Do the dirty work
        $row_offset = -1;

        if ( is_array( $values ) && $values ) :
            foreach ( $values as $i => $value ) :

                // Row indexes must be integers
                if ( ! is_int( $i ) && ! ctype_digit( (string) $i ) ) {
                    continue;
                }
                $i = (int) $i;

                $row_offset = max( $i, $row_offset );
    ?>
        <div class="loop_wrapper">
            <div class="xfields_loop_head<?php echo esc_attr( $css_class ); ?>">
                <a class="xfields_delete_field" href="javascript:;"></a>
                <a class="xfields_toggle_field" href="javascript:;"></a>
                <a class="xfields_insert_field" href="javascript:;"></a>
                <span class="label"><?php echo esc_html( $this->dynamic_label( $row_label, $results, $values[ $i ] ) ); ?>&nbsp;</span>
            </div>
            <div class="xfields_loop_body<?php echo esc_attr( $css_class ); ?>">
            <?php foreach ( $results as $field ) : ?>
                <label><?php echo esc_html( $field->label ); ?></label>

                <?php if ( ! empty( $field->notes ) ) : ?>
                <p class="notes"><?php echo esc_html( $field->notes ); ?></p>
                <?php endif; ?>

                <div class="field field-<?php echo esc_attr( $field->name ); ?> xfields_<?php echo esc_attr( $field->type ); ?>">
                <?php if ( 'loop' == $field->type ) : ?>
                    <?php $this->recursive_html( $group_id, $field->id, "{$parent_tag}[$i][$field->id]", $i ); ?>
                <?php else : ?>
                <?php
                    $args = [
                        'type' => $field->type,
                        'input_name' => "xfields[input]{$parent_tag}[$i][$field->id][value][]",
                        'input_class' => $field->type,
                        'options' => $field->options,
                    ];

                    if ( isset( $values[ $i ][ $field->id ] ) ) {
                        $args['value'] = $values[ $i ][ $field->id ];
                    }
                    elseif ( isset( $field->options['default_value'] ) ) {
                        $args['value'] = $field->options['default_value'];
                    }

                    XFields()->create_field( $args );
                ?>
                <?php endif; ?>
                </div>
            <?php endforeach; ?>
            </div>
        </div>

        <?php endforeach; endif; ?>

        <div class="table_footer">
            <input type="button" class="button-primary xfields_add_field" value="<?php echo esc_attr( $button_label ); ?>" data-loop-tag="<?php echo esc_attr( $parent_tag ); ?>" data-rows="<?php echo (int) ( $row_offset + 1 ); ?>" />
        </div>
    <?php
    }


    /*---------------------------------------------------------------------------------------------
        input_head
    ---------------------------------------------------------------------------------------------*/

    function input_head( $field = null ) {
    ?>
        <script>
        (function($) {
            $(function() {
                $(document).on('click', '.xfields_add_field', function() {
                    var num_rows = $(this).attr('data-rows');
                    var loop_tag = $(this).attr('data-loop-tag');
                    var loop_id = loop_tag.match(/.*\[(.*?)\]/)[1];
                    var html = XFields.loop_buffer[loop_id].replace(/\[clone\]/g, loop_tag + '[' + num_rows + ']');
                    $(this).attr('data-rows', parseInt(num_rows)+1);
                    $(html).insertBefore( $(this).closest('.table_footer') ).addClass('loop_wrapper_new');
                    $(this).trigger('xfields/ready');
                });

                $(document).on('click', '.xfields_insert_field', function(event) {
                    event.stopPropagation();
                    // Use the "Add Row" button of THIS loop (previously the first one on the page)
                    var $add_field = $(this).closest('.loop_wrapper').siblings('.table_footer').find('.xfields_add_field').first();
                    if (!$add_field.length) {
                        return;
                    }
                    var num_rows = $add_field.attr('data-rows');
                    var loop_tag = $add_field.attr('data-loop-tag');
                    var loop_id = loop_tag.match(/.*\[(.*?)\]/)[1];
                    var html = XFields.loop_buffer[loop_id].replace(/\[clone\]/g, loop_tag + '[' + num_rows + ']');
                    $add_field.attr('data-rows', parseInt(num_rows)+1);
                    $(html).insertAfter( $(this).closest('.loop_wrapper') ).addClass('loop_wrapper_new');
                    $add_field.trigger('xfields/ready');
                });

                $(document).on('click', '.xfields_loop_head .xfields_delete_field', function(event) {
                    if (confirm(<?php echo wp_json_encode( __( 'Remove this row?', 'x-fields' ) ); ?>)) {
                        $(this).closest('.loop_wrapper').remove();
                    }
                    event.stopPropagation();
                });

                $(document).on('click', '.xfields_loop_head', function() {
                    $(this).toggleClass('open');
                    $(this).siblings('.xfields_loop_body').toggleClass('open');
                });

                // Hide or show all rows
                // The HTML is located in includes/form.php
                $(document).on('click', '.xfields_loop_toggle', function() {
                    $(this).closest('.field').find('.xfields_loop_head').toggleClass('open');
                    $(this).closest('.field').find('.xfields_loop_body').toggleClass('open');
                });

                $('.xfields_loop').sortable({
                    axis: 'y',
                    containment: 'parent',
                    items: '.loop_wrapper',
                    handle: '.xfields_loop_head',
                    update: function(event, ui) {

                        // To re-order field names:
                        // 1. Get the depth of the dragged element
                        // 2. Loop through each input field within the dragged element
                        // 3. Reset the array index within the name attribute
                        var $container = ui.item.closest('.field');
                        var depth = $container.closest('.xfields_loop').parents('.xfields_loop').length;
                        var array_element = 3 + (depth * 2);

                        var counter = -1;
                        var last_index = -1;
                        $container.find('[name^="xfields[input]"]').each(function() {
                            var name_attr = $(this).attr('name').split('[');
                            var current_index = parseInt( name_attr[array_element] );
                            if (current_index != last_index) {
                                counter += 1;
                            }
                            name_attr[array_element] = counter + ']';
                            last_index = current_index;
                            $(this).attr('name', name_attr.join('['));
                        });
                    }
                });
            });
        })(jQuery);
        </script>
    <?php
    }


    /*
    ================================================================
        dynamic_label
    ================================================================
    */
    function dynamic_label( $row_label, $fields = [], $values = [] ) {

        // Exit stage left
        if ( '{' != substr( $row_label, 0, 1 ) || '}' != substr( $row_label, -1 ) ) {
            return $row_label;
        }

        $field = false;
        $fallback = false;
        $field_name = substr( $row_label, 1, -1 );

        // Check for fallback value
        if ( false !== strpos( $field_name, ':' ) ) {
            list( $field_name, $fallback ) = explode( ':', $field_name );
        }

        // Get all field names and IDs
        foreach ( $fields as $f ) {
            if ( $field_name == $f->name ) {
                $field = $f;
                break;
            }
        }

        if ( ! empty( $field ) && is_array( $values ) && isset( $values[ $field->id ] ) ) {
            if ( 'select' == $field->type ) {
                $select_key = reset( $values[ $field->id ] );
                $row_label = isset( $field->options['choices'][ $select_key ] ) ? $field->options['choices'][ $select_key ] : $select_key;
            }
            else {
                $row_label = $values[ $field->id ];
            }

            if ( is_array( $row_label ) ) {
                $row_label = implode( ', ', array_filter( $row_label, 'is_scalar' ) );
            }
        }
        elseif ( false !== $fallback ) {
             $row_label = $fallback;
        }

        return $row_label;
    }


    /**
     * Safely walk $array using a tag like "[12][0][15]"
     * @param array $array
     * @param string $tag
     * @return mixed false if the path doesn't exist
     * @since 2.7.0
     */
    function get_nested_value( $array, $tag ) {
        if ( ! preg_match_all( '/\[(\d+)\]/', (string) $tag, $matches ) ) {
            return false;
        }

        $value = $array;
        foreach ( $matches[1] as $key ) {
            if ( ! is_array( $value ) || ! array_key_exists( (int) $key, $value ) ) {
                return false;
            }
            $value = $value[ (int) $key ];
        }

        return $value;
    }


    /*
    ================================================================
        prepare_value
    ================================================================
    */
    function prepare_value( $value, $field = null ) {
        return $value;
    }
}

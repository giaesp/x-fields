<?php

defined( 'ABSPATH' ) || exit;

class xfields_term extends xfields_field
{

    function __construct() {
        $this->name = 'term';
        $this->label = __( 'Term', 'x-fields' );
    }


    function html( $field ) {
        global $wpdb;

        $selected_posts = [];
        $available_posts = [];

        $taxonomies = [];
        if ( ! empty( $field->options['taxonomies'] ) ) {
            foreach ( (array) $field->options['taxonomies'] as $taxonomy ) {
                $taxonomies[] = $taxonomy;
            }
        }
        else {
            $taxonomies = get_taxonomies( [ 'public' => true ] );
        }

        $args = [
            'taxonomy'   => $taxonomies,
            'hide_empty' => false,
            'fields'     => 'ids',
            'orderby'    => 'name',
            'order'      => 'ASC'
        ];

        $args = apply_filters( 'xfields_field_term_query_args', $args, [ 'field' => $field ] );

        $query = get_terms( $args );
        $query = is_wp_error( $query ) ? [] : (array) $query;

        foreach ( $query as $term_id ) {
            $term = get_term( $term_id );
            if ( ! ( $term instanceof WP_Term ) ) {
                continue;
            }
            $available_posts[] = (object) [
                'term_id'  => $term->term_id,
                'taxonomy' => $term->taxonomy,
                'name'     => $term->name,
            ];
        }

        $ids = xfields_sanitize_id_list( $field->value );
        $field->value = implode( ',', $ids );

        if ( ! empty( $ids ) ) {
            // Restrict to the allowed taxonomies (IDs can be tampered with)
            $results = get_terms( [ 'taxonomy' => array_values( (array) $taxonomies ), 'include' => $ids, 'orderby' => 'include', 'hide_empty' => false ] );
            foreach ( is_wp_error( $results ) ? [] : $results as $result ) {
                $selected_posts[ $result->term_id ] = $result;
            }
        }
    ?>
        <div class="filter_posts">
            <input type="text" class="xfields_filter_input" autocomplete="off" placeholder="<?php esc_attr_e( 'Search terms', 'x-fields' ); ?>" />
        </div>

        <div class="available_posts post_list">
        <?php foreach ( $available_posts as $term ) : ?>
            <div rel="<?php echo (int) $term->term_id; ?>"<?php echo isset( $selected_posts[ $term->term_id ] ) ? ' class="used"' : ''; ?> title="<?php echo esc_attr( $term->name ); ?>"><?php echo wp_kses_post( apply_filters( 'xfields_term_display', esc_html( $term->name ), $term->term_id, $field ) ); ?></div>
        <?php endforeach; ?>
        </div>

        <div class="selected_posts post_list">
        <?php foreach ( $selected_posts as $term ) : ?>
            <div rel="<?php echo (int) $term->term_id; ?>"><span class="remove"></span><?php echo wp_kses_post( apply_filters( 'xfields_term_display', esc_html( $term->name ), $term->term_id, $field ) ); ?></div>
        <?php endforeach; ?>
        </div>
        <div class="clear"></div>
        <input type="hidden" name="<?php echo esc_attr( $field->input_name ); ?>" class="<?php echo esc_attr( $field->input_class ); ?>" value="<?php echo esc_attr( $field->value ); ?>" />
    <?php
    }


    function options_html( $key, $field ) {
        $args = [ 'public' => true ];
        $choices = apply_filters( 'xfields_field_term_taxonomies', get_taxonomies( $args ) );

    ?>
        <tr class="field_option field_option_<?php echo esc_attr( $this->name ); ?>">
            <td class="label">
                <label><?php esc_html_e('Taxonomies', 'x-fields' ); ?></label>
                <p class="description"><?php esc_html_e('Limit terms to the following taxonomies', 'x-fields' ); ?></p>
            </td>
            <td>
                <?php
                    XFields()->create_field( [
                        'type'          => 'select',
                        'input_name'    => "xfields[fields][$key][options][taxonomies]",
                        'options'       => [ 'multiple' => '1', 'choices' => $choices ],
                        'value'         => $this->get_option( $field, 'taxonomies' ),
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


    function input_head( $field = null ) {
    ?>
        <script>
        (function($) {
            window.update_term_values = function(field) {
                var term_ids = [];
                field.find('.selected_posts div').each(function(idx) {
                    term_ids[idx] = $(this).attr('rel');
                });
                field.find('input.term').val(term_ids.join(','));
            }

            $(function() {
                $(document).on('xfields/ready', '.xfields_add_field', function() {
                    $('.xfields_term:not(.ready)').init_term();
                });
                $('.xfields_term').init_term();

                // add selected post
                $(document).on('click', '.xfields_term .available_posts div', function() {
                    var parent = $(this).closest('.field');
                    var term_id = $(this).attr('rel');
                    var html = $(this).html();
                    $(this).addClass('used');
                    parent.find('.selected_posts').append('<div rel="'+term_id+'"><span class="remove"></span>'+html+'</div>');
                    update_term_values(parent);
                });

                // remove selected post
                $(document).on('click', '.xfields_term .selected_posts .remove', function() {
                    var div = $(this).parent();
                    var parent = div.closest('.field');
                    var term_id = div.attr('rel');
                    parent.find('.available_posts div[rel="'+parseInt(term_id, 10)+'"]').removeClass('used');
                    div.remove();
                    update_term_values(parent);
                });

                // filter posts
                $(document).on('keyup', '.xfields_term .xfields_filter_input', function() {
                    var input = $(this).val();
                    var parent = $(this).closest('.field');
                    var regex = new RegExp(input.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'i');
                    parent.find('.available_posts div:not(.used)').each(function() {
                        if (-1 < $(this).html().search(regex)) {
                            $(this).removeClass('hidden');
                        }
                        else {
                            $(this).addClass('hidden');
                        }
                    });
                });
            });

            $.fn.init_term = function() {
                this.each(function() {
                    var $this = $(this);
                    $this.addClass('ready');

                    // sortable
                    $this.find('.selected_posts').sortable({
                        axis: 'y',
                        update: function(event, ui) {
                            var parent = $(this).closest('.field');
                            update_term_values(parent);
                        }
                    });
                });
            }
        })(jQuery);
        </script>
    <?php
    }


    function prepare_value( $value, $field = null ) {
        return $value;
    }


    function format_value_for_input( $value, $field = null ) {
        return implode( ',', xfields_sanitize_id_list( $value ) );
    }


    function pre_save( $value, $field = null ) {
        // Only positive integer IDs are allowed (prevents SQL injection in html())
        return xfields_sanitize_id_list( $value );
    }
}

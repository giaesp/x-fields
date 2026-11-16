<?php

defined( 'ABSPATH' ) || exit;

class xfields_relationship extends xfields_field
{

    function __construct() {
        $this->name = 'relationship';
        $this->label = __( 'Relationship', 'x-fields' );
    }


    function html( $field ) {
        global $wpdb;

        $selected_posts = [];
        $available_posts = [];

        $post_types = [];
        if ( ! empty( $field->options['post_types'] ) ) {
            foreach ( (array) $field->options['post_types'] as $type ) {
                $post_types[] = $type;
            }
        }
        else {
            // Same list offered in the field settings (was exclude_from_search => true)
            $post_types = array_values( get_post_types( [ 'exclude_from_search' => false ] ) );
        }

        $current = get_the_ID();

        $args = [
            'post__not_in'      => $current ? [ (int) $current ] : [],  // a post can't relate to itself
            'post_type'         => $post_types,
            'post_status'       => [ 'publish', 'private' ],
            'posts_per_page'    => -1,
            'orderby'           => 'title',
            'order'             => 'ASC'
        ];

        $args = apply_filters( 'xfields_field_relationship_query_args', $args, [ 'field' => $field ] );
        $query = new WP_Query( $args );
        $query->posts = array_filter( $query->posts, function( $p ) {
            return current_user_can( 'read_post', $p->ID );
        } );

        foreach ( $query->posts as $post_obj ) {
            $post_title = ( 'private' == $post_obj->post_status ) ? '(Private) ' . $post_obj->post_title : $post_obj->post_title;
            $available_posts[] = (object) [
                'ID'            => $post_obj->ID,
                'post_type'     => $post_obj->post_type,
                'post_status'   => $post_obj->post_status,
                'post_title'    => $post_title,
            ];
        }

        $ids = xfields_sanitize_id_list( $field->value );
        $field->value = implode( ',', $ids );

        if ( ! empty( $ids ) ) {
            $results = get_posts( [
                'post__in'               => $ids,
                'orderby'                => 'post__in',
                'post_type'              => 'any',
                'post_status'            => 'any',
                'posts_per_page'         => count( $ids ),
                'ignore_sticky_posts'    => true,
                'no_found_rows'          => true,
                'update_post_term_cache' => false,
            ] );
            foreach ( $results as $result ) {
                // Never reveal titles the current user can't read (IDs can be tampered with)
                if ( ! current_user_can( 'read_post', $result->ID ) ) {
                    continue;
                }
                $result->post_title = ( 'private' == $result->post_status ) ? '(Private) ' . $result->post_title : $result->post_title;
                $selected_posts[ $result->ID ] = $result;
            }
        }
    ?>
        <div class="filter_posts">
            <input type="text" class="xfields_filter_input" autocomplete="off" placeholder="<?php esc_attr_e( 'Search posts', 'x-fields' ); ?>" />
        </div>

        <div class="available_posts post_list">
        <?php foreach ( $available_posts as $post ) : ?>
            <div rel="<?php echo (int) $post->ID; ?>"<?php echo isset( $selected_posts[ $post->ID ] ) ? ' class="used"' : ''; ?> title="<?php echo esc_attr( $post->post_type ); ?>"><?php echo wp_kses_post( apply_filters( 'xfields_relationship_display', esc_html( $post->post_title ), $post->ID, $field ) ); ?></div>
        <?php endforeach; ?>
        </div>

        <div class="selected_posts post_list">
        <?php foreach ( $selected_posts as $post ) : ?>
            <div rel="<?php echo (int) $post->ID; ?>"><span class="remove"></span><?php echo wp_kses_post( apply_filters( 'xfields_relationship_display', esc_html( $post->post_title ), $post->ID, $field ) ); ?></div>
        <?php endforeach; ?>
        </div>
        <div class="clear"></div>
        <input type="hidden" name="<?php echo esc_attr( $field->input_name ); ?>" class="<?php echo esc_attr( $field->input_class ); ?>" value="<?php echo esc_attr( $field->value ); ?>" />
    <?php
    }


    function options_html( $key, $field ) {
        $args = [ 'exclude_from_search' => false ];
        $choices = apply_filters( 'xfields_field_relationship_post_types', get_post_types( $args ) );

    ?>
        <tr class="field_option field_option_<?php echo esc_attr( $this->name ); ?>">
            <td class="label">
                <label><?php esc_html_e('Post Types', 'x-fields' ); ?></label>
                <p class="description"><?php esc_html_e('Limit posts to the following types', 'x-fields' ); ?></p>
            </td>
            <td>
                <?php
                    XFields()->create_field( [
                        'type'          => 'select',
                        'input_name'    => "xfields[fields][$key][options][post_types]",
                        'options'       => [ 'multiple' => '1', 'choices' => $choices ],
                        'value'         => $this->get_option( $field, 'post_types' ),
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
            window.update_relationship_values = function(field) {
                var post_ids = [];
                field.find('.selected_posts div').each(function(idx) {
                    post_ids[idx] = $(this).attr('rel');
                });
                field.find('input.relationship').val(post_ids.join(','));
            }

            $(function() {
                $(document).on('xfields/ready', '.xfields_add_field', function() {
                    $('.xfields_relationship:not(.ready)').init_relationship();
                });
                $('.xfields_relationship').init_relationship();

                // add selected post
                $(document).on('click', '.xfields_relationship .available_posts div', function() {
                    var parent = $(this).closest('.field');
                    var post_id = $(this).attr('rel');
                    var html = $(this).html();
                    $(this).addClass('used');
                    parent.find('.selected_posts').append('<div rel="'+post_id+'"><span class="remove"></span>'+html+'</div>');
                    update_relationship_values(parent);
                });

                // remove selected post
                $(document).on('click', '.xfields_relationship .selected_posts .remove', function() {
                    var div = $(this).parent();
                    var parent = div.closest('.field');
                    var post_id = div.attr('rel');
                    parent.find('.available_posts div[rel="'+parseInt(post_id, 10)+'"]').removeClass('used');
                    div.remove();
                    update_relationship_values(parent);
                });

                // filter posts
                $(document).on('keyup', '.xfields_relationship .xfields_filter_input', function() {
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

            $.fn.init_relationship = function() {
                this.each(function() {
                    var $this = $(this);
                    $this.addClass('ready');

                    // sortable
                    $this.find('.selected_posts').sortable({
                        axis: 'y',
                        update: function(event, ui) {
                            var parent = $(this).closest('.field');
                            update_relationship_values(parent);
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

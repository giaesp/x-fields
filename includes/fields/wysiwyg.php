<?php

defined( 'ABSPATH' ) || exit;

/**
 * WYSIWYG field.
 *
 * Built on the official WordPress editor API (wp.editor.initialize, WP 4.8+):
 * Visual + Text tabs, "Add Media" button and core TinyMCE plugins, with no bundled
 * TinyMCE plugins of our own. Works in Loop rows (initialised when a row is added,
 * re-initialised after drag & drop).
 */
class xfields_wysiwyg extends xfields_field
{

    function __construct() {
        $this->name = 'wysiwyg';
        $this->label = __( 'Wysiwyg Editor', 'x-fields' );
    }


    function html( $field ) {
        $value = is_scalar( $field->value ) ? (string) $field->value : '';
    ?>
        <textarea name="<?php echo esc_attr( $field->input_name ); ?>" class="wp-editor-area xfields-wysiwyg-area <?php echo esc_attr( $field->input_class ); ?>" rows="12" data-media="<?php echo current_user_can( 'upload_files' ) ? '1' : '0'; ?>"><?php echo esc_textarea( $value ); ?></textarea>
    <?php
    }


    function options_html( $key, $field ) {
    ?>
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
                                'default' => __( 'Default', 'x-fields' ),
                                'none' => __( 'None (bypass filters)', 'x-fields' )
                            ],
                            'force_single' => true,
                        ],
                        'value' => $this->get_option( $field, 'formatting', 'default' ),
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
        // Loads TinyMCE, Quicktags and the wp.editor API (admin and front-end)
        wp_enqueue_editor();

        if ( current_user_can( 'upload_files' ) ) {
            wp_enqueue_media();
        }
    ?>
        <script>
        (function($) {
            var counter = 0;
            var settings = {
                tinymce: {
                    wpautop: true,
                    plugins: 'charmap colorpicker hr lists paste tabfocus textcolor fullscreen wordpress wpautoresize wpeditimage wpemoji wpgallery wplink wptextpattern',
                    toolbar1: 'formatselect bold italic bullist numlist blockquote alignleft aligncenter alignright link wp_more fullscreen wp_adv',
                    toolbar2: 'strikethrough hr forecolor pastetext removeformat charmap outdent indent undo redo wp_help',
                    wp_autoresize_on: true
                },
                quicktags: true
            };

            function editor_ready() {
                return window.wp && wp.editor && typeof wp.editor.initialize === 'function';
            }

            function init_editors(context) {
                if (!editor_ready()) {
                    return;
                }
                $(context).find('.xfields_wysiwyg textarea.xfields-wysiwyg-area').each(function() {
                    // Skip loop templates and already initialised editors
                    if ($(this).hasClass('xfields-ready') || /\[clone\]/.test(this.name)) {
                        return;
                    }
                    if (!this.id) {
                        counter++;
                        this.id = 'xfields_wysiwyg_' + counter + '_' + Math.random().toString(36).slice(2, 8);
                    }
                    $(this).addClass('xfields-ready');
                    wp.editor.initialize(this.id, $.extend(true, {}, settings, {
                        mediaButtons: $(this).data('media') === 1
                    }));
                });
            }

            function save_all() {
                if (window.tinymce) {
                    window.tinymce.triggerSave();
                }
            }

            $(function() {
                init_editors(document);

                $(document).on('xfields/ready', '.xfields_add_field', function() {
                    init_editors(document);
                });

                // TinyMCE iframes can't be moved in the DOM: remove before sorting, restore after
                $(document).on('sortstart', '.meta-box-sortables, .xfields_loop', function(e, ui) {
                    save_all();
                    $(ui.item).find('textarea.xfields-wysiwyg-area.xfields-ready').each(function() {
                        wp.editor.remove(this.id);
                        $(this).removeClass('xfields-ready');
                    });
                });
                $(document).on('sortstop', '.meta-box-sortables, .xfields_loop', function(e, ui) {
                    init_editors(ui.item);
                });

                // Always submit the latest editor content
                $(document).on('submit', 'form', save_all);
            });
        })(jQuery);
        </script>
    <?php
    }


    // Raw value; html() escapes it
    function format_value_for_input( $value, $field = null ) {
        return is_scalar( $value ) ? (string) $value : '';
    }


    function format_value_for_api( $value, $field = null ) {
        $formatting = $this->get_option( $field, 'formatting', 'default' );
        // Core filter, intentionally applied (same output as post content)
        return ( 'none' == $formatting ) ? $value : apply_filters( 'the_content', $value ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
    }
}

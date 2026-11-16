<?php

defined( 'ABSPATH' ) || exit;

class cfs_file extends cfs_field
{

    function __construct() {
        $this->name = 'file';
        $this->label = __( 'File Upload', 'cfs' );
    }


    function html( $field ) {
        $field->value = is_scalar( $field->value ) ? (string) $field->value : '';
        $file_url = esc_html( $field->value );

        if ( '' !== $field->value && ctype_digit( $field->value ) ) {
            if ( wp_attachment_is_image( (int) $field->value ) ) {
                $src = wp_get_attachment_image_src( (int) $field->value );
                $file_url = $src ? '<img src="' . esc_url( $src[0] ) . '" />' : '';
            }
            else
            {
                $url = (string) wp_get_attachment_url( (int) $field->value );
                $filename = wp_basename( $url );
                $file_url = '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $filename ) . '</a>';
            }
        }

        $file_type = $this->get_option( $field, 'file_type', 'file' );

        // CSS logic for "Add" / "Remove" buttons
        $css = empty( $field->value ) ? [ '', ' hidden' ] : [ ' hidden', '' ];
    ?>
        <span class="file_url"><?php echo $file_url; ?></span>
        <input type="button" class="media button add<?php echo $css[0]; ?>" data-file-type="<?php echo esc_attr( $file_type ); ?>" value="<?php esc_attr_e( 'Add File', 'cfs' ); ?>" />
        <input type="button" class="media button remove<?php echo $css[1]; ?>" value="<?php esc_attr_e( 'Remove', 'cfs' ); ?>" />
        <input type="hidden" name="<?php echo esc_attr( $field->input_name ); ?>" class="file_value" value="<?php echo esc_attr( $field->value ); ?>" />
    <?php
    }


    function options_html( $key, $field ) {
    ?>
        <tr class="field_option field_option_<?php echo esc_attr( $this->name ); ?>">
            <td class="label">
                <label><?php _e( 'File Type', 'cfs' ); ?></label>
            </td>
            <td>
                <?php
                    CFS()->create_field( [
                        'type' => 'select',
                        'input_name' => "cfs[fields][$key][options][file_type]",
                        'options' => [
                            'choices' => [
                                'file'  => __( 'Any', 'cfs' ),
                                'image' => __( 'Image', 'cfs' ),
                                'audio' => __( 'Audio', 'cfs' ),
                                'video' => __( 'Video', 'cfs' )
                            ],
                            'force_single' => true,
                        ],
                        'value' => $this->get_option( $field, 'file_type', 'file' ),
                    ] );
                ?>
            </td>
        </tr>
        <tr class="field_option field_option_<?php echo esc_attr( $this->name ); ?>">
            <td class="label">
                <label><?php _e( 'Return Value', 'cfs' ); ?></label>
            </td>
            <td>
                <?php
                    CFS()->create_field( [
                        'type' => 'select',
                        'input_name' => "cfs[fields][$key][options][return_value]",
                        'options' => [
                            'choices' => [
                                'url' => __( 'File URL', 'cfs' ),
                                'id' => __( 'Attachment ID', 'cfs' )
                            ],
                            'force_single' => true,
                        ],
                        'value' => $this->get_option( $field, 'return_value', 'url' ),
                    ] );
                ?>
            </td>
        </tr>
        <tr class="field_option field_option_<?php echo esc_attr( $this->name ); ?>">
            <td class="label">
                <label><?php _e( 'Validation', 'cfs' ); ?></label>
            </td>
            <td>
                <?php
                    CFS()->create_field( [
                        'type' => 'true_false',
                        'input_name' => "cfs[fields][$key][options][required]",
                        'input_class' => 'true_false',
                        'value' => $this->get_option( $field, 'required' ),
                        'options' => [ 'message' => __( 'This is a required field', 'cfs' ) ],
                    ] );
                ?>
            </td>
        </tr>
    <?php
    }


    function input_head( $field = null ) {
        wp_enqueue_media();
    ?>
        <style>
        .cfs_frame .media-frame-menu {
            display: none;
        }

        .cfs_frame .media-frame-title,
        .cfs_frame .media-frame-router,
        .cfs_frame .media-frame-content,
        .cfs_frame .media-frame-toolbar {
            left: 0;
        }
        </style>

        <script>
        (function($) {
            $(function() {

                var cfs_frames = {};
                var $cfs_button = null;

                $(document).on('click', '.cfs_input .media.button.add', function(e) {
                    $cfs_button = $(this);

                    // One frame per file type (the "File Type" option was previously ignored)
                    var file_type = $cfs_button.attr('data-file-type') || 'file';
                    var library = ('file' === file_type) ? {} : { type: file_type };

                    if (cfs_frames[file_type]) {
                        cfs_frames[file_type].open();
                        return;
                    }

                    var frame = cfs_frames[file_type] = wp.media({
                        className: 'media-frame cfs_frame',
                        frame: 'post',
                        multiple: false,
                        library: library
                    });

                    frame.on('insert', function() {
                        var attachment = frame.state().get('selection').first().toJSON();
                        var $preview;
                        if ('image' == attachment.type && 'undefined' != typeof attachment.sizes) {
                            var file_url = attachment.sizes.full.url;
                            if ('undefined' != typeof attachment.sizes.thumbnail) {
                                file_url = attachment.sizes.thumbnail.url;
                            }
                            $preview = $('<img />').attr('src', file_url);
                        }
                        else {
                            $preview = $('<a target="_blank" rel="noopener noreferrer"></a>').attr('href', attachment.url).text(attachment.filename);
                        }
                        $cfs_button.hide();
                        $cfs_button.siblings('.media.button.remove').show();
                        $cfs_button.siblings('.file_value').val(attachment.id);
                        $cfs_button.siblings('.file_url').empty().append($preview);
                    });

                    frame.open();
                    frame.content.mode('upload');
                });

                $(document).on('click', '.cfs_input .media.button.remove', function() {
                    $(this).siblings('.file_url').html('');
                    $(this).siblings('.file_value').val('');
                    $(this).siblings('.media.button.add').show();
                    $(this).hide();
                });
            });
        })(jQuery);
        </script>
    <?php
    }


    function format_value_for_api( $value, $field = null ) {
        if ( is_int( $value ) || ( is_string( $value ) && '' !== $value && ctype_digit( $value ) ) ) {
            $return_value = $this->get_option( $field, 'return_value', 'url' );
            return ( 'id' == $return_value ) ? (int) $value : wp_get_attachment_url( $value );
        }
        return $value;
    }
}

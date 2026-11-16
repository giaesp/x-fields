<?php

defined( 'ABSPATH' ) || exit;

class xf_tab extends xf_field
{

    function __construct() {
        $this->name = 'tab';
        $this->label = __( 'Tab', 'x-fields' );
    }


    // Prevent tabs from inheriting the parent field HTML
    function html( $field ) {

    }


    // Prevent tabs from inheriting the parent options HTML
    function options_html( $key, $field ) {

    }


    // Tab handling javascript
    function input_head( $field = null ) {
    ?>
        <script>
        (function($) {
            $(document).on('click', '.xf-tab', function() {
                var tab = $(this).attr('rel'),
                    $context = $(this).parents('.xf_input');
                $context.find('.xf-tab').removeClass('active');
                $context.find('.xf-tab-content').removeClass('active');
                $(this).addClass('active');
                $context.find('.xf-tab-content-' + tab).addClass('active');
            });

            $(function() {
                $('.xf-tabs').each(function(){
                    $(this).find('.xf-tab:first').click();
                });
            });
        })(jQuery);
        </script>
    <?php
    }
}

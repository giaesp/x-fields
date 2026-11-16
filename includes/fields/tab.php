<?php

defined( 'ABSPATH' ) || exit;

class xfields_tab extends xfields_field
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
            $(document).on('click', '.xfields-tab', function() {
                var tab = $(this).attr('rel'),
                    $context = $(this).parents('.xfields_input');
                $context.find('.xfields-tab').removeClass('active');
                $context.find('.xfields-tab-content').removeClass('active');
                $(this).addClass('active');
                $context.find('.xfields-tab-content-' + tab).addClass('active');
            });

            $(function() {
                $('.xfields-tabs').each(function(){
                    $(this).find('.xfields-tab:first').click();
                });
            });
        })(jQuery);
        </script>
    <?php
    }
}

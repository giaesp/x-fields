<?php

defined( 'ABSPATH' ) || exit;

class xf_date extends xf_field
{

    function __construct() {
        $this->name = 'date';
        $this->label = __( 'Date', 'x-fields' );
    }


    function input_head( $field = null ) {
        $this->load_assets();
    ?>
        <link rel="stylesheet" type="text/css" href="<?php echo XF_URL; ?>/includes/fields/date/datepicker.css" />
        <script>
        (function($) {
            $(function() {
                $(document).on('xf/ready', '.xf_add_field', function() {
                    $('.xf_date:not(.ready)').init_date();
                });
                $('.xf_date').init_date();
            });

            $.fn.init_date = function() {
                this.each(function() {
                    //$(this).find('input.date').datetime();
                    $(this).find('input.date').datepicker({
                        format: 'yyyy-mm-dd',
                        todayHighlight: true,
                        autoclose: true,
                        clearBtn: true
                    });
                    $(this).addClass('ready');
                });
            };
        })(jQuery);
        </script>
    <?php
    }


    function load_assets() {
        wp_register_script( 'bootstrap-datepicker', XF_URL . '/includes/fields/date/bootstrap-datepicker.js', [ 'jquery' ] );
        wp_enqueue_script( 'bootstrap-datepicker' );
    }
}

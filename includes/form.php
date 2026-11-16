<?php

defined( 'ABSPATH' ) || exit;

class xf_form
{

    public $used_types;
    public $assets_loaded;
    public $session;


    public function __construct() {
        $this->used_types = [];
        $this->assets_loaded = false;

        add_action( 'init', [ $this, 'init' ], 100 );
        add_action( 'admin_head', [ $this, 'head_scripts' ] );
        add_action( 'admin_print_footer_scripts', [ $this, 'footer_scripts' ] );
        add_action( 'admin_notices', [ $this, 'admin_notice' ] );
    }


    /**
     * Initialize the session and save the form
     * @since 1.8.5
     */
    public function init() {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return;
        }

        if ( isset( $_POST['wp-preview'] ) && 'dopreview' == $_POST['wp-preview'] ) {
            return;
        }

        $this->session = new xf_session();

        // Save the form
        if ( isset( $_POST['xf']['save'] ) && is_string( $_POST['xf']['save'] ) ) {
            if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['xf']['save'] ) ), 'xf_save_input' ) ) {
                $session = $this->session->get();

                if ( empty( $session ) ) {
                    wp_die( esc_html__( 'Your session has expired.', 'x-fields' ), '', [ 'response' => 403 ] );
                }

                // Raw on purpose: save_fields() unslashes (stripslashes_deep) and sanitizes per field type / KSES
                $field_data = isset( $_POST['xf']['input'] ) && is_array( $_POST['xf']['input'] ) ? $_POST['xf']['input'] : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
                $post_data = [];
                $is_front_end = ! empty( $session['front_end'] );

                // Form settings are session-based for added security
                $post_id = isset( $session['post_id'] ) ? (int) $session['post_id'] : 0;

                // Admin (meta box) saves require the right to edit the post
                if ( ! $is_front_end && ( $post_id < 1 || ! current_user_can( 'edit_post', $post_id ) ) ) {
                    wp_die( esc_html__( 'Sorry, you are not allowed to edit this item.', 'x-fields' ), '', [ 'response' => 403 ] );
                }
                $field_groups = isset( $session['field_groups'] ) ? (array) $session['field_groups'] : [];

                // Sanitize field groups
                foreach ( $field_groups as $key => $val ) {
                    $field_groups[$key] = (int) $val;
                }

                // Title
                // (sanitized through the core *_save_pre filters, which apply KSES
                // for users without the unfiltered_html capability)
                if ( isset( $_POST['xf']['post_title'] ) && is_string( $_POST['xf']['post_title'] ) ) {
                    $post_data['post_title'] = sanitize_post_field( 'post_title', wp_unslash( $_POST['xf']['post_title'] ), $post_id, 'db' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize_post_field() applies title_save_pre / KSES
                }

                // Content
                if ( isset( $_POST['xf']['post_content'] ) && is_string( $_POST['xf']['post_content'] ) ) {
                    $post_data['post_content'] = sanitize_post_field( 'post_content', wp_unslash( $_POST['xf']['post_content'] ), $post_id, 'db' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize_post_field() applies content_save_pre / KSES

                    // Anonymous visitors must not be able to inject shortcodes
                    if ( ! is_user_logged_in() ) {
                        $post_data['post_content'] = str_replace( [ '[', ']' ], [ '&#91;', '&#93;' ], $post_data['post_content'] );
                    }
                }

                // New posts
                if ( $post_id < 1 ) {
                    // Post type
                    if ( isset( $session['post_type'] ) ) {
                        $post_data['post_type'] = $session['post_type'];
                    }

                    // Post status
                    if ( isset( $session['post_status'] ) ) {
                        $post_data['post_status'] = $session['post_status'];
                    }
                }
                else {
                    $post_data['ID'] = $post_id;
                }

                $options = [
                    'format'        => 'input',
                    'field_groups'  => $field_groups
                ];

                // Hook parameters
                $hook_params = [
                    'field_data'    => $field_data,
                    'post_data'     => $post_data,
                    'options'       => $options,
                ];

                // Server-side validation (the browser checks can be bypassed)
                $errors = XF()->api->validate_input( $field_data, $field_groups );

                if ( ! empty( $errors ) ) {
                    if ( $is_front_end ) {
                        // Public forms: never store invalid submissions
                        wp_die(
                            '<p>' . esc_html__( 'Please correct the following and try again:', 'x-fields' ) . '</p><ul><li>' . implode( '</li><li>', array_map( 'esc_html', $errors ) ) . '</li></ul>',
                            esc_html__( 'Form errors', 'x-fields' ),
                            [ 'response' => 400, 'back_link' => true ]
                        );
                    }

                    // Admin: never lose the editor's work; warn when publishing (drafts are free, as client-side)
                    $status = isset( $_POST['post_status'] ) ? sanitize_key( wp_unslash( $_POST['post_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above
                    $is_draft_save = isset( $_POST['save'] ) || in_array( $status, [ 'draft', 'pending', 'auto-draft' ], true ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

                    if ( ! $is_draft_save ) {
                        set_transient( 'xf_validation_' . get_current_user_id(), [ 'post_id' => $post_id, 'errors' => $errors ], 5 * MINUTE_IN_SECONDS );
                    }
                }

                // Pre-save hook
                do_action( 'xf_pre_save_input', $hook_params );

                // Save the input values
                $hook_params['post_data']['ID'] = XF()->save(
                    $field_data,
                    $post_data,
                    $options
                );

                // After-save hook
                do_action( 'xf_after_save_input', $hook_params );

                // Delete expired sessions
                $this->session->cleanup();

                // Redirect public forms
                if ( $is_front_end ) {
                    $redirect_url = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : home_url( '/' );
                    if ( ! empty( $session['confirmation_url'] ) ) {
                        $redirect_url = $session['confirmation_url'];
                    }

                    // Only same-site (or allowed_redirect_hosts) destinations: prevents open redirects
                    // such as //evil.example in REQUEST_URI
                    wp_safe_redirect( wp_validate_redirect( esc_url_raw( $redirect_url ), home_url( '/' ) ) );
                    exit;
                }
            }
        }
    }


    /**
     * Load form dependencies
     * @since 1.8.5
     */
    public function load_assets() {
        if ( $this->assets_loaded ) {
            return;
        }

        $this->assets_loaded = true;

        add_action( 'wp_head', [ $this, 'head_scripts' ] );
        add_action( 'wp_footer', [ $this, 'footer_scripts' ], 25 );

        wp_enqueue_script( 'jquery-ui-core' );
        wp_enqueue_script( 'jquery-ui-sortable' );
        wp_enqueue_script( 'xf-validation', XF_URL . '/assets/js/validation.js', [ 'jquery' ], XF_VERSION );
        wp_enqueue_script( 'jquery-powertip', XF_URL . '/assets/js/jquery-powertip/jquery.powertip.min.js', [ 'jquery' ], XF_VERSION );
        wp_enqueue_style( 'jquery-powertip', XF_URL . '/assets/js/jquery-powertip/jquery.powertip.css', [], XF_VERSION );
        wp_enqueue_style( 'xf-input', XF_URL . '/assets/css/input.css', [], XF_VERSION );
    }


    /**
     * Handle front-end validation
     * @since 1.8.8
     */
    function head_scripts() {
    ?>

<script>
var XF = XF || {};
XF['get_field_value'] = {};
XF['loop_buffer'] = [];
</script>

    <?php
    }


    /**
     * Allow for custom client-side validators
     * @since 1.9.5
     */
    function footer_scripts() {
        do_action( 'xf_custom_validation' );
    }


    /**
     * Add an admin notice to be displayed in the event of
     * validation errors
     * @since 2.6
     */
    function admin_notice() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        if ( ! isset( $screen->base ) || $screen->base !== 'post' ) {
            return;
        }

        // Server-side validation result from the last save
        $key = 'xf_validation_' . get_current_user_id();
        $result = get_transient( $key );
        if ( is_array( $result ) && ! empty( $result['errors'] ) && (int) get_the_ID() === (int) $result['post_id'] ) {
            delete_transient( $key );
            echo '<div class="notice notice-warning is-dismissible"><p><strong>' . esc_html__( 'Saved, but some fields need attention:', 'x-fields' ) . '</strong></p><ul style="list-style:disc;margin-left:20px">';
            foreach ( $result['errors'] as $error ) {
                echo '<li>' . esc_html( $error ) . '</li>';
            }
            echo '</ul></div>';
        }

        echo '<div class="notice notice-error" id="xf-validation-admin-notice" style="display: none;"><p><strong>';
        echo esc_html__( 'One (or more) of your fields had validation errors. More information is available below.', 'x-fields' );
        echo '</strong></p></div>';
    }


    /**
     * Render the HTML input form
     * @param array $params
     * @return string form HTML code
     * @since 1.8.5
     */
    public function render( $params ) {
        global $post;

        $defaults = [
            'post_id'               => false, // false = new entries
            'field_groups'          => [], // group IDs, required for new entries
            'post_title'            => false,
            'post_content'          => false,
            'post_status'           => 'draft',
            'post_type'             => 'post',
            'excluded_fields'       => [],
            'confirmation_message'  => '',
            'confirmation_url'      => '',
            'submit_label'          => __( 'Submit', 'x-fields' ),
            'front_end'             => true,
        ];

        $params = array_merge( $defaults, (array) $params );
        $input_fields = [];

        // The session isn't created during AJAX / autosave requests
        if ( ! ( $this->session instanceof xf_session ) ) {
            $this->session = new xf_session();
        }

        // Keep track of field validators
        XF()->validators = [];

        $post_id = (int) $params['post_id'];

        if ( 0 < $post_id ) {
            $post = get_post( $post_id );
        }

        if ( empty( $params['field_groups'] ) ) {
            $field_groups = XF()->api->get_matching_groups( $post_id, true );
            $field_groups = array_keys( $field_groups );
        }
        else {
            $field_groups = $params['field_groups'];
        }

        if ( ! empty( $field_groups ) ) {
            $input_fields = XF()->api->get_input_fields( [
                'group_id' => $field_groups,
                'post_id_values' => $post_id,
            ] );
        }

        // Hook to allow for overridden field settings
        $input_fields = apply_filters( 'xf_pre_render_fields', $input_fields, $params );

        // The SESSION should contain all applicable field group IDs. Since add_meta_box only
        // passes 1 field group at a time, we use XF()->group_ids from admin_head.php
        // to store all group IDs needed for the SESSION.
        $all_group_ids = ( false === $params['front_end'] ) ? XF()->group_ids : $field_groups;
        $all_group_ids = array_map( 'intval', (array) $all_group_ids );

        $session_data = [
            'post_id'               => $post_id,
            'post_type'             => $params['post_type'],
            'post_status'           => $params['post_status'],
            'field_groups'          => $all_group_ids,
            'confirmation_message'  => $params['confirmation_message'],
            'confirmation_url'      => $params['confirmation_url'],
            'front_end'             => $params['front_end'],
        ];

        // Set the SESSION
        $this->session->set( $session_data );

        if ( false !== $params['front_end'] ) {
    ?>

<div class="xf_input no_box">
    <form id="post" method="post" action="">

    <?php
        }

        if ( false !== $params['post_title'] ) {
    ?>

        <div class="field" data-validator="required">
            <label><?php echo esc_html( $params['post_title'] ); ?></label>
            <input type="text" name="xf[post_title]" value="<?php echo empty( $post_id ) ? '' : esc_attr( $post->post_title ); ?>" />
        </div>

    <?php
        }

        if ( false !== $params['post_content'] ) {
    ?>

        <div class="field">
            <label><?php echo esc_html( $params['post_content'] ); ?></label>
            <textarea name="xf[post_content]"><?php echo empty( $post_id ) ? '' : esc_textarea( $post->post_content ); ?></textarea>
        </div>

    <?php
        }

        // Detect tabs
        $tabs = [];
        $is_first_tab = true;
        foreach ( $input_fields as $key => $field ) {
            if ( 'tab' == $field->type ) {
                $tabs[] = $field;
            }
        }

        do_action( 'xf_form_before_fields', $params, [
            'group_ids'     => $all_group_ids,
            'input_fields'  => $input_fields
        ] );

        // Add any necessary head scripts
        foreach ( $input_fields as $key => $field ) {

            // Exclude fields
            if ( in_array( $field->name, (array) $params['excluded_fields'] ) ) {
                continue;
            }

            // Skip missing field types
            if ( ! isset( XF()->fields[ $field->type ] ) ) {
                continue;
            }

            // Output tabs
            if ( 'tab' == $field->type && $is_first_tab ) {
                echo '<div class="xf-tabs">';
                foreach ( $tabs as $key => $tab ) {
                    echo '<div class="xf-tab" rel="' . esc_attr( $tab->name ) . '">' . esc_html( $tab->label ) . '</div>';
                }
                echo '</div>';
                $is_first_tab = false;
            }

            // Keep track of active field types
            if ( ! isset( $this->used_types[ $field->type ] ) ) {
                XF()->fields[ $field->type ]->input_head( $field );
                $this->used_types[ $field->type ] = true;
            }

            $validator = '';

            if ( in_array( $field->type, [ 'relationship', 'term', 'user', 'loop' ] ) ) {
                $min = empty( $field->options['limit_min'] ) ? 0 : (int) $field->options['limit_min'];
                $max = empty( $field->options['limit_max'] ) ? 0 : (int) $field->options['limit_max'];
                $validator = "limit|$min,$max";
            }

            if ( isset( $field->options['required'] ) && 0 < (int) $field->options['required'] ) {
                if ( 'date' == $field->type ) {
                    $validator = 'valid_date';
                }
                elseif ( 'color' == $field->type ) {
                    $validator = 'valid_color';
                }
                else {
                    $validator = 'required';
                }
            }

            if ( ! empty( $validator ) ) {
                XF()->validators[ $field->name ] = [
                    'rule'  => $validator,
                    'type'  => $field->type
                ];
            }

            // Ignore sub-fields
            if ( 1 > (int) $field->parent_id ) {

                // Tab handling
                if ( 'tab' == $field->type ) {

                    // Close the previous tab
                    if ( isset( $tabs[0] ) && $field->name != $tabs[0]->name ) {
                        echo '</div>';
                    }
                    echo '<div class="xf-tab-content xf-tab-content-' . esc_attr( $field->name ) . '">';

					if ( ! empty( $field->notes ) ) {
						echo '<div class="xf-tab-notes">' . esc_html( $field->notes ) . '</div>';
					}
                }
                else {
    ?>

        <div class="field field-<?php echo esc_attr( $field->name ); ?>" data-type="<?php echo esc_attr( $field->type ); ?>" data-name="<?php echo esc_attr( $field->name ); ?>">
            <?php if ( 'loop' == $field->type ) : ?>
            <a href="javascript:;" class="xf_loop_toggle" title="<?php esc_html_e( 'Toggle row visibility', 'x-fields' ); ?>"></a>
            <?php endif; ?>

            <?php if ( ! empty( $field->label ) ) : ?>
            <label><?php echo esc_html( $field->label ); ?></label>
            <?php endif; ?>

            <?php if ( ! empty( $field->notes ) ) : ?>
            <p class="notes"><?php echo esc_html( $field->notes ); ?></p>
            <?php endif; ?>

            <div class="xf_<?php echo esc_attr( $field->type ); ?>">

    <?php
                XF()->create_field( [
                    'id'            => $field->id,
                    'group_id'      => $field->group_id,
                    'type'          => $field->type,
                    'input_name'    => "xf[input][$field->id][value]",
                    'input_class'   => $field->type,
                    'options'       => $field->options,
                    'value'         => $field->value,
                    'notes'         => $field->notes,
                ] );
    ?>

            </div>
        </div>

    <?php
                }
            }
        }

        // Make sure to close tabs
        if ( ! empty( $tabs ) ) {
            echo '</div>';
        }

        do_action( 'xf_form_after_fields', $params, [
            'group_ids'     => $all_group_ids,
            'input_fields'  => $input_fields
        ] );
    ?>

        <script>
        (function($) {
            XF.field_rules = XF.field_rules || {};
            $.extend( XF.field_rules, <?php echo wp_json_encode( XF()->validators, JSON_HEX_TAG | JSON_HEX_AMP ); ?> );
        })(jQuery);
        </script>
        <input type="hidden" name="xf[save]" value="<?php echo esc_attr( wp_create_nonce( 'xf_save_input' ) ); ?>" />
        <input type="hidden" name="xf[session_id]" value="<?php echo esc_attr( $this->session->session_id ); ?>" />

        <?php if ( false !== $params['front_end'] ) : ?>

        <input type="submit" value="<?php echo esc_attr( $params['submit_label'] ); ?>" />
    </form>
</div>

    <?php
        endif;
    }
}

XF()->form = new xf_form();

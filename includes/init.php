<?php

defined( 'ABSPATH' ) || exit;


class xfields_init
{

    function __construct() {
        add_action( 'init', [ $this, 'init' ] );
    }


    function init() {

        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            $this->load_textdomain();
        }

        add_action( 'admin_head',                       [ $this, 'admin_head' ] );
        add_action( 'admin_enqueue_scripts',            [ $this, 'admin_enqueue_scripts' ] );
        $this->register_assets();
        add_action( 'admin_menu',                       [ $this, 'admin_menu' ] );
        add_action( 'save_post',                        [ $this, 'save_post' ] );
        add_action( 'delete_post',                      [ $this, 'delete_post' ] );
        add_action( 'add_meta_boxes',                   [ $this, 'add_meta_boxes' ] );
        add_action( 'xfields_cleanup_sessions',             [ $this, 'cleanup_sessions' ] );
        if ( ! wp_next_scheduled( 'xfields_cleanup_sessions' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'xfields_cleanup_sessions' );
        }
        add_action( 'wp_ajax_xfields_ajax_handler',         [ $this, 'ajax_handler' ] );
        add_filter( 'manage_xfields_group_posts_columns',         [ $this, 'xfields_columns' ] );
        add_action( 'manage_xfields_group_posts_custom_column',   [ $this, 'xfields_column_content' ], 10, 2 );

        include( XFIELDS_DIR . '/includes/api.php' );
        xfields_migration::maybe_migrate_v1();
        include_once( XFIELDS_DIR . '/includes/upgrade.php' );
        xfields_migration::maybe_migrate();
        include( XFIELDS_DIR . '/includes/field.php' );
        include( XFIELDS_DIR . '/includes/field_group.php' );
        include( XFIELDS_DIR . '/includes/session.php' );
        include( XFIELDS_DIR . '/includes/form.php' );
        include( XFIELDS_DIR . '/includes/third_party.php' );
        include( XFIELDS_DIR . '/includes/revision.php' );


        $this->register_post_type();
        XFields()->fields = $this->get_field_types();

        // XFields is ready
        do_action( 'xfields_init' );
    }


    /**
     * Register the field group post type
     */
    function register_post_type() {
        register_post_type( 'xfields_group', [
            'public'            => false,
            'show_ui'           => true,
            'show_in_menu'      => 'options-general.php',
            // Field groups are site configuration: administrators only (filterable)
            'capabilities'      => xfields_group_capabilities(),
            'map_meta_cap'      => false,
            'hierarchical'      => false,
            'supports'          => [ 'title' ],
            'query_var'         => false,
            'labels'            => [
                'name'                  => __( 'Field Groups', 'x-fields' ),
                'singular_name'         => __( 'Field Group', 'x-fields' ),
                'all_items'             => __( 'X-Fields', 'x-fields' ),
                'add_new_item'          => __( 'Add New Field Group', 'x-fields' ),
                'edit_item'             => __( 'Edit Field Group', 'x-fields' ),
                'new_item'              => __( 'New Field Group', 'x-fields' ),
                'view_item'             => __( 'View Field Group', 'x-fields' ),
                'search_items'          => __( 'Search Field Groups', 'x-fields' ),
                'not_found'             => __( 'No Field Groups found', 'x-fields' ),
                'not_found_in_trash'    => __( 'No Field Groups found in Trash', 'x-fields' ),
            ],
        ] );
    }


    function load_textdomain() {
        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter
        $locale = apply_filters( 'plugin_locale', get_locale(), 'x-fields' );
        // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        $mofile = WP_LANG_DIR . '/x-fields/x-fields-' . $locale . '.mo';

        if ( file_exists( $mofile ) ) {
            load_textdomain( 'x-fields', $mofile );
        }
        else {
            // Loads the translations bundled in /languages (installs from GitHub); on WordPress.org
            // language packs from translate.wordpress.org take precedence automatically.
            // phpcs:disable PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
            load_plugin_textdomain( 'x-fields', false, dirname( plugin_basename( XFIELDS_FILE ) ) . '/languages' );
            // phpcs:enable PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
        }
    }


    /**
     * Register field types
     */
    function get_field_types() {

        // support custom field types
        $field_types = apply_filters( 'xfields_field_types', [
            'text'          => XFIELDS_DIR . '/includes/fields/text.php',
            'textarea'      => XFIELDS_DIR . '/includes/fields/textarea.php',
            'wysiwyg'       => XFIELDS_DIR . '/includes/fields/wysiwyg.php',
            'hyperlink'     => XFIELDS_DIR . '/includes/fields/hyperlink.php',
            'date'          => XFIELDS_DIR . '/includes/fields/date/date.php',
            'color'         => XFIELDS_DIR . '/includes/fields/color/color.php',
            'true_false'    => XFIELDS_DIR . '/includes/fields/true_false.php',
            'select'        => XFIELDS_DIR . '/includes/fields/select.php',
            'relationship'  => XFIELDS_DIR . '/includes/fields/relationship.php',
            'term'          => XFIELDS_DIR . '/includes/fields/term.php',
            'user'          => XFIELDS_DIR . '/includes/fields/user.php',
            'file'          => XFIELDS_DIR . '/includes/fields/file.php',
            'loop'          => XFIELDS_DIR . '/includes/fields/loop.php',
            'tab'           => XFIELDS_DIR . '/includes/fields/tab.php',
        ] );

        foreach ( $field_types as $type => $path ) {
            $class_name = 'xfields_' . $type;

            // allow for multiple classes per file
            if ( ! class_exists( $class_name ) ) {
                include_once( $path );
            }

            $field_types[ $type ] = new $class_name();
        }

        return $field_types;
    }


    /**
     * Register all bundled assets once (versioned by plugin version)
     */
    function register_assets() {
        $url = XFIELDS_URL;
        $ver = XFIELDS_VERSION;
        $min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

        // Tom Select 2.6.2 (Apache-2.0) - searchable / multi selects
        wp_register_script( 'tom-select', "$url/assets/vendor/tom-select/tom-select.complete$min.js", [], '2.6.2', true );
        wp_register_style( 'tom-select', "$url/assets/vendor/tom-select/tom-select$min.css", [], '2.6.2' );

        wp_register_script( 'xfields-fields', "$url/assets/js/fields.js", [ 'jquery', 'jquery-ui-sortable', 'tom-select' ], $ver, true );
        wp_register_style( 'xfields-fields', "$url/assets/css/fields.css", [ 'tom-select' ], $ver );

        wp_register_script( 'xfields-validation', "$url/assets/js/validation.js", [ 'jquery' ], $ver, true );
        wp_register_style( 'xfields-input', "$url/assets/css/input.css", [ 'tom-select' ], $ver );
    }


    /**
     * Field group editor assets
     */
    function admin_enqueue_scripts( $hook ) {
        $screen = get_current_screen();

        if ( in_array( $hook, [ 'post.php', 'post-new.php' ], true ) && is_object( $screen ) && 'xfields_group' === $screen->post_type ) {
            wp_enqueue_script( 'xfields-fields' );
            wp_enqueue_style( 'xfields-fields' );
        }
    }


    /**
     * admin_head
     */
    function admin_head() {
        $screen = get_current_screen();

        if ( is_object( $screen ) && 'post' == $screen->base ) {
            include( XFIELDS_DIR . '/templates/admin_head.php' );
        }
    }



    /**
    * admin_menu
    */
    function admin_menu() {
        if ( false === apply_filters( 'xfields_disable_admin', false ) ) {
            add_submenu_page( 'tools.php', __( 'X-Fields Tools', 'x-fields' ), __( 'X-Fields Tools', 'x-fields' ), xfields_manage_capability(), 'xfields-tools', [ $this, 'page_tools' ] );
        }
    }

    /**
     * add_meta_boxes
     */
    function add_meta_boxes() {
        add_meta_box( 'xfields_fields', __('Fields', 'x-fields' ), [ $this, 'meta_box' ], 'xfields_group', 'normal', 'high', [ 'box' => 'fields' ] );
        add_meta_box( 'xfields_rules', __('Placement Rules', 'x-fields' ), [ $this, 'meta_box' ], 'xfields_group', 'normal', 'high', [ 'box' => 'rules' ] );
        add_meta_box( 'xfields_extras', __('Extras', 'x-fields' ), [ $this, 'meta_box' ], 'xfields_group', 'normal', 'high', [ 'box' => 'extras' ] );
    }


    /**
     * meta_box
     * @param object $post
     * @param array $metabox
     */
    function meta_box( $post, $metabox ) {
        $box = isset( $metabox['args']['box'] ) ? $metabox['args']['box'] : '';

        if ( in_array( $box, [ 'fields', 'rules', 'extras', 'input' ], true ) ) {
            include( XFIELDS_DIR . "/templates/meta_box_$box.php" );
        }
    }


    /**
     * page_tools
     */
    function page_tools() {
        include( XFIELDS_DIR . '/templates/page_tools.php' );
    }


    /**
     * save_post
     */
    function save_post( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! isset( $_POST['xfields']['save'] ) || ! is_string( $_POST['xfields']['save'] ) ) {
            return;
        }

        if ( false !== wp_is_post_revision( $post_id ) ) {
            return;
        }

        // Only field groups are handled here
        if ( 'xfields_group' !== get_post_type( $post_id ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['xfields']['save'] ) ), 'xfields_save_fields' ) ) {
            // Raw on purpose: xfields_field_group::save() unslashes (stripslashes_deep) and sanitizes every key
            // phpcs:disable WordPress.Security.ValidatedSanitizedInput
            $fields = isset( $_POST['xfields']['fields'] ) && is_array( $_POST['xfields']['fields'] ) ? $_POST['xfields']['fields'] : [];
            $rules = isset( $_POST['xfields']['rules'] ) && is_array( $_POST['xfields']['rules'] ) ? $_POST['xfields']['rules'] : [];
            $extras = isset( $_POST['xfields']['extras'] ) && is_array( $_POST['xfields']['extras'] ) ? $_POST['xfields']['extras'] : [];
            // phpcs:enable WordPress.Security.ValidatedSanitizedInput

            XFields()->field_group->save( [
                'post_id'   => $post_id,
                'fields'    => $fields,
                'rules'     => $rules,
                'extras'    => $extras,
            ] );
        }
    }


    /**
     * delete_post
     * @return boolean
     */
    function delete_post( $post_id ) {
        global $wpdb;

        if ( 'xfields_group' != get_post_type( $post_id ) ) {
            $wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE post_id = %d", $wpdb->prefix . "xfields_values", $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- own table, no API
        }

        return true;
    }


    /**
     * ajax_handler
     */
    function ajax_handler() {
        if ( ! current_user_can( xfields_manage_capability() ) ) {
            wp_die( '', '', [ 'response' => 403 ] );
        }

        if ( ! check_ajax_referer( 'xfields_admin_nonce', 'nonce', false ) ) {
            exit;
        }

        $ajax_method = isset( $_POST['action_type'] ) && is_string( $_POST['action_type'] ) ? sanitize_key( wp_unslash( $_POST['action_type'] ) ) : false;

        if ( $ajax_method && is_admin() ) {
            include_once( XFIELDS_DIR . '/includes/ajax.php' );
            $ajax = new xfields_ajax();

            if ( 'import' == $ajax_method ) {
                // JSON is decoded and every value is validated/sanitized by xfields_field_group::import()
                $import_code = isset( $_POST['import_code'] ) && is_string( $_POST['import_code'] ) ? wp_unslash( $_POST['import_code'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $options = [
                    'import_code' => json_decode( $import_code, true ),
                ];
                echo wp_kses_post( XFields()->field_group->import( $options ) );
            }
            elseif ( 'export' == $ajax_method ) {
                echo wp_json_encode( XFields()->field_group->export( $_POST ) );
            }
            elseif ( 'reset' == $ajax_method ) {
                $ajax->reset();
                // __FILE__ pointed to includes/init.php, so the plugin was never deactivated
                deactivate_plugins( plugin_basename( XFIELDS_FILE ) );
                echo esc_url_raw( admin_url( 'plugins.php' ) );
            }
            elseif ( 'search_posts' == $ajax_method ) {
                // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON response (wp_json_encode)
                echo $ajax->search_posts( $_POST );
                // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
            }
        }

        exit;
    }


    /**
     * Daily purge of expired form sessions
     */
    function cleanup_sessions() {
        ( new xfields_session() )->cleanup();
    }


    /**
     * Customize table columns on the Field Group listing
     */
    function xfields_columns() {
        return [
            'cb'            => '<input type="checkbox" />',
            'title'         => __( 'Title', 'x-fields' ),
            'placement'     => __( 'Placement', 'x-fields' ),
        ];
    }


    /**
     * Populate the "Placement" column on the Field Group listing
     */
    function xfields_column_content( $column_name, $post_id ) {
        if ( 'placement' == $column_name ) {
            global $wpdb;

            $labels = [
                'post_types'        => __( 'Post Types', 'x-fields' ),
                'user_roles'        => __( 'User Roles', 'x-fields' ),
                'post_ids'          => __( 'Posts', 'x-fields' ),
                'term_ids'          => __( 'Term IDs', 'x-fields' ),
                'page_templates'    => __( 'Page Templates', 'x-fields' ),
                'post_formats'      => __( 'Post Formats', 'x-fields' )
            ];

            $field_groups = XFields()->field_group->load_field_groups();

            // Make sure the field group exists
            $rules = [];
            if ( isset( $field_groups[ $post_id ] ) ) {
                $rules = $field_groups[ $post_id ]['rules'];
            }

            foreach ( (array) $rules as $criteria => $data ) {
                $label = isset( $labels[ $criteria ] ) ? $labels[ $criteria ] : $criteria;
                $values = isset( $data['values'] ) ? (array) $data['values'] : [];
                $op = isset( $data['operator'] ) ? $data['operator'] : '==';
                $op = is_array( $op ) ? reset( $op ) : $op;
                $operator = ( '==' == $op ) ? '=' : '!=';

                // Get post titles
                if ( 'post_ids' == $criteria ) {
                    $temp = [];
                    foreach ( $values as $val ) {
                        $temp[] = get_the_title( (int) $val );
                    }
                    $values = $temp;
                }

                echo '<div><strong>' . esc_html( $label ) . '</strong> ' . esc_html( $operator ) . ' ' . esc_html( implode( ', ', $values ) ) . '</div>';
            }
        }
    }
}

new xfields_init();

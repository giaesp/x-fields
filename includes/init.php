<?php

defined( 'ABSPATH' ) || exit;

class xf_init
{

    function __construct() {
        add_action( 'init', [ $this, 'init' ] );
    }


    function init() {

        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            $this->load_textdomain();
        }

        add_action( 'admin_head',                       [ $this, 'admin_head' ] );
        add_action( 'admin_menu',                       [ $this, 'admin_menu' ] );
        add_action( 'save_post',                        [ $this, 'save_post' ] );
        add_action( 'delete_post',                      [ $this, 'delete_post' ] );
        add_action( 'add_meta_boxes',                   [ $this, 'add_meta_boxes' ] );
        add_action( 'xf_cleanup_sessions',             [ $this, 'cleanup_sessions' ] );
        if ( ! wp_next_scheduled( 'xf_cleanup_sessions' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'xf_cleanup_sessions' );
        }
        add_action( 'wp_ajax_xf_ajax_handler',         [ $this, 'ajax_handler' ] );
        add_filter( 'manage_xf_group_posts_columns',         [ $this, 'xf_columns' ] );
        add_action( 'manage_xf_group_posts_custom_column',   [ $this, 'xf_column_content' ], 10, 2 );

        include( XF_DIR . '/includes/api.php' );
        include_once( XF_DIR . '/includes/upgrade.php' );
        xf_migration::maybe_migrate();
        include( XF_DIR . '/includes/field.php' );
        include( XF_DIR . '/includes/field_group.php' );
        include( XF_DIR . '/includes/session.php' );
        include( XF_DIR . '/includes/form.php' );
        include( XF_DIR . '/includes/third_party.php' );
        include( XF_DIR . '/includes/revision.php' );


        $this->register_post_type();
        XF()->fields = $this->get_field_types();

        // XF is ready
        do_action( 'xf_init' );
    }


    /**
     * Register the field group post type
     */
    function register_post_type() {
        register_post_type( 'xf_group', [
            'public'            => false,
            'show_ui'           => true,
            'show_in_menu'      => 'options-general.php',
            // Field groups are site configuration: administrators only (filterable)
            'capabilities'      => xf_group_capabilities(),
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
        $locale = apply_filters( 'plugin_locale', get_locale(), 'x-fields' );
        $mofile = WP_LANG_DIR . '/x-fields/x-fields-' . $locale . '.mo';

        if ( file_exists( $mofile ) ) {
            load_textdomain( 'x-fields', $mofile );
        }
        else {
            load_plugin_textdomain( 'x-fields', false, dirname( plugin_basename( XF_FILE ) ) . '/languages' );
        }
    }


    /**
     * Register field types
     */
    function get_field_types() {

        // support custom field types
        $field_types = apply_filters( 'xf_field_types', [
            'text'          => XF_DIR . '/includes/fields/text.php',
            'textarea'      => XF_DIR . '/includes/fields/textarea.php',
            'wysiwyg'       => XF_DIR . '/includes/fields/wysiwyg.php',
            'hyperlink'     => XF_DIR . '/includes/fields/hyperlink.php',
            'date'          => XF_DIR . '/includes/fields/date/date.php',
            'color'         => XF_DIR . '/includes/fields/color/color.php',
            'true_false'    => XF_DIR . '/includes/fields/true_false.php',
            'select'        => XF_DIR . '/includes/fields/select.php',
            'relationship'  => XF_DIR . '/includes/fields/relationship.php',
            'term'          => XF_DIR . '/includes/fields/term.php',
            'user'          => XF_DIR . '/includes/fields/user.php',
            'file'          => XF_DIR . '/includes/fields/file.php',
            'loop'          => XF_DIR . '/includes/fields/loop.php',
            'tab'           => XF_DIR . '/includes/fields/tab.php',
        ] );

        foreach ( $field_types as $type => $path ) {
            $class_name = 'xf_' . $type;

            // allow for multiple classes per file
            if ( ! class_exists( $class_name ) ) {
                include_once( $path );
            }

            $field_types[ $type ] = new $class_name();
        }

        return $field_types;
    }


    /**
     * admin_head
     */
    function admin_head() {
        $screen = get_current_screen();

        if ( is_object( $screen ) && 'post' == $screen->base ) {
            include( XF_DIR . '/templates/admin_head.php' );
        }
    }



    /**
    * admin_menu
    */
    function admin_menu() {
        if ( false === apply_filters( 'xf_disable_admin', false ) ) {
            add_submenu_page( 'tools.php', __( 'X-Fields Tools', 'x-fields' ), __( 'X-Fields Tools', 'x-fields' ), xf_manage_capability(), 'xf-tools', [ $this, 'page_tools' ] );
        }
    }

    /**
     * add_meta_boxes
     */
    function add_meta_boxes() {
        add_meta_box( 'xf_fields', __('Fields', 'x-fields' ), [ $this, 'meta_box' ], 'xf_group', 'normal', 'high', [ 'box' => 'fields' ] );
        add_meta_box( 'xf_rules', __('Placement Rules', 'x-fields' ), [ $this, 'meta_box' ], 'xf_group', 'normal', 'high', [ 'box' => 'rules' ] );
        add_meta_box( 'xf_extras', __('Extras', 'x-fields' ), [ $this, 'meta_box' ], 'xf_group', 'normal', 'high', [ 'box' => 'extras' ] );
    }


    /**
     * meta_box
     * @param object $post
     * @param array $metabox
     */
    function meta_box( $post, $metabox ) {
        $box = isset( $metabox['args']['box'] ) ? $metabox['args']['box'] : '';

        if ( in_array( $box, [ 'fields', 'rules', 'extras', 'input' ], true ) ) {
            include( XF_DIR . "/templates/meta_box_$box.php" );
        }
    }


    /**
     * page_tools
     */
    function page_tools() {
        include( XF_DIR . '/templates/page_tools.php' );
    }


    /**
     * save_post
     */
    function save_post( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! isset( $_POST['xf']['save'] ) || ! is_string( $_POST['xf']['save'] ) ) {
            return;
        }

        if ( false !== wp_is_post_revision( $post_id ) ) {
            return;
        }

        // Only field groups are handled here
        if ( 'xf_group' !== get_post_type( $post_id ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( wp_verify_nonce( $_POST['xf']['save'], 'xf_save_fields' ) ) {
            $fields = isset( $_POST['xf']['fields'] ) && is_array( $_POST['xf']['fields'] ) ? $_POST['xf']['fields'] : [];
            $rules = isset( $_POST['xf']['rules'] ) && is_array( $_POST['xf']['rules'] ) ? $_POST['xf']['rules'] : [];
            $extras = isset( $_POST['xf']['extras'] ) && is_array( $_POST['xf']['extras'] ) ? $_POST['xf']['extras'] : [];

            XF()->field_group->save( [
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

        if ( 'xf_group' != get_post_type( $post_id ) ) {
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}xf_values WHERE post_id = %d", $post_id ) );
        }

        return true;
    }


    /**
     * ajax_handler
     */
    function ajax_handler() {
        if ( ! current_user_can( xf_manage_capability() ) ) {
            wp_die( '', '', [ 'response' => 403 ] );
        }

        if ( ! check_ajax_referer( 'xf_admin_nonce', 'nonce', false ) ) {
            exit;
        }

        $ajax_method = isset( $_POST['action_type'] ) && is_string( $_POST['action_type'] ) ? $_POST['action_type'] : false;

        if ( $ajax_method && is_admin() ) {
            include_once( XF_DIR . '/includes/ajax.php' );
            $ajax = new xf_ajax();

            if ( 'import' == $ajax_method ) {
                $import_code = isset( $_POST['import_code'] ) && is_string( $_POST['import_code'] ) ? wp_unslash( $_POST['import_code'] ) : '';
                $options = [
                    'import_code' => json_decode( $import_code, true ),
                ];
                echo XF()->field_group->import( $options );
            }
            elseif ( 'export' == $ajax_method ) {
                echo wp_json_encode( XF()->field_group->export( $_POST ) );
            }
            elseif ( 'reset' == $ajax_method ) {
                $ajax->reset();
                // __FILE__ pointed to includes/init.php, so the plugin was never deactivated
                deactivate_plugins( plugin_basename( XF_DIR . '/xf.php' ) );
                echo esc_url_raw( admin_url( 'plugins.php' ) );
            }
            elseif ( 'search_posts' == $ajax_method ) {
                echo $ajax->search_posts( $_POST );
            }
        }

        exit;
    }


    /**
     * Daily purge of expired form sessions
     */
    function cleanup_sessions() {
        ( new xf_session() )->cleanup();
    }


    /**
     * Customize table columns on the Field Group listing
     */
    function xf_columns() {
        return [
            'cb'            => '<input type="checkbox" />',
            'title'         => __( 'Title', 'x-fields' ),
            'placement'     => __( 'Placement', 'x-fields' ),
        ];
    }


    /**
     * Populate the "Placement" column on the Field Group listing
     */
    function xf_column_content( $column_name, $post_id ) {
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

            $field_groups = XF()->field_group->load_field_groups();

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

                echo '<div><strong>' . esc_html( $label ) . '</strong> ' . $operator . ' ' . esc_html( implode( ', ', $values ) ) . '</div>';
            }
        }
    }
}

new xf_init();

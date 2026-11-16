<?php

defined( 'ABSPATH' ) || exit;

class xf_third_party
{

    public function __construct() {

        // Post Type Switcher - http://wordpress.org/plugins/post-type-switcher/
        add_filter( 'pts_post_type_filter', [ $this, 'pts_post_type_filter' ] );

        // WPML - http://wpml.org/
        add_action( 'icl_make_duplicate', [ $this, 'wpml_handler' ], 10, 4 );

        // Polylang - https://wordpress.org/plugins/polylang/
        add_filter( 'pll_get_post_types', [ $this, 'pll_get_post_types' ], 10, 2 );
        add_filter( 'pll_copy_post_metas', [ $this, 'pll_copy_post_metas' ], 10, 3 );
        add_action( 'add_meta_boxes', [ $this, 'pll_prefill_translation' ], 1, 2 );

        // Duplicate Post - http://wordpress.org/plugins/duplicate-post/
        add_action( 'dp_duplicate_post', [ $this, 'duplicate_post' ], 100, 2 );
        add_action( 'dp_duplicate_page', [ $this, 'duplicate_post' ], 100, 2 );
    }


    /**
     * WPML support
     *
     * Properly copy XF fields on WPML post duplication (requires WPML 2.6+)
     *
     * @param int $master_id
     * @param string $lang
     * @param array $post_data
     * @param int $duplicate_id
     * @since 1.6.8
     */
    function wpml_handler( $master_id, $lang, $post_data, $duplicate_id ) {
        $field_data = XF()->get( false, $master_id, [ 'format' => 'raw' ] );

        if ( ! empty( $field_data ) ) {
            XF()->save( $field_data, [ 'ID' => $duplicate_id ] );
        }
    }


    /**
     * Polylang: field groups are configuration, never translated
     * @param array $post_types
     * @param bool $is_settings
     * @return array
     * @since 1.0.0
     */
    function pll_get_post_types( $post_types, $is_settings ) {
        unset( $post_types['xf_group'] );
        return $post_types;
    }


    /**
     * Polylang: don't let Polylang copy/sync X-Fields values as plain postmeta.
     * Without the matching rows in xf_values they would be orphaned (and invisible).
     * X-Fields copies them itself when a translation is created (see below).
     * @param array $keys
     * @param bool $sync
     * @param int $from
     * @return array
     * @since 1.0.0
     */
    function pll_copy_post_metas( $keys, $sync = false, $from = 0 ) {
        $names = wp_list_pluck( XF()->api->find_input_fields(), 'name' );
        return array_values( array_diff( (array) $keys, $names ) );
    }


    /**
     * Polylang: when a new translation is created (post-new.php?from_post=X&new_lang=Y),
     * copy the X-Fields values of the source post so editors only have to translate them.
     * @param string $post_type
     * @param WP_Post $post
     * @since 1.0.0
     */
    function pll_prefill_translation( $post_type, $post = null ) {
        if ( ! function_exists( 'pll_get_post_language' ) || ! ( $post instanceof WP_Post ) ) {
            return;
        }

        // Read-only prefill of a brand-new auto-draft; protected by edit_post capability checks below
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if ( empty( $_GET['from_post'] ) || empty( $_GET['new_lang'] ) || 'auto-draft' !== $post->post_status ) {
            return;
        }

        $from = absint( $_GET['from_post'] );
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if ( ! $from || ! current_user_can( 'edit_post', $from ) || ! current_user_can( 'edit_post', $post->ID ) ) {
            return;
        }

        // Only once, and never over existing data
        if ( ! empty( XF()->get( false, $post->ID, [ 'format' => 'raw' ] ) ) ) {
            return;
        }

        $field_data = XF()->get( false, $from, [ 'format' => 'raw' ] );

        if ( ! empty( $field_data ) ) {
            XF()->save( $field_data, [ 'ID' => $post->ID ] );
        }
    }


    /**
     * Post Type Switcher support
     * @param array $args
     * @return array
     * @since 1.8.1
     */
    function pts_post_type_filter( $args ) {
        if ( function_exists( 'get_current_screen' ) ) {
            $screen = get_current_screen();

            // Was $current_screen (undefined), so this never matched
            if ( isset( $screen->id ) && 'xf_group' == $screen->id ) {
                $args = [ 'public' => false, 'show_ui' => true ];
            }
        }

        return $args;
    }


    /**
     * Duplicate Post support
     * @param int $new_post_id
     * @param object $post
     * @since 2.0.0
     */
    function duplicate_post($new_post_id, $post) {
        $field_data = XF()->get( false, $post->ID, [ 'format' => 'raw' ] );

        if ( is_array( $field_data ) ) {
            foreach ( $field_data as $key => $value ) {
                foreach ( (array) $value as $val ) {
                    delete_post_meta( $new_post_id, $key, $val );
                }
            }
        }

        $post_data = [ 'ID' => $new_post_id ];
        XF()->save( $field_data, $post_data );
    }
}

new xf_third_party();

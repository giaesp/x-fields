<?php
/**
 * Plugin Name:       X-Fields
 * Plugin URI:        https://github.com/giaesp/x-fields
 * Description:       Simple, secure custom fields for WordPress: field groups, placement rules, loops (repeaters), relationships and front-end forms.
 * Version:           2.0.1
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Gianluca Esposito
 * Author URI:        https://github.com/giaesp
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       x-fields
 * Domain Path:       /languages
 *
 * X-Fields is a fork of Custom Field Suite.
 * Copyright (C) 2026 Gianluca Esposito
 * Copyright (C) 2011-2024 Matt Gibbs (Custom Field Suite, https://github.com/mgibbs189/custom-field-suite)
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software Foundation; either version 2 of the
 * License, or (at your option) any later version. This program is distributed WITHOUT ANY WARRANTY.
 * See the LICENSE file for details.
 */

defined( 'ABSPATH' ) || exit;

define( 'XFIELDS_VERSION', '2.0.1' );
define( 'XFIELDS_FILE', __FILE__ );
define( 'XFIELDS_DIR', __DIR__ );
define( 'XFIELDS_URL', plugins_url( '', __FILE__ ) );

// Refuse to load alongside Custom Field Suite: both register the same UI and would fight over saves
if ( class_exists( 'Custom_Field_Suite' ) ) {
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'X-Fields is not active: please deactivate Custom Field Suite first. Your CFS data will be migrated automatically.', 'x-fields' ) . '</p></div>';
    } );
    return;
}

require_once XFIELDS_DIR . '/includes/migration.php';
register_activation_hook( __FILE__, [ 'xfields_migration', 'activate' ] );
register_deactivation_hook( __FILE__, function() {
    wp_clear_scheduled_hook( 'xfields_cleanup_sessions' );
} );

class X_Fields
{

    public $api;
    public $form;
    public $fields;
    public $field_group;
    public $validators = [];
    public $group_ids = [];
    private static $instance;


    function __construct() {

        // get the gears turning
        include( XFIELDS_DIR . '/includes/init.php' );
    }


    /**
     * Singleton
     */
    public static function instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self;
        }
        return self::$instance;
    }


    /**
     * Public API methods
     */
    function get( $field_name = false, $post_id = false, $options = [] ) {
        return XFields()->api->get( $field_name, $post_id, $options );
    }


    function get_field_info( $field_name = false, $post_id = false ) {
        return XFields()->api->get_field_info( $field_name, $post_id );
    }


    function get_reverse_related( $post_id, $options = [] ) {
        return XFields()->api->get_reverse_related( $post_id, $options );
    }


    function save( $field_data = [], $post_data = [], $options = [] ) {
        return XFields()->api->save_fields( $field_data, $post_data, $options );
    }


    function find_fields( $params = [] ) {
        return XFields()->api->find_input_fields( $params );
    }


    function form( $params = [] ) {
        ob_start();
        XFields()->form->render( $params );
        return ob_get_clean();
    }


    /**
     * Render a field's admin settings HTML
     */
    function field_html( $field ) {
        include( XFIELDS_DIR . '/templates/field_html.php' );
    }


    /**
     * Trigger the field type "html" method
     */
    function create_field( $field ) {
        $defaults = [
            'type'          => 'text',
            'input_name'    => '',
            'input_class'   => '',
            'options'       => [],
            'value'         => '',
        ];

        $field = (object) array_merge( $defaults, (array) $field );

        // Skip unknown field types (avoids a fatal error on PHP 8)
        if ( ! isset( XFields()->fields[ $field->type ] ) ) {
            return;
        }

        XFields()->fields[ $field->type ]->html( $field );
    }
}


/**
 * Capability required to manage field groups and X-Fields tools.
 * @return string
 */
function xfields_manage_capability() {
    return (string) apply_filters( 'xfields_manage_capability', 'manage_options' );
}


/**
 * Map every field group capability to the manage capability.
 * @return array
 */
function xfields_group_capabilities() {
    $cap = xfields_manage_capability();
    $keys = [ 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'publish_posts',
        'read_private_posts', 'delete_posts', 'delete_private_posts', 'delete_published_posts',
        'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts' ];
    return array_fill_keys( $keys, $cap );
}


function XFields() {
    return X_Fields::instance();
}


XFields();

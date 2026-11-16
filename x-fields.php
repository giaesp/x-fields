<?php
/**
 * Plugin Name:       X-Fields
 * Plugin URI:        https://github.com/giaesp/x-fields
 * Description:       Simple, secure custom fields for WordPress: field groups, placement rules, loops (repeaters), relationships and front-end forms.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Gianluca Esposito
 * Author URI:        https://github.com/giaesp
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       x-fields
 * Domain Path:       /languages
 * Update URI:        https://github.com/giaesp/x-fields
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

define( 'XF_VERSION', '1.0.0' );
define( 'XF_FILE', __FILE__ );
define( 'XF_DIR', __DIR__ );
define( 'XF_URL', plugins_url( '', __FILE__ ) );

// Refuse to load alongside Custom Field Suite: both register the same UI and would fight over saves
if ( class_exists( 'Custom_Field_Suite' ) ) {
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'X-Fields is not active: please deactivate Custom Field Suite first. Your CFS data will be migrated automatically.', 'x-fields' ) . '</p></div>';
    } );
    return;
}

require_once XF_DIR . '/includes/migration.php';
register_activation_hook( __FILE__, [ 'xf_migration', 'activate' ] );

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
        include( XF_DIR . '/includes/init.php' );
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
        return XF()->api->get( $field_name, $post_id, $options );
    }


    function get_field_info( $field_name = false, $post_id = false ) {
        return XF()->api->get_field_info( $field_name, $post_id );
    }


    function get_reverse_related( $post_id, $options = [] ) {
        return XF()->api->get_reverse_related( $post_id, $options );
    }


    function save( $field_data = [], $post_data = [], $options = [] ) {
        return XF()->api->save_fields( $field_data, $post_data, $options );
    }


    function find_fields( $params = [] ) {
        return XF()->api->find_input_fields( $params );
    }


    function form( $params = [] ) {
        ob_start();
        XF()->form->render( $params );
        return ob_get_clean();
    }


    /**
     * Render a field's admin settings HTML
     */
    function field_html( $field ) {
        include( XF_DIR . '/templates/field_html.php' );
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
        if ( ! isset( XF()->fields[ $field->type ] ) ) {
            return;
        }

        XF()->fields[ $field->type ]->html( $field );
    }
}


function XF() {
    return X_Fields::instance();
}


XF();

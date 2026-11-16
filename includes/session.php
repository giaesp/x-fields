<?php

defined( 'ABSPATH' ) || exit;

// This file reads/writes X-Fields' own tables ({prefix}xfields_values, {prefix}xfields_sessions) and the
// group/value rows they reference; WordPress has no API for them. All queries are prepared or use integer/constant values.
// phpcs:disable WordPress.DB.DirectDatabaseQuery

class xfields_session
{
    public $session_id;
    public $session_data;
    public $expires = 14400; // 4 hours


    /**
     * Constructor
     */
    public function __construct() {
        // The nonce is verified by the caller (xfields_form::init); the ID is validated as 32 hex chars
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $posted = isset( $_POST['xfields']['session_id'] ) && is_string( $_POST['xfields']['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['xfields']['session_id'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if ( $this->is_valid( $posted ) ) {
            $this->session_id = $posted;
        }
        else {
            // Cryptographically secure, unpredictable ID (was md5( uniqid() ))
            $this->session_id = bin2hex( random_bytes( 16 ) );
        }
    }


    /**
     * Load the session (expired sessions return an empty array)
     * @return array
     */
    public function get() {
        global $wpdb;

        $output = [];
        $session_data = $wpdb->get_var( $wpdb->prepare(
            "SELECT data FROM {$wpdb->prefix}xfields_sessions WHERE id = %s AND expires > %d",
            $this->session_id, time()
        ) );

        if ( ! empty( $session_data ) && is_serialized( $session_data ) ) {
            $output = @unserialize( $session_data, [ 'allowed_classes' => false ] );
            $output = is_array( $output ) ? $output : [];
        }

        // Sessions are bound to the user that created them
        if ( ! isset( $output['user_id'] ) || (int) $output['user_id'] !== get_current_user_id() ) {
            return [];
        }

        return $output;
    }


    /**
     * Update the session
     * @param array $session_data 
     */
    public function set( $session_data ) {
        global $wpdb;

        $session_data = (array) $session_data;
        $session_data['user_id'] = get_current_user_id();

        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}xfields_sessions WHERE id = %s LIMIT 1", $this->session_id ) );

        // Every rendered form creates a row: purge expired ones regularly (1% of writes + daily cron)
        if ( 1 === wp_rand( 1, 100 ) ) {
            $this->cleanup();
        }

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}xfields_sessions VALUES (%s, %s, %s)",
                $this->session_id, serialize( $session_data ), time() + $this->expires
            )
        );
    }


    /**
     * Remove expired sessions
     */
    public function cleanup() {
        global $wpdb;

        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}xfields_sessions WHERE CAST(expires AS UNSIGNED) <= %d", time() ) );
    }


    /**
     * Validate the MD5 session hash
     * @param string $session_id 
     * @return boolean
     */
    public function is_valid( $session_id ) {
        return (bool) preg_match( '/^[a-f0-9]{32}$/', (string) $session_id );
    }
}

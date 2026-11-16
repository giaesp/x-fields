<?php

defined( 'ABSPATH' ) || exit;

class xf_upgrade
{

    public $version;
    public $last_version;


    public function __construct() {
        $this->version = XF_VERSION;
        $this->last_version = (string) get_option( 'xf_version', '' );

        if ( version_compare( $this->last_version, $this->version, '<' ) ) {
            if ( version_compare( $this->last_version, '1.0.0', '<' ) ) {
                require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
                $this->clean_install();
            }
            else {
                $this->run_upgrade();
            }

            update_option( 'xf_version', $this->version );
        }
    }

    private function clean_install() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "
        CREATE TABLE {$wpdb->prefix}xf_values (
            id INT unsigned not null auto_increment,
            field_id INT unsigned,
            meta_id INT unsigned,
            post_id INT unsigned,
            base_field_id INT unsigned default 0,
            hierarchy TEXT,
            depth INT unsigned default 0,
            weight INT unsigned default 0,
            sub_weight INT unsigned default 0,
            PRIMARY KEY (id),
            INDEX field_id_idx (field_id),
            INDEX post_id_idx (post_id)
        ) $charset_collate";
        dbDelta( $sql );

        $sql = "
        CREATE TABLE {$wpdb->prefix}xf_sessions (
            id VARCHAR(32),
            data TEXT,
            expires VARCHAR(10),
            PRIMARY KEY (id)
        ) $charset_collate";
        dbDelta( $sql );

        // Set the field counter
        update_option( 'xf_next_field_id', 1 );
    }

    private function run_upgrade() {
    }
}

if ( ! did_action( 'xf_upgrade_done' ) ) {
    new xf_upgrade();
    do_action( 'xf_upgrade_done' );
}

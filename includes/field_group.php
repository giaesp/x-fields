<?php

defined( 'ABSPATH' ) || exit;

class xf_field_group
{
    public $cache;


    /*
    ================================================================
        Load all field groups
    ================================================================
    */
    public function load_field_groups() {
        global $wpdb;

        if ( isset( $this->cache['field_groups'] ) ) {
            return $this->cache['field_groups'];
        }

        $sql = "
        SELECT p.ID, p.post_title,
            m1.meta_value as fields,
            m2.meta_value AS rules,
            m3.meta_value AS extras
        FROM $wpdb->posts p
        INNER JOIN $wpdb->postmeta m1 ON m1.post_id = p.ID AND m1.meta_key = 'xf_fields'
        INNER JOIN $wpdb->postmeta m2 ON m2.post_id = p.ID AND m2.meta_key = 'xf_rules'
        INNER JOIN $wpdb->postmeta m3 ON m3.post_id = p.ID AND m3.meta_key = 'xf_extras'
        WHERE p.post_status = 'publish'";
        $results = $wpdb->get_results( $sql );

        $output = [];
        foreach ( $results as $result ) {
            $output[ $result->ID ] = [
                'title'     => $result->post_title,
                'fields'    => (array) $this->safe_unserialize( $result->fields ),
                'rules'     => (array) $this->safe_unserialize( $result->rules ),
                'extras'    => (array) $this->safe_unserialize( $result->extras ),
            ];
        }

        $this->cache['field_groups'] = $output;

        return $output;
    }


    /**
     * Unserialize without instantiating objects (prevents PHP object injection)
     * @param string $value
     * @return mixed
     */
    public function safe_unserialize( $value ) {
        if ( ! is_string( $value ) || ! is_serialized( $value ) ) {
            return $value;
        }
        return @unserialize( $value, [ 'allowed_classes' => false ] );
    }


    /**
     * Sanitize a field name (used as meta_key, CSS class and API key)
     * @param string $name
     * @return string
     */
    public function sanitize_field_name( $name ) {
        return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $name );
    }


    /*
    ================================================================
        Import field groups
    ================================================================
    */
    public function import( $options ) {
        global $wpdb;

        if ( ! empty( $options['import_code'] ) && is_array( $options['import_code'] ) ) {

            // Collect stats
            $stats = [];

            // Get all existing field group names
            $existing_groups = $wpdb->get_col( "SELECT post_name FROM {$wpdb->posts} WHERE post_type = 'xf_group'" );

            // Loop through field groups
            foreach ( $options['import_code'] as $group ) {

                // Validate the structure
                if ( ! is_array( $group ) || empty( $group['post_name'] ) || ! isset( $group['post_title'] ) ) {
                    continue;
                }

                $group['post_title'] = sanitize_text_field( $group['post_title'] );
                $group['post_name'] = sanitize_title( $group['post_name'] );
                $group['xf_fields'] = isset( $group['xf_fields'] ) && is_array( $group['xf_fields'] ) ? $group['xf_fields'] : [];
                $group['xf_rules'] = isset( $group['xf_rules'] ) && is_array( $group['xf_rules'] ) ? $this->sanitize_rules( $group['xf_rules'] ) : [];
                $group['xf_extras'] = isset( $group['xf_extras'] ) && is_array( $group['xf_extras'] ) ? $this->sanitize_extras( $group['xf_extras'] ) : [];

                // Make sure this field group doesn't exist
                if ( ! in_array( $group['post_name'], $existing_groups ) ) {

                    // Insert new post
                    $post_id = wp_insert_post( [
                        'post_title' => $group['post_title'],
                        'post_name' => $group['post_name'],
                        'post_type' => 'xf_group',
                        'post_status' => 'publish',
                        'post_content' => '',
                        'post_content_filtered' => '',
                        'post_excerpt' => '',
                        'to_ping' => '',
                        'pinged' => '',
                    ] );

                    // Generate new field IDs
                    $field_id_mapping = [];
                    $next_field_id = (int) get_option( 'xf_next_field_id' );
                    $clean_fields = [];

                    // First pass: assign new IDs
                    foreach ( $group['xf_fields'] as $data ) {
                        if ( ! is_array( $data ) || ! isset( $data['id'], $data['type'] ) || ! isset( XF()->fields[ $data['type'] ] ) ) {
                            continue;
                        }
                        $field_id_mapping[ (int) $data['id'] ] = $next_field_id;
                        $next_field_id++;
                    }

                    // Second pass: remap IDs and sanitize
                    foreach ( $group['xf_fields'] as $data ) {
                        if ( ! is_array( $data ) || ! isset( $data['id'], $data['type'] ) || ! isset( XF()->fields[ $data['type'] ] ) ) {
                            continue;
                        }
                        $parent_id = isset( $data['parent_id'] ) ? (int) $data['parent_id'] : 0;
                        $clean_fields[] = [
                            'id'        => $field_id_mapping[ (int) $data['id'] ],
                            'name'      => $this->sanitize_field_name( isset( $data['name'] ) ? $data['name'] : '' ),
                            'label'     => sanitize_text_field( isset( $data['label'] ) ? $data['label'] : '' ),
                            'type'      => $data['type'],
                            'notes'     => sanitize_textarea_field( isset( $data['notes'] ) ? $data['notes'] : '' ),
                            'parent_id' => ( 0 < $parent_id && isset( $field_id_mapping[ $parent_id ] ) ) ? $field_id_mapping[ $parent_id ] : 0,
                            'weight'    => isset( $data['weight'] ) ? (int) $data['weight'] : 0,
                            'options'   => isset( $data['options'] ) && is_array( $data['options'] ) ? $data['options'] : [],
                        ];
                    }
                    $group['xf_fields'] = $clean_fields;

                    update_option( 'xf_next_field_id', $next_field_id );
                    update_post_meta( $post_id, 'xf_fields', $group['xf_fields'] );
                    update_post_meta( $post_id, 'xf_rules', $group['xf_rules'] );
                    update_post_meta( $post_id, 'xf_extras', $group['xf_extras'] );

                    $stats['imported'][] = $group['post_title'];
                }
                else {
                    $stats['skipped'][] = $group['post_title'];
                }
            }

            $return = '';
            if ( ! empty( $stats['imported'] ) ) {
                $return .= '<div>' . esc_html__( 'Imported', 'x-fields' ) . ': ' . esc_html( implode( ', ', $stats['imported'] ) ) . '</div>';
            }
            if ( ! empty( $stats['skipped'] ) ) {
                $return .= '<div>' . esc_html__( 'Skipped', 'x-fields' ) . ': ' . esc_html( implode( ', ', $stats['skipped'] ) ) . '</div>';
            }
            if ( '' === $return ) {
                $return = '<div>' . esc_html__( 'Nothing to import', 'x-fields' ) . '</div>';
            }
            return $return;
        }
        else {
            return '<div>' . esc_html__( 'Nothing to import', 'x-fields' ) . '</div>';
        }
    }


    /*
    ================================================================
        Export field groups
    ================================================================
    */
    public function export( $options ) {
        global $wpdb;

        $post_ids = [];
        $field_groups = [];
        $requested = isset( $options['field_groups'] ) ? (array) $options['field_groups'] : [];
        foreach ( $requested as $post_id ) {
            $post_ids[] = (int) $post_id;
        }

        $post_ids = array_filter( $post_ids );
        if ( empty( $post_ids ) ) {
            return [];
        }

        $post_ids = implode( ',', $post_ids );
        $post_data = $wpdb->get_results( "SELECT ID, post_title, post_name FROM {$wpdb->posts} WHERE post_type = 'xf_group' AND ID IN ($post_ids)" );

        foreach ( $post_data as $row ) {
            $field_groups[ $row->ID ] = [
                'post_title' => $row->post_title,
                'post_name' => $row->post_name,
            ];
        }

        $meta_data = $wpdb->get_results( "SELECT * FROM {$wpdb->postmeta} WHERE meta_key IN ('xf_fields','xf_rules','xf_extras') AND post_id IN ($post_ids)" );
        foreach ( $meta_data as $row ) {
            if ( ! isset( $field_groups[ $row->post_id ] ) ) {
                continue;
            }
            $value = $this->safe_unserialize( $row->meta_value );
            $field_groups[ $row->post_id ][ $row->meta_key ] = $value;
        }

        // Strip out the field group keys
        $temp = [];
        foreach ( $field_groups as $field_group ) {
            $temp[] = $field_group;
        }
        $field_groups = $temp;

        return $field_groups;
    }


    /**
     * Save field group settings
     * @param array $params
     */
    function save( $params = [] ) {
        global $wpdb;

        $post_id = (int) $params['post_id'];
        $params['fields'] = isset( $params['fields'] ) && is_array( $params['fields'] ) ? $params['fields'] : [];
        $params['rules'] = isset( $params['rules'] ) && is_array( $params['rules'] ) ? $params['rules'] : [];
        $params['extras'] = isset( $params['extras'] ) && is_array( $params['extras'] ) ? $params['extras'] : [];

        /*---------------------------------------------------------------------------------------------
            Save fields
        ---------------------------------------------------------------------------------------------*/

        $weight = 0;
        $prev_fields = [];
        $current_field_ids = [];
        $next_field_id = (int) get_option( 'xf_next_field_id' );
        $existing_fields = get_post_meta( $post_id, 'xf_fields', true );

        if ( ! empty( $existing_fields ) && is_array( $existing_fields ) ) {
            foreach ( $existing_fields as $item ) {
                if ( isset( $item['id'] ) ) {
                    $prev_fields[ (int) $item['id'] ] = isset( $item['name'] ) ? $item['name'] : '';
                }
            }
        }

        // Map submitted (possibly temporary) IDs to final IDs, so loop children keep their parent
        $id_map = [];

        $new_fields = [];

        foreach ( $params['fields'] as $key => $field ) {

            if ( ! is_array( $field ) ) {
                continue;
            }

            // Sanitize the field
            $field = stripslashes_deep( $field );

            // Skip unknown field types (avoids a fatal error on PHP 8)
            if ( empty( $field['type'] ) || ! isset( XF()->fields[ $field['type'] ] ) ) {
                continue;
            }

            $field = array_merge( [ 'id' => 0, 'name' => '', 'label' => '', 'notes' => '', 'parent_id' => 0, 'options' => [] ], $field );
            $field['options'] = is_array( $field['options'] ) ? $field['options'] : [];

            // Allow for field customizations
            $field = XF()->fields[ $field['type'] ]->pre_save_field( $field );

            $field['name'] = $this->sanitize_field_name( $field['name'] );
            $field['label'] = sanitize_text_field( $field['label'] );
            $field['notes'] = sanitize_textarea_field( $field['notes'] );

            if ( '' === $field['name'] ) {
                continue;
            }

            // Set the parent ID
            $field['parent_id'] = empty( $field['parent_id'] ) ? 0 : (int) $field['parent_id'];

            // Save empty array for fields without options
            $field['options'] = empty( $field['options'] ) ? [] : $field['options'];

            // Only reuse IDs that belong to this field group (prevents hijacking other groups' fields)
            $field['id'] = (int) $field['id'];
            if ( 0 < $field['id'] && isset( $prev_fields[ $field['id'] ] ) ) {

                // We use this variable to check for deleted fields
                $current_field_ids[] = $field['id'];

                $id_map[ $field['id'] ] = $field['id'];

                // Rename the postmeta key if necessary
                if ( $field['name'] != $prev_fields[ $field['id'] ] ) {
                    $wpdb->query(
                        $wpdb->prepare("
                            UPDATE {$wpdb->postmeta} m
                            INNER JOIN {$wpdb->prefix}xf_values v ON v.meta_id = m.meta_id
                            SET meta_key = %s
                            WHERE v.field_id = %d",
                            $field['name'], $field['id']
                        )
                    );
                }
            }
            else {
                if ( 0 < $field['id'] ) {
                    $id_map[ $field['id'] ] = $next_field_id;
                }
                $field['id'] = $next_field_id;
                $next_field_id++;
            }

            $data = [
                'id'            => $field['id'],
                'name'          => $field['name'],
                'label'         => $field['label'],
                'type'          => $field['type'],
                'notes'         => $field['notes'],
                'parent_id'     => $field['parent_id'],
                'weight'        => $weight,
                'options'       => $field['options'],
            ];

            $new_fields[] = $data;

            $weight++;
        }

        // Fix parent references; drop dangling parents
        $valid_ids = wp_list_pluck( $new_fields, 'id' );
        foreach ( $new_fields as &$nf ) {
            if ( 0 < $nf['parent_id'] && ! in_array( $nf['parent_id'], $valid_ids ) ) {
                $nf['parent_id'] = isset( $id_map[ $nf['parent_id'] ] ) ? $id_map[ $nf['parent_id'] ] : 0;
            }
        }
        unset( $nf );

        // Save the fields
        update_post_meta( $post_id, 'xf_fields', $new_fields );

        // Update the field ID counter
        update_option( 'xf_next_field_id', $next_field_id );

        // Remove values for deleted fields
        $deleted_field_ids = array_diff( array_keys( $prev_fields ), $current_field_ids );

        // Filter deleted field IDs before deleting meta
        $deleted_field_ids = apply_filters( 'xf_deleted_field_ids', $deleted_field_ids );

        $deleted_field_ids = array_filter( array_map( 'intval', (array) $deleted_field_ids ) );

        if ( 0 < count( $deleted_field_ids ) ) {
            $deleted_field_ids = implode( ',', $deleted_field_ids );
            $wpdb->query("
                DELETE v, m
                FROM {$wpdb->prefix}xf_values v
                INNER JOIN {$wpdb->postmeta} m ON m.meta_id = v.meta_id
                WHERE v.field_id IN ($deleted_field_ids)"
            );
        }

        /*---------------------------------------------------------------------------------------------
            Save rules
        ---------------------------------------------------------------------------------------------*/

        $data = [];
        $rule_types = [ 'post_types', 'post_formats', 'user_roles', 'post_ids', 'term_ids', 'page_templates' ];
        $rules_in = stripslashes_deep( $params['rules'] );

        foreach ( $rule_types as $type ) {
            if ( ! empty( $rules_in[ $type ] ) ) {

                // Break apart the autocomplete string
                if ( 'post_ids' == $type && is_string( $rules_in[ $type ] ) ) {
                    $rules_in[ $type ] = explode( ',', $rules_in[ $type ] );
                }

                $data[ $type ] = [
                    'operator' => isset( $rules_in['operator'][ $type ] ) ? $rules_in['operator'][ $type ] : '==',
                    'values' => $rules_in[ $type ],
                ];
            }
        }

        $data = $this->sanitize_rules( $data );
        $data = apply_filters( 'xf_save_field_group_rules', $data, $post_id );
        update_post_meta( $post_id, 'xf_rules', $data );

        /*---------------------------------------------------------------------------------------------
            Save extras
        ---------------------------------------------------------------------------------------------*/

        update_post_meta( $post_id, 'xf_extras', $this->sanitize_extras( stripslashes_deep( $params['extras'] ) ) );
    }


    /**
     * Sanitize placement rules
     * @param array $rules
     * @return array
     */
    public function sanitize_rules( $rules ) {
        $clean = [];
        $allowed = [ 'post_types', 'post_formats', 'user_roles', 'post_ids', 'term_ids', 'page_templates' ];

        foreach ( (array) $rules as $type => $rule ) {
            if ( ! in_array( $type, $allowed, true ) || ! is_array( $rule ) ) {
                continue;
            }

            $operator = isset( $rule['operator'] ) ? $rule['operator'] : '==';
            $operator = is_array( $operator ) ? reset( $operator ) : $operator;
            $operator = ( '!=' === $operator ) ? '!=' : '==';

            $values = isset( $rule['values'] ) ? (array) $rule['values'] : [];

            if ( in_array( $type, [ 'post_ids', 'term_ids' ], true ) ) {
                $values = array_values( array_filter( array_map( 'absint', $values ) ) );
            }
            else {
                $values = array_values( array_filter( array_map( function( $v ) {
                    return is_scalar( $v ) ? sanitize_text_field( (string) $v ) : '';
                }, $values ), 'strlen' ) );
            }

            if ( ! empty( $values ) ) {
                $clean[ $type ] = [ 'operator' => $operator, 'values' => $values ];
            }
        }

        return $clean;
    }


    /**
     * Sanitize field group extras
     * @param array $extras
     * @return array
     */
    public function sanitize_extras( $extras ) {
        $extras = (array) $extras;

        return [
            'order'       => isset( $extras['order'] ) ? (int) $extras['order'] : 0,
            'context'     => ( isset( $extras['context'] ) && 'side' === $extras['context'] ) ? 'side' : 'normal',
            'hide_editor' => empty( $extras['hide_editor'] ) ? 0 : 1,
        ];
    }
}

XF()->field_group = new xf_field_group();

<?php

defined( 'ABSPATH' ) || exit;

class xf_api
{
    public $cache;
    public $saved_fields;


    /*
    ================================================================
        Abstraction for get_field / get_fields
    ================================================================
    */
    public function get( $field_name, $post_id, $options ) {
        if ( false !== $field_name ) {
            return $this->get_field( $field_name, $post_id, $options );
        }

        return $this->get_fields( $post_id, $options );
    }


    /*
    ================================================================
        Get a field value
    ================================================================
    */
    public function get_field( $field_name, $post_id, $options ) {
        global $post;

        $defaults = [ 'format' => 'api' ]; // "api", "input", or "raw"
        $options = array_merge( $defaults, (array) $options );

        if ( empty( $post_id ) && empty( $post ) ) {
            return null;
        }

        $post_id = empty( $post_id ) ? $post->ID : (int) $post_id;

        // Trigger get_fields if not in cache
        if ( ! isset( $this->cache[ $post_id ][ $options['format'] ][ $field_name ] ) ) {
            $fields = $this->get_fields( $post_id, $options );

            return isset( $fields[ $field_name ] ) ? $fields[ $field_name ] : null;
        }

        return $this->cache[ $post_id ][ $options['format'] ][ $field_name ];
    }


    /*
    ================================================================
        Get all field values for a specific post
    ================================================================
    */
    public function get_fields( $post_id, $options ) {
        global $post, $wpdb;

        $defaults = [ 'format' => 'api' ]; // "api", "input", or "raw"
        $options = array_merge( $defaults, (array) $options );

        if ( empty( $post_id ) ) {
            if ( empty( $post->ID ) ) {
                return [];
            }
            $post_id = $post->ID;
        }
        $post_id = (int) $post_id;

        // Return cached results
        if ( isset( $this->cache[ $post_id ][ $options['format'] ] ) ) {
            return $this->cache[ $post_id ][ $options['format'] ];
        }

        $fields = [];
        $field_data = [];

        // Get all field groups for this post
        $group_ids = $this->get_matching_groups( $post_id, true );

        if ( ! empty( $group_ids ) ) {
            $results = $this->find_input_fields( [ 'group_id' => array_keys( $group_ids ) ] );
            foreach ( $results as $result ) {
                $result = (object) $result;
                $fields[ $result->id ] = $result;
            }

            if ( ! empty( $fields ) ) {
                // Make sure we're using active field groups
                $field_ids = implode( ',', array_map( 'intval', array_keys( $fields ) ) );

                // Get all the field data
                $sql = "
                SELECT m.meta_value, v.field_id, v.hierarchy, v.weight
                FROM {$wpdb->prefix}xf_values v
                INNER JOIN {$wpdb->postmeta} m ON m.meta_id = v.meta_id
                WHERE v.field_id IN ($field_ids) AND v.post_id = $post_id
                ORDER BY v.depth, FIELD(v.field_id, $field_ids), v.weight, v.sub_weight";

                $results = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL -- only table names, constants or integer IDs are interpolated
                $num_rows = $wpdb->num_rows;

                $prev_hierarchy = '';
                $prev_field_id = '';
                $prev_item = '';

                foreach ( $results as $row_count => $result ) {
                    if ( ! isset( $fields[ $result->field_id ] ) ) {
                        continue;
                    }

                    $field = $fields[ $result->field_id ];
                    $current_item = "{$result->hierarchy}.{$result->weight}.{$result->field_id}";

                    if ( ! empty( $result->hierarchy ) ) {

                        // Format for API (field names)
                        if ( 'api' == $options['format'] || 'raw' == $options['format'] ) {
                            $tmp = explode( ':', $result->hierarchy );
                            foreach ( $tmp as $key => $val ) {
                                if ( 0 == ( $key % 2 ) && isset( $fields[ $val ] ) ) {
                                    $tmp[ $key ] = $fields[ $val ]->name;
                                }
                            }
                            $hierarchy = implode( ':', $tmp );
                        }
                        // Format for input (field IDs)
                        else {
                            $hierarchy = $result->hierarchy;
                        }

                        $this->assemble_value_array( $field_data, $hierarchy, $field, $result->meta_value );
                    }
                    else {
                        // Get the field name for "api" or "raw" formats
                        if ( 'api' == $options['format'] || 'raw' == $options['format'] ) {
                            $hierarchy = $field->name;
                        }
                        else {
                            $hierarchy = $field->id;
                        }

                        if ( isset( $field_data[ $hierarchy ] ) && is_array( $field_data[ $hierarchy ] ) ) {
                            $field_data[ $hierarchy ][] = $result->meta_value;
                        }
                        else {
                            $field_data[ $hierarchy ] = [ $result->meta_value ];
                        }
                    }

                    // Assemble the values
                    if ( $current_item != $prev_item && '' != $prev_item && isset( $fields[ $prev_field_id ] ) ) { // call assemble_value_array on previous field
                        $this->assemble_value_array( $field_data, $prev_hierarchy, $fields[ $prev_field_id ], false, $options );
                    }

                    if ( $num_rows == ( $row_count + 1 ) ) { // last row
                        $this->assemble_value_array( $field_data, $hierarchy, $field, false, $options );
                    }

                    $prev_hierarchy = $hierarchy;
                    $prev_field_id = $field->id;
                    $prev_item = $current_item;
                }
            }
        }

        $this->cache[ $post_id ][ $options['format'] ] = $field_data;
        return $field_data;
    }


    /*
    ================================================================
        Get properties for one or more fields
    ================================================================
    */
    public function get_field_info( $field_name = false, $post_id = false ) {
        global $post, $wpdb;

        if ( empty( $post_id ) ) {
            if ( empty( $post->ID ) ) {
                return [];
            }
            $post_id = $post->ID;
        }
        $post_id = (int) $post_id;

        // Get all field groups for this post
        $group_ids = $this->get_matching_groups( $post_id, true );
        $group_ids = array_keys( $group_ids );

        $output = [];

        if ( ! empty( $group_ids ) ) {
            $results = $this->find_input_fields( [ 'group_id' => $group_ids ] );
            foreach ( $results as $result ) {
                if ( $result['name'] == $field_name ) {
                    $output = (array) $result;
                }
                elseif ( empty( $field_name ) ) {
                    $output[ $result['name'] ] = (array) $result;
                }
            }
        }

        return $output;
    }


    /*
    ================================================================
        Get referenced field values (using relationship fields)
    ================================================================
    */
    public function get_reverse_related( $post_id, $options = [] ) {
        global $wpdb;

        $options = (array) $options;
        $where = $wpdb->prepare( 'm.meta_value = %s', (string) (int) $post_id );

        // Build a safe, escaped IN() list
        $in_list = function( $values ) {
            $values = array_map( function( $v ) { return "'" . esc_sql( (string) $v ) . "'"; }, (array) $values );
            return implode( ',', $values );
        };

        if ( ! empty( $options['field_name'] ) ) {
            $where .= ' AND m.meta_key IN (' . $in_list( $options['field_name'] ) . ')';
        }
        if ( isset( $options['field_type'] ) ) {
            $field_type = $options['field_type'];
        }
        if ( ! empty( $options['post_type'] ) ) {
            $where .= ' AND p.post_type IN (' . $in_list( $options['post_type'] ) . ')';
        }
        if ( ! empty( $options['post_status'] ) ) {
            $where .= ' AND p.post_status IN (' . $in_list( $options['post_status'] ) . ')';
        }

        // Limit to specific field types
        $field_type = empty( $field_type ) ? 'relationship' : $field_type;
        $results = $this->find_input_fields( [ 'field_type' => $field_type ] );

        if ( ! empty( $results ) ) {
            $field_ids = [];
            foreach ( $results as $result ) {
                $field_ids[] = (int) $result['id'];
            }
            $where .= " AND v.field_id IN (" . implode( ',', $field_ids ) . ")";
        }

        $sql = "
        SELECT DISTINCT p.ID
        FROM {$wpdb->prefix}xf_values v
        INNER JOIN $wpdb->posts p ON p.ID = v.post_id
        INNER JOIN $wpdb->postmeta m ON m.meta_id = v.meta_id
        WHERE $where";

        $results = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL -- only table names, constants or integer IDs are interpolated
        $output = [];

        foreach ( $results as $result ) {
            $output[] = $result->ID;
        }
        return $output;
    }


    /*
    ================================================================
        Save field value
    ================================================================
    */
    public function save_fields( $field_data = [], $post_data = [], $options = [] ) {
        global $wpdb;

        $defaults = [
            'format'            => 'api', // "api" or "input"
            'field_groups'      => [],
        ];
        $options = array_merge( $defaults, (array) $options );
        $field_data = (array) $field_data;
        $post_data = (array) $post_data;

        // Log saved fields
        $this->saved_fields = [];

        $fields = [];
        $field_id_lookup = [];
        $group_ids = [];

        // create post if the ID is missing
        if ( empty( $post_data['ID'] ) ) {
            $post_defaults = [
                'post_title'                => 'My XF post',
                'post_content'              => '',
                'post_content_filtered'     => '',
                'post_excerpt'              => '',
                'to_ping'                   => '',
                'pinged'                    => '',
            ];
            $post_data = array_merge( $post_defaults, $post_data );
            $post_id = wp_insert_post( $post_data );
        }
        else {
            $post_id = (int) $post_data['ID'];

            if ( 1 < count( $post_data ) ) {
                unset( $post_data['ID'] );
                $wpdb->update( $wpdb->posts, $post_data, [ 'ID' => $post_id ] );
                clean_post_cache( $post_id );
            }
        }

        // For input forms, get the group IDs from the HTTP POST
        // Otherwise, the field group might not match anymore (e.g. the taxonomy changed)
        if ( empty( $post_id ) || is_wp_error( $post_id ) ) {
            return 0;
        }

        if ( 'input' == $options['format'] ) {
            $group_ids = array_map( 'intval', (array) $options['field_groups'] );
        }
        elseif ( 'api' == $options['format'] ) {

            // For revisions, get the parent post's field groups
            if ( wp_is_post_revision( $post_id ) ) {
                $revision_id = wp_is_post_revision( $post_id );
                $group_ids = $this->get_matching_groups( $revision_id, true );
            }
            else {
                $group_ids = $this->get_matching_groups( $post_id, true );
            }

            $group_ids = array_keys( $group_ids );
        }

        if ( ! empty( $group_ids ) ) {
            $parent_fields = [];
            $results = $this->find_input_fields( [ 'group_id' => $group_ids ] );
            foreach ( $results as $result ) {

                // Store all the field objects for the current field group(s)
                $fields[ $result['id'] ] = (object) $result;

                // Store lookup values for the recursion
                $field_id_lookup[ $result['parent_id'] . ':' . $result['name'] ] = $result['id'];

                // Store parent fields separately
                if ( 0 == (int) $result['parent_id'] ) {
                    $parent_fields[ $result['name'] ] = $result['id'];
                }
            }
        }

        // If this is an API call, flatten the data!
        if ( 'api' == $options['format'] ) {
            $field_ids = [];

            foreach ( $field_data as $field_name => $junk ) {
                if ( isset( $parent_fields[ $field_name ] ) ) {
                    $field_ids[] = (int) $parent_fields[ $field_name ];
                }
            }

            $field_ids = implode( ',', $field_ids );

            $sql = "
            DELETE v, m
            FROM {$wpdb->prefix}xf_values v
            LEFT JOIN {$wpdb->postmeta} m ON m.meta_id = v.meta_id
            WHERE v.post_id = $post_id AND (v.field_id IN ($field_ids) OR v.base_field_id IN ($field_ids))";

            if ( ! empty( $field_ids ) ) {
                $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL -- only table names, constants or integer IDs are interpolated
            }
        }
        elseif ( 'input' == $options['format'] ) {

            // If saving raw input, delete existing postdata
            $results = empty( $group_ids ) ? [] : $this->find_input_fields( [ 'group_id' => $group_ids ] );
            if ( ! empty( $results ) ) {
                $field_ids = [];
                foreach ( $results as $result ) {
                    $field_ids[] = (int) $result['id'];
                }
                $field_ids = implode( ',', $field_ids );

                $sql = "
                DELETE v, m
                FROM {$wpdb->prefix}xf_values v
                LEFT JOIN {$wpdb->postmeta} m ON m.meta_id = v.meta_id
                WHERE v.post_id = $post_id AND v.field_id IN ($field_ids)";

                if ( ! empty( $field_ids ) ) {
                    $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL -- only table names, constants or integer IDs are interpolated
                }
            }
        }

        // Save recursively
        $field_data = stripslashes_deep( $field_data );

        foreach ( $field_data as $field_id => $field_array ) {
            $this->save_fields_recursive(
                [
                    'field_id'              => $field_id,
                    'field_array'           => $field_array,
                    'post_id'               => $post_id,
                    'parent_id'             => 0,
                    'all_fields'            => $fields,
                    'hierarchy'             => [],
                    'format'                => $options['format'],
                    'field_id_lookup'       => $field_id_lookup,
                    'weight'                => 0,
                    'depth'                 => 0,
                    // Values from forms are untrusted: filter HTML for users without unfiltered_html
                    'kses'                  => ( 'input' == $options['format'] && ! current_user_can( 'unfiltered_html' ) ),
                    'anonymous'             => ( 'input' == $options['format'] && ! is_user_logged_in() ),
                ]
            );
        }

        // Clear the cache
        $this->cache[ $post_id ] = null;

        return $post_id;
    }


    /*
    ================================================================
        Extend save_fields to support loop fields
    ================================================================
    */
    private function save_fields_recursive( $params ) {
        global $wpdb;

        $field_type = 'loop';
        $field_id = $params['field_id'];
        $field_array = (array) $params['field_array'];

        if ( 0 == $params['depth'] % 2 ) {

            // If not raw input, then field_id is actually the field name, and
            // we need to lookup the ID from the "field_id_lookup" array
            if ( 'input' != $params['format'] ) {
                $field_name = $field_id;
                $lookup_key = $params['parent_id'] . ':' . $field_name;

                if ( ! isset( $params['field_id_lookup'][ $lookup_key ] ) ) {
                    return;
                }

                $field_id = (int) $params['field_id_lookup'][ $lookup_key ];
            }

            $field_id = (int) $field_id;

            // Exit if the field is missing
            if ( ! isset( $params['all_fields'][ $field_id ] ) ) {
                return;
            }

            $field_type = $params['all_fields'][ $field_id ]->type;

            if ( ! isset( XF()->fields[ $field_type ] ) ) {
                return;
            }
        }
        else {
            // Odd depth = loop row index; always an integer
            $field_id = (int) $field_id;
        }

        // We've found the values
        if ( isset( $field_array['value'] ) || 'loop' != $field_type ) {
            $values = isset( $field_array['value'] ) ? $field_array['value'] : $field_array;

            // Filter untrusted HTML before the field type transforms (e.g. serializes) the value
            if ( ! empty( $params['kses'] ) ) {
                $neutralize_shortcodes = ! empty( $params['anonymous'] );
                $values = map_deep( $values, function( $v ) use ( $neutralize_shortcodes ) {
                    if ( ! is_string( $v ) ) {
                        return $v;
                    }
                    $v = wp_kses_post( $v );
                    // Anonymous visitors must not be able to run shortcodes when the theme outputs the value
                    return $neutralize_shortcodes ? str_replace( [ '[', ']' ], [ '&#91;', '&#93;' ], $v ) : $v;
                } );
            }

            // Trigger the pre_save hook
            $values = XF()->fields[ $field_type ]->pre_save( $values, $params['all_fields'][ $field_id ] );

            $sub_weight = 0;

            foreach ( (array) $values as $value ) {

                // Only scalar values can be stored
                if ( ! is_scalar( $value ) && null !== $value ) {
                    continue;
                }

                $value = (string) $value;

                // Insert into postmeta
                $data = [
                    'post_id'       => $params['post_id'],
                    'meta_key'      => $params['all_fields'][ $field_id ]->name,
                    'meta_value'    => $value,
                ];

                $wpdb->insert( $wpdb->postmeta, $data );
                $meta_id = $wpdb->insert_id;

                // Get the top-level field ID from the hierarchy array
                $base_field_id = empty( $params['hierarchy'] ) ? 0 : $params['hierarchy'][0];

                // Insert into xf_values
                $data = [
                    'field_id'          => $field_id,
                    'meta_id'           => $meta_id,
                    'post_id'           => $params['post_id'],
                    'base_field_id'     => $base_field_id,
                    'hierarchy'         => implode( ':', $params['hierarchy'] ),
                    'depth'             => floor( count( $params['hierarchy'] ) / 2 ),
                    'weight'            => $params['weight'],
                    'sub_weight'        => $sub_weight,
                ];

                $wpdb->insert( $wpdb->prefix . 'xf_values', $data );
                $sub_weight++;

                // Update log
                $this->saved_fields[ $field_id ] = true;
            }
        }
        // Keep recursing
        else {
            foreach ( $field_array as $sub_field_id => $sub_field_array ) {

                // Hierarchy keys must be integers (field IDs or row indexes)
                if ( 'input' == $params['format'] && ! is_int( $sub_field_id ) && ! ctype_digit( (string) $sub_field_id ) ) {
                    continue;
                }

                $new_params = $params;
                $new_params['field_array'] = $sub_field_array;
                $new_params['field_id'] = $sub_field_id;
                $new_params['weight'] = $field_id;
                $new_params['depth']++;

                // If not raw input, then sub_field_id is actually the field name
                if ( 'input' != $params['format'] ) {
                    if ( 0 == $new_params['depth'] % 2 ) {
                        $lookup_key = $new_params['parent_id'] . ':' . $sub_field_id;
                        if ( ! isset( $params['field_id_lookup'][ $lookup_key ] ) ) {
                            continue;
                        }
                        $sub_field_id = $params['field_id_lookup'][ $lookup_key ];
                    }
                    else {
                        $new_params['parent_id'] = $field_id;
                    }
                }

                if ( empty( $new_params['hierarchy'] ) ) {
                    $new_params['hierarchy'][] = $field_id;
                }

                $new_params['hierarchy'][] = (int) $sub_field_id;
                $this->save_fields_recursive( $new_params );
            }
        }
    }


    /**
     * Server-side validation of raw form input (required fields and min/max limits).
     * Mirrors the client-side rules in assets/js/validation.js, which can be bypassed.
     *
     * @param array $field_data Raw input (format "input": keyed by field ID)
     * @param array $group_ids  Field group IDs being saved
     * @return array List of error messages (empty = valid)
     * @since 1.1.0
     */
    public function validate_input( $field_data, $group_ids ) {
        $fields = [];
        foreach ( $this->find_input_fields( [ 'group_id' => array_map( 'intval', (array) $group_ids ) ] ) as $f ) {
            $fields[ (int) $f['id'] ] = (object) $f;
        }

        $errors = [];
        $this->validate_level( (array) $field_data, 0, $fields, $errors, '' );

        return apply_filters( 'xf_validate_input', array_values( array_unique( $errors ) ), $field_data, $group_ids );
    }


    /**
     * Validate one level of fields (top level, or one loop row)
     */
    private function validate_level( $data, $parent_id, $fields, &$errors, $prefix ) {
        foreach ( $fields as $id => $field ) {
            if ( (int) $field->parent_id !== (int) $parent_id || 'tab' === $field->type ) {
                continue;
            }

            $options  = is_array( $field->options ) ? $field->options : [];
            $label    = $prefix . ( '' !== (string) $field->label ? $field->label : $field->name );
            $required = ! empty( $options['required'] );
            $min      = empty( $options['limit_min'] ) ? 0 : (int) $options['limit_min'];
            $max      = empty( $options['limit_max'] ) ? 0 : (int) $options['limit_max'];

            if ( 'loop' === $field->type ) {
                $rows = ( isset( $data[ $id ] ) && is_array( $data[ $id ] ) ) ? array_filter( $data[ $id ], 'is_array' ) : [];
                $this->check_count( count( $rows ), $min, $max, $label, $errors );

                $n = 0;
                foreach ( $rows as $row ) {
                    $n++;
                    /* translators: 1: loop label, 2: row number */
                    $this->validate_level( $row, $id, $fields, $errors, sprintf( __( '%1$s, row %2$d', 'x-fields' ), $label, $n ) . ' › ' );
                }
                continue;
            }

            $value = ( isset( $data[ $id ] ) && is_array( $data[ $id ] ) && array_key_exists( 'value', $data[ $id ] ) ) ? $data[ $id ]['value'] : null;

            if ( in_array( $field->type, [ 'relationship', 'term', 'user' ], true ) ) {
                $count = count( xf_sanitize_id_list( $value ) );
                $this->check_count( $count, $min, $max, $label, $errors );
                $empty = ( 0 === $count );
            }
            else {
                $flat  = array_filter( (array) $value, function( $v ) {
                    return is_scalar( $v ) && '' !== trim( (string) $v );
                } );
                $empty = empty( $flat );
            }

            if ( $required && $empty ) {
                /* translators: %s: field label */
                $errors[] = sprintf( __( '%s is required.', 'x-fields' ), $label );
            }
        }
    }


    /**
     * Check a min/max item count
     */
    private function check_count( $count, $min, $max, $label, &$errors ) {
        if ( 0 < $min && $count < $min ) {
            /* translators: 1: field label, 2: minimum number of items */
            $errors[] = sprintf( _n( '%1$s needs at least %2$d item.', '%1$s needs at least %2$d items.', $min, 'x-fields' ), $label, $min );
        }
        if ( 0 < $max && $count > $max ) {
            /* translators: 1: field label, 2: maximum number of items */
            $errors[] = sprintf( _n( '%1$s allows at most %2$d item.', '%1$s allows at most %2$d items.', $max, 'x-fields' ), $label, $max );
        }
    }


    /*
    ================================================================
        Find input fields
    ================================================================
    */
    public function find_input_fields( $params = [] ) {
        global $wpdb;

        $defaults = [
            'post_id'       => false, // a single post ID
            'group_id'      => [],
            'field_id'      => [],
            'field_type'    => [],
            'field_name'    => [],
            'parent_id'     => [],
        ];

        $params = array_merge( $defaults, $params );

        $field_groups = XF()->field_group->load_field_groups();

        $output = [];

        foreach ( $field_groups as $group_id => $group ) {

            // Exclude by group ID
            if ( ! empty( $params['group_id'] ) && ! in_array( $group_id, (array) $params['group_id'] ) ) {
                continue;
            }

            // Exclude by group ID (groups attached to a specific post)
            if ( ! empty( $params['post_id'] ) ) {
                $post_id = (int) $params['post_id'];
                $matching_group_ids = array_keys( $this->get_matching_groups( $post_id, true ) );
                if ( ! in_array( $group_id, $matching_group_ids ) ) {
                    continue;
                }
            }

            foreach ( $group['fields'] as $field ) {

                // Other exclusions
                if ( ! empty( $params['field_id'] ) && ! in_array( $field['id'], (array) $params['field_id'] ) ) {
                    continue;
                }
                if ( ! empty( $params['parent_id'] ) && ! in_array( $field['parent_id'], (array) $params['parent_id'] ) ) {
                    continue;
                }
                if ( ! empty( $params['field_type'] ) && ! in_array( $field['type'], (array) $params['field_type'] ) ) {
                    continue;
                }
                if ( ! empty( $params['field_name'] ) && ! in_array( $field['name'], (array) $params['field_name'] ) ) {
                    continue;
                }

                // Attach the group ID
                $field['group_id'] = $group_id;

                $output[] = $field;
            }
        }

        return $output;
    }


    /*
    ================================================================
        Get input fields / values for a specific post ID
    ================================================================
    */
    public function get_input_fields( $params = [] ) {
        global $post, $wpdb;

        $fields = $this->find_input_fields( $params );

        // Values can come from an explicit post ID (front-end forms) or the global $post
        if ( isset( $params['post_id_values'] ) ) {
            $values_post_id = (int) $params['post_id_values'];
        }
        else {
            $values_post_id = empty( $post->ID ) ? 0 : (int) $post->ID;
        }

        $values = ( 0 < $values_post_id ) ? $this->get_fields( $values_post_id, [ 'format' => 'input' ] ) : [];

        $output = [];

        foreach ( $fields as $field ) {
            $field = (object) $field;

            // If no field value exists, set it to NULL
            $field->value = isset( $values[ $field->id ] ) ? $values[ $field->id ] : null;

            if ( ! isset( $field->value ) && isset( $field->options['default_value'] ) ) {
                $field->value = $field->options['default_value'];
            }

            $output[ $field->id ] = $field;
        }

        return apply_filters( 'xf_get_input_fields', $output, $params );
    }


    /*
    ================================================================
        Determine which field groups to use for the current post
    ================================================================
    */
    public function get_matching_groups( $params, $skip_roles = false ) {
        global $wpdb;

        $post_id = 0;
        $temp = [];

        // Set post ID
        if ( ! is_array( $params ) ) {
            $post_id = (int) $params;

            if ( wp_is_post_revision( $post_id ) ) {
                $post_id = wp_is_post_revision( $post_id );
            }

            $rule_types = [
                'post_ids' => $post_id
            ];
        }
        else {
            $rule_types = $params;

            if ( ! empty( $params['post_ids'] ) ) {
                $first = (array) $params['post_ids'];
                $post_id = (int) reset( $first );
            }
        }

        // Detect post_types / page_templates if they weren't sent
        if ( ! empty( $rule_types[ 'post_ids' ] ) ) {
            $rule_types['post_ids'] = array_map( 'absint', (array) $rule_types['post_ids'] );

            if ( ! isset( $rule_types['post_types'] ) ) {
                $rule_types['post_types'] = [];

                foreach ( $rule_types['post_ids'] as $pid ) {
                    $post_type = get_post_type( $pid );

                    if ( ! in_array( $post_type, $rule_types['post_types'] ) ) {
                        $rule_types['post_types'][] = $post_type;
                    }
                }
            }

            if ( ! isset( $rule_types['post_formats'] ) ) {
                $rule_types['post_formats'] = [];

                foreach ( $rule_types['post_ids'] as $pid ) {
                    $post_format = get_post_format( $pid );

                    // Prevent post_format = false
                    $post_format = ( false === $post_format ) ? 'standard' : $post_format;

                    if ( ! in_array( $post_format, $rule_types['post_formats'] ) ) {
                        $rule_types['post_formats'][] = $post_format;
                    }
                }
            }

            if ( ! isset( $rule_types['page_templates'] ) ) {
                $rule_types['page_templates'] = [];

                foreach ( $rule_types['post_ids'] as $pid ) {
                    $page_template = get_post_meta( $pid, '_wp_page_template', true );

                    if ( ! empty( $page_template ) && ! in_array( $page_template, $rule_types['page_templates'] ) ) {
                        $rule_types['page_templates'][] = $page_template;
                    }
                }
            }
        }

        // Set defaults
        $rule_types = array_merge(
            [
                'post_types'        => [],
                'post_formats'      => [],
                'user_roles'        => (array) wp_get_current_user()->roles,
                'term_ids'          => [],
                'post_ids'          => [],
                'page_templates'    => []
            ], $rule_types
        );

        // Get all field groups
        $field_groups = XF()->field_group->load_field_groups();

        // Ignore user_roles if used within get_fields
        if ( false !== $skip_roles ) {
            unset( $rule_types['user_roles'] );
        }

        foreach ( $field_groups as $group_id => $result ) {
            $fail = false;

            $rules = is_array( $result['rules'] ) ? $result['rules'] : [];
            $extras = is_array( $result['extras'] ) ? $result['extras'] : [];

            foreach ( $rule_types as $rule_type => $value ) {
                if ( ! empty( $rules[ $rule_type ] ) ) {

                    // Only lookup a post's term IDs if the rule exists
                    if ( 'term_ids' == $rule_type ) {
                        $sql = "
                        SELECT tt.term_id
                        FROM $wpdb->term_taxonomy tt
                        INNER JOIN $wpdb->term_relationships tr ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tr.object_id = %d";
                        $value = $wpdb->get_col( $wpdb->prepare( $sql, $post_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL -- only table names, constants or integer IDs are interpolated
                    }

                    $operator = isset( $rules[ $rule_type ]['operator'] ) ? (array) $rules[ $rule_type ]['operator'] : [ '==' ];
                    $rule_values = isset( $rules[ $rule_type ]['values'] ) ? (array) $rules[ $rule_type ]['values'] : [];
                    $in_array = ( 0 < count( array_intersect( (array) $value, $rule_values ) ) );

                    if ( ( $in_array && '!=' == $operator[0] ) || ( ! $in_array && '==' == $operator[0] ) ) {
                        $fail = true;
                    }
                }
            }

            if ( ! $fail ) {
                $temp[] = [
                    'post_id' => $group_id,
                    'post_title' => $result['title'],
                    'order' => empty( $extras['order'] ) ? 0 : (int) $extras['order']
                ];
            }
        }

        $matches = [];

        // Sort the field groups
        if ( ! empty( $temp ) ) {
            $temp = $this->array_orderby( $temp, 'order' );
            foreach ( $temp as $values ) {
                $matches[ $values['post_id'] ] = $values['post_title'];
            }
        }

        // Allow for overrides
        return apply_filters( 'xf_matching_groups', $matches, $params, $rule_types );
    }


    /*
    ================================================================
        Replace a value within a multidimensional array
    ================================================================
    */
    private function assemble_value_array( &$field_data, $hierarchy, $field, $field_value = false, $options = false ) {
        $value = &$field_data;

        foreach ( explode( ':', $hierarchy ) as $i ) {
            $value = &$value[ $i ];
        }

        if ( false !== $field_value ) {
            $value = (array) $value;
            $value[] = $field_value;
        }
        else {
            if ( ! isset( XF()->fields[ $field->type ] ) ) {
                return;
            }

            $value = XF()->fields[ $field->type ]->prepare_value( $value, $field );

            $format = is_array( $options ) && isset( $options['format'] ) ? $options['format'] : 'api';

            if ( 'api' == $format ) {
                $value = XF()->fields[ $field->type ]->format_value_for_api( $value, $field );
            }
            elseif ( 'input' == $format ) {
                $value = XF()->fields[ $field->type ]->format_value_for_input( $value, $field );
            }
        }
    }


    /*
    ================================================================
        MySQL "ORDER BY" for PHP associative arrays
        @link https://gist.github.com/mgibbs189/4634604
    ================================================================
    */
    private function array_orderby() {
        $args = func_get_args();
        $data = array_shift( $args );

        if ( ! is_array( $data ) ) {
            return [];
        }

        $multisort_params = [];
        foreach ( $args as $n => $field ) {
            if ( is_string( $field ) ) {
                $tmp = [];
                foreach ( $data as $row ) {
                    $tmp[] = isset( $row[ $field ] ) ? $row[ $field ] : null;
                }
                $args[ $n ] = $tmp;
            }
            $multisort_params[] = &$args[ $n ];
        }

        $multisort_params[] = &$data;
        call_user_func_array( 'array_multisort', $multisort_params );
        return end( $multisort_params );
    }
}

XF()->api = new xf_api();

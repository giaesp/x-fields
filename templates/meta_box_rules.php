<?php

defined( 'ABSPATH' ) || exit;

global $post;

$xfields_user_roles = [];
$xfields_term_ids = [];

$xfields_equals_text = __( 'equals', 'x-fields' );
$xfields_not_equals_text = __( 'is not', 'x-fields' );
$xfields_rules = (array) get_post_meta( $post->ID, 'xfields_rules', true );

// Populate rules if empty
$xfields_rule_types = [
    'post_types',
    'post_formats',
    'user_roles',
    'post_ids',
    'term_ids',
    'page_templates'
];

foreach ( $xfields_rule_types as $xfields_type ) {
    if ( ! isset( $xfields_rules[ $xfields_type ] ) || ! is_array( $xfields_rules[ $xfields_type ] ) ) {
        $xfields_rules[ $xfields_type ] = [ 'operator' => [ '==' ], 'values' => [] ];
    }
    $xfields_rules[ $xfields_type ]['values'] = isset( $xfields_rules[ $xfields_type ]['values'] ) ? (array) $xfields_rules[ $xfields_type ]['values'] : [];
    $xfields_rules[ $xfields_type ]['operator'] = isset( $xfields_rules[ $xfields_type ]['operator'] ) ? $xfields_rules[ $xfields_type ]['operator'] : '==';
}

// Post types
$xfields_post_types = [];
$xfields_types = get_post_types();
foreach ( $xfields_types as $xfields_post_type ) {
    if ( ! in_array( $xfields_post_type, [ 'xfields_group', 'attachment', 'revision', 'nav_menu_item' ] ) ) {
        $xfields_post_types[ $xfields_post_type ] = $xfields_post_type;
    }
}

// Post formats
$xfields_post_formats = [];
if ( current_theme_supports( 'post-formats' ) ) {
    $xfields_post_formats = [ 'standard' => 'Standard' ];
    $xfields_post_formats_slugs = get_theme_support( 'post-formats' );

    if ( isset( $xfields_post_formats_slugs[0] ) && is_array( $xfields_post_formats_slugs[0] ) ) {
        foreach ( $xfields_post_formats_slugs[0] as $xfields_post_format ) {
            $xfields_post_formats[ $xfields_post_format ] = get_post_format_string( $xfields_post_format );
        }
    }
}

// User roles
foreach ( wp_roles()->roles as $xfields_key => $xfields_role ) {
    $xfields_user_roles[ $xfields_key ] = $xfields_key;
}

// Post IDs
$xfields_post_ids = [];
$xfields_json_posts = [];

$xfields_post_in = array_filter( array_map( 'absint', $xfields_rules['post_ids']['values'] ) );

if ( ! empty( $xfields_post_in ) ) {
    $xfields_results = get_posts( [
        'post__in'       => $xfields_post_in,
        'post_type'      => 'any',
        'post_status'    => 'any',
        'orderby'        => [ 'type' => 'ASC', 'title' => 'ASC' ],
        'posts_per_page' => count( $xfields_post_in ),
        'no_found_rows'  => true,
    ] );

    foreach ( $xfields_results as $xfields_result ) {
        $xfields_parent = '';

        if (
            isset( $xfields_result->post_parent ) &&
            absint( $xfields_result->post_parent ) > 0 &&
            $xfields_parent = get_post( $xfields_result->post_parent )
        ) {
            $xfields_parent = "$xfields_parent->post_title >";
        }

        $xfields_json_posts[] = [ 'id' => $xfields_result->ID, 'text' => "($xfields_result->post_type) $xfields_parent $xfields_result->post_title (#$xfields_result->ID)" ];
        $xfields_post_ids[] = (int) $xfields_result->ID;
    }
}

// Term IDs (all taxonomies except tags)
$xfields_results = get_terms( [
    'taxonomy'   => array_values( array_diff( get_taxonomies(), [ 'post_tag' ] ) ),
    'hide_empty' => false,
    'orderby'    => 'name',
] );
$xfields_results = is_wp_error( $xfields_results ) ? [] : $xfields_results;

foreach ( $xfields_results as $xfields_result ) {
    $xfields_term_ids[ $xfields_result->term_id ] = "($xfields_result->taxonomy) $xfields_result->name";
}

// Page templates
$xfields_page_templates = [];
$xfields_templates = get_page_templates();

foreach ( $xfields_templates as $xfields_template_name => $xfields_filename ) {
    $xfields_page_templates[ $xfields_filename ] = $xfields_template_name;
}


// Rules UI is initialised by assets/js/fields.js (Tom Select)
wp_add_inline_script( 'xfields-fields', 'window.XFields = Object.assign( window.XFields || {}, ' . wp_json_encode( [
    'rules' => [
        'nonce'       => wp_create_nonce( 'xfields_admin_nonce' ),
        'placeholder' => __( 'Leave blank to skip this rule', 'x-fields' ),
        'search'      => __( 'Type at least 2 characters…', 'x-fields' ),
        'no_results'  => __( 'No results', 'x-fields' ),
    ],
] ) . ' );', 'before' );
?>

<table>
    <tr>
        <td class="label">
            <label><?php esc_html_e( 'Post Types', 'x-fields' ); ?></label>
        </td>
        <td style="width:80px; vertical-align:top">
            <?php
                XFields()->create_field( [
                    'type' => 'select',
                    'input_name' => "xfields[rules][operator][post_types]",
                    'options' => [
                        'choices' => [
                            '==' => $xfields_equals_text,
                            '!=' => $xfields_not_equals_text,
                        ],
                        'force_single' => true,
                    ],
                    'value' => $xfields_rules['post_types']['operator'],
                ] );
            ?>
        </td>
        <td>
            <?php
                XFields()->create_field( [
                    'type' => 'select',
                    'input_class' => 'xfields-enhanced',
                    'input_name' => "xfields[rules][post_types]",
                    'options' => [ 'multiple' => '1', 'choices' => $xfields_post_types ],
                    'value' => $xfields_rules['post_types']['values'],
                ] );
            ?>
        </td>
    </tr>
    <?php if ( current_theme_supports( 'post-formats' ) && count( $xfields_post_formats ) ) : ?>
        <tr>
            <td class="label">
                <label><?php esc_html_e( 'Post Formats', 'x-fields' ); ?></label>
            </td>
            <td style="width:80px; vertical-align:top">
                <?php
                XFields()->create_field( [
                    'type' => 'select',
                    'input_name' => "xfields[rules][operator][post_formats]",
                    'options' => [
                        'choices' => [
                            '==' => $xfields_equals_text,
                            '!=' => $xfields_not_equals_text,
                        ],
                        'force_single' => true,
                    ],
                    'value' => $xfields_rules['post_formats']['operator'],
                ] );
                ?>
            </td>
            <td>
                <?php
                XFields()->create_field( [
                    'type' => 'select',
                    'input_class' => 'xfields-enhanced',
                    'input_name' => "xfields[rules][post_formats]",
                    'options' => [ 'multiple' => '1', 'choices' => $xfields_post_formats ],
                    'value' => $xfields_rules['post_formats']['values'],
                ] );
                ?>
            </td>
        </tr>
    <?php endif; ?>
    <tr>
        <td class="label">
            <label><?php esc_html_e( 'User Roles', 'x-fields' ); ?></label>
        </td>
        <td style="width:80px; vertical-align:top">
            <?php
                XFields()->create_field( [
                    'type' => 'select',
                    'input_name' => "xfields[rules][operator][user_roles]",
                    'options' => [
                        'choices' => [
                            '==' => $xfields_equals_text,
                            '!=' => $xfields_not_equals_text,
                        ],
                        'force_single' => true,
                    ],
                    'value' => $xfields_rules['user_roles']['operator'],
                ] );
            ?>
        </td>
        <td>
            <?php
                XFields()->create_field( [
                    'type' => 'select',
                    'input_class' => 'xfields-enhanced',
                    'input_name' => "xfields[rules][user_roles]",
                    'options' => [ 'multiple' => '1', 'choices' => $xfields_user_roles ],
                    'value' => $xfields_rules['user_roles']['values'],
                ] );
            ?>
        </td>
    </tr>
    <tr>
        <td class="label">
            <label><?php esc_html_e('Posts', 'x-fields' ); ?></label>
        </td>
        <td style="width:80px; vertical-align:top">
            <?php
                XFields()->create_field( [
                    'type' => 'select',
                    'input_name' => "xfields[rules][operator][post_ids]",
                    'options' => [
                        'choices' => [
                            '==' => $xfields_equals_text,
                            '!=' => $xfields_not_equals_text,
                        ],
                        'force_single' => true,
                    ],
                    'value' => $xfields_rules['post_ids']['operator'],
                ] );
            ?>
        </td>
        <td>
            <select name="xfields[rules][post_ids][]" class="xfields-post-search" multiple>
                <?php foreach ( $xfields_json_posts as $xfields_item ) : ?>
                <option value="<?php echo (int) $xfields_item['id']; ?>" selected><?php echo esc_html( $xfields_item['text'] ); ?></option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
    <tr>
        <td class="label">
            <label><?php esc_html_e( 'Taxonomy Terms', 'x-fields' ); ?></label>
        </td>
        <td style="width:80px; vertical-align:top">
            <?php
                XFields()->create_field( [
                    'type' => 'select',
                    'input_name' => "xfields[rules][operator][term_ids]",
                    'options' => [
                        'choices' => [
                            '==' => $xfields_equals_text,
                            '!=' => $xfields_not_equals_text,
                        ],
                        'force_single' => true,
                    ],
                    'value' => $xfields_rules['term_ids']['operator'],
                ] );
            ?>
        </td>
        <td>
            <?php
                XFields()->create_field( [
                    'type' => 'select',
                    'input_class' => 'xfields-enhanced',
                    'input_name' => "xfields[rules][term_ids]",
                    'options' => [ 'multiple' => '1', 'choices' => $xfields_term_ids ],
                    'value' => $xfields_rules['term_ids']['values'],
                ] );
            ?>
        </td>
    </tr>
    <tr>
        <td class="label">
            <label><?php esc_html_e( 'Page Templates', 'x-fields' ); ?></label>
        </td>
        <td style="width:80px; vertical-align:top">
            <?php
                XFields()->create_field( [
                    'type' => 'select',
                    'input_name' => "xfields[rules][operator][page_templates]",
                    'options' => [
                        'choices' => [
                            '==' => $xfields_equals_text,
                            '!=' => $xfields_not_equals_text,
                        ],
                        'force_single' => true,
                    ],
                    'value' => $xfields_rules['page_templates']['operator'],
                ] );
            ?>
        </td>
        <td>
            <?php
                XFields()->create_field( [
                    'type' => 'select',
                    'input_class' => 'xfields-enhanced',
                    'input_name' => "xfields[rules][page_templates]",
                    'options' => [ 'multiple' => '1', 'choices' => $xfields_page_templates ],
                    'value' => $xfields_rules['page_templates']['values'],
                ] );
            ?>
        </td>
    </tr>
</table>

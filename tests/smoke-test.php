<?php
/**
 * CFS smoke / security test.
 * Run with: wp eval-file wp-content/plugins/custom-field-suite/tests/smoke-test.php
 * Creates temporary data and removes it at the end.
 */

error_reporting( E_ALL );
$GLOBALS['cfs_test_errors'] = [];
set_error_handler( function( $no, $str, $file, $line ) {
    if ( false !== strpos( $file, 'custom-field-suite' ) ) {
        $GLOBALS['cfs_test_errors'][] = "$str @ " . basename( $file ) . ":$line";
    }
    return false;
} );

$GLOBALS['cfs_pass'] = 0; $GLOBALS['cfs_fail'] = 0;
function cfs_t( $name, $cond ) {
    if ( $cond ) { $GLOBALS['cfs_pass']++; echo "  OK   $name\n"; }
    else { $GLOBALS['cfs_fail']++; echo "  FAIL $name\n"; }
}

wp_set_current_user( 1 );

// ---- Field group --------------------------------------------------------
$group_id = wp_insert_post( [ 'post_type' => 'cfs', 'post_status' => 'publish', 'post_title' => 'Test group' ] );
CFS()->field_group->save( [
    'post_id' => $group_id,
    'fields'  => [
        [ 'id' => 0, 'name' => 'title_<b>x', 'label' => 'Titolo<script>', 'type' => 'text', 'notes' => '', 'parent_id' => 0, 'options' => [ 'default_value' => '' ] ],
        [ 'id' => 0, 'name' => 'body', 'label' => 'Body', 'type' => 'wysiwyg', 'notes' => '', 'parent_id' => 0 ],
        [ 'id' => 0, 'name' => 'link', 'label' => 'Link', 'type' => 'hyperlink', 'notes' => '', 'parent_id' => 0, 'options' => [ 'format' => 'html' ] ],
        [ 'id' => 0, 'name' => 'related', 'label' => 'Related', 'type' => 'relationship', 'notes' => '', 'parent_id' => 0 ],
        [ 'id' => 0, 'name' => 'flag', 'label' => 'Flag', 'type' => 'true_false', 'notes' => '', 'parent_id' => 0 ],
        [ 'id' => 0, 'name' => 'choice', 'label' => 'Choice', 'type' => 'select', 'notes' => '', 'parent_id' => 0, 'options' => [ 'choices' => "a : Alpha\nb : Beta" ] ],
        [ 'id' => 0, 'name' => 'nope', 'label' => 'Bad type', 'type' => 'does_not_exist', 'notes' => '', 'parent_id' => 0 ],
    ],
    'rules'  => [ 'post_types' => [ 'page' ], 'operator' => [ 'post_types' => '==' ] ],
    'extras' => [ 'order' => '3<x>', 'context' => 'evil', 'hide_editor' => 'yes' ],
] );

$fields = get_post_meta( $group_id, 'cfs_fields', true );
$names = wp_list_pluck( $fields, 'name' );
cfs_t( 'unknown field type skipped (no fatal)', ! in_array( 'nope', $names, true ) && 6 === count( $fields ) );
cfs_t( 'field name sanitized', in_array( 'title_bx', $names, true ) );
cfs_t( 'field label sanitized', false === strpos( $fields[0]['label'], '<' ) );
$extras = get_post_meta( $group_id, 'cfs_extras', true );
cfs_t( 'extras sanitized', 3 === $extras['order'] && 'normal' === $extras['context'] && 1 === $extras['hide_editor'] );

// Loop with a sub-field (needs the loop ID first)
$fields[] = [ 'id' => 0, 'name' => 'rows', 'label' => 'Rows', 'type' => 'loop', 'notes' => '', 'parent_id' => 0 ];
CFS()->field_group->save( [ 'post_id' => $group_id, 'fields' => $fields, 'rules' => [ 'post_types' => [ 'page' ], 'operator' => [ 'post_types' => '==' ] ], 'extras' => [] ] );
$fields = get_post_meta( $group_id, 'cfs_fields', true );
$loop_id = (int) wp_list_pluck( $fields, 'id', 'name' )['rows'];
$fields[] = [ 'id' => 0, 'name' => 'row_text', 'label' => 'Row text', 'type' => 'text', 'notes' => '', 'parent_id' => $loop_id ];
CFS()->field_group->save( [ 'post_id' => $group_id, 'fields' => $fields, 'rules' => [ 'post_types' => [ 'page' ], 'operator' => [ 'post_types' => '==' ] ], 'extras' => [] ] );
CFS()->field_group->cache = null;
$ids = wp_list_pluck( get_post_meta( $group_id, 'cfs_fields', true ), 'id', 'name' );

// ---- Forged field ID can't hijack another group's field ------------------
$other_group = wp_insert_post( [ 'post_type' => 'cfs', 'post_status' => 'publish', 'post_title' => 'Other' ] );
CFS()->field_group->save( [ 'post_id' => $other_group, 'fields' => [
    [ 'id' => $ids['body'], 'name' => 'hijack', 'label' => 'x', 'type' => 'text', 'notes' => '', 'parent_id' => 0 ],
], 'rules' => [], 'extras' => [] ] );
$other_fields = get_post_meta( $other_group, 'cfs_fields', true );
cfs_t( 'forged field ID gets a new ID', (int) $other_fields[0]['id'] !== (int) $ids['body'] );
CFS()->field_group->cache = null;

// ---- Save + read via API --------------------------------------------------
$target = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Target' ] );
$page   = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Test page' ] );

CFS()->save( [
    'title_bx' => 'Hello & <b>world</b>',
    'body'     => '<p>Body</p>',
    'link'     => [ 'url' => 'https://example.com', 'text' => 'Example', 'target' => '_blank' ],
    'related'  => [ $target ],
    'flag'     => 1,
    'choice'   => [ 'b' ],
    'rows'     => [ [ 'row_text' => 'r1' ], [ 'row_text' => 'r2' ] ],
], [ 'ID' => $page ] );

CFS()->api->cache = [];
$all = CFS()->get( false, $page );
cfs_t( 'text roundtrip', 'Hello & <b>world</b>' === $all['title_bx'] );
cfs_t( 'hyperlink html has rel=noopener', false !== strpos( $all['link'], 'rel="noopener noreferrer"' ) );
cfs_t( 'relationship roundtrip', [ (string) $target ] == $all['related'] || [ $target ] == $all['related'] );
cfs_t( 'true_false roundtrip', 1 === $all['flag'] );
cfs_t( 'select roundtrip', [ 'b' => 'Beta' ] === $all['choice'] );
cfs_t( 'loop roundtrip', isset( $all['rows'][1]['row_text'] ) && 'r2' === $all['rows'][1]['row_text'] );
cfs_t( 'reverse related', in_array( $page, array_map( 'intval', CFS()->get_reverse_related( $target ) ), true ) );

// ---- SQL injection attempts ---------------------------------------------
$t0 = microtime( true );
$r = CFS()->get_reverse_related( $target, [ 'post_type' => [ "page') OR SLEEP(3) OR ('" ], 'field_name' => [ "x') OR 1=1 -- " ] ] );
cfs_t( 'get_reverse_related not injectable', ( microtime( true ) - $t0 ) < 2 && empty( $r ) );

// Malicious relationship value submitted through a form (input format)
wp_set_current_user( 0 );
CFS()->save( [
    $ids['related'] => [ 'value' => [ "1) OR SLEEP(3) OR (1" ] ],
], [ 'ID' => $page ], [ 'format' => 'input', 'field_groups' => [ $group_id ] ] );
wp_set_current_user( 1 );
CFS()->api->cache = [];
$stored = CFS()->get( 'related', $page, [ 'format' => 'raw' ] );
cfs_t( 'relationship stores only integer IDs', [ '1' ] == (array) $stored || [] == (array) $stored );

// ---- XSS: anonymous form input is filtered with KSES ---------------------
wp_set_current_user( 0 );
CFS()->save( [
    $ids['title_bx'] => [ 'value' => '<script>alert(1)</script>ok' ],
], [ 'ID' => $page ], [ 'format' => 'input', 'field_groups' => [ $group_id ] ] );
wp_set_current_user( 1 );
CFS()->api->cache = [];
cfs_t( 'script stripped for users without unfiltered_html', false === strpos( (string) CFS()->get( 'title_bx', $page ), '<script' ) );

// Hyperlink javascript: URL + bad target
CFS()->save( [ 'link' => [ 'url' => 'javascript:alert(1)', 'text' => 'x', 'target' => '" onmouseover="alert(1)' ] ], [ 'ID' => $page ] );
CFS()->api->cache = [];
$link = (string) CFS()->get( 'link', $page );
cfs_t( 'hyperlink blocks javascript: and bad target', false === stripos( $link, 'javascript' ) && false === strpos( $link, 'onmouseover' ) );

// ---- RCE via loop row key (old eval) -------------------------------------
global $wpdb;
$evil = "0]);file_put_contents('/tmp/cfs_pwned','x');//";
CFS()->save( [
    $ids['rows'] => [ $evil => [ $ids['row_text'] => [ 'value' => 'pwn' ] ] ],
], [ 'ID' => $page ], [ 'format' => 'input', 'field_groups' => [ $group_id ] ] );
$bad_rows = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}cfs_values WHERE post_id = %d AND hierarchy LIKE %s", $page, '%file_put%' ) );
cfs_t( 'non-integer loop keys are rejected on save', 0 === $bad_rows );

// Even with legacy malicious data in the DB, rendering must not eval it
$wpdb->insert( $wpdb->postmeta, [ 'post_id' => $page, 'meta_key' => 'row_text', 'meta_value' => 'legacy' ] );
$wpdb->insert( $wpdb->prefix . 'cfs_values', [ 'field_id' => $ids['row_text'], 'meta_id' => $wpdb->insert_id, 'post_id' => $page, 'base_field_id' => $ids['rows'], 'hierarchy' => $ids['rows'] . ':' . $evil . ':' . $ids['row_text'], 'depth' => 1 ] );
@unlink( '/tmp/cfs_pwned' );
CFS()->api->cache = [];
$GLOBALS['post'] = get_post( $page );
ob_start();
CFS()->create_field( [ 'type' => 'loop', 'id' => $ids['rows'], 'group_id' => $group_id ] );
ob_end_clean();
cfs_t( 'loop rendering does not execute stored code', ! file_exists( '/tmp/cfs_pwned' ) );

// ---- Sessions -------------------------------------------------------------
$s = new cfs_session();
cfs_t( 'session id is 32 hex chars', (bool) preg_match( '/^[a-f0-9]{32}$/', $s->session_id ) );
$s->set( [ 'post_id' => $page, 'front_end' => false ] );
cfs_t( 'session readable by owner', ! empty( $s->get() ) );
wp_set_current_user( 0 );
cfs_t( 'session not readable by another user', [] === $s->get() );
wp_set_current_user( 1 );

// ---- Rendering (admin form) -----------------------------------------------
$GLOBALS['post'] = get_post( $page );
CFS()->group_ids = [ $group_id ];
$html = CFS()->form( [ 'post_id' => $page, 'field_groups' => [ $group_id ], 'front_end' => false ] );
cfs_t( 'admin form renders', false !== strpos( $html, 'cfs[session_id]' ) );
$front = CFS()->form( [ 'post_id' => false, 'field_groups' => [ $group_id ], 'post_title' => 'Title <b>', 'front_end' => true ] );
cfs_t( 'front-end form renders (no global post)', false !== strpos( $front, '<form' ) && false !== strpos( $front, 'Title &lt;b&gt;' ) );

// ---- Import / export --------------------------------------------------------
$export = CFS()->field_group->export( [ 'field_groups' => [ $group_id ] ] );
cfs_t( 'export works', ! empty( $export[0]['cfs_fields'] ) );
cfs_t( 'export with no groups is safe', [] === CFS()->field_group->export( [] ) );
$export[0]['post_name'] = 'imported-copy';
$export[0]['post_title'] = '<img src=x onerror=alert(1)>';
$msg = CFS()->field_group->import( [ 'import_code' => $export ] );
cfs_t( 'import escapes output', false === strpos( $msg, '<img' ) );
cfs_t( 'import with garbage is safe', 0 === strpos( CFS()->field_group->import( [ 'import_code' => [ 'x', 5, null ] ] ), '<div>' . esc_html__( 'Nothing to import', 'cfs' ) ) );

// ---- Cleanup ----------------------------------------------------------------
$imported = get_page_by_path( 'imported-copy', OBJECT, 'cfs' );
foreach ( array_filter( [ $group_id, $other_group, $target, $page, $imported ? $imported->ID : 0 ] ) as $id ) {
    wp_delete_post( $id, true );
}

echo "\nPHP notices/warnings from CFS: " . count( $GLOBALS['cfs_test_errors'] ) . "\n";
foreach ( array_unique( $GLOBALS['cfs_test_errors'] ) as $e ) { echo "  - $e\n"; }
echo "\nResult: {$GLOBALS['cfs_pass']} passed, {$GLOBALS['cfs_fail']} failed\n";

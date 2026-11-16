<?php
/**
 * XFields smoke / security test.
 * Run with: wp eval-file wp-content/plugins/custom-field-suite/tests/smoke-test.php
 * Creates temporary data and removes it at the end.
 */

error_reporting( E_ALL );
$GLOBALS['xfields_test_errors'] = [];
set_error_handler( function( $no, $str, $file, $line ) {
    if ( false !== strpos( $file, 'custom-field-suite' ) ) {
        $GLOBALS['xfields_test_errors'][] = "$str @ " . basename( $file ) . ":$line";
    }
    return false;
} );

$GLOBALS['xfields_pass'] = 0; $GLOBALS['xfields_fail'] = 0;
function xfields_t( $name, $cond ) {
    if ( $cond ) { $GLOBALS['xfields_pass']++; echo "  OK   $name\n"; }
    else { $GLOBALS['xfields_fail']++; echo "  FAIL $name\n"; }
}

wp_set_current_user( 1 );

$GLOBALS['xfields_test_posts'] = [];
$GLOBALS['xfields_test_users'] = [];
function xfields_test_post( $args ) { $id = wp_insert_post( $args ); $GLOBALS['xfields_test_posts'][] = $id; return $id; }
function xfields_test_user( $role ) {
    $id = wp_insert_user( [ 'user_login' => 'xfields_test_' . $role . '_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => $role ] );
    $GLOBALS['xfields_test_users'][] = $id;
    return $id;
}
register_shutdown_function( function() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/user.php';
    $imported = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'xfields_group' AND post_name = 'imported-copy'" );
    foreach ( array_filter( array_merge( $GLOBALS['xfields_test_posts'], [ $imported ] ) ) as $id ) {
        wp_delete_post( $id, true );
    }
    foreach ( array_filter( $GLOBALS['xfields_test_users'] ) as $uid ) {
        wp_delete_user( $uid );
    }
} );

// ---- Field group --------------------------------------------------------
$group_id = xfields_test_post( [ 'post_type' => 'xfields_group', 'post_status' => 'publish', 'post_title' => 'Test group' ] );
XFields()->field_group->save( [
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

$fields = get_post_meta( $group_id, 'xfields_fields', true );
$names = wp_list_pluck( $fields, 'name' );
xfields_t( 'unknown field type skipped (no fatal)', ! in_array( 'nope', $names, true ) && 6 === count( $fields ) );
xfields_t( 'field name sanitized', in_array( 'title_bx', $names, true ) );
xfields_t( 'field label sanitized', false === strpos( $fields[0]['label'], '<' ) );
$extras = get_post_meta( $group_id, 'xfields_extras', true );
xfields_t( 'extras sanitized', 3 === $extras['order'] && 'normal' === $extras['context'] && 1 === $extras['hide_editor'] );

// Loop with a sub-field (needs the loop ID first)
$fields[] = [ 'id' => 0, 'name' => 'rows', 'label' => 'Rows', 'type' => 'loop', 'notes' => '', 'parent_id' => 0 ];
XFields()->field_group->save( [ 'post_id' => $group_id, 'fields' => $fields, 'rules' => [ 'post_types' => [ 'page' ], 'operator' => [ 'post_types' => '==' ] ], 'extras' => [] ] );
$fields = get_post_meta( $group_id, 'xfields_fields', true );
$loop_id = (int) wp_list_pluck( $fields, 'id', 'name' )['rows'];
$fields[] = [ 'id' => 0, 'name' => 'row_text', 'label' => 'Row text', 'type' => 'text', 'notes' => '', 'parent_id' => $loop_id ];
XFields()->field_group->save( [ 'post_id' => $group_id, 'fields' => $fields, 'rules' => [ 'post_types' => [ 'page' ], 'operator' => [ 'post_types' => '==' ] ], 'extras' => [] ] );
XFields()->field_group->cache = null;
$ids = wp_list_pluck( get_post_meta( $group_id, 'xfields_fields', true ), 'id', 'name' );

// ---- Forged field ID can't hijack another group's field ------------------
$other_group = xfields_test_post( [ 'post_type' => 'xfields_group', 'post_status' => 'publish', 'post_title' => 'Other' ] );
XFields()->field_group->save( [ 'post_id' => $other_group, 'fields' => [
    [ 'id' => $ids['body'], 'name' => 'hijack', 'label' => 'x', 'type' => 'text', 'notes' => '', 'parent_id' => 0 ],
], 'rules' => [], 'extras' => [] ] );
$other_fields = get_post_meta( $other_group, 'xfields_fields', true );
xfields_t( 'forged field ID gets a new ID', (int) $other_fields[0]['id'] !== (int) $ids['body'] );
XFields()->field_group->cache = null;

// ---- Save + read via API --------------------------------------------------
$target = xfields_test_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Target' ] );
$page = xfields_test_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Test page' ] );

XFields()->save( [
    'title_bx' => 'Hello & <b>world</b>',
    'body'     => '<p>Body</p>',
    'link'     => [ 'url' => 'https://example.com', 'text' => 'Example', 'target' => '_blank' ],
    'related'  => [ $target ],
    'flag'     => 1,
    'choice'   => [ 'b' ],
    'rows'     => [ [ 'row_text' => 'r1' ], [ 'row_text' => 'r2' ] ],
], [ 'ID' => $page ] );

XFields()->api->cache = [];
$all = XFields()->get( false, $page );
xfields_t( 'text roundtrip', 'Hello & <b>world</b>' === $all['title_bx'] );
xfields_t( 'hyperlink html has rel=noopener', false !== strpos( $all['link'], 'rel="noopener noreferrer"' ) );
xfields_t( 'relationship roundtrip', [ (string) $target ] == $all['related'] || [ $target ] == $all['related'] );
xfields_t( 'true_false roundtrip', 1 === $all['flag'] );
xfields_t( 'select roundtrip', [ 'b' => 'Beta' ] === $all['choice'] );
xfields_t( 'loop roundtrip', isset( $all['rows'][1]['row_text'] ) && 'r2' === $all['rows'][1]['row_text'] );
xfields_t( 'reverse related', in_array( $page, array_map( 'intval', XFields()->get_reverse_related( $target ) ), true ) );

// ---- SQL injection attempts ---------------------------------------------
$t0 = microtime( true );
$r = XFields()->get_reverse_related( $target, [ 'post_type' => [ "page') OR SLEEP(3) OR ('" ], 'field_name' => [ "x') OR 1=1 -- " ] ] );
xfields_t( 'get_reverse_related not injectable', ( microtime( true ) - $t0 ) < 2 && empty( $r ) );

// Malicious relationship value submitted through a form (input format)
wp_set_current_user( 0 );
XFields()->save( [
    $ids['related'] => [ 'value' => [ "1) OR SLEEP(3) OR (1" ] ],
], [ 'ID' => $page ], [ 'format' => 'input', 'field_groups' => [ $group_id ] ] );
wp_set_current_user( 1 );
XFields()->api->cache = [];
$stored = XFields()->get( 'related', $page, [ 'format' => 'raw' ] );
xfields_t( 'relationship stores only integer IDs', [ '1' ] == (array) $stored || [] == (array) $stored );

// ---- XSS: anonymous form input is filtered with KSES ---------------------
wp_set_current_user( 0 );
XFields()->save( [
    $ids['title_bx'] => [ 'value' => '<script>alert(1)</script>ok' ],
], [ 'ID' => $page ], [ 'format' => 'input', 'field_groups' => [ $group_id ] ] );
wp_set_current_user( 1 );
XFields()->api->cache = [];
xfields_t( 'script stripped for users without unfiltered_html', false === strpos( (string) XFields()->get( 'title_bx', $page ), '<script' ) );

// Hyperlink javascript: URL + bad target
XFields()->save( [ 'link' => [ 'url' => 'javascript:alert(1)', 'text' => 'x', 'target' => '" onmouseover="alert(1)' ] ], [ 'ID' => $page ] );
XFields()->api->cache = [];
$link = (string) XFields()->get( 'link', $page );
xfields_t( 'hyperlink blocks javascript: and bad target', false === stripos( $link, 'javascript' ) && false === strpos( $link, 'onmouseover' ) );

// ---- RCE via loop row key (old eval) -------------------------------------
global $wpdb;
$evil = "0]);file_put_contents('/tmp/xfields_pwned','x');//";
XFields()->save( [
    $ids['rows'] => [ $evil => [ $ids['row_text'] => [ 'value' => 'pwn' ] ] ],
], [ 'ID' => $page ], [ 'format' => 'input', 'field_groups' => [ $group_id ] ] );
$bad_rows = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}xfields_values WHERE post_id = %d AND hierarchy LIKE %s", $page, '%file_put%' ) );
xfields_t( 'non-integer loop keys are rejected on save', 0 === $bad_rows );

// Even with legacy malicious data in the DB, rendering must not eval it
$wpdb->insert( $wpdb->postmeta, [ 'post_id' => $page, 'meta_key' => 'row_text', 'meta_value' => 'legacy' ] );
$wpdb->insert( $wpdb->prefix . 'xfields_values', [ 'field_id' => $ids['row_text'], 'meta_id' => $wpdb->insert_id, 'post_id' => $page, 'base_field_id' => $ids['rows'], 'hierarchy' => $ids['rows'] . ':' . $evil . ':' . $ids['row_text'], 'depth' => 1 ] );
@unlink( '/tmp/xfields_pwned' );
XFields()->api->cache = [];
$GLOBALS['post'] = get_post( $page );
ob_start();
XFields()->create_field( [ 'type' => 'loop', 'id' => $ids['rows'], 'group_id' => $group_id ] );
ob_end_clean();
xfields_t( 'loop rendering does not execute stored code', ! file_exists( '/tmp/xfields_pwned' ) );

// ---- Sessions -------------------------------------------------------------
$s = new xfields_session();
xfields_t( 'session id is 32 hex chars', (bool) preg_match( '/^[a-f0-9]{32}$/', $s->session_id ) );
$s->set( [ 'post_id' => $page, 'front_end' => false ] );
xfields_t( 'session readable by owner', ! empty( $s->get() ) );
wp_set_current_user( 0 );
xfields_t( 'session not readable by another user', [] === $s->get() );
wp_set_current_user( 1 );

// ---- Rendering (admin form) -----------------------------------------------
$GLOBALS['post'] = get_post( $page );
XFields()->group_ids = [ $group_id ];
$html = XFields()->form( [ 'post_id' => $page, 'field_groups' => [ $group_id ], 'front_end' => false ] );
xfields_t( 'admin form renders', false !== strpos( $html, 'xfields[session_id]' ) );
$front = XFields()->form( [ 'post_id' => false, 'field_groups' => [ $group_id ], 'post_title' => 'Title <b>', 'front_end' => true ] );
xfields_t( 'front-end form renders (no global post)', false !== strpos( $front, '<form' ) && false !== strpos( $front, 'Title &lt;b&gt;' ) );

// ---- Import / export --------------------------------------------------------
$export = XFields()->field_group->export( [ 'field_groups' => [ $group_id ] ] );
xfields_t( 'export works', ! empty( $export[0]['xfields_fields'] ) );
xfields_t( 'export with no groups is safe', [] === XFields()->field_group->export( [] ) );
$export[0]['post_name'] = 'imported-copy';
$export[0]['post_title'] = '<img src=x onerror=alert(1)>';
$msg = XFields()->field_group->import( [ 'import_code' => $export ] );
xfields_t( 'import escapes output', false === strpos( $msg, '<img' ) );
xfields_t( 'import with garbage is safe', 0 === strpos( XFields()->field_group->import( [ 'import_code' => [ 'x', 5, null ] ] ), '<div>' . esc_html__( 'Nothing to import', 'x-fields' ) ) );

// ---- Round 2 hardening ------------------------------------------------------
// Editors can't manage field groups
$editor = xfields_test_user( 'editor' );
wp_set_current_user( $editor );
xfields_t( 'editor cannot edit field groups', ! current_user_can( 'edit_post', $group_id ) && ! current_user_can( get_post_type_object( 'xfields_group' )->cap->create_posts ) );
wp_set_current_user( 1 );
xfields_t( 'admin can edit field groups', current_user_can( 'edit_post', $group_id ) );

// Select only accepts configured choices
XFields()->save( [ 'choice' => [ 'b', '<script>x</script>', 'zzz' ] ], [ 'ID' => $page ] );
XFields()->api->cache = [];
xfields_t( 'select drops values outside choices', [ 'b' => 'Beta' ] === XFields()->get( 'choice', $page ) );

// true_false is normalized
XFields()->save( [ 'flag' => 'yes<script>' ], [ 'ID' => $page ] );
XFields()->api->cache = [];
xfields_t( 'true_false normalized to 0/1', in_array( XFields()->get( 'flag', $page, [ 'format' => 'raw' ] ), [ '0', '1', 0, 1 ], true ) );

// Anonymous visitors can't inject shortcodes
wp_set_current_user( 0 );
XFields()->save( [ $ids['title_bx'] => [ 'value' => '[gallery ids="1"]hello' ] ], [ 'ID' => $page ], [ 'format' => 'input', 'field_groups' => [ $group_id ] ] );
wp_set_current_user( 1 );
XFields()->api->cache = [];
xfields_t( 'anonymous shortcodes neutralized', false === strpos( (string) XFields()->get( 'title_bx', $page ), '[gallery' ) );

// Field options are not double-encoded on re-save
$f = new xfields_text();
xfields_t( 'get_option returns raw value (no double encoding)', 'A & B' === $f->get_option( (object) [ 'options' => [ 'default_value' => 'A & B' ] ], 'default_value' ) );

// Open redirect protection used by front-end forms
xfields_t( 'protocol-relative redirect rejected', home_url( '/' ) === wp_validate_redirect( esc_url_raw( '//evil.example/x' ), home_url( '/' ) ) );

// Relationship field never reveals unreadable posts
$private = xfields_test_post( [ 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Secret draft title', 'post_author' => 1 ] );
$contrib = xfields_test_user( 'contributor' );
wp_set_current_user( $contrib );
ob_start();
XFields()->create_field( [ 'type' => 'relationship', 'value' => (string) $private, 'options' => [ 'post_types' => [ 'page' ] ] ] );
$rel_html = ob_get_clean();
wp_set_current_user( 1 );
xfields_t( 'relationship hides unreadable post titles', false === strpos( $rel_html, 'Secret draft title' ) );

// User field shows display names, not login names
wp_set_current_user( $contrib );
ob_start();
XFields()->create_field( [ 'type' => 'user', 'value' => '' ] );
$user_html = ob_get_clean();
wp_set_current_user( 1 );
xfields_t( 'user field does not expose login names', false === strpos( $user_html, '>' . get_userdata( $contrib )->user_login . '<' ) );

// Expired sessions are purged
$wpdb->insert( $wpdb->prefix . 'xfields_sessions', [ 'id' => str_repeat( 'a', 32 ), 'data' => 'x', 'expires' => (string) ( time() - 10 ) ] );
( new xfields_session() )->cleanup();
xfields_t( 'expired sessions purged', 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}xfields_sessions WHERE id = '" . str_repeat( 'a', 32 ) . "'" ) );
xfields_t( 'session cleanup cron scheduled', (bool) wp_next_scheduled( 'xfields_cleanup_sessions' ) );


// ---- Server-side validation (1.1.0) ------------------------------------------
$vg = xfields_test_post( [ 'post_type' => 'xfields_group', 'post_status' => 'publish', 'post_title' => 'Validation' ] );
XFields()->field_group->save( [ 'post_id' => $vg, 'fields' => [
    [ 'id' => 0, 'name' => 'v_req', 'label' => 'Required text', 'type' => 'text', 'options' => [ 'required' => 1 ] ],
    [ 'id' => 0, 'name' => 'v_rel', 'label' => 'Two to three', 'type' => 'relationship', 'options' => [ 'limit_min' => 2, 'limit_max' => 3 ] ],
    [ 'id' => 0, 'name' => 'v_loop', 'label' => 'Rows', 'type' => 'loop', 'options' => [ 'limit_min' => 1 ] ],
], 'rules' => [], 'extras' => [] ] );
$vf = get_post_meta( $vg, 'xfields_fields', true );
$vids = wp_list_pluck( $vf, 'id', 'name' );
$vf[] = [ 'id' => 0, 'name' => 'v_row_req', 'label' => 'Row title', 'type' => 'text', 'parent_id' => $vids['v_loop'], 'options' => [ 'required' => 1 ] ];
XFields()->field_group->save( [ 'post_id' => $vg, 'fields' => $vf, 'rules' => [], 'extras' => [] ] );
XFields()->field_group->cache = null;
$vids = wp_list_pluck( get_post_meta( $vg, 'xfields_fields', true ), 'id', 'name' );

$errs = XFields()->api->validate_input( [], [ $vg ] );
xfields_t( 'validation: empty submission reports required + limits', 3 === count( $errs ) );
$errs = XFields()->api->validate_input( [
    $vids['v_req'] => [ 'value' => '   ' ],
    $vids['v_rel'] => [ 'value' => '5,6,7,8' ],
    $vids['v_loop'] => [ 0 => [ $vids['v_row_req'] => [ 'value' => [ '' ] ] ] ],
], [ $vg ] );
xfields_t( 'validation: whitespace, too many items, empty required row field', 3 === count( $errs ) );
$errs = XFields()->api->validate_input( [
    $vids['v_req'] => [ 'value' => 'ok' ],
    $vids['v_rel'] => [ 'value' => '5,6' ],
    $vids['v_loop'] => [ 0 => [ $vids['v_row_req'] => [ 'value' => [ 'Row 1' ] ] ] ],
], [ $vg ] );
xfields_t( 'validation: valid submission passes', [] === $errs );
$errs = XFields()->api->validate_input( [ $vids['v_req'] => 'not-an-array', $vids['v_loop'] => 'x' ], [ $vg ] );
xfields_t( 'validation: malformed input handled', 3 === count( $errs ) );

// ---- Cleanup happens in the shutdown function (also after fatal errors) ----

echo "\nPHP notices/warnings from XFields: " . count( $GLOBALS['xfields_test_errors'] ) . "\n";
foreach ( array_unique( $GLOBALS['xfields_test_errors'] ) as $e ) { echo "  - $e\n"; }
echo "\nResult: {$GLOBALS['xfields_pass']} passed, {$GLOBALS['xfields_fail']} failed\n";

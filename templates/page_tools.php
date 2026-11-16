<?php

defined( 'ABSPATH' ) || exit;

global $wpdb;

$sql = "
SELECT ID, post_title
FROM $wpdb->posts
WHERE post_type = 'xf_group' AND post_status = 'publish'
ORDER BY post_title";
$results = $wpdb->get_results($sql); // phpcs:ignore WordPress.DB.PreparedSQL -- only table names, constants or integer IDs are interpolated
?>

<style type="text/css">
.xf-tools { --xf-accent: var(--wp-admin-theme-color, #2271b1); max-width: 960px; }
.xf-tools .nav-tab-wrapper { margin-bottom: 0; }
.xf-tools .nav-tab { cursor: pointer; }
.xf-tools .tab-content { display: none; padding: 20px 24px; border: 1px solid #c3c4c7; border-top: 0; background: #fff; }
.xf-tools .tab-content.active { display: block; }
.xf-tools .tab-content h2 { margin-top: 0; }
.xf-tools .xf-tools-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 20px; }
.xf-tools select[multiple], .xf-tools textarea { width: 100%; min-height: 180px; font-family: Menlo, Consolas, monospace; font-size: 12px; }
.xf-tools select[multiple] { font-family: inherit; font-size: 13px; }
.xf-tools .xf-tools-actions { margin-top: 12px; }
.xf-tools #import-message:not(:empty) { margin-top: 12px; padding: 8px 12px; border-left: 4px solid var(--xf-accent); background: #f6f7f7; }
.xf-tools .xf-danger-zone { border-left: 4px solid #d63638; }
.xf-tools .button.xf-button-danger { border-color: #d63638; color: #d63638; }
.xf-tools .button.xf-button-danger:hover { background: #d63638; color: #fff; }
@media (max-width: 782px) { .xf-tools .xf-tools-grid { grid-template-columns: 1fr; } }
</style>

<script>
(function($) {
    $(function() {
        var xf_nonce = <?php echo wp_json_encode( wp_create_nonce( 'xf_admin_nonce' ) ); ?>;

        $('.nav-tab').click(function() {
            $('.tab-content').removeClass('active');
            $('.nav-tab').removeClass('nav-tab-active');
            $('.tab-content.' + $(this).attr('rel')).addClass('active');
            $(this).addClass('nav-tab-active');
        });

        $('#button-export').click(function() {
            var groups = $('#export-field-groups').val();
            if (null != groups) {
                $.post(ajaxurl, {
                    action: 'xf_ajax_handler',
                    action_type: 'export',
                    nonce: xf_nonce,
                    field_groups: $('#export-field-groups').val()
                },
                function(response) {
                    $('#export-output').text(response);
                    $('#export-area').show();
                });
            }
        });

        $('#button-import').click(function() {
            $.post(ajaxurl, {
                action: 'xf_ajax_handler',
                action_type: 'import',
                nonce: xf_nonce,
                import_code: $('#import-code').val()
            },
            function(response) {
                $('#import-message').html(response);
            });
        });

        $('#button-reset').click(function() {
            if (confirm(<?php echo wp_json_encode( __( 'This will delete all X-Fields data. Are you sure?', 'x-fields' ) ); ?>)) {
                $.post(ajaxurl, {
                    action: 'xf_ajax_handler',
                    action_type: 'reset',
                    nonce: xf_nonce
                },
                function(response) {
                    window.location.replace(response);
                });
            }
        });
    });
})(jQuery);
</script>

<div class="wrap xf-tools">
    <h1><?php esc_html_e( 'Tools', 'x-fields' ); ?></h1>

    <p class="description"><?php esc_html_e( 'Move field groups between sites, or remove all X-Fields data.', 'x-fields' ); ?></p>

    <h2 class="nav-tab-wrapper">
        <a class="nav-tab nav-tab-active" rel="export"><?php esc_html_e('Export', 'x-fields' ); ?></a>
        <a class="nav-tab" rel="import"><?php esc_html_e('Import', 'x-fields' ); ?></a>
        <a class="nav-tab" rel="reset"><?php esc_html_e('Reset', 'x-fields' ); ?></a>
    </h2>

    <div class="content-container">

        <!-- Export -->

        <div class="tab-content export active">
            <div class="xf-tools-grid">
                <div>
                    <h2><?php esc_html_e( 'Export field groups', 'x-fields' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Select one or more groups (Ctrl/Cmd + click), then copy the generated code.', 'x-fields' ); ?></p>
                    <div>
                            <select id="export-field-groups" multiple="multiple">
                                <?php foreach ($results as $result) : ?>
                                <option value="<?php echo (int) $result->ID; ?>"><?php echo esc_html( $result->post_title ); ?></option>
                                <?php endforeach; ?>
                            </select>
                    </div>
                    <div class="xf-tools-actions">
                        <button type="button" id="button-export" class="button button-primary"><?php esc_html_e( 'Generate export code', 'x-fields' ); ?></button>
                    </div>
                </div>
                <div id="export-area" style="display:none">
                    <h2><?php esc_html_e( 'Export code', 'x-fields' ); ?></h2>
                    <textarea id="export-output" readonly onclick="this.select()"></textarea>
                </div>
            </div>
        </div>

        <!-- Import -->

        <div class="tab-content import">
            <h2><?php esc_html_e( 'Import field groups', 'x-fields' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Groups whose slug already exists on this site are skipped.', 'x-fields' ); ?></p>
            <textarea id="import-code" placeholder="<?php esc_attr_e( 'Paste the import code here', 'x-fields' ); ?>"></textarea>
            <div class="xf-tools-actions"><button type="button" id="button-import" class="button button-primary"><?php esc_html_e( 'Import', 'x-fields' ); ?></button></div>
            <div id="import-message"></div>
        </div>

        <!-- Reset -->

        <div class="tab-content reset xf-danger-zone">
            <h2><?php esc_html_e('Reset and deactivate.', 'x-fields' ); ?></h2>
            <p><?php esc_html_e('This will delete all X-Fields data and deactivate the plugin.', 'x-fields' ); ?></p>
            <button type="button" id="button-reset" class="button xf-button-danger"><?php esc_html_e( 'Delete everything', 'x-fields' ); ?></button>
        </div>
    </div>
</div>

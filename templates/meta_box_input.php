<?php

defined( 'ABSPATH' ) || exit;

XF()->form->load_assets();

// The form HTML is escaped field by field inside xf_form::render()
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
echo XF()->form( [
    'post_id'       => (int) $post->ID,
    'field_groups'  => (int) $metabox['args']['group_id'],
    'front_end'     => false,
] );
// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

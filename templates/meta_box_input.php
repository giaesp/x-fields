<?php

defined( 'ABSPATH' ) || exit;

XF()->form->load_assets();

echo XF()->form( [
    'post_id'       => $post->ID,
    'field_groups'  => $metabox['args']['group_id'],
    'front_end'     => false,
] );

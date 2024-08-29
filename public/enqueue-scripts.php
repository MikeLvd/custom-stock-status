<?php

function enqueue_custom_public_styles()
{
    wp_enqueue_style('front-custom-stock-status-styles', plugins_url('/assets/css/front-custom-stock-status.css', __DIR__), array(), '1.0', 'all');
}

add_action('wp_enqueue_scripts', 'enqueue_custom_public_styles');

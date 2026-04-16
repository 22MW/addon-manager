<?php
/*
Plugin Name: Debug Elementor related products args
Description: Addon de depuración que registra en error_log los argumentos de productos relacionados enviados por Elementor Pro.
Marketing Description: Diagnóstico rápido para detectar conflictos en productos relacionados de Elementor Pro.
Parameters: Sin configuración: pensado para depuración puntual en logs.
Version: 1.0
*/

add_filter('elementor_pro/woocommerce/query/products/query_args', function($query_args) {
    error_log('DEBUG elementor_pro query_args[posts_per_page]: ' . var_export($query_args['posts_per_page'] ?? null, true));
    return $query_args;
}, 999);

add_filter('elementor_pro/woocommerce/widgets/query_args', function($args) {
    error_log('DEBUG elementor_pro widget args[posts_per_page]: ' . var_export($args['posts_per_page'] ?? null, true));
    return $args;
}, 999);

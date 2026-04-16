<?php
/**
 * Plugin Name: Limpiar Transients WC
 * Description: Desactiva caché de filtros y limpia transients de WooCommerce de forma periódica.
 * Version: 1.4
 */

// 1. Desactiva cache de filtros
add_filter('woocommerce_layered_nav_count_maybe_cache', '__return_false');

// 2. Limpieza forzada primera vez
add_action('init', function() {
    if (!get_option('limpieza_transients_inicial')) {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wc_layered_nav_counts%'");
        update_option('limpieza_transients_inicial', 1);
        update_option('ultima_limpieza_transients', current_time('mysql'));
    }
    
    $programado = wp_next_scheduled('limpiar_wc_transients_hora');
    if (!$programado || $programado < time()) {
        wp_clear_scheduled_hook('limpiar_wc_transients_hora');
        wp_schedule_event(time() + 3600, 'hourly', 'limpiar_wc_transients_hora');
    }
});

// 3. Limpieza programada
add_action('limpiar_wc_transients_hora', function() {
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wc_layered_nav_counts%'");
    update_option('ultima_limpieza_transients', current_time('mysql'));
});

// 4. Mensaje en admin
add_action('admin_notices', function() {
    if (!current_user_can('manage_options')) return;
    
    global $wpdb;
    $size = $wpdb->get_var("SELECT ROUND(SUM(LENGTH(option_value))/1024/1024, 2) FROM {$wpdb->options} WHERE option_name LIKE '_transient_wc_layered_nav_counts%'");
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_wc_layered_nav_counts%'");
    
    $ultima = get_option('ultima_limpieza_transients', 'Nunca');
    $programado = wp_next_scheduled('limpiar_wc_transients_hora');
    $proximo = $programado ? date('Y-m-d H:i:s', $programado) : 'No programado';
    
    echo '<div class="notice notice-success"><p>';
    echo '<strong>✅ Limpieza Transients WC:</strong><br>';
    echo 'Tamaño actual: ' . ($size ?: 0) . ' MB (' . $count . ' transients)<br>';
    echo 'Última limpieza: ' . $ultima . '<br>';
    echo 'Próxima limpieza: ' . $proximo;
    echo '</p></div>';
});

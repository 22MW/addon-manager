<?php
/*
Plugin Name: Desactivar Gutenberg y Comentarios en Multisite
Description: Desactiva editor de bloques y comentarios en todos los sitios de la red multisite.
Author: 22MW
Version: 1.0
*/

if (is_multisite()) {
    // Desactivar Gutenberg (Editor de Bloques)
    add_filter('use_block_editor_for_post', '__return_false', 10);

    // Desactivar Comentarios
    add_filter('comments_open', '__return_false', 20, 2);
    add_filter('pings_open', '__return_false', 20, 2);
    add_action('admin_menu', function() {
        remove_menu_page('edit-comments.php');
    });
    add_action('wp_dashboard_setup', function() {
        remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
    });
    add_action('admin_init', function() {
        global $pagenow;
        if ($pagenow === 'edit-comments.php') {
            wp_redirect(admin_url());
            exit;
        }
    });
    add_action('admin_bar_menu', function($wp_admin_bar) {
        $wp_admin_bar->remove_node('comments');
    }, 999);
    add_action('widgets_init', function() {
        unregister_widget('WP_Widget_Recent_Comments');
    });
}

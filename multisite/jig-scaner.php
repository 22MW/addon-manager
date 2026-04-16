<?php
add_action('network_admin_menu', function () {
    //add_menu_page('JIG Shortcode Scanner', 'JIG Scanner', 'manage_network', 'jig-scanner', 'jig_shortcode_report');
});

function jig_shortcode_report() {
    if (!is_super_admin()) {
        wp_die('Solo disponible para administradores de red');
    }

    echo '<div class="wrap"><h1>Uso de [justified_image_grid]</h1>';

    $sites = get_sites();
    foreach ($sites as $site) { 
        switch_to_blog($site->blog_id);
        $site_name = get_bloginfo('name');
        $site_url = get_site_url();

        // Obtener páginas que contienen el shortcode
        $args = [
            'post_type' => ['page', 'post'], // Puedes añadir otros tipos si lo necesitas
            'post_status' => 'publish',
            's' => '[justified_image_grid',
            'posts_per_page' => -1
        ];
        $query = new WP_Query($args);

        if ($query->have_posts()) {
            echo '<h2>' . esc_html($site_name) . '</h2>';
            echo '<ul>';
            while ($query->have_posts()) {
                $query->the_post();
                echo '<li><a href="' . get_permalink() . '" target="_blank">' . get_the_title() . '</a></li>';
            }
            echo '<li><a href="' . esc_url($site_url . '/wp-admin/admin.php?page=justified-image-grid') . '" target="_blank">🔧 Ajustes del plugin</a></li>';
            echo '</ul>';
        }

        wp_reset_postdata();
        restore_current_blog();
    }

    echo '</div>';
}


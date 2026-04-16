<?php
/**
 * Plugin Name: Multisite Info
 * Description: Panel en red con información de sitios y plugins en formato grid para auditoría multisite.
 * Author: 22MW
 * Version: 2.2
 */

if (!defined('WPINC')) {
    die;
}

// Solo ejecutar en Multisite
if (!is_multisite()) {
    return;
}

// Agregar menú en la administración de la red
function multisite_info_add_network_menu() {
    add_menu_page(
        'Multisite Info',
        'Multisite Info',
        'manage_network',
        'multisite-info',
        'multisite_info_display_page',
        'dashicons-networking',
        20
    );
}
add_action('network_admin_menu', 'multisite_info_add_network_menu');

// Agregar estilos CSS para la distribución en Grid
function multisite_info_enqueue_styles() {
    $custom_css = "
    .multisite-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        grid-auto-rows: auto;
        gap: 10px;
        padding: 10px;
    }
    .multisite-item {
        background: white;
        padding: 15px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        max-height: 390px;
        overflow: auto;
    }
    .multisite-item h4 {
        margin: 5px 0;
    }
    .multisite-item ul {
        margin: 0;
        padding: 0;
        list-style-type: none;
    }
    ";
    wp_add_inline_style('admin-bar', $custom_css);
}
add_action('admin_enqueue_scripts', 'multisite_info_enqueue_styles');

// Mostrar la página con pestañas
function multisite_info_display_page() {
    if (!current_user_can('manage_network')) {
        wp_die(__('No tienes permisos para acceder a esta página.'));
    }

    $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'sites';

    echo '<div class="wrap">';
    echo '<h1>Información del Multisite</h1>';

    // Menú de pestañas
    echo '<h2 class="nav-tab-wrapper">';
    echo '<a href="?page=multisite-info&tab=sites" class="nav-tab ' . ($tab == 'sites' ? 'nav-tab-active' : '') . '">Sitios</a>';
    echo '<a href="?page=multisite-info&tab=plugins" class="nav-tab ' . ($tab == 'plugins' ? 'nav-tab-active' : '') . '">Plugins - Usados - No Globales</a>';
    echo '<a href="?page=multisite-info&tab=global-plugins" class="nav-tab ' . ($tab == 'global-plugins' ? 'nav-tab-active' : '') . '">Plugins - No Usados y Globales</a>';

    echo '</h2>';

    // Cargar la pestaña correspondiente
    if ($tab == 'sites') {
        echo get_multisite_details();
    } elseif ($tab == 'plugins') {
        echo shortcode_plugins_usage();
    } else {
        echo get_global_plugins();
    }

    echo '</div>';
}

// Función para mostrar la información de cada sitio en formato Grid (SIN plugins de la red)
if (!function_exists('get_multisite_details')) {
    function get_multisite_details() {
        if (!is_multisite()) {
            return "Este WordPress no es un multisite.";
        }
        if (!current_user_can('administrator')) {
            return '<p>No tienes permisos para ver esta información.</p>';
        }

        global $wpdb;
        $sites = get_sites([
            'archived' => 0,
            'deleted' => 0,
            'spam' => 0,
            'number' => 10000,
        ]);

        $total_sites = count($sites);

        $output = '<div class="plugin-summary">';
        $output .= '<p>Total de Sitios: ' . esc_html($total_sites) . '</p>';
        $output .= '</div>';
        $output .= "<div class='multisite-grid'>";

        $themes_sites = [];

        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);

            $site_name = get_bloginfo('name');
            $site_url = get_bloginfo('url');
            $dashboard_url = admin_url();
            $plugins_url = admin_url('plugins.php');

            $theme = wp_get_theme();
            $theme_name = $theme->get('Name');
            $theme_version = $theme->get('Version');

            // Agrupar para la lista final
            $themes_sites[$theme_name . " (v" . $theme_version . ")"][] = [
                'blog_id' => $site->blog_id,
                'name' => $site_name,
                'url' => $site_url,
                'dashboard' => $dashboard_url
            ];

            // Plugins activos
            $active_plugins = get_option('active_plugins', []);
            $plugins = [];

            foreach ($active_plugins as $plugin_path) {
                $plugin_file = WP_PLUGIN_DIR . '/' . $plugin_path;
                if (file_exists($plugin_file)) {
                    $plugin_data = get_plugin_data($plugin_file);
                    $plugins[] = $plugin_data['Name'] . ' (' . $plugin_data['Version'] . ')';
                }
            }

            $output .= "<div class='multisite-item'>";
            $output .= "<h4><strong>Blog ID:</strong> {$site->blog_id}</h4>";
            $output .= "<h4><a style='color:#000000' href='{$site_url}' target='_blank'>{$site_name}</a> — <a href='{$dashboard_url}' target='_blank'>Dashboard</a> — <a href='{$plugins_url}' target='_blank'> Plugins</a></h4>";
            $output .= "<h4><strong>Tema activo:</strong> {$theme_name} (v{$theme_version})</h4>";
            // Obtener versión desde opciones de JetEngine del sitio actual

            // Obtener la opción config-web
            $config_web = get_option('config-web', []);
            $jetengine_version = isset($config_web['versio_theme']) ? $config_web['versio_theme'] : '4.0';
            $output .= "<h4><strong>Versión:</strong> {$jetengine_version}</h4>";

// Verificar si Elementor está activo y obtener plantillas single
$elementor_active = is_plugin_active('elementor/elementor.php') || is_plugin_active('elementor-pro/elementor-pro.php');

if ($elementor_active) {
    $elementor_templates_url = admin_url('edit.php?post_type=elementor_library&tabs_group=library&elementor_library_type=single');
    
    // Obtener plantillas single de Elementor
    $templates = get_posts([
        'post_type' => 'elementor_library',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => '_elementor_template_type',
                'value' => 'single'
            ]
        ]
    ]);
    
    $template_count = count($templates);
    $output .= "<h4><strong>Elementor:</strong> Activo ({$template_count} plantillas single) — <a href='{$elementor_templates_url}' target='_blank'>Ver plantillas</a></h4>";
    
    if ($template_count > 0) {
        $output .= "<ul>";
        foreach ($templates as $template) {
            $edit_url = admin_url('post.php?post=' . $template->ID . '&action=elementor');
            $output .= "<li> &nbsp;&nbsp; <string>·</strong> <a href='{$edit_url}' target='_blank'>{$template->post_title}</a></li>";
        }
        $output .= "</ul>";
    }
} else {
    $output .= "<h4><strong>Elementor:</strong> No activo</h4>";
}





            $output .= "<h4><strong>Plugins activos:</strong></h4><ul>";
            foreach ($plugins as $plugin) {
                $output .= "<li>{$plugin}</li>";
            }
            $output .= "</ul></div>";

            restore_current_blog();
        }

        $output .= "</div>"; // Cierra Grid

        // Lista agrupada por tema
        $output .= "<div class='multisite-theme-list'>";
        $output .= "<h3>Lista agrupada por Tema:</h3>";

        foreach ($themes_sites as $theme_label => $sites) {
            $output .= "<h4>Tema activo: {$theme_label}</h4><ul>";
            foreach ($sites as $site) {
                $output .= "<li>- Blog ID: {$site['blog_id']} — <a href='{$site['url']}' target='_blank'>{$site['name']}</a> — <a href='{$site['dashboard']}' target='_blank'>Dashboard</a></li>";
            }
            $output .= "</ul>";
        }

        $output .= "</div>"; // Cierra lista agrupada

        return $output;
    }
}


// Función para mostrar la información de cada plugin en formato Grid (SIN plugins de la red)
if (!function_exists('shortcode_plugins_usage')) {
    function shortcode_plugins_usage() {
        if (!current_user_can('administrator')) {
            return '<p>No tienes permisos para ver esta información.</p>';
        }

        global $wpdb;
        $sites = get_sites([
            'archived' => 0,
            'deleted' => 0,
            'spam' => 0,
            'number' => 10000,
        ]);

        $plugin_usage = [];
        $all_plugins = get_plugins();

        // Inicializar contador para todos los plugins
        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $plugin_usage[$plugin_data['Name']] = [
                'count' => 0,
                'sites' => [],
            ];
        }

        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            
            $site_name = get_bloginfo('name');
            $site_url = home_url();
            $active_plugins = get_option('active_plugins', []);
            
            foreach ($active_plugins as $plugin_path) {
                // Buscar el plugin en la lista de todos los plugins
                $found = false;
                foreach ($all_plugins as $plugin_file => $plugin_data) {
                    if ($plugin_file === $plugin_path) {
                        $plugin_name = $plugin_data['Name'];
                        $plugin_usage[$plugin_name]['count']++;
                        $plugin_usage[$plugin_name]['sites'][] = [
                            'name' => $site_name,
                            'url' => $site_url,
                            'plugins_url' => admin_url('plugins.php'),
                        ];
                        $found = true;
                        break;
                    }
                }
                
                // Si no se encontró, podría ser un plugin que no está en get_plugins()
                if (!$found) {
                    $plugin_file_full = WP_PLUGIN_DIR . '/' . $plugin_path;
                    if (file_exists($plugin_file_full)) {
                        $plugin_data = get_plugin_data($plugin_file_full);
                        $plugin_name = $plugin_data['Name'] ?: $plugin_path;
                        
                        if (!isset($plugin_usage[$plugin_name])) {
                            $plugin_usage[$plugin_name] = [
                                'count' => 0,
                                'sites' => [],
                            ];
                        }
                        
                        $plugin_usage[$plugin_name]['count']++;
                        $plugin_usage[$plugin_name]['sites'][] = [
                            'name' => $site_name,
                            'url' => $site_url,
                            'plugins_url' => admin_url('plugins.php'),
                        ];
                    }
                }
            }
            
            restore_current_blog();
        }

        // Ordenar plugins por número de usos (descendente)
        uasort($plugin_usage, function($a, $b) {
            return $b['count'] - $a['count'];
        });

        $output = '<div class="multisite-grid">'; // Inicia Grid
        
        foreach ($plugin_usage as $plugin_name => $data) {
            if ($data['count'] > 0) { // Solo mostrar plugins usados en algún sitio
                $output .= "<div class='multisite-item'>";
                $output .= '<h3>' . esc_html($plugin_name) . ' (' . esc_html($data['count']) . ' sitios)</h3>';
                $output .= '<ul>';
                foreach ($data['sites'] as $site) {
                    $output .= "<li><a style='color:#000000' href='{$site['url']}' target='_blank'>{$site['name']}</a> — <a href='{$site['plugins_url']}' target='_blank'>Editar Plugins</a></li>";
                }
                $output .= '</ul>';
                $output .= '</div>';
            }
        }
        $output .= '</div>'; // Cierra Grid

        return $output;
    }
}

// Función para mostrar plugins globales y no usados en ninguna web
function get_global_plugins() {
    if (!current_user_can('administrator')) {
        return '<p>No tienes permisos para ver esta información.</p>';
    }

    $all_plugins = get_plugins();
    $used_plugins = [];
    $global_plugins = get_site_option('active_sitewide_plugins', []);

    $sites = get_sites(['archived' => 0, 'deleted' => 0, 'spam' => 0, 'number' => 10000]);
    
    foreach ($sites as $site) {
        switch_to_blog($site->blog_id);
        $active_plugins = get_option('active_plugins', []);
        $used_plugins = array_merge($used_plugins, $active_plugins);
        restore_current_blog();
    }

    $used_plugins = array_unique($used_plugins);
    
    $output = '<h2>Plugins No Usados</h2>';
    $output .= '<div class="multisite-grid">';
    foreach ($all_plugins as $plugin_file => $plugin_data) {
        if (!isset($global_plugins[$plugin_file])) {
            $is_used = in_array($plugin_file, $used_plugins) ? 'Sí' : 'No';
            if ($is_used === 'No') {
                $output .= "<div class='multisite-item'>";
                $output .= "<h3>" . esc_html($plugin_data['Name']) . "</h3>";
                $output .= "<p><strong>Versión:</strong> " . esc_html($plugin_data['Version']) . "</p>";
                $output .= "</div>";
            }
        }
    }
    $output .= '</div>';

    $output .= '<h2>Plugins Activados para la Red</h2>';
    $output .= '<div class="multisite-grid">';
    foreach ($global_plugins as $plugin_file => $timestamp) {
        $plugin_data = $all_plugins[$plugin_file] ?? [];
        if ($plugin_data) {
            $output .= "<div class='multisite-item'>";
            $output .= "<h3>" . esc_html($plugin_data['Name']) . "</h3>";
            $output .= "<p><strong>Versión:</strong> " . esc_html($plugin_data['Version']) . "</p>";
            $output .= "</div>";
        }
    }
    $output .= '</div>';

    return $output;
}

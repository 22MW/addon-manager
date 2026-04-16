<?php

/**
 * Plugin Name: Addon Manager
 * Plugin URI: https://22mw.online/
 * Description: Panel central para activar/desactivar mini-addons (WordPress, WooCommerce y Multisite) desde una única interfaz.
 * Version: 1.0.2
 * Author: 22MW
 * Author URI: https://22mw.online/
 * Update URI: https://github.com/22MW/addon-manager
 */

if (!defined('ABSPATH')) exit;

if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-github-updater.php';
    (new Addon_Manager_Github_Updater())->register_hooks();
}

class Addon_Manager
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_toggle_addon', array($this, 'toggle_addon'));
        add_action('wp_loaded', array($this, 'load_active_addons'));
    }

    public function load_active_addons()
    {
        $active_addons = get_option('active_addons', array());
        if (empty($active_addons)) return;

        $folders = array('addons', 'woo', 'multisite');

        foreach ($active_addons as $addon_file) {
            foreach ($folders as $folder) {
                $addon_path = plugin_dir_path(__FILE__) . $folder . '/' . $addon_file;
                if (file_exists($addon_path)) {
                    include_once $addon_path;
                    break;
                }
            }
        }
    }

    private function get_addons()
    {
        $addons = array(
            'wp' => array(),
            'woo' => array(),
            'multisite' => array()
        );

        $folders = array('addons' => 'wp', 'woo' => 'woo', 'multisite' => 'multisite');

        foreach ($folders as $folder => $tab) {
            $addon_dir = plugin_dir_path(__FILE__) . $folder . '/';

            if (!is_dir($addon_dir)) continue;

            $files = scandir($addon_dir);

            foreach ($files as $file) {
                if (substr($file, -4) === '.php') {
                    $plugin_data = get_file_data($addon_dir . $file, array(
                        'Name' => 'Plugin Name',
                        'Description' => 'Description',
                        'Version' => 'Version',
                        'LongDescription' => 'Long Description',
                        'MarketingDescription' => 'Marketing Description',
                        'Parameters' => 'Parameters'
                    ));

                    if ($plugin_data['Name']) {
                        $addons[$tab][] = array(
                            'file' => $file,
                            'path' => $addon_dir . $file,
                            'name' => $plugin_data['Name'],
                            'tab' => $tab,
                            'description' => $plugin_data['Description'],
                            'version' => $plugin_data['Version'],
                            'long_description' => $plugin_data['LongDescription'],
                            'marketing_description' => $plugin_data['MarketingDescription'],
                            'parameters' => $plugin_data['Parameters']
                        );
                    }
                }
            }
        }

        return $addons;
    }

    private function resolve_callback_file($callback)
    {
        try {
            if ($callback instanceof Closure) {
                $ref = new ReflectionFunction($callback);
                return (string) $ref->getFileName();
            }

            if (is_string($callback)) {
                if (function_exists($callback)) {
                    $ref = new ReflectionFunction($callback);
                    return (string) $ref->getFileName();
                }

                if (strpos($callback, '::') !== false) {
                    $parts = explode('::', $callback);
                    if (count($parts) === 2 && method_exists($parts[0], $parts[1])) {
                        $ref = new ReflectionMethod($parts[0], $parts[1]);
                        return (string) $ref->getFileName();
                    }
                }
            }

            if (is_array($callback) && count($callback) === 2) {
                if (method_exists($callback[0], $callback[1])) {
                    $ref = new ReflectionMethod($callback[0], $callback[1]);
                    return (string) $ref->getFileName();
                }
            }
        } catch (Throwable $e) {
            return '';
        }

        return '';
    }

    private function get_slug_from_hook($hook_name)
    {
        $hook_name = (string) $hook_name;
        if ($hook_name === '') {
            return '';
        }

        if (strpos($hook_name, 'toplevel_page_') === 0) {
            return substr($hook_name, strlen('toplevel_page_'));
        }

        $pos = strpos($hook_name, '_page_');
        if ($pos !== false) {
            return substr($hook_name, $pos + strlen('_page_'));
        }

        return '';
    }

    private function get_menu_title_by_slug($slug)
    {
        global $menu, $submenu;

        if (!empty($menu) && is_array($menu)) {
            foreach ($menu as $item) {
                if (isset($item[2]) && $item[2] === $slug) {
                    return trim(wp_strip_all_tags((string) $item[0]));
                }
            }
        }

        if (!empty($submenu) && is_array($submenu)) {
            foreach ($submenu as $parent_items) {
                if (!is_array($parent_items)) {
                    continue;
                }

                foreach ($parent_items as $item) {
                    if (isset($item[2]) && $item[2] === $slug) {
                        return trim(wp_strip_all_tags((string) $item[0]));
                    }
                }
            }
        }

        return '';
    }

    private function get_addon_description_text($addon)
    {
        $marketing_description = isset($addon['marketing_description']) ? trim((string) $addon['marketing_description']) : '';
        if ($marketing_description !== '') {
            return $marketing_description;
        }

        if (isset($addon['description']) && trim((string) $addon['description']) !== '') {
            return (string) $addon['description'];
        }

        return 'Módulo listo para activar y ampliar capacidades del proyecto.';
    }

    private function get_addon_parameters_text($addon)
    {
        $parameters = isset($addon['parameters']) ? trim((string) $addon['parameters']) : '';
        if ($parameters !== '') {
            return $parameters;
        }

        $long_description = isset($addon['long_description']) ? trim((string) $addon['long_description']) : '';

        if ($long_description !== '') {
            if (preg_match('/(?:Parámetros|Parametros)\s*:\s*(.+)$/iu', $long_description, $m)) {
                return trim((string) $m[1]);
            }
            return $long_description;
        }

        return 'Sin parámetros.';
    }

    private function get_detected_settings_pages()
    {
        global $_registered_pages, $wp_filter;

        if (empty($_registered_pages) || !is_array($_registered_pages)) {
            return array();
        }

        $plugin_base = wp_normalize_path(plugin_dir_path(__FILE__));
        $active_addons = get_option('active_addons', array());
        $active_lookup = array_flip(array_map('strval', (array) $active_addons));
        $detected = array();

        foreach (array_keys($_registered_pages) as $hook_name) {
            $slug = $this->get_slug_from_hook($hook_name);
            if ($slug === '' || $slug === 'addon-manager') {
                continue;
            }

            if (!isset($wp_filter[$hook_name]) || !($wp_filter[$hook_name] instanceof WP_Hook)) {
                continue;
            }

            $callback_file = '';
            foreach ((array) $wp_filter[$hook_name]->callbacks as $priority_callbacks) {
                foreach ((array) $priority_callbacks as $callback_data) {
                    if (!isset($callback_data['function'])) {
                        continue;
                    }

                    $file = $this->resolve_callback_file($callback_data['function']);
                    if ($file !== '') {
                        $callback_file = $file;
                        break 2;
                    }
                }
            }

            if ($callback_file === '') {
                continue;
            }

            $normalized_file = wp_normalize_path($callback_file);
            if (strpos($normalized_file, $plugin_base) !== 0) {
                continue;
            }

            $relative_file = ltrim(str_replace($plugin_base, '', $normalized_file), '/');
            if (
                strpos($relative_file, 'addons/') !== 0 &&
                strpos($relative_file, 'woo/') !== 0 &&
                strpos($relative_file, 'multisite/') !== 0
            ) {
                continue;
            }

            $addon_file = basename($relative_file);
            if (!isset($active_lookup[$addon_file])) {
                continue;
            }

            $url = menu_page_url($slug, false);
            if (!$url) {
                $url = admin_url('admin.php?page=' . rawurlencode($slug));
            }

            $title = $this->get_menu_title_by_slug($slug);
            if ($title === '') {
                $title = ucwords(str_replace(array('-', '_'), ' ', $slug));
            }

            if (!isset($detected[$addon_file])) {
                $detected[$addon_file] = array(
                    'pages' => array(),
                );
            }

            if (!isset($detected[$addon_file]['pages'][$slug])) {
                $detected[$addon_file]['pages'][$slug] = array(
                    'title' => $title,
                    'slug' => $slug,
                    'url' => $url,
                );
            }
        }

        if (empty($detected)) {
            return array();
        }

        ksort($detected);
        foreach ($detected as $addon_file => $data) {
            ksort($detected[$addon_file]['pages']);
        }

        return $detected;
    }

    public function add_admin_page()
    {
        add_menu_page(
            'Addon Manager',
            'Addons',
            'manage_options',
            'addon-manager',
            array($this, 'render_admin_page'),
            'dashicons-admin-plugins',
            30
        );
    }

    public function enqueue_assets($hook)
    {
        if ($hook !== 'toplevel_page_addon-manager') return;

        wp_enqueue_style('addon-manager-css', plugin_dir_url(__FILE__) . 'assets/style.css', array(), '3.0.0');
        wp_enqueue_script('addon-manager-js', plugin_dir_url(__FILE__) . 'assets/script.js', array('jquery'), '3.0.0', true);

        wp_localize_script('addon-manager-js', 'addonManager', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('addon_toggle_nonce')
        ));
    }

    public function render_admin_page()
    {
?>
        <div class="wrap">
            <h1>Addons Manager</h1>

            <?php
            $is_multisite = is_multisite();
            $is_woo_active = class_exists('WooCommerce');
            $active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'wp';
            $valid_tabs = array('wp');
            if ($is_woo_active) {
                $valid_tabs[] = 'woo';
            }
            if ($is_multisite) {
                $valid_tabs[] = 'multisite';
            }
            if (!in_array($active_tab, $valid_tabs, true)) {
                $active_tab = 'wp';
            }
            ?>
            <h2 class="nav-tab-wrapper">
                <a href="?page=addon-manager&tab=wp" class="nav-tab <?php echo $active_tab === 'wp' ? 'nav-tab-active' : ''; ?>">WordPress</a>

                <?php if ($is_woo_active): ?>
                    <a href="?page=addon-manager&tab=woo" class="nav-tab <?php echo $active_tab === 'woo' ? 'nav-tab-active' : ''; ?>">WooCommerce</a>
                <?php else: ?>
                    <span class="nav-tab nav-tab-disabled" style="opacity:0.5;cursor:not-allowed;" title="WooCommerce no está activo">WooCommerce</span>
                <?php endif; ?>

                <?php if ($is_multisite): ?>
                    <a href="?page=addon-manager&tab=multisite" class="nav-tab <?php echo $active_tab === 'multisite' ? 'nav-tab-active' : ''; ?>">Multisite</a>
                <?php else: ?>
                    <span class="nav-tab nav-tab-disabled" style="opacity:0.5;cursor:not-allowed;" title="No es un sitio Multisite">Multisite</span>
                <?php endif; ?>
            </h2>



            <div id="addon-message"></div>

            <?php
            $all_addons = $this->get_addons();
            $addons = isset($all_addons[$active_tab]) ? $all_addons[$active_tab] : array();
            $active_addons = get_option('active_addons', array());
            $settings_pages = $this->get_detected_settings_pages();

            if (empty($addons)) {
                echo '<p>No hay addons disponibles en esta sección.</p>';
            } else {
                echo '<div class="addons-grid">';
                foreach ($addons as $addon) {
                    $is_active = in_array($addon['file'], $active_addons);
                    $parameters_text = $this->get_addon_parameters_text($addon);
                    $description_text = $this->get_addon_description_text($addon);
                    $addon_pages = isset($settings_pages[$addon['file']]['pages']) ? $settings_pages[$addon['file']]['pages'] : array();
            ?>
                    <div class="addon-card <?php echo $is_active ? 'active' : ''; ?>">
                        <div class="addon-header">
                            <label class="switch">
                                <input type="checkbox"
                                    class="addon-toggle"
                                    data-addon="<?php echo esc_attr($addon['file']); ?>"
                                    <?php checked($is_active); ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="addon-info">
                            <h3><?php echo esc_html($addon['name']); ?></h3>
                            <p class="addon-description"><strong>Descripción:</strong> <?php echo esc_html($description_text); ?></p>
                            <div class="addon-long-description">
                                <strong>Parámetros:</strong>
                                <p style="margin:8px 0 0;"><?php echo esc_html($parameters_text); ?></p>
                            </div>
                            <p class="addon-version">Versión: <?php echo esc_html($addon['version']); ?></p>
                            <?php if ($is_active && !empty($addon_pages)): ?>
                                <div style="margin-top:10px;">
                                    <?php
                                    $first_page = reset($addon_pages);
                                    ?>
                                    <a class="button button-secondary" href="<?php echo esc_url($first_page['url']); ?>" target="_blank" rel="noopener noreferrer">Configurar</a>
                                    <?php if (count($addon_pages) > 1): ?>
                                        <?php $first_slug = isset($first_page['slug']) ? (string) $first_page['slug'] : ''; ?>
                                        <?php foreach ($addon_pages as $page): ?>
                                            <?php if (isset($page['slug']) && (string) $page['slug'] === $first_slug) {
                                                continue;
                                            } ?>
                                            <a class="button button-link" href="<?php echo esc_url($page['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($page['title']); ?></a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
            <?php
                }
                echo '</div>';
            }
            ?>
            <div class="addon-instructions" style="background:#ffffff;border-radius:20px;padding:15px;margin:20px 0;">
                <h2 style="margin-top:0;">Instrucciones de Uso</h2>
                <p><strong>¿Qué es esto?</strong> Gestor de addons modulares para extender funcionalidades de WordPress sin sobrecargar el sitio.</p>

                <h3> Cómo usar:</h3>
                <ol>
                    <li><strong>Activar/Desactivar:</strong> Usa los switches para activar solo los addons que necesites.</li>
                    <li><strong>Añadir nuevos addons:</strong> Sube archivos PHP a la carpeta <code>addons/</code>, <code>woo/</code> o <code>multisite/</code> dentro del plugin Addon Manager.</li>
                    <li><strong>Estructura recomendada:</strong> Cada addon debe tener cabecera con <code>Plugin Name</code>, <code>Description</code>, <code>Marketing Description</code>, <code>Parameters</code> y <code>Version</code>.</li>
                    <li><strong>Tarjetas en UI:</strong> "Descripción" usa <code>Marketing Description</code> (fallback: <code>Description</code>) y "Parámetros" usa <code>Parameters</code> (fallback legacy: <code>Long Description</code>).</li>
                </ol>

                <h3> Consideraciones:</h3>
                <ul>
                    <li>Estos addons <strong>no son MU-plugins reales</strong>: se cargan desde Addon Manager como plugin normal.</li>
                    <li>Solo se ejecutan los que tienen el <strong>switch activado</strong>.</li>
                    <li><strong>Diferencia clave de prioridad de carga:</strong> los MU-plugins se cargan antes y no se activan/desactivan desde "Plugins"; los plugins normales se cargan después y sí se gestionan desde "Plugins".</li>
                    <li><strong>Orden en este caso:</strong> MU-plugins globales de WordPress &rarr; plugins normales (incluido Addon Manager) &rarr; Addon Manager incluye solo los addons activos.</li>
                    <li>Compatible con Elementor, ACF, WooCommerce y la mayoría de plugins.</li>
                    <li>No afecta el rendimiento: solo carga los addons activos.</li>
                </ul>
            </div>
        </div>
<?php
    }

    public function toggle_addon()
    {
        check_ajax_referer('addon_toggle_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos');
        }

        $addon_file = sanitize_text_field($_POST['addon']);
        $active_addons = get_option('active_addons', array());

        if (in_array($addon_file, $active_addons)) {
            $active_addons = array_diff($active_addons, array($addon_file));
            $message = 'Addon desactivado correctamente';
        } else {
            $active_addons[] = $addon_file;
            $message = 'Addon activado correctamente';
        }

        update_option('active_addons', array_values($active_addons));
        wp_send_json_success($message);
    }
}

new Addon_Manager();

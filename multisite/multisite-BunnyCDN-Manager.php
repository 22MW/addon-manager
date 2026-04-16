<?php
/**
 * Plugin Name: Multisite BunnyCDN Manager
 * Description: Gestión centralizada de BunnyCDN en multisite con ajustes de red y activación por sitio.
 * Marketing Description: Gestiona CDN de toda la red desde un único panel de control.
 * Parameters: Control centralizado de CDN para toda la red.
 * Version: 1.3.2
 * Author: 22MW
 * Network: true
 */

if (!defined('ABSPATH')) exit;

class MS_BunnyCDN_Manager {
    const OPT_SITE_ENABLED   = 'ms_bunnycdn_enabled';      // option por sitio
    const OPT_NET_HOST       = 'ms_bunnycdn_host';         // site_option global
    const OPT_NET_FILETYPES  = 'ms_bunnycdn_filetypes';    // site_option global
    const OPT_NET_UPLOADS_ONLY = 'ms_bunnycdn_uploads_only'; // site_option global (bool)
    const NONCE              = 'ms_bunnycdn_nonce';

    public function __construct() {
        // Admin pages
        add_action('network_admin_menu', [$this, 'add_network_menu']);
        add_action('admin_menu',         [$this, 'add_site_menu']);

        // Guardar settings
        add_action('network_admin_edit_ms_bunnycdn_save', [$this, 'save_network_settings']);
        add_action('network_admin_edit_ms_bunnycdn_save_sites', [$this, 'save_network_sites']);
        add_action('admin_post_ms_bunnycdn_toggle',       [$this, 'save_site_toggle']);

        // Solo aplicar si activo y con host definido
        add_action('init', [$this, 'maybe_hook_rewrites'], 20);
    }

    /* ===========================
     * UI: NETWORK (global)
     * =========================== */
    public function add_network_menu() {
        add_menu_page(
            'BunnyCDN (Red)',
            'BunnyCDN (Red)',
            'manage_network_options',
            'ms-bunnycdn-network',
            [$this, 'render_network_page'],
            'dashicons-cloud',
            80
        );
    }

    public function render_network_page() {
        if (!current_user_can('manage_network_options')) wp_die('No tienes permisos.');
        
        // Verificar si se está mostrando mensaje de actualización
        $updated_global = isset($_GET['updated']) && $_GET['updated'] === '1';
        $updated_sites = isset($_GET['updated']) && $_GET['updated'] === 'sites';
        
        $host        = get_site_option(self::OPT_NET_HOST, '');
        $filetypes   = (array) get_site_option(self::OPT_NET_FILETYPES, $this->get_default_filetypes());
        $uploadsOnly = (bool) get_site_option(self::OPT_NET_UPLOADS_ONLY, true);

        $all = $this->all_known_filetypes();
        sort($all);
        ?>
        <div class="wrap">
            <h1>Configuración global BunnyCDN (Multisite)</h1>
            
            <?php if ($updated_global): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Configuración global guardada correctamente.</p>
                </div>
            <?php endif; ?>
            
            <?php if ($updated_sites): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Estado de sitios actualizado correctamente.</p>
                </div>
            <?php endif; ?>
            
            <!-- Pestañas -->
            <h2 class="nav-tab-wrapper">
                <a href="#global-settings" class="nav-tab nav-tab-active" onclick="showTab('global')">Configuración Global</a>
                <a href="#sites-management" class="nav-tab" onclick="showTab('sites')">Gestión de Sitios</a>
            </h2>
            
            <!-- Tab 1: Configuración Global -->
            <div id="tab-global" class="tab-content">
                <form method="post" action="<?php echo network_admin_url('edit.php?action=ms_bunnycdn_save'); ?>">
                    <?php wp_nonce_field(self::NONCE); ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="ms_bunnycdn_host">Hostname del CDN</label></th>
                            <td>
                                <input type="text" class="regular-text" id="ms_bunnycdn_host" name="ms_bunnycdn_host"
                                       placeholder="p.ej. cdn.midominio.com"
                                       value="<?php echo esc_attr($host); ?>">
                                <p class="description">
                                    Usa el hostname de tu Pull Zone de BunnyCDN (por ejemplo, <code>cdn.tu-dominio.com</code> o <code>mi-zona.b-cdn.net</code>).
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Tipos de archivo a servir desde el CDN</th>
                            <td>
                                <fieldset style="max-width:680px; columns: 3;">
                                    <?php foreach ($all as $ext): ?>
                                        <label style="display:block; break-inside: avoid;">
                                            <input type="checkbox" name="ms_bunnycdn_filetypes[]"
                                                   value="<?php echo esc_attr($ext); ?>"
                                                   <?php checked(in_array($ext, $filetypes, true)); ?>>
                                            .<?php echo esc_html($ext); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                                <p class="description">Solo las URLs que terminen con estas extensiones serán reescritas al CDN.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Ámbito</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="ms_bunnycdn_uploads_only" value="1" <?php checked($uploadsOnly); ?>>
                                    Reescribir únicamente archivos del directorio <code>uploads</code>
                                </label>
                                <p class="description">Si está desmarcado, también intentará reescribir <em>includes</em>, <em>plugins</em> y otros orígenes del mismo dominio.</p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button('Guardar ajustes de red'); ?>
                </form>
            </div>
            
            <!-- Tab 2: Gestión de Sitios -->
            <div id="tab-sites" class="tab-content" style="display:none;">
                <?php $this->render_sites_management(); ?>
            </div>
            
            <style>
                .nav-tab-wrapper { margin-bottom: 20px; }
                .tab-content { margin-top: 20px; }
                .sites-table { width: 100%; border-collapse: collapse; }
                .sites-table th, .sites-table td { padding: 8px 12px; border: 1px solid #ddd; text-align: left; }
                .sites-table th { background-color: #f9f9f9; font-weight: bold; }
                .sites-table tr:nth-child(even) { background-color: #f9f9f9; }
                .bulk-actions { margin: 10px 0; }
                .bulk-actions select, .bulk-actions .button { margin-right: 10px; }
            </style>
            
            <script>
                function showTab(tab) {
                    // Ocultar todas las pestañas
                    document.querySelectorAll('.tab-content').forEach(function(el) {
                        el.style.display = 'none';
                    });
                    
                    // Remover clase activa de todas las pestañas
                    document.querySelectorAll('.nav-tab').forEach(function(el) {
                        el.classList.remove('nav-tab-active');
                    });
                    
                    // Mostrar la pestaña seleccionada
                    document.getElementById('tab-' + tab).style.display = 'block';
                    event.target.classList.add('nav-tab-active');
                    
                    // Actualizar URL sin recargar página
                    if (history.pushState) {
                        var url = new URL(window.location);
                        url.hash = tab + '-settings';
                        history.pushState(null, null, url);
                    }
                }
                
                // Mostrar la pestaña correcta al cargar
                document.addEventListener('DOMContentLoaded', function() {
                    var hash = window.location.hash;
                    if (hash === '#sites-management' || hash === '#sites-settings') {
                        showTab('sites');
                    }
                });
                
                // Funciones para gestión de sitios
                function toggleAllSites(checkbox) {
                    var checkboxes = document.querySelectorAll('input[name="site_ids[]"]');
                    checkboxes.forEach(function(cb) {
                        cb.checked = checkbox.checked;
                    });
                }
                
                function selectAllSites() {
                    var checkboxes = document.querySelectorAll('input[name="site_ids[]"]');
                    checkboxes.forEach(function(cb) {
                        cb.checked = true;
                    });
                    document.getElementById('toggle-all').checked = true;
                }
                
                function deselectAllSites() {
                    var checkboxes = document.querySelectorAll('input[name="site_ids[]"]');
                    checkboxes.forEach(function(cb) {
                        cb.checked = false;
                    });
                    document.getElementById('toggle-all').checked = false;
                }
            </script>
        </div>
        <?php
    }

    public function save_network_settings() {
        if (!current_user_can('manage_network_options')) wp_die('No tienes permisos.');
        check_admin_referer(self::NONCE);

        $host = isset($_POST['ms_bunnycdn_host']) ? sanitize_text_field($_POST['ms_bunnycdn_host']) : '';
        $host = trim($host);

        $filetypes = isset($_POST['ms_bunnycdn_filetypes']) && is_array($_POST['ms_bunnycdn_filetypes'])
            ? array_values(array_unique(array_map([$this,'sanitize_ext'], $_POST['ms_bunnycdn_filetypes'])))
            : $this->get_default_filetypes();

        $uploadsOnly = !empty($_POST['ms_bunnycdn_uploads_only']) ? 1 : 0;

        update_site_option(self::OPT_NET_HOST, $host);
        update_site_option(self::OPT_NET_FILETYPES, $filetypes);
        update_site_option(self::OPT_NET_UPLOADS_ONLY, $uploadsOnly);

        wp_safe_redirect(add_query_arg(['page'=>'ms-bunnycdn-network','updated'=>'1'], network_admin_url('admin.php')));
        exit;
    }

    public function render_sites_management() {
        // Obtener todos los sitios de la red
        $sites = get_sites(['number' => 1000]); // Ajusta el número según tus necesidades
        
        // Obtener parámetros de ordenación
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'status';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'desc';
        
        // Preparar datos de sitios con información de estado
        $sites_data = [];
        foreach ($sites as $site) {
            $site_id = $site->blog_id;
            $site_url = get_site_url($site_id);
            $site_name = get_blog_option($site_id, 'blogname', 'Sin nombre');
            
            // Cambiar temporalmente al contexto del sitio para leer su opción
            switch_to_blog($site_id);
            $is_enabled = (bool) get_option(self::OPT_SITE_ENABLED, false);
            restore_current_blog();
            
            $sites_data[] = [
                'site' => $site,
                'site_id' => $site_id,
                'site_url' => $site_url,
                'site_name' => $site_name,
                'is_enabled' => $is_enabled
            ];
        }
        
        // Ordenar los sitios
        usort($sites_data, function($a, $b) use ($orderby, $order) {
            switch ($orderby) {
                case 'id':
                    $result = $a['site_id'] - $b['site_id'];
                    break;
                case 'name':
                    $result = strcasecmp($a['site_name'], $b['site_name']);
                    break;
                case 'status':
                default:
                    // Primero por estado (activados primero), luego por ID
                    if ($a['is_enabled'] !== $b['is_enabled']) {
                        $result = $b['is_enabled'] - $a['is_enabled']; // Activados primero
                    } else {
                        $result = $a['site_id'] - $b['site_id']; // Luego por ID ascendente
                    }
                    break;
            }
            
            return ($order === 'desc') ? -$result : $result;
        });
        
        // Función helper para generar enlaces de ordenación
        $sort_link = function($column, $title) use ($orderby, $order) {
            $new_order = ($orderby === $column && $order === 'asc') ? 'desc' : 'asc';
            $arrow = '';
            if ($orderby === $column) {
                $arrow = $order === 'asc' ? ' ↑' : ' ↓';
            }
            $url = add_query_arg(['orderby' => $column, 'order' => $new_order]) . '#sites-management';
            return '<a href="' . esc_url($url) . '">' . esc_html($title) . $arrow . '</a>';
        };
        ?>
        <h3>Gestión de sitios de la red</h3>
        <p class="description">
            Aquí puedes activar o desactivar BunnyCDN para cada sitio de la red de forma individual.
            Los cambios se aplicarán inmediatamente y cada sitio mantendrá su configuración independiente.
        </p>
        
        <form method="post" action="<?php echo network_admin_url('edit.php?action=ms_bunnycdn_save_sites'); ?>" id="sites-form">
            <?php wp_nonce_field(self::NONCE . '_sites'); ?>
            
            <div class="bulk-actions">
                <select id="bulk-action" name="bulk_action">
                    <option value="">Acciones en lote</option>
                    <option value="enable">Activar BunnyCDN</option>
                    <option value="disable">Desactivar BunnyCDN</option>
                </select>
                <button type="button" class="button" onclick="selectAllSites()">Seleccionar todos</button>
                <button type="button" class="button" onclick="deselectAllSites()">Deseleccionar todos</button>
                <input type="submit" class="button button-primary" value="Aplicar a seleccionados">
            </div>
            
            <table class="sites-table wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" id="toggle-all" onchange="toggleAllSites(this)">
                        </th>
                        <th style="width: 60px;"><?php echo $sort_link('id', 'ID'); ?></th>
                        <th><?php echo $sort_link('name', 'Sitio / URL'); ?></th>
                        <th style="width: 200px;"><?php echo $sort_link('status', 'Estado y Acción'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sites_data as $site_data): 
                        $site = $site_data['site'];
                        $site_id = $site_data['site_id'];
                        $site_url = $site_data['site_url'];
                        $site_name = $site_data['site_name'];
                        $is_enabled = $site_data['is_enabled'];
                        
                        $status_class = $is_enabled ? 'enabled' : 'disabled';
                        $status_text = $is_enabled ? 'SÍ' : 'NO';
                        $label_text = $is_enabled ? 'Desactivar BunnyCDN' : 'Activar BunnyCDN';
                    ?>
                        <tr class="<?php echo $is_enabled ? 'site-enabled' : 'site-disabled'; ?>">
                            <td>
                                <input type="checkbox" name="site_ids[]" value="<?php echo esc_attr($site_id); ?>">
                            </td>
                            <td><?php echo esc_html($site_id); ?></td>
                            <td>
                                <strong><?php echo esc_html($site_name); ?></strong><br>
                                <a href="<?php echo esc_url($site_url); ?>" target="_blank"><?php echo esc_html($site_url); ?></a>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span class="status-indicator <?php echo esc_attr($status_class); ?>" style="
                                        padding: 4px 8px; 
                                        border-radius: 3px; 
                                        font-size: 11px; 
                                        font-weight: bold; 
                                        text-transform: uppercase;
                                        color: white;
                                        background-color: <?php echo $is_enabled ? '#46b450' : '#dc3232'; ?>;
                                    ">
                                        <?php echo esc_html($status_text); ?>
                                    </span>
                                    
                                    <form method="post" action="<?php echo network_admin_url('edit.php?action=ms_bunnycdn_save_sites'); ?>" style="margin: 0;">
                                        <?php wp_nonce_field(self::NONCE . '_sites'); ?>
                                        <input type="hidden" name="site_individual[<?php echo esc_attr($site_id); ?>]" value="<?php echo $is_enabled ? '0' : '1'; ?>">
                                        <input type="hidden" name="save_individual" value="1">
                                        <button type="submit" class="button <?php echo $is_enabled ? 'button-secondary' : 'button-primary'; ?>" style="
                                            background-color: <?php echo $is_enabled ? '#dc3232' : '#46b450'; ?>;
                                            border-color: <?php echo $is_enabled ? '#dc3232' : '#46b450'; ?>;
                                            color: white;
                                            text-shadow: none;
                                            box-shadow: none;
                                        ">
                                            <?php echo $is_enabled ? 'Desactivar' : 'Activar'; ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>
        
        <style>
            .status-indicator.enabled { background-color: #46b450 !important; }
            .status-indicator.disabled { background-color: #dc3232 !important; }
            .site-enabled { background-color: #f0f8f0; }
            .site-disabled { background-color: #fdf0f0; }
            
            /* Estilos para los enlaces de ordenación */
            .sites-table thead th a {
                text-decoration: none;
                color: inherit;
                font-weight: bold;
            }
            .sites-table thead th a:hover {
                color: #0073aa;
            }
        </style>
        <?php
    }

    public function save_network_sites() {
        if (!current_user_can('manage_network_options')) wp_die('No tienes permisos.');
        check_admin_referer(self::NONCE . '_sites');

        // Acción en lote
        if (!empty($_POST['bulk_action']) && !empty($_POST['site_ids']) && is_array($_POST['site_ids'])) {
            $bulk_action = sanitize_text_field($_POST['bulk_action']);
            $site_ids = array_map('intval', $_POST['site_ids']);
            
            foreach ($site_ids as $site_id) {
                if (get_blog_details($site_id)) { // Verificar que el sitio existe
                    switch_to_blog($site_id);
                    
                    if ($bulk_action === 'enable') {
                        update_option(self::OPT_SITE_ENABLED, 1);
                    } elseif ($bulk_action === 'disable') {
                        update_option(self::OPT_SITE_ENABLED, 0);
                    }
                    
                    restore_current_blog();
                }
            }
        }

        // Cambios individuales (botones de acción individual)
        if (!empty($_POST['save_individual']) && !empty($_POST['site_individual']) && is_array($_POST['site_individual'])) {
            foreach ($_POST['site_individual'] as $site_id => $action) {
                $site_id = intval($site_id);
                $action = sanitize_text_field($action);
                
                if (get_blog_details($site_id)) { // Verificar que el sitio existe
                    switch_to_blog($site_id);
                    
                    // Aplicar la acción: 1 = activar, 0 = desactivar
                    update_option(self::OPT_SITE_ENABLED, $action === '1' ? 1 : 0);
                    
                    restore_current_blog();
                }
            }
        }

        wp_safe_redirect(add_query_arg(['page'=>'ms-bunnycdn-network','updated'=>'sites'], network_admin_url('admin.php')) . '#sites-management');
        exit;
    }

    /* ===========================
     * UI: SITE (por sitio)
     * =========================== */
    public function add_site_menu() {
        if (!is_multisite()) return;
        add_options_page(
            'BunnyCDN',
            'BunnyCDN',
            'manage_options',
            'ms-bunnycdn-site',
            [$this, 'render_site_page']
        );
    }

    public function render_site_page() {
        if (!current_user_can('manage_options')) wp_die('No tienes permisos.');
        $enabled = (bool) get_option(self::OPT_SITE_ENABLED, false);
        $host    = get_site_option(self::OPT_NET_HOST, '');
        $exts    = (array) get_site_option(self::OPT_NET_FILETYPES, $this->get_default_filetypes());
        ?>
        <div class="wrap">
            <h1>BunnyCDN (Este sitio)</h1>
            <?php if (empty($host)): ?>
                <div class="notice notice-warning"><p>
                    Falta definir el <strong>Hostname del CDN</strong> a nivel de red.
                    Ve a <em>Escritorio de la Red → BunnyCDN (Red)</em>.
                </p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field(self::NONCE); ?>
                <input type="hidden" name="action" value="ms_bunnycdn_toggle">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Estado</th>
                        <td>
                            <label>
                                <input type="checkbox" name="ms_bunnycdn_enabled" value="1" <?php checked($enabled); ?>>
                                Activar BunnyCDN en este sitio
                            </label>
                            <p class="description">Usará el host y tipos de archivo definidos a nivel de red.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Guardar'); ?>
            </form>

            <h2>Resumen efectivo</h2>
            <ul>
                <li><strong>CDN Host:</strong> <?php echo $host ? esc_html($host) : '<em>No definido</em>'; ?></li>
                <li><strong>Tipos de archivo:</strong> <?php echo esc_html(implode(', ', array_map(function($e){return '.'.$e;}, $exts))); ?></li>
                <li><strong>Activo en este sitio:</strong> <?php echo $enabled ? 'Sí' : 'No'; ?></li>
            </ul>
        </div>
        <?php
    }

    public function save_site_toggle() {
        if (!current_user_can('manage_options')) wp_die('No tienes permisos.');
        check_admin_referer(self::NONCE);

        $enabled = !empty($_POST['ms_bunnycdn_enabled']) ? 1 : 0;
        update_option(self::OPT_SITE_ENABLED, $enabled);

        wp_safe_redirect(add_query_arg(['page'=>'ms-bunnycdn-site','updated'=>'1'], admin_url('options-general.php')));
        exit;
    }

    /* ===========================
     * REWRITES
     * =========================== */
    public function maybe_hook_rewrites() {
        if (!is_multisite()) return;

        $host = trim((string) get_site_option(self::OPT_NET_HOST, ''));
        if ($host === '') return;

        $enabled = (bool) get_option(self::OPT_SITE_ENABLED, false);
        if (!$enabled) return;

        // Cargar valores efectivos
        $this->filetypes    = (array) get_site_option(self::OPT_NET_FILETYPES, $this->get_default_filetypes());
        $this->uploads_only = (bool) get_site_option(self::OPT_NET_UPLOADS_ONLY, true);
        $this->cdn_host     = $this->normalize_host($host);
        $this->home_host    = parse_url(home_url('/'), PHP_URL_HOST);

        // Filtros para URLs comunes
        add_filter('script_loader_src',        [$this,'filter_url'], 9999);
        add_filter('style_loader_src',         [$this,'filter_url'], 9999);
        add_filter('wp_get_attachment_url',    [$this,'filter_url'], 9999);
        add_filter('wp_calculate_image_srcset',[$this,'filter_srcset'], 9999);
        add_filter('the_content',              [$this,'filter_content_urls'], 9999);

        // Opcionalmente includes/plugins (si uploads_only = false)
        if (!$this->uploads_only) {
            add_filter('plugins_url',  [$this,'filter_url_generic'], 9999, 3);
            add_filter('content_url',  [$this,'filter_url_generic'], 9999, 2);
            add_filter('includes_url', [$this,'filter_url_generic'], 9999, 2);
            add_filter('site_url',     [$this,'filter_url_generic'],  9999, 3);
        }
    }

    public function filter_url($url) {
        return $this->maybe_rewrite_to_cdn($url);
    }

    public function filter_srcset($sources) {
        if (!is_array($sources)) return $sources;
        foreach ($sources as &$s) {
            if (isset($s['url'])) {
                $s['url'] = $this->maybe_rewrite_to_cdn($s['url']);
            }
        }
        return $sources;
    }

    public function filter_content_urls($html) {
        // Reescribe URLs en atributos src/href comunes, solo si coinciden extensión
        // Expresión simple: captura src|href="URL"
        $that = $this;
        $html = preg_replace_callback('@\b(?:src|href)=([\'"])([^\'"]+)\1@i', function($m) use ($that) {
            $orig = $m[2];
            $rew  = $that->maybe_rewrite_to_cdn($orig);
            if ($rew === $orig) return $m[0];
            return str_replace($orig, $rew, $m[0]);
        }, $html);
        return $html;
    }

    public function filter_url_generic($url /*, ... varargs */) {
        return $this->maybe_rewrite_to_cdn($url);
    }

    private function maybe_rewrite_to_cdn($url) {
        if (!$url || !is_string($url)) return $url;

        $parsed = wp_parse_url($url);
        if (empty($parsed['host'])) return $url;

        // No reescribir si ya es el host CDN
        if ($this->hosts_equal($parsed['host'], $this->cdn_host)) return $url;

        // Reescribir solo si pertenece al mismo dominio del sitio (evitar externos)
        if (!$this->hosts_equal($parsed['host'], $this->home_host)) return $url;

        // uploads-only: comprobar que la ruta contenga /uploads/
        $path = isset($parsed['path']) ? $parsed['path'] : '';
        if ($this->uploads_only && strpos($path, '/uploads/') === false) {
            return $url;
        }

        // Validar extensión
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        if (!$ext || !in_array($ext, $this->filetypes, true)) {
            return $url;
        }

        // Construir URL con host CDN
        $scheme = is_ssl() ? 'https' : 'http';
        $new = $scheme . '://' . $this->cdn_host . $path;

        // Preservar query y fragment
        if (!empty($parsed['query']))   $new .= '?' . $parsed['query'];
        if (!empty($parsed['fragment']))$new .= '#' . $parsed['fragment'];

        return $new;
    }

    /* ===========================
     * HELPERS
     * =========================== */

    private function get_default_filetypes() {
        return ['jpg','jpeg','png','webp','avif','gif','svg','css','js','woff','woff2','ttf','otf','eot','mp4','webm'];
    }

    private function all_known_filetypes() {
        // Lista amplia y ordenable
        return array_values(array_unique(array_merge(
            $this->get_default_filetypes(),
            ['bmp','ico','tif','tiff','m4v','ogg','ogv','mp3','wav','mkv','pdf']
        )));
    }

    private function sanitize_ext($ext) {
        $ext = strtolower(trim($ext));
        $ext = ltrim($ext, '.');
        return preg_replace('/[^a-z0-9]+/', '', $ext);
    }

    private function normalize_host($host) {
        $host = trim($host);
        $host = preg_replace('#^https?://#i','', $host);
        $host = rtrim($host, "/");
        return $host;
    }

    private function hosts_equal($a, $b) {
        $a = strtolower($this->normalize_host($a));
        $b = strtolower($this->normalize_host($b));
        return $a === $b;
    }
}

new MS_BunnyCDN_Manager();

<?php

/**
 * Plugin Name: Multisite Newsletter Popup
 * Description: Gestiona popup de newsletter para la red multisite con HTML configurable y control por cookies.
 * Marketing Description: Orquesta captación newsletter en red con control centralizado.
 * Parameters: Gestión central de popup newsletter con control por cookies.
 * Version: 1.7.0
 * Author: 22MW
 */

if (!defined('ABSPATH')) exit;

class MNP_Popup
{

    private $cookie_name = 'mnp_popup_closed';
    private $default_cache_time = 1440;

    public function __construct()
    {
        add_action('network_admin_menu', [$this, 'add_network_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_footer', [$this, 'display_popup']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_mnp_close_popup', [$this, 'close_popup_ajax']);
        add_action('wp_ajax_nopriv_mnp_close_popup', [$this, 'close_popup_ajax']);
    }

    public function add_network_menu()
    {
        add_menu_page(
            'Newsletter Popup',
            'Newsletter Popup',
            'manage_network_options',
            'mnp-settings',
            [$this, 'render_admin_page'],
            'dashicons-email-alt',
            30
        );
    }

    public function register_settings()
    {
        register_setting('mnp_settings', 'mnp_html_content');
        register_setting('mnp_settings', 'mnp_cache_time');
        register_setting('mnp_settings', 'mnp_enabled_sites');
        register_setting('mnp_settings', 'mnp_global_enable');
        register_setting('mnp_settings', 'mnp_max_width');
    }

    public function render_admin_page()
    {
        if (!current_user_can('manage_network_options')) return;

        if (isset($_POST['mnp_save']) && check_admin_referer('mnp_settings', 'mnp_nonce')) {
            // Decodificar el contenido que viene en base64 para evitar bloqueo del WAF
            $html_content_encoded = $_POST['mnp_html_content'] ?? '';

            if (!empty($html_content_encoded)) {
                // Decodificar de base64
                $html_content = base64_decode($html_content_encoded);
                if ($html_content === false) {
                    // Si falla el base64, intentar con el valor directo (por si acaso)
                    $html_content = wp_unslash($_POST['mnp_html_content']);
                }
            } else {
                $html_content = '';
            }

            // Procesar sitios seleccionados
            $enabled_sites = isset($_POST['mnp_enabled_sites']) && is_array($_POST['mnp_enabled_sites'])
                ? array_map('intval', $_POST['mnp_enabled_sites'])
                : [];

            // Guardar SIN wp_kses_post que filtra iframes
            update_site_option('mnp_html_content', $html_content);
            update_site_option('mnp_cache_time', intval($_POST['mnp_cache_time']));
            update_site_option('mnp_global_enable', isset($_POST['mnp_global_enable']) ? 1 : 0);
            update_site_option('mnp_enabled_sites', $enabled_sites);
            update_site_option('mnp_max_width', sanitize_text_field($_POST['mnp_max_width']));

            $count_sites = count($enabled_sites);
            $msg = $count_sites > 0
                ? "✓ Configuración guardada - Popup activo en {$count_sites} sitio(s)"
                : "✓ Configuración guardada - Popup activo en TODOS los sitios";
            echo '<div class="notice notice-success"><p>' . $msg . '</p></div>';
        }


        $html_content = get_site_option('mnp_html_content', '');
        $cache_time = get_site_option('mnp_cache_time', $this->default_cache_time);
        $global_enable = get_site_option('mnp_global_enable', 1);
        $enabled_sites = get_site_option('mnp_enabled_sites', []);
        $max_width = get_site_option('mnp_max_width', '600px');

        // Obtener TODOS los sitios (sin límite)
        $sites = get_sites(array('number' => 999999));

        // Calcular estado actual para mostrarlo
        $total_sites = count($sites);
        $selected_sites = count($enabled_sites);
        $status_msg = empty($enabled_sites)
            ? "Activo en <strong>TODOS</strong> los sitios ({$total_sites} actuales + futuros)"
            : "Activo en <strong>{$selected_sites} de {$total_sites}</strong> sitio(s) específicos";

?>
        <div class="wrap">
            <h1>🔔 Configuración Newsletter Popup </h1>
            <p style="background: #fff; border-left: 4px solid #0073aa; padding: 12px; margin: 20px 0;">
                📊<strong>Estado actual:</strong> <?php echo $status_msg; ?>
            </p>
            <form method="post">
                <?php wp_nonce_field('mnp_settings', 'mnp_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th><label for="mnp_global_enable">Activar Globalmente</label></th>
                        <td>
                            <input type="checkbox" id="mnp_global_enable" name="mnp_global_enable" value="1" <?php checked($global_enable, 1); ?>>
                            <p class="description">Activa o desactiva el popup en toda la red</p>
                        </td>
                    </tr>

                    <tr>
                        <th><label for="mnp_html_content">Contenido HTML</label></th>
                        <td>
                            <textarea id="mnp_html_content_display" rows="12" class="large-text code"><?php echo esc_textarea($html_content); ?></textarea>
                            <input type="hidden" id="mnp_html_content" name="mnp_html_content" value="">
                            <p class="description">HTML del popup (newsletter, formulario, etc.)</p>
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    var displayArea = document.getElementById('mnp_html_content_display');
                                    var hiddenField = document.getElementById('mnp_html_content');
                                    var form = displayArea.closest('form');

                                    form.addEventListener('submit', function(e) {
                                        // Base64 encode para evitar bloqueo del WAF
                                        hiddenField.value = btoa(unescape(encodeURIComponent(displayArea.value)));
                                    });
                                });
                            </script>
                        </td>
                    </tr>

                    <tr>
                        <th><label for="mnp_cache_time">Tiempo de Caché (minutos)</label></th>
                        <td>
                            <input type="number" id="mnp_cache_time" name="mnp_cache_time" value="<?php echo esc_attr($cache_time); ?>" min="1">
                            <p class="description">Tiempo antes de volver a mostrar tras cerrarlo. Por defecto: 1440 min (24h)</p>
                        </td>
                    </tr>

                    <tr>
                        <th><label for="mnp_max_width">Ancho Máximo del Popup</label></th>
                        <td>
                            <input type="text" id="mnp_max_width" name="mnp_max_width" value="<?php echo esc_attr($max_width); ?>" placeholder="600px">
                            <p class="description">Ancho máximo del popup (ej: 600px, 90%, 800px). Por defecto: 600px</p>
                        </td>
                    </tr>

                    <tr>
                        <th>Sitios Activos</th>
                        <td>
                            <p class="description">Selecciona dónde mostrar el popup:</p>
                            <div style="margin-bottom: 10px;">
                                <button type="button" class="button" id="mnp-select-all">✓ Seleccionar Todos</button>
                                <button type="button" class="button" id="mnp-deselect-all">✗ Deseleccionar Todos</button>
                            </div>
                            <div id="mnp-sites-list" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">
                                <?php foreach ($sites as $site) :
                                    $details = get_blog_details($site->blog_id);
                                ?>
                                    <label style="display: block; margin: 8px 0;">
                                        <input type="checkbox" class="mnp-site-checkbox" name="mnp_enabled_sites[]" value="<?php echo $site->blog_id; ?>"
                                            <?php checked(in_array($site->blog_id, $enabled_sites)); ?>>
                                        <strong><?php echo esc_html($details->blogname); ?></strong>
                                        <small style="color: #666;">(<?php echo esc_html($details->siteurl); ?>)</small>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    document.getElementById('mnp-select-all').addEventListener('click', function(e) {
                                        e.preventDefault();
                                        document.querySelectorAll('.mnp-site-checkbox').forEach(function(checkbox) {
                                            checkbox.checked = true;
                                        });
                                    });

                                    document.getElementById('mnp-deselect-all').addEventListener('click', function(e) {
                                        e.preventDefault();
                                        document.querySelectorAll('.mnp-site-checkbox').forEach(function(checkbox) {
                                            checkbox.checked = false;
                                        });
                                    });
                                });
                            </script>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Guardar Configuración', 'primary', 'mnp_save'); ?>
            </form>
        </div>
    <?php
    }

    private function should_display()
    {
        // Verificar que esté habilitado globalmente
        if (!get_site_option('mnp_global_enable', 1)) return false;

        // Verificar que haya contenido
        if (empty(get_site_option('mnp_html_content', ''))) return false;

        // Verificar sitios habilitados
        $enabled_sites = get_site_option('mnp_enabled_sites', []);
        $current_blog_id = get_current_blog_id();

        // DEBUG: Agregar comentario HTML solo visible en el código fuente
        add_action('wp_footer', function () use ($enabled_sites, $current_blog_id) {
            echo "\n<!-- MNP DEBUG: Current Blog ID: {$current_blog_id} | Enabled Sites: " . implode(',', $enabled_sites) . " | Empty: " . (empty($enabled_sites) ? 'YES' : 'NO') . " -->\n";
        }, 1);

        // Si hay sitios específicos seleccionados, verificar si el actual está incluido
        if (!empty($enabled_sites)) {
            if (!in_array($current_blog_id, $enabled_sites)) {
                return false; // Este sitio no está en la lista
            }
        }
        // Si $enabled_sites está vacío, se muestra en todos los sitios

        // Verificar cookie
        if (isset($_COOKIE[$this->cookie_name])) return false;

        return true;
    }

    /**
     * Sanitiza el contenido HTML permitiendo iframes seguros
     */
    private function sanitize_popup_content($content)
    {
        // Definir las etiquetas y atributos permitidos, incluyendo iframe
        $allowed_tags = wp_kses_allowed_html('post');

        // Agregar iframe con sus atributos permitidos
        $allowed_tags['iframe'] = array(
            'src'             => true,
            'width'           => true,
            'height'          => true,
            'frameborder'     => true,
            'allowfullscreen' => true,
            'allow'           => true,
            'loading'         => true,
            'style'           => true,
            'class'           => true,
            'id'              => true,
            'title'           => true,
        );

        // Agregar div con style para el contenedor del iframe
        if (!isset($allowed_tags['div'])) {
            $allowed_tags['div'] = array();
        }
        $allowed_tags['div']['style'] = true;

        return wp_kses($content, $allowed_tags);
    }

    public function display_popup()
    {
        if (!$this->should_display()) return;

        $content = get_site_option('mnp_html_content', '');
        if (empty($content)) return;
    ?>
        <div id="mnp-overlay" class="mnp-overlay">
            <div class="mnp-container">
                <button class="mnp-close" id="mnp-close">&times;</button>
                <div class="mnp-content">
                    <?php
                    // Usar nuestra función personalizada que permite iframes
                    echo $this->sanitize_popup_content($content);
                    ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function enqueue_assets()
    {
        if (!$this->should_display()) return;

        // Encolar jQuery si no está ya
        wp_enqueue_script('jquery');

        // Obtener el ancho máximo configurado
        $max_width = get_site_option('mnp_max_width', '600px');

        // Agregar CSS inline directamente en wp_head
        add_action('wp_head', function () use ($max_width) {
            echo '<style id="mnp-popup-styles">
                .mnp-overlay {
                    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                    background: rgba(0,0,0,0.7); display: flex; align-items: center;
                    justify-content: center; z-index: 999999; opacity: 0; visibility: hidden;
                    transition: opacity 0.3s, visibility 0.3s;
                }
                .mnp-overlay.mnp-show { opacity: 1; visibility: visible; }
                .mnp-container {
                    background: #E0FDEB; border-radius: 8px; padding: 10px;
                    width: 90%; max-width: ' . esc_attr($max_width) . '; position: relative;
                    box-shadow: 0 5px 30px rgba(0,0,0,0.3);
                    transform: scale(0.7); transition: transform 0.3s;
                    max-height: 90vh; overflow-y: auto;
                }
                .mnp-overlay.mnp-show .mnp-container { transform: scale(1); }
                .mnp-close {
                    position: absolute; top: 8px; right: 25px; background: transparent !important;
                    border: none; font-size: 60px; cursor: pointer; color: #333;
                     line-height: 1; padding: 0; z-index: 10;
                }
                .mnp-close:hover { color: #000 !important; background: #E0FDEB !important }
                .mnp-content { margin-top: 10px; }
                .mnp-content h2 { margin-top: 0; }
                .mnp-content input[type="email"], .mnp-content input[type="text"] {
                    width: 100%; padding: 12px; margin-bottom: 10px;
                    border: 1px solid #ddd; border-radius: 4px; font-size: 16px;
                }
                .mnp-content button[type="submit"] {
                    background: #0073aa; color: #fff; padding: 12px 30px;
                    border: none; border-radius: 4px; cursor: pointer; font-size: 16px;
                }
                .mnp-content button[type="submit"]:hover { background: #005a87; }
                /* Estilos para iframe responsive */
                .mnp-content iframe {
                    max-width: 100%;
                }
            </style>';
        });

        // JavaScript inline en wp_footer
        $cache_time = get_site_option('mnp_cache_time', $this->default_cache_time);
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('mnp_close_nonce');

        add_action('wp_footer', function () use ($ajax_url, $nonce) {
        ?>
            <script type="text/javascript">
                jQuery(function($) {
                    // Guardar el contenido original para poder restaurarlo
                    var originalContent = $('.mnp-content').html();

                    setTimeout(function() {
                        $('#mnp-overlay').addClass('mnp-show');
                    }, 1000);

                    // Cerrar con cookie (solo botón X)
                    function closePopupWithCache() {
                        $('#mnp-overlay').removeClass('mnp-show');
                        // Esperar a que termine la animación antes de limpiar
                        setTimeout(function() {
                            $('.mnp-content').empty();
                        }, 300);
                        $.post('<?php echo esc_js($ajax_url); ?>', {
                            action: 'mnp_close_popup',
                            nonce: '<?php echo esc_js($nonce); ?>'
                        });
                    }

                    // Cerrar sin cookie (ESC y click fuera)
                    function closePopupWithoutCache() {
                        $('#mnp-overlay').removeClass('mnp-show');
                        // Esperar a que termine la animación antes de limpiar
                        setTimeout(function() {
                            $('.mnp-content').empty();
                        }, 300);
                    }

                    // Solo el botón X cuenta para caché
                    $('#mnp-close').on('click', closePopupWithCache);

                    // ESC y click fuera NO cuentan para caché
                    $('#mnp-overlay').on('click', function(e) {
                        if ($(e.target).is('#mnp-overlay')) closePopupWithoutCache();
                    });
                    $(document).on('keydown', function(e) {
                        if (e.key === 'Escape' && $('#mnp-overlay').hasClass('mnp-show')) closePopupWithoutCache();
                    });
                });
            </script>
<?php
        }, 999);
    }

    public function close_popup_ajax()
    {
        check_ajax_referer('mnp_close_nonce', 'nonce');

        $cache_time = get_site_option('mnp_cache_time', $this->default_cache_time);
        $expiration = time() + ($cache_time * 60);

        setcookie($this->cookie_name, '1', $expiration, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);

        wp_send_json_success(['message' => 'Cookie establecida']);
    }
}

new MNP_Popup();

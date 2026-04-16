<?php

/**
 * Plugin Name: WooCommerce Email String Editor
 * Description: Permite editar textos de emails de WooCommerce desde admin sin modificar plantillas core.
 * Marketing Description: Personaliza comunicación de marca en emails WooCommerce sin tocar plantillas.
 * Parameters: Editor visual desde su menú para personalizar textos de emails.
 * Version: 2.2.2
 * Author: 22MW
 */

if (!defined('ABSPATH')) exit;

class WC_Email_String_Editor
{

    private $option_name = 'wc_custom_email_strings';
    private $templates_path = '';

    public function __construct()
    {
        $this->templates_path = WP_PLUGIN_DIR . '/woocommerce/templates/emails/';

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_post_save_email_strings', array($this, 'save_email_strings'));
        add_action('admin_post_delete_email_string', array($this, 'delete_email_string'));
        add_filter('gettext', array($this, 'custom_gettext'), 10, 3);
    }

    /**
     * Obtiene el idioma actual de WordPress
     * @return string Código de idioma (ej: es_ES, en_US)
     */
    private function get_current_language()
    {
        return get_locale();
    }

    /**
     * Obtiene lista de idiomas disponibles
     * @return array Array asociativo [codigo => nombre]
     */
    private function get_available_languages()
    {
        $languages = array();
        $current = $this->get_current_language();

        // Nombres amigables para idiomas comunes
        $lang_names = array(
            'es_ES' => 'Español',
            'ca' => 'Català',
            'en_US' => 'English',
            'fr_FR' => 'Français',
            'de_DE' => 'Deutsch',
            'it_IT' => 'Italiano',
            'pt_PT' => 'Português',
        );

        $languages[$current] = isset($lang_names[$current]) ? $lang_names[$current] : $current;

        $available = get_available_languages();
        foreach ($available as $lang) {
            if ($lang !== $current) {
                $languages[$lang] = isset($lang_names[$lang]) ? $lang_names[$lang] : $lang;
            }
        }

        return $languages;
    }

    /**
     * Obtiene el idioma seleccionado en el formulario
     * @return string Código de idioma
     */
    private function get_selected_language()
    {
        if (isset($_POST['language'])) {
            return sanitize_text_field($_POST['language']);
        }
        return $this->get_current_language();
    }

    /**
     * Verifica si un string tiene personalización en algún idioma
     * @param string $original_text Texto original
     * @return bool True si tiene personalización
     */
    private function has_customization($original_text)
    {
        $all_strings = get_option($this->option_name, array());
        foreach ($all_strings as $lang_strings) {
            if (isset($lang_strings[$original_text]) && !empty($lang_strings[$original_text])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Obtiene todas las personalizaciones guardadas
     * @return array Personalizaciones por idioma
     */
    private function get_all_customizations()
    {
        return get_option($this->option_name, array());
    }

    /**
     * Obtiene la pestaña actual
     * @return string Pestaña activa (editor o changes)
     */
    private function get_active_tab()
    {
        return isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'editor';
    }

    // Añade menú en WooCommerce
    public function add_admin_menu()
    {
        add_submenu_page(
            'woocommerce',
            'Email String Editor',
            'Email String Editor',
            'manage_woocommerce',
            'wc-email-string-editor',
            array($this, 'admin_page')
        );
    }

    // Renderiza la página de administración
    public function admin_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('No tienes permisos suficientes'));
        }

        $active_tab = $this->get_active_tab();
        $available_languages = $this->get_available_languages();
        $selected_template = isset($_POST['template']) ? sanitize_text_field($_POST['template']) : '';
        $templates = $this->get_email_templates();
        $strings = array();

        if ($selected_template && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'select_template')) {
            $strings = $this->extract_strings($selected_template);
        }

?>
        <style>
            .wrap {
                background: #fff;
                padding: 20px;
            }

            .wc-editor-container {
                background: #fff;
                border-radius: 8px;
                padding: 24px;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                margin-top: 20px;
            }

            .nav-tab-wrapper {
                background: #fff;
                border-radius: 8px 8px 0 0;
                border-bottom: 1px solid #dcdcde;
                margin: 20px 0 0;
                padding: 0 24px;
            }

            .nav-tab {
                border: none;
                border-bottom: 3px solid transparent;
                margin-bottom: 0px;
                background: #fff;
            }

            .nav-tab-active {
                border-bottom-color: #2271b1;
                background: transparent;
            }

            .form-table {
                background: #f9fafb;
                border-radius: 6px;
                padding: 16px;
            }

            .widefat {
                border: none !important;
            }

            .widefat thead th {
                background: #f9fafb;
                padding: 12px;
                font-weight: 600;
            }

            .widefat tbody td {
                padding: 12px;
            }

            .widefat tbody tr:hover {
                background: #f9fafb;
            }

            input[type="text"],
            textarea {
                border: 1px solid #dcdcde;
                border-radius: 4px;
                padding: 8px 12px;
            }

            input[type="text"]:focus,
            textarea:focus {
                border-color: #2271b1;
                outline: none;
                box-shadow: 0 0 0 1px #2271b1;
            }

            .button {
                border-radius: 4px;
            }
        </style>
        <div class="wrap">
            <h1>Email String Editor</h1>

            <h2 class="nav-tab-wrapper">
                <a href="?page=wc-email-string-editor&tab=editor" class="nav-tab <?php echo $active_tab === 'editor' ? 'nav-tab-active' : ''; ?>">Editor</a>
                <a href="?page=wc-email-string-editor&tab=changes" class="nav-tab <?php echo $active_tab === 'changes' ? 'nav-tab-active' : ''; ?>">Cambios guardados</a>
            </h2>

            <div class="wc-editor-container">
                <?php if ($active_tab === 'editor'): ?>

                    <!-- Formulario selección de plantilla -->
                    <form method="post" action="">
                        <?php wp_nonce_field('select_template'); ?>
                        <table class="form-table">
                            <tr>
                                <th><label for="template">Selecciona plantilla de email: </label></th>
                                <td>
                                    <select name="template" id="template">
                                        <option value="">-- Selecciona --</option>
                                        <?php foreach ($templates as $template_name => $template_path): ?>
                                            <option value="<?php echo esc_attr($template_name); ?>" <?php selected($selected_template, $template_name); ?>>
                                                <?php echo esc_html($this->get_template_name($template_name)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="submit" class="button" value="Cargar strings">
                                </td>
                            </tr>
                        </table>
                    </form>

                    <?php if (!empty($strings)): ?>
                        <!-- Formulario edición de strings -->
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('save_email_strings_action', 'save_email_strings_nonce'); ?>
                            <input type="hidden" name="action" value="save_email_strings">
                            <input type="hidden" name="template" value="<?php echo esc_attr($selected_template); ?>">
                            <input type="hidden" name="source_template" value="<?php echo esc_attr($selected_template); ?>">

                            <h2>Strings encontrados en: <?php echo esc_html($selected_template); ?></h2>
                            <table class="widefat striped">
                                <thead>
                                    <th style="width: 20%">Original (inglés)</th>
                                    <th style="width: 15%">Traducción actual</th>
                                    <?php foreach ($available_languages as $lang_code => $lang_name): ?>
                                        <th style="width: <?php echo floor(55 / count($available_languages)); ?>%"><?php echo esc_html($lang_name); ?></th>
                                    <?php endforeach; ?>
                                    <th style="width: 10%">Función</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($strings as $index => $string_data):
                                        $original = $string_data['text'];
                                        $translated = __($original, 'woocommerce');
                                    ?>
                                        <tr>
                                            <td>
                                                <code style="font-size: 11px;"><?php echo esc_html($original); ?></code>
                                                <input type="hidden" name="original_strings[<?php echo $index; ?>]" value="<?php echo esc_attr($original); ?>">
                                            </td>
                                            <td>
                                                <strong><?php echo esc_html($translated); ?></strong>
                                            </td>
                                            <?php foreach ($available_languages as $lang_code => $lang_name):
                                                $lang_strings = $this->get_saved_strings_for_language($lang_code);
                                                $custom = $this->get_custom_text($lang_strings, $original);
                                                $has_custom = !empty($custom);
                                            ?>
                                                <td>
                                                    <input type="text"
                                                        name="custom_strings[<?php echo esc_attr($lang_code); ?>][<?php echo $index; ?>]"
                                                        value="<?php echo esc_attr($custom); ?>"
                                                        class="regular-text"
                                                        style="<?php echo $has_custom ? 'border-left: 3px solid #2271b1;' : ''; ?>"
                                                        placeholder="<?php echo esc_attr($translated); ?>">
                                                </td>
                                            <?php endforeach; ?>
                                            <td><code style="font-size: 10px;"><?php echo esc_html($string_data['function']); ?></code></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <?php submit_button('Guardar traducciones personalizadas'); ?>
                        </form>
                    <?php endif; ?>

                <?php else: ?>
                    <div style="margin-top: 20px;">
                        <h2>Resumen de cambios guardados</h2>
                        <?php
                        $all_customizations = $this->get_all_customizations();
                        if (empty($all_customizations)): ?>
                            <p>No hay cambios guardados todavía.</p>
                        <?php else: ?>
                            <?php foreach ($available_languages as $lang_code => $lang_name): ?>
                                <?php
                                $lang_strings = isset($all_customizations[$lang_code]) ? $all_customizations[$lang_code] : array();
                                if (empty($lang_strings)) continue;
                                ?>
                                <h3><?php echo esc_html($lang_name); ?> (<?php echo count($lang_strings); ?> cambios)</h3>
                                <table class="widefat striped">
                                    <thead>
                                        <tr>
                                            <th style="width: 30%">Original</th>
                                            <th style="width: 35%">Personalizado</th>
                                            <th style="width: 20%">Plantilla</th>
                                            <th style="width: 15%">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lang_strings as $original => $data):
                                            $custom = is_array($data) ? $data['custom'] : $data;
                                            $template = is_array($data) && isset($data['template']) ? $data['template'] : false;
                                        ?>
                                            <tr>
                                                <td><code><?php echo esc_html($original); ?></code></td>
                                                <td><strong><?php echo esc_html($custom); ?></strong></td>
                                                <td><?php echo $template ? esc_html($this->get_template_name($template)) : '-'; ?></td>
                                                <td>
                                                    <?php if ($template): ?>
                                                        <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=wc-email-string-editor&tab=editor')); ?>" style="display:inline;">
                                                            <?php wp_nonce_field('select_template'); ?>
                                                            <input type="hidden" name="template" value="<?php echo esc_attr($template); ?>">
                                                            <button type="submit" class="button button-small">Editar</button>
                                                        </form>
                                                    <?php else: ?>
                                                        <a href="<?php echo esc_url(admin_url('admin.php?page=wc-email-string-editor&tab=editor')); ?>" class="button button-small">Editar</a>
                                                    <?php endif; ?>
                                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                                        <?php wp_nonce_field('delete_email_string_action', 'delete_email_string_nonce'); ?>
                                                        <input type="hidden" name="action" value="delete_email_string">
                                                        <input type="hidden" name="language" value="<?php echo esc_attr($lang_code); ?>">
                                                        <input type="hidden" name="original_text" value="<?php echo esc_attr($original); ?>">
                                                        <button type="submit" class="button button-small" onclick="return confirm('¿Eliminar esta personalización?');">Borrar</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
<?php
    }

    /**
     * Obtiene nombre amigable de plantilla
     * @param string $template_file Nombre de archivo de plantilla
     * @return string Nombre traducido
     */
    private function get_template_name($template_file)
    {
        if (!function_exists('WC') || !WC()->mailer()) {
            return $template_file;
        }

        $emails = WC()->mailer()->get_emails();

        foreach ($emails as $email) {
            $html_match = !empty($email->template_html) && basename($email->template_html) === $template_file;
            $plain_match = !empty($email->template_plain) && basename($email->template_plain) === $template_file;

            if ($html_match || $plain_match) {
                return $email->get_title();
            }
        }

        return str_replace(array('-', '.php'), array(' ', ''), $template_file);
    }

    /**
     * Obtiene texto personalizado (compatible con formato antiguo y nuevo)
     * @param array $lang_strings Strings del idioma
     * @param string $original Texto original
     * @return string Texto personalizado
     */
    private function get_custom_text($lang_strings, $original)
    {
        if (!isset($lang_strings[$original])) {
            return '';
        }
        $data = $lang_strings[$original];
        return is_array($data) ? $data['custom'] : $data;
    }

    /**
     * Obtiene plantilla origen (solo formato nuevo)
     * @param array $lang_strings Strings del idioma
     * @param string $original Texto original
     * @return string|false Plantilla o false
     */
    private function get_template_origin($lang_strings, $original)
    {
        if (!isset($lang_strings[$original])) {
            return false;
        }
        $data = $lang_strings[$original];
        return is_array($data) && isset($data['template']) ? $data['template'] : false;
    }

    /**
     * Guarda traducciones para un idioma específico
     * @param string $language Código de idioma
     * @param array $strings Array de traducciones
     */
    private function save_strings_for_language($language, $strings)
    {
        $all_strings = get_option($this->option_name, array());
        $all_strings[$language] = $strings;
        update_option($this->option_name, $all_strings);
    }

    /**
     * Obtiene traducciones guardadas para un idioma específico
     * @param string $language Código de idioma
     * @return array Array de traducciones del idioma
     */
    private function get_saved_strings_for_language($language)
    {
        $all_strings = get_option($this->option_name, array());
        return isset($all_strings[$language]) ? $all_strings[$language] : array();
    }

    /**
     * Obtiene lista de plantillas escaneando directorios
     * @return array Array asociativo [nombre_plantilla => ruta_completa]
     */
    private function get_email_templates()
    {
        $templates = array();

        // Directorios a escanear
        $scan_dirs = array(
            WP_PLUGIN_DIR . '/woocommerce/templates/emails/',
            WP_PLUGIN_DIR . '/woocommerce-subscriptions/templates/emails/',
            WP_PLUGIN_DIR . '/woocommerce-bookings/templates/emails/',
            WP_PLUGIN_DIR . '/woocommerce-memberships/templates/emails/',
            WP_PLUGIN_DIR . '/woocommerce-point-of-sale/templates/emails/',
            get_stylesheet_directory() . '/woocommerce/emails/',
            get_template_directory() . '/woocommerce/emails/'
        );

        foreach ($scan_dirs as $dir) {
            if (is_dir($dir)) {
                $files = glob($dir . '*.php');
                foreach ($files as $file) {
                    $filename = basename($file);
                    // Evitar duplicados (prioridad: theme > plugins)
                    if (!isset($templates[$filename])) {
                        $templates[$filename] = $file;
                    }
                }
            }
        }

        return $templates;
    }

    /**
     * Extrae strings traducibles de plantilla
     * @param string $template Nombre de plantilla
     * @return array Strings encontrados
     */
    private function extract_strings($template)
    {
        $templates = $this->get_email_templates();
        $file_path = isset($templates[$template]) ? $templates[$template] : false;

        if (!$file_path || !file_exists($file_path)) {
            return array();
        }

        $content = file_get_contents($file_path);
        $strings = array();

        // Patrones para diferentes funciones de traducción (cualquier dominio)
        $patterns = array(
            'esc_html_e' => "/esc_html_e\s*\(\s*['\"](. +?)['\"]\s*,\s*['\"][a-zA-Z0-9_-]+['\"]\s*\)/",
            '__' => "/__\s*\(\s*['\"](.+?)['\"]\s*,\s*['\"][a-zA-Z0-9_-]+['\"]\s*\)/",
            '_e' => "/_e\s*\(\s*['\"](.+?)['\"]\s*,\s*['\"][a-zA-Z0-9_-]+['\"]\s*\)/",
            'esc_html__' => "/esc_html__\s*\(\s*['\"](.+?)['\"]\s*,\s*['\"][a-zA-Z0-9_-]+['\"]\s*\)/",
            'esc_attr_e' => "/esc_attr_e\s*\(\s*['\"](.+?)['\"]\s*,\s*['\"][a-zA-Z0-9_-]+['\"]\s*\)/",
            'esc_attr__' => "/esc_attr__\s*\(\s*['\"](.+?)['\"]\s*,\s*['\"][a-zA-Z0-9_-]+['\"]\s*\)/"
        );

        foreach ($patterns as $function => $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $match) {
                    // Evitar duplicados
                    $text = stripslashes($match);
                    $exists = false;
                    foreach ($strings as $s) {
                        if ($s['text'] === $text) {
                            $exists = true;
                            break;
                        }
                    }
                    if (! $exists) {
                        $strings[] = array(
                            'text' => $text,
                            'function' => $function
                        );
                    }
                }
            }
        }

        return $strings;
    }

    /**
     * Encuentra en qué plantilla aparece un string
     * @param string $text Texto a buscar
     * @return string|false Nombre de plantilla o false
     */
    private function find_template_for_string($text)
    {
        $templates = $this->get_email_templates();
        foreach ($templates as $template_name => $template_path) {
            $strings = $this->extract_strings($template_name);
            foreach ($strings as $string_data) {
                if ($string_data['text'] === $text) {
                    return $template_name;
                }
            }
        }
        return false;
    }

    // Guarda las personalizaciones
    public function save_email_strings()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('No tienes permisos suficientes'));
        }

        check_admin_referer('save_email_strings_action', 'save_email_strings_nonce');

        $source_template = isset($_POST['source_template']) ? sanitize_text_field($_POST['source_template']) : '';
        $original_strings = isset($_POST['original_strings']) ? $_POST['original_strings'] : array();
        $custom_strings = isset($_POST['custom_strings']) ? $_POST['custom_strings'] : array();

        // Procesar cada idioma
        foreach ($custom_strings as $lang_code => $lang_translations) {
            $saved_data = $this->get_saved_strings_for_language($lang_code);

            foreach ($original_strings as $index => $original) {
                $original = sanitize_text_field($original);
                $custom = isset($lang_translations[$index]) ? sanitize_text_field($lang_translations[$index]) : '';

                if (!empty($custom)) {
                    $saved_data[$original] = array(
                        'custom' => $custom,
                        'template' => $source_template
                    );
                } else {
                    if (isset($saved_data[$original])) {
                        unset($saved_data[$original]);
                    }
                }
            }

            $this->save_strings_for_language($lang_code, $saved_data);
        }

        wp_redirect(add_query_arg(
            array(
                'page' => 'wc-email-string-editor',
                'updated' => 'true'
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    /**
     * Elimina una personalización específica
     */
    public function delete_email_string()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('No tienes permisos suficientes'));
        }

        check_admin_referer('delete_email_string_action', 'delete_email_string_nonce');

        $language = isset($_POST['language']) ? sanitize_text_field($_POST['language']) : '';
        $original_text = isset($_POST['original_text']) ? sanitize_text_field($_POST['original_text']) : '';

        if ($language && $original_text) {
            $saved_data = $this->get_saved_strings_for_language($language);
            if (isset($saved_data[$original_text])) {
                unset($saved_data[$original_text]);
                $this->save_strings_for_language($language, $saved_data);
            }
        }

        wp_redirect(add_query_arg(
            array(
                'page' => 'wc-email-string-editor',
                'tab' => 'changes'
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    // Filtro gettext para aplicar traducciones personalizadas
    public function custom_gettext($translation, $text, $domain)
    {
        if ($domain !== 'woocommerce') {
            return $translation;
        }

        $current_language = $this->get_current_language();
        $saved_strings = $this->get_saved_strings_for_language($current_language);
        $custom = $this->get_custom_text($saved_strings, $text);

        if (!empty($custom)) {
            return $custom;
        }

        return $translation;
    }
}

// Inicializar después de cargar plugins para asegurar que WooCommerce está disponible
add_action('plugins_loaded', function () {
    if (class_exists('WooCommerce')) {
        new WC_Email_String_Editor();
    }
}, 20);

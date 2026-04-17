<?php

/**
 * Plugin Name: Addon Manager
 * Plugin URI: https://22mw.online/
 * Description: Panel central para activar/desactivar mini-addons (WordPress, WooCommerce y Multisite) desde una única interfaz.
 * Version: 1.0.3
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
    private const OPTION_ACTIVE_CORE = 'active_addons';
    private const OPTION_ACTIVE_USER = 'addon_manager_active_user_addons';
    private const OPTION_PENDING_ACTIVATION = 'addon_manager_pending_activation';
    private const OPTION_ADMIN_NOTICE = 'addon_manager_admin_notice';
    private const OPTION_BLOCKED_ADDONS = 'addon_manager_blocked_addons';
    private const OPTION_RUNTIME_LOADING = 'addon_manager_runtime_loading';
    private const OPTION_APPROVED_SIGNATURES = 'addon_manager_approved_signatures';
    private const HEALTHCHECK_TRANSIENT_PREFIX = 'addon_manager_hc_';
    private const MAX_USER_ADDON_SIZE = 524288;
    private static $global_notice_rendered = false;
    private $is_healthcheck_request = false;
    private $healthcheck_payload = array();

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_page'));
        add_action('admin_notices', array($this, 'display_global_admin_notice'));
        add_action('all_admin_notices', array($this, 'display_global_admin_notice'));
        add_action('network_admin_notices', array($this, 'display_global_admin_notice'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_toggle_addon', array($this, 'toggle_addon'));
        add_action('wp_ajax_addon_manager_upload_user_addon', array($this, 'handle_user_addon_upload_ajax'));
        add_action('wp_loaded', array($this, 'load_active_addons'));
        add_action('admin_post_addon_manager_upload_user_addon', array($this, 'handle_user_addon_upload'));
        add_action('admin_post_addon_manager_delete_user_addon', array($this, 'handle_user_addon_delete'));
        add_action('init', array($this, 'maybe_handle_healthcheck_request'), 1);
        add_action('template_redirect', array($this, 'maybe_send_healthcheck_response'), 0);

        register_shutdown_function(array($this, 'handle_shutdown_fatal_recovery'));
    }

    public function load_active_addons()
    {
        $approved_signatures = $this->get_approved_signatures();
        $signatures_changed = false;

        $core_active = $this->get_active_core_addons();
        $valid_core_active = array();
        if (!empty($core_active)) {
            foreach ($core_active as $relative_file) {
                $addon_path = plugin_dir_path(__FILE__) . $relative_file;
                $addon_id = $this->build_core_addon_id($relative_file);
                if (!is_file($addon_path)) {
                    if (isset($approved_signatures[$addon_id])) {
                        unset($approved_signatures[$addon_id]);
                        $signatures_changed = true;
                    }
                    $reason = 'No se encontró el archivo del addon activo.';
                    $this->set_blocked_addon($addon_id, $reason);
                    $this->set_auto_disabled_notice(basename($relative_file), $reason);
                    continue;
                }

                $current_signature = $this->build_addon_file_signature($addon_path);
                $approved_signature = isset($approved_signatures[$addon_id]) ? (string) $approved_signatures[$addon_id] : '';
                if ($approved_signature === '') {
                    $approved_signatures[$addon_id] = $current_signature;
                    $signatures_changed = true;
                } elseif ($current_signature !== $approved_signature) {
                    unset($approved_signatures[$addon_id]);
                    $signatures_changed = true;
                    $reason = 'Se detectó un cambio en el archivo del addon activo.';
                    $this->set_blocked_addon($addon_id, $reason);
                    $this->set_auto_disabled_notice(basename($relative_file), $reason);
                    continue;
                }

                $lint_message = '';
                if (!$this->lint_php_file($addon_path, $lint_message)) {
                    $reason = $this->build_syntax_error_message($lint_message);
                    $this->set_blocked_addon($addon_id, $reason);
                    $this->set_auto_disabled_notice(basename($relative_file), $reason);
                    unset($approved_signatures[$addon_id]);
                    $signatures_changed = true;
                    continue;
                }

                $valid_core_active[] = $relative_file;
                $this->set_runtime_loading_marker($addon_id, basename($relative_file));
                include_once $addon_path;
                $this->clear_runtime_loading_marker();
            }
        }

        if ($valid_core_active !== $core_active) {
            $this->set_active_core_addons($valid_core_active);
        }

        $user_active = $this->get_active_user_addons();
        $user_dir = $this->get_user_addons_dir();
        $valid_user_active = array();
        foreach ($user_active as $file_name) {
            $addon_path = trailingslashit($user_dir) . $file_name;
            $addon_id = $this->build_user_addon_id($file_name);
            if (!is_file($addon_path)) {
                if (isset($approved_signatures[$addon_id])) {
                    unset($approved_signatures[$addon_id]);
                    $signatures_changed = true;
                }
                $reason = 'No se encontró el archivo del addon activo.';
                $this->set_blocked_addon($addon_id, $reason);
                $this->set_auto_disabled_notice($file_name, $reason);
                continue;
            }

            $current_signature = $this->build_addon_file_signature($addon_path);
            $approved_signature = isset($approved_signatures[$addon_id]) ? (string) $approved_signatures[$addon_id] : '';
            if ($approved_signature === '') {
                $approved_signatures[$addon_id] = $current_signature;
                $signatures_changed = true;
            } elseif ($current_signature !== $approved_signature) {
                unset($approved_signatures[$addon_id]);
                $signatures_changed = true;
                $reason = 'Se detectó un cambio en el archivo del addon activo.';
                $this->set_blocked_addon($addon_id, $reason);
                $this->set_auto_disabled_notice($file_name, $reason);
                continue;
            }

            $lint_message = '';
            if (!$this->lint_php_file($addon_path, $lint_message)) {
                $reason = $this->build_syntax_error_message($lint_message);
                $this->set_blocked_addon($addon_id, $reason);
                $this->set_auto_disabled_notice($file_name, $reason);
                unset($approved_signatures[$addon_id]);
                $signatures_changed = true;
                continue;
            }

            $valid_user_active[] = $file_name;
            $this->set_runtime_loading_marker($addon_id, $file_name);
            include_once $addon_path;
            $this->clear_runtime_loading_marker();
        }

        if ($valid_user_active !== $user_active) {
            $this->set_active_user_addons($valid_user_active);
        }

        if ($signatures_changed) {
            $this->set_approved_signatures($approved_signatures);
        }

        $this->maybe_include_healthcheck_probe_addon();
    }

    private function get_core_folders_map()
    {
        return array(
            'addons' => 'wp',
            'woo' => 'woo',
            'multisite' => 'multisite',
        );
    }

    private function build_core_addon_id($relative_file)
    {
        return 'core:' . ltrim((string) $relative_file, '/');
    }

    private function build_user_addon_id($file_name)
    {
        return 'user:' . (string) $file_name;
    }

    private function parse_addon_id($addon_id)
    {
        $addon_id = trim((string) $addon_id);
        if ($addon_id === '') {
            return array(
                'origin' => '',
                'value' => '',
            );
        }

        if (strpos($addon_id, 'core:') === 0) {
            return array(
                'origin' => 'core',
                'value' => ltrim(substr($addon_id, 5), '/'),
            );
        }

        if (strpos($addon_id, 'user:') === 0) {
            return array(
                'origin' => 'user',
                'value' => basename(substr($addon_id, 5)),
            );
        }

        return array(
            'origin' => '',
            'value' => '',
        );
    }

    private function get_addon_path_by_id($addon_id)
    {
        $parsed = $this->parse_addon_id($addon_id);
        if ($parsed['origin'] === 'core') {
            return plugin_dir_path(__FILE__) . ltrim((string) $parsed['value'], '/');
        }

        if ($parsed['origin'] === 'user') {
            return trailingslashit($this->get_user_addons_dir()) . basename((string) $parsed['value']);
        }

        return '';
    }

    private function maybe_include_healthcheck_probe_addon()
    {
        if (!$this->is_healthcheck_request) {
            return;
        }

        $addon_id = isset($this->healthcheck_payload['addon_id']) ? (string) $this->healthcheck_payload['addon_id'] : '';
        if ($addon_id === '') {
            $this->healthcheck_payload['probe_error'] = 'Payload de healthcheck inválido.';
            return;
        }

        $addon_path = $this->get_addon_path_by_id($addon_id);
        if ($addon_path === '' || !is_file($addon_path)) {
            $this->healthcheck_payload['probe_error'] = 'No se encontró el archivo del addon en verificación.';
            return;
        }

        $lint_message = '';
        if (!$this->lint_php_file($addon_path, $lint_message)) {
            $message = $this->build_syntax_error_message($lint_message);
            $this->set_blocked_addon($addon_id, $message);
            $this->healthcheck_payload['probe_error'] = $message;
            return;
        }

        include_once $addon_path;
    }

    public function maybe_send_healthcheck_response()
    {
        if (!$this->is_healthcheck_request) {
            return;
        }

        if (!empty($this->healthcheck_payload['probe_error'])) {
            wp_send_json_error(array(
                'message' => (string) $this->healthcheck_payload['probe_error'],
            ), 500);
        }

        wp_send_json_success(array(
            'status' => 'ok',
            'time' => time(),
        ), 200);
    }

    private function get_user_addons_dir()
    {
        $uploads = wp_upload_dir();
        if (empty($uploads['basedir'])) {
            return '';
        }

        return trailingslashit($uploads['basedir']) . 'addon-manager/user-addons';
    }

    private function ensure_user_addons_dir()
    {
        $dir = $this->get_user_addons_dir();
        if ($dir === '') {
            return '';
        }

        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        if (is_dir($dir)) {
            $index_file = trailingslashit($dir) . 'index.php';
            if (!is_file($index_file)) {
                file_put_contents($index_file, "<?php\n// Silence is golden.\n");
            }

            $htaccess_file = trailingslashit($dir) . '.htaccess';
            if (!is_file($htaccess_file)) {
                $rules = "Order Allow,Deny\nDeny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n";
                file_put_contents($htaccess_file, $rules);
            }
        }

        return is_dir($dir) ? $dir : '';
    }

    private function get_available_core_relative_paths()
    {
        $available = array();
        foreach ($this->get_core_folders_map() as $folder => $tab) {
            $addon_dir = plugin_dir_path(__FILE__) . $folder . '/';
            if (!is_dir($addon_dir)) {
                continue;
            }

            $files = scandir($addon_dir);
            foreach ($files as $file) {
                if (substr($file, -4) !== '.php') {
                    continue;
                }

                $relative = $folder . '/' . $file;
                $available[$relative] = $relative;
            }
        }

        return array_values($available);
    }

    private function get_active_core_addons()
    {
        $raw = get_option(self::OPTION_ACTIVE_CORE, array());
        if (!is_array($raw)) {
            $raw = array();
        }

        $available = $this->get_available_core_relative_paths();
        $available_lookup = array_flip($available);
        $canonical = array();
        $legacy = array();

        foreach ($raw as $entry) {
            $entry = trim((string) $entry);
            if ($entry === '') {
                continue;
            }

            if (strpos($entry, '/') !== false) {
                if (isset($available_lookup[$entry])) {
                    $canonical[$entry] = $entry;
                }
                continue;
            }

            $legacy[] = $entry;
        }

        if (!empty($legacy)) {
            foreach ($legacy as $legacy_file) {
                foreach ($available as $relative) {
                    if (basename($relative) === $legacy_file) {
                        $canonical[$relative] = $relative;
                        break;
                    }
                }
            }
        }

        $result = array_values($canonical);
        if ($result !== $raw) {
            update_option(self::OPTION_ACTIVE_CORE, $result);
        }

        return $result;
    }

    private function set_active_core_addons(array $core_addons)
    {
        $normalized = array();
        foreach ($core_addons as $entry) {
            $entry = ltrim(trim((string) $entry), '/');
            if ($entry !== '') {
                $normalized[$entry] = $entry;
            }
        }
        update_option(self::OPTION_ACTIVE_CORE, array_values($normalized));
    }

    private function get_active_user_addons()
    {
        $raw = get_option(self::OPTION_ACTIVE_USER, array());
        if (!is_array($raw)) {
            $raw = array();
        }

        $normalized = array();
        foreach ($raw as $entry) {
            $entry = basename(trim((string) $entry));
            if ($entry !== '' && substr($entry, -4) === '.php') {
                $normalized[$entry] = $entry;
            }
        }

        $result = array_values($normalized);
        if ($result !== $raw) {
            update_option(self::OPTION_ACTIVE_USER, $result);
        }

        return $result;
    }

    private function set_active_user_addons(array $user_addons)
    {
        $normalized = array();
        foreach ($user_addons as $entry) {
            $entry = basename(trim((string) $entry));
            if ($entry !== '' && substr($entry, -4) === '.php') {
                $normalized[$entry] = $entry;
            }
        }
        update_option(self::OPTION_ACTIVE_USER, array_values($normalized));
    }

    private function get_user_addons()
    {
        $user_addons = array();
        $user_dir = $this->ensure_user_addons_dir();
        if ($user_dir === '') {
            return $user_addons;
        }

        $files = scandir($user_dir);
        foreach ($files as $file) {
            if (substr($file, -4) !== '.php') {
                continue;
            }

            $file_path = trailingslashit($user_dir) . $file;
            if (!is_file($file_path)) {
                continue;
            }

            $plugin_data = get_file_data($file_path, array(
                'Name' => 'Plugin Name',
                'Description' => 'Description',
                'Version' => 'Version',
                'LongDescription' => 'Long Description',
                'MarketingDescription' => 'Marketing Description',
                'Parameters' => 'Parameters'
            ));

            if (empty($plugin_data['Name'])) {
                continue;
            }

            $user_addons[] = array(
                'id' => $this->build_user_addon_id($file),
                'origin' => 'user',
                'file' => $file,
                'relative_file' => $file,
                'path' => $file_path,
                'name' => $plugin_data['Name'],
                'tab' => 'user',
                'description' => $plugin_data['Description'],
                'version' => $plugin_data['Version'],
                'long_description' => $plugin_data['LongDescription'],
                'marketing_description' => $plugin_data['MarketingDescription'],
                'parameters' => $plugin_data['Parameters']
            );
        }

        return $user_addons;
    }

    private function get_addons()
    {
        $addons = array(
            'wp' => array(),
            'woo' => array(),
            'multisite' => array(),
            'user' => array(),
        );

        foreach ($this->get_core_folders_map() as $folder => $tab) {
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
                        $relative_file = $folder . '/' . $file;
                        $addons[$tab][] = array(
                            'id' => $this->build_core_addon_id($relative_file),
                            'origin' => 'core',
                            'file' => $file,
                            'relative_file' => $relative_file,
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

        $addons['user'] = $this->get_user_addons();

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

    private function get_all_addons_index()
    {
        $index = array();
        $all_addons = $this->get_addons();
        foreach ($all_addons as $tab_addons) {
            foreach ($tab_addons as $addon) {
                if (empty($addon['id'])) {
                    continue;
                }
                $index[(string) $addon['id']] = $addon;
            }
        }

        return $index;
    }

    private function get_all_active_addon_ids()
    {
        $ids = array();

        foreach ($this->get_active_core_addons() as $relative_file) {
            $ids[$this->build_core_addon_id($relative_file)] = true;
        }

        foreach ($this->get_active_user_addons() as $file_name) {
            $ids[$this->build_user_addon_id($file_name)] = true;
        }

        return array_keys($ids);
    }

    private function get_blocked_addons()
    {
        $blocked = get_option(self::OPTION_BLOCKED_ADDONS, array());
        return is_array($blocked) ? $blocked : array();
    }

    private function set_blocked_addon($addon_id, $message)
    {
        $blocked = $this->get_blocked_addons();
        $blocked[(string) $addon_id] = array(
            'message' => (string) $message,
            'time' => time(),
        );
        update_option(self::OPTION_BLOCKED_ADDONS, $blocked);
    }

    private function clear_blocked_addon($addon_id)
    {
        $blocked = $this->get_blocked_addons();
        if (isset($blocked[$addon_id])) {
            unset($blocked[$addon_id]);
            update_option(self::OPTION_BLOCKED_ADDONS, $blocked);
        }
    }

    private function set_admin_notice($type, $message)
    {
        update_option(self::OPTION_ADMIN_NOTICE, array(
            'type' => (string) $type,
            'message' => (string) $message,
            'time' => time(),
        ));
    }

    private function set_auto_disabled_notice($addon_name, $reason)
    {
        $addon_name = trim((string) $addon_name);
        if ($addon_name === '') {
            $addon_name = 'addon';
        }

        $reason = trim((string) $reason);
        if ($reason === '') {
            $reason = 'Motivo no especificado.';
        }

        $this->set_admin_notice('error', 'Se desactivó automáticamente "' . $addon_name . '" por seguridad. Motivo: ' . $reason);
    }

    private function get_addon_label_from_id($addon_id)
    {
        $parsed = $this->parse_addon_id($addon_id);
        if ($parsed['origin'] === 'core') {
            return basename((string) $parsed['value']);
        }

        if ($parsed['origin'] === 'user') {
            return basename((string) $parsed['value']);
        }

        return (string) $addon_id;
    }

    private function get_recent_blocked_notice($max_age_seconds = 1800)
    {
        $blocked = $this->get_blocked_addons();
        if (empty($blocked)) {
            return array();
        }

        $latest_id = '';
        $latest = array();
        foreach ($blocked as $addon_id => $payload) {
            if (!is_array($payload)) {
                continue;
            }

            $blocked_time = isset($payload['time']) ? (int) $payload['time'] : 0;
            if ($blocked_time <= 0) {
                continue;
            }

            if (empty($latest) || $blocked_time > (int) $latest['time']) {
                $latest = $payload;
                $latest_id = (string) $addon_id;
            }
        }

        if (empty($latest) || $latest_id === '') {
            return array();
        }

        $age = time() - (int) $latest['time'];
        if ($age < 0 || $age > (int) $max_age_seconds) {
            return array();
        }

        $label = $this->get_addon_label_from_id($latest_id);
        $reason = isset($latest['message']) ? trim((string) $latest['message']) : '';
        if ($reason === '') {
            $reason = 'Motivo no especificado.';
        }

        return array(
            'type' => 'error',
            'message' => 'Se desactivó automáticamente "' . $label . '" por seguridad. Motivo: ' . $reason,
        );
    }

    private function consume_admin_notice()
    {
        $notice = get_option(self::OPTION_ADMIN_NOTICE, array());
        delete_option(self::OPTION_ADMIN_NOTICE);
        return is_array($notice) ? $notice : array();
    }

    private function get_notice_from_query()
    {
        if (!isset($_GET['am_notice_msg'])) {
            return array();
        }

        $query_notice_type = isset($_GET['am_notice_type']) ? sanitize_key(wp_unslash($_GET['am_notice_type'])) : 'error';
        $query_notice_msg = sanitize_text_field((string) wp_unslash($_GET['am_notice_msg']));
        if ($query_notice_msg === '') {
            return array();
        }

        return array(
            'type' => $query_notice_type === 'success' ? 'success' : 'error',
            'message' => $query_notice_msg,
        );
    }

    public function display_global_admin_notice()
    {
        if (self::$global_notice_rendered) {
            return;
        }

        if (!is_admin() || (!current_user_can('manage_options') && !current_user_can('activate_plugins'))) {
            return;
        }

        $notice = $this->consume_admin_notice();
        $query_notice = $this->get_notice_from_query();
        if (!empty($query_notice['message'])) {
            $notice = $query_notice;
        }

        if (empty($notice['message'])) {
            $notice = $this->get_recent_blocked_notice();
        }

        if (empty($notice['message'])) {
            return;
        }

        $notice_type = !empty($notice['type']) && $notice['type'] === 'success' ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . esc_attr($notice_type) . ' is-dismissible"><p>' . esc_html((string) $notice['message']) . '</p></div>';
        self::$global_notice_rendered = true;
    }

    private function get_approved_signatures()
    {
        $raw = get_option(self::OPTION_APPROVED_SIGNATURES, array());
        if (!is_array($raw)) {
            $raw = array();
        }

        $normalized = array();
        foreach ($raw as $addon_id => $signature) {
            $addon_id = trim((string) $addon_id);
            $signature = trim((string) $signature);
            if ($addon_id === '' || $signature === '') {
                continue;
            }
            $normalized[$addon_id] = $signature;
        }

        if ($normalized !== $raw) {
            update_option(self::OPTION_APPROVED_SIGNATURES, $normalized);
        }

        return $normalized;
    }

    private function set_approved_signatures(array $signatures)
    {
        $normalized = array();
        foreach ($signatures as $addon_id => $signature) {
            $addon_id = trim((string) $addon_id);
            $signature = trim((string) $signature);
            if ($addon_id === '' || $signature === '') {
                continue;
            }
            $normalized[$addon_id] = $signature;
        }

        update_option(self::OPTION_APPROVED_SIGNATURES, $normalized);
    }

    private function set_approved_signature($addon_id, $signature)
    {
        $addon_id = trim((string) $addon_id);
        $signature = trim((string) $signature);
        if ($addon_id === '' || $signature === '') {
            return;
        }

        $signatures = $this->get_approved_signatures();
        if (isset($signatures[$addon_id]) && $signatures[$addon_id] === $signature) {
            return;
        }

        $signatures[$addon_id] = $signature;
        $this->set_approved_signatures($signatures);
    }

    private function clear_approved_signature($addon_id)
    {
        $addon_id = trim((string) $addon_id);
        if ($addon_id === '') {
            return;
        }

        $signatures = $this->get_approved_signatures();
        if (!isset($signatures[$addon_id])) {
            return;
        }

        unset($signatures[$addon_id]);
        $this->set_approved_signatures($signatures);
    }

    private function build_addon_file_signature($file_path)
    {
        $file_path = (string) $file_path;
        if ($file_path === '' || !is_file($file_path)) {
            return '';
        }

        $mtime = @filemtime($file_path);
        $size = @filesize($file_path);
        $hash = '';
        if (function_exists('sha1_file')) {
            $hash_value = @sha1_file($file_path);
            if (is_string($hash_value)) {
                $hash = $hash_value;
            }
        }

        return (string) ((int) $mtime) . '|' . (string) ((int) $size) . '|' . $hash;
    }

    private function get_php_lint_binary()
    {
        $candidates = array();

        if (defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '') {
            $php_binary = (string) PHP_BINARY;
            $php_binary_name = strtolower((string) basename($php_binary));
            if (strpos($php_binary_name, 'php-fpm') !== false) {
                $candidates[] = dirname($php_binary) . '/php';
            } else {
                $candidates[] = $php_binary;
            }
        }

        if (defined('PHP_BINDIR') && is_string(PHP_BINDIR) && PHP_BINDIR !== '') {
            $candidates[] = rtrim((string) PHP_BINDIR, '/\\') . '/php';
        }

        if (function_exists('exec')) {
            $output = array();
            $exit_code = 0;
            @exec('command -v php 2>/dev/null', $output, $exit_code);
            if ($exit_code === 0 && !empty($output[0])) {
                $candidates[] = trim((string) $output[0]);
            }
        }

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '') {
                continue;
            }

            $candidate_name = strtolower((string) basename($candidate));
            if (strpos($candidate_name, 'php-fpm') !== false) {
                continue;
            }

            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private function build_syntax_error_message($lint_message = '')
    {
        $message = 'Error de sintaxis en el addon. Revisa el archivo y vuelve a intentarlo.';
        if (preg_match('/on line\s+(\d+)/i', (string) $lint_message, $matches)) {
            $message .= ' Línea ' . (int) $matches[1] . '.';
        }

        return $message;
    }

    private function lint_php_file($file_path, &$lint_message = '')
    {
        $lint_message = '';

        if (!function_exists('exec')) {
            return true;
        }

        $php_binary = $this->get_php_lint_binary();
        if ($php_binary === '') {
            return true;
        }

        $command = escapeshellarg($php_binary) . ' -l ' . escapeshellarg($file_path) . ' 2>&1';
        $output = array();
        $exit_code = 0;
        @exec($command, $output, $exit_code);

        if ($exit_code !== 0) {
            $lint_message = trim(implode("\n", $output));
            return false;
        }

        return true;
    }

    private function find_blocked_pattern($content)
    {
        $patterns = array(
            '/\beval\s*\(/i' => 'eval()',
            '/\bbase64_decode\s*\(/i' => 'base64_decode()',
            '/\bshell_exec\s*\(/i' => 'shell_exec()',
            '/\bexec\s*\(/i' => 'exec()',
            '/\bsystem\s*\(/i' => 'system()',
            '/\bpassthru\s*\(/i' => 'passthru()',
            '/\bproc_open\s*\(/i' => 'proc_open()',
            '/\bpopen\s*\(/i' => 'popen()',
        );

        foreach ($patterns as $regex => $label) {
            if (preg_match($regex, $content)) {
                return $label;
            }
        }

        return '';
    }

    private function sanitize_user_addon_filename($original_name)
    {
        $base = sanitize_file_name((string) $original_name);
        $base = basename($base);
        if ($base === '' || substr($base, -4) !== '.php') {
            return '';
        }

        return $base;
    }

    private function validate_user_addon_upload($file)
    {
        if (empty($file['tmp_name']) || !is_file($file['tmp_name'])) {
            return array('ok' => false, 'message' => 'No se recibió un archivo válido.');
        }

        $file_name = $this->sanitize_user_addon_filename($file['name']);
        if ($file_name === '') {
            return array('ok' => false, 'message' => 'Solo se permiten archivos .php válidos.');
        }

        $size = isset($file['size']) ? (int) $file['size'] : 0;
        if ($size <= 0 || $size > self::MAX_USER_ADDON_SIZE) {
            return array('ok' => false, 'message' => 'El archivo supera el tamaño máximo permitido (512 KB).');
        }

        $tmp_path = (string) $file['tmp_name'];
        $content = file_get_contents($tmp_path);
        if (!is_string($content) || trim($content) === '') {
            return array('ok' => false, 'message' => 'No se pudo leer el archivo subido.');
        }

        if (strpos($content, '<?php') === false) {
            return array('ok' => false, 'message' => 'El archivo debe contener código PHP válido.');
        }

        $blocked_pattern = $this->find_blocked_pattern($content);
        if ($blocked_pattern !== '') {
            return array('ok' => false, 'message' => 'Patrón bloqueado detectado: ' . $blocked_pattern);
        }

        $plugin_data = get_file_data($tmp_path, array(
            'Name' => 'Plugin Name',
            'Description' => 'Description',
            'Version' => 'Version',
        ));

        if (empty($plugin_data['Name'])) {
            return array('ok' => false, 'message' => 'El addon debe incluir cabecera con Plugin Name.');
        }

        $lint_message = '';
        if (!$this->lint_php_file($tmp_path, $lint_message)) {
            return array('ok' => false, 'message' => $this->build_syntax_error_message($lint_message));
        }

        return array(
            'ok' => true,
            'message' => '',
            'file_name' => $file_name,
        );
    }

    private function build_upload_redirect_url($type, $message, $tab = 'user')
    {
        $tab = sanitize_key((string) $tab);
        if ($tab === '') {
            $tab = 'user';
        }

        return add_query_arg(array(
            'page' => 'addon-manager',
            'tab' => $tab,
            'am_notice_type' => sanitize_key((string) $type),
            'am_notice_msg' => (string) $message,
        ), admin_url('admin.php'));
    }

    private function redirect_with_notice($type, $message)
    {
        $type = $type === 'success' ? 'success' : 'error';
        $message = (string) $message;
        $this->set_admin_notice($type, $message);
        $url = $this->build_upload_redirect_url($type, $message, 'user');

        if (!headers_sent()) {
            wp_safe_redirect($url);
            exit;
        }

        echo '<div class="wrap"><h1>Addon Manager</h1><p>' . esc_html($message) . '</p>';
        echo '<p><a class="button button-primary" href="' . esc_url($url) . '">Volver</a></p></div>';
        exit;
    }

    public function handle_user_addon_upload()
    {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos.');
        }

        check_admin_referer('addon_manager_upload_user_addon');

        $result = $this->process_user_addon_upload();
        if (empty($result['ok'])) {
            $this->redirect_with_notice('error', (string) $result['message']);
        }

        $this->redirect_with_notice('success', (string) $result['message']);
    }

    public function handle_user_addon_delete()
    {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos.');
        }

        check_admin_referer('addon_manager_delete_user_addon');

        $file_name = '';
        if (isset($_POST['user_addon_file'])) {
            $file_name = $this->sanitize_user_addon_filename(wp_unslash($_POST['user_addon_file']));
        }

        if ($file_name === '') {
            $this->redirect_with_notice('error', 'Archivo de addon inválido.');
        }

        $addon_id = $this->build_user_addon_id($file_name);
        $addon_path = trailingslashit($this->get_user_addons_dir()) . $file_name;
        if (!is_file($addon_path)) {
            $this->disable_addon_by_id($addon_id);
            $this->clear_blocked_addon($addon_id);
            $this->redirect_with_notice('error', 'El archivo no existe o ya fue eliminado.');
        }

        $this->disable_addon_by_id($addon_id);
        $this->clear_blocked_addon($addon_id);

        $pending = $this->get_pending_activation();
        if (!empty($pending['addon_id']) && $pending['addon_id'] === $addon_id) {
            $this->clear_pending_activation();
        }

        if (!@unlink($addon_path)) {
            $this->redirect_with_notice('error', 'No se pudo eliminar el addon. Revisa permisos de carpeta.');
        }

        $this->redirect_with_notice('success', 'Addon de usuario eliminado correctamente.');
    }

    private function process_user_addon_upload()
    {
        $user_dir = $this->ensure_user_addons_dir();
        if ($user_dir === '') {
            return array(
                'ok' => false,
                'message' => 'No se pudo preparar la carpeta de addons de usuario.',
            );
        }

        if (empty($_FILES['user_addon_file']) || !is_array($_FILES['user_addon_file'])) {
            return array(
                'ok' => false,
                'message' => 'Debes seleccionar un archivo .php.',
            );
        }

        $file = $_FILES['user_addon_file'];
        if (!empty($file['error'])) {
            return array(
                'ok' => false,
                'message' => 'Error al subir el archivo. Código: ' . (int) $file['error'],
            );
        }

        $validation = $this->validate_user_addon_upload($file);
        if (empty($validation['ok'])) {
            return array(
                'ok' => false,
                'message' => (string) $validation['message'],
            );
        }

        $file_name = (string) $validation['file_name'];
        $target = trailingslashit($user_dir) . $file_name;
        if (is_file($target)) {
            return array(
                'ok' => false,
                'message' => 'Ya existe un addon con ese nombre. Renómbralo antes de subirlo.',
            );
        }

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            return array(
                'ok' => false,
                'message' => 'No se pudo mover el archivo al directorio de addons de usuario.',
            );
        }

        @chmod($target, 0644);

        return array(
            'ok' => true,
            'message' => 'Addon subido correctamente. Ya puedes activarlo desde la lista.',
            'file_name' => $file_name,
        );
    }

    public function handle_user_addon_upload_ajax()
    {
        check_ajax_referer('addon_manager_upload_user_addon', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => 'No tienes permisos.',
            ));
        }

        $result = $this->process_user_addon_upload();
        if (empty($result['ok'])) {
            $message = (string) $result['message'];
            $this->set_admin_notice('error', $message);
            wp_send_json_error(array(
                'message' => $message,
                'redirect_url' => $this->build_upload_redirect_url('error', $message, 'user'),
            ));
        }

        $message = (string) $result['message'];
        $this->set_admin_notice('success', $message);
        wp_send_json_success(array(
            'message' => $message,
            'redirect_url' => $this->build_upload_redirect_url('success', $message, 'user'),
        ));
    }

    private function set_pending_activation($addon)
    {
        update_option(self::OPTION_PENDING_ACTIVATION, array(
            'addon_id' => (string) ($addon['id'] ?? ''),
            'addon_name' => (string) ($addon['name'] ?? ''),
            'origin' => (string) ($addon['origin'] ?? ''),
            'created_at' => time(),
        ));
    }

    private function get_pending_activation()
    {
        $pending = get_option(self::OPTION_PENDING_ACTIVATION, array());
        return is_array($pending) ? $pending : array();
    }

    private function clear_pending_activation()
    {
        delete_option(self::OPTION_PENDING_ACTIVATION);
    }

    private function set_runtime_loading_marker($addon_id, $addon_name)
    {
        update_option(self::OPTION_RUNTIME_LOADING, array(
            'addon_id' => (string) $addon_id,
            'addon_name' => (string) $addon_name,
            'created_at' => time(),
        ));
    }

    private function get_runtime_loading_marker()
    {
        $payload = get_option(self::OPTION_RUNTIME_LOADING, array());
        return is_array($payload) ? $payload : array();
    }

    private function clear_runtime_loading_marker()
    {
        delete_option(self::OPTION_RUNTIME_LOADING);
    }

    private function perform_activation_healthcheck($addon)
    {
        $token = wp_generate_password(20, false, false);
        $transient_key = self::HEALTHCHECK_TRANSIENT_PREFIX . $token;
        set_transient($transient_key, array(
            'addon_id' => (string) ($addon['id'] ?? ''),
            'created_at' => time(),
        ), MINUTE_IN_SECONDS * 2);

        $url = add_query_arg(array(
            'addon_manager_healthcheck' => 1,
            'am_token' => $token,
            'am_addon' => (string) ($addon['id'] ?? ''),
        ), home_url('/'));

        $response = wp_remote_get($url, array(
            'timeout' => 12,
            'redirection' => 3,
            'sslverify' => apply_filters('https_local_ssl_verify', false),
            'headers' => array(
                'Cache-Control' => 'no-cache',
                'Pragma' => 'no-cache',
            ),
        ));

        if (is_wp_error($response)) {
            return array(
                'ok' => false,
                'message' => 'No pudimos validar el addon en este momento. Inténtalo de nuevo en unos segundos.',
            );
        }

        $http_code = (int) wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            $message = 'No se pudo activar el addon porque no superó la validación automática.';
            if ($http_code >= 500) {
                $message = 'No se pudo activar el addon porque provocó un error interno durante la validación.';
            } elseif ($http_code === 401 || $http_code === 403) {
                $message = 'No se pudo activar el addon por una restricción de acceso durante la validación.';
            }

            return array(
                'ok' => false,
                'message' => $message,
            );
        }

        $body = wp_remote_retrieve_body($response);
        $json = json_decode((string) $body, true);
        if (!is_array($json)) {
            return array(
                'ok' => false,
                'message' => 'No pudimos confirmar la validación automática del addon. No se ha activado.',
            );
        }

        if (empty($json['success'])) {
            $message = 'Falló la verificación interna.';
            if (!empty($json['data']['message']) && is_string($json['data']['message'])) {
                $message = (string) $json['data']['message'];
            }
            return array(
                'ok' => false,
                'message' => $message,
            );
        }

        return array(
            'ok' => true,
            'message' => '',
        );
    }

    public function maybe_handle_healthcheck_request()
    {
        if (!isset($_GET['addon_manager_healthcheck'])) {
            return;
        }

        $token = isset($_GET['am_token']) ? sanitize_text_field(wp_unslash($_GET['am_token'])) : '';
        if ($token === '') {
            wp_send_json_error(array('message' => 'Token faltante.'), 403);
        }

        $transient_key = self::HEALTHCHECK_TRANSIENT_PREFIX . $token;
        $payload = get_transient($transient_key);
        delete_transient($transient_key);

        if (!is_array($payload) || empty($payload['addon_id'])) {
            wp_send_json_error(array('message' => 'Token inválido.'), 403);
        }

        $this->is_healthcheck_request = true;
        $this->healthcheck_payload = $payload;
    }

    private function is_fatal_error($error)
    {
        if (!is_array($error) || !isset($error['type'])) {
            return false;
        }

        $fatal_types = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
        return in_array((int) $error['type'], $fatal_types, true);
    }

    private function disable_addon_by_id($addon_id)
    {
        $addon_id = (string) $addon_id;
        $parsed = $this->parse_addon_id($addon_id);
        if ($parsed['origin'] === 'core') {
            $active_core = $this->get_active_core_addons();
            $active_core = array_values(array_diff($active_core, array($parsed['value'])));
            $this->set_active_core_addons($active_core);
            $this->clear_approved_signature($addon_id);
            return;
        }

        if ($parsed['origin'] === 'user') {
            $active_user = $this->get_active_user_addons();
            $active_user = array_values(array_diff($active_user, array($parsed['value'])));
            $this->set_active_user_addons($active_user);
            $this->clear_approved_signature($addon_id);
        }
    }

    public function handle_shutdown_fatal_recovery()
    {
        $error = error_get_last();
        if (!$this->is_fatal_error($error)) {
            return;
        }

        $error_message = isset($error['message']) ? (string) $error['message'] : 'Fatal error';
        $runtime_loading = $this->get_runtime_loading_marker();
        if (!empty($runtime_loading['addon_id']) && !empty($runtime_loading['created_at'])) {
            if ((time() - (int) $runtime_loading['created_at']) <= 600) {
                $addon_id = (string) $runtime_loading['addon_id'];
                $addon_name = !empty($runtime_loading['addon_name']) ? (string) $runtime_loading['addon_name'] : $addon_id;

                $this->disable_addon_by_id($addon_id);
                $this->set_blocked_addon($addon_id, $error_message);
                $this->set_admin_notice('error', 'Se desactivó automáticamente "' . $addon_name . '" por error crítico al cargar el addon.');
            }

            $this->clear_runtime_loading_marker();
            return;
        }

        $pending = $this->get_pending_activation();
        if (empty($pending['addon_id']) || empty($pending['created_at'])) {
            return;
        }

        if ((time() - (int) $pending['created_at']) > 600) {
            return;
        }

        $addon_id = (string) $pending['addon_id'];
        $addon_name = !empty($pending['addon_name']) ? (string) $pending['addon_name'] : $addon_id;

        $this->disable_addon_by_id($addon_id);
        $this->set_blocked_addon($addon_id, $error_message);
        $this->set_admin_notice('error', 'Se desactivó automáticamente "' . $addon_name . '" por error crítico: ' . $error_message);
        $this->clear_pending_activation();
    }

    private function get_detected_settings_pages()
    {
        global $_registered_pages, $wp_filter;

        if (empty($_registered_pages) || !is_array($_registered_pages)) {
            return array();
        }

        $plugin_base = wp_normalize_path(plugin_dir_path(__FILE__));
        $user_base = wp_normalize_path($this->get_user_addons_dir());
        $active_lookup = array_flip($this->get_all_active_addon_ids());
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
            $addon_id = '';

            if (strpos($normalized_file, $plugin_base) === 0) {
                $relative_file = ltrim(str_replace($plugin_base, '', $normalized_file), '/');
                if (
                    strpos($relative_file, 'addons/') === 0 ||
                    strpos($relative_file, 'woo/') === 0 ||
                    strpos($relative_file, 'multisite/') === 0
                ) {
                    $addon_id = $this->build_core_addon_id($relative_file);
                }
            } elseif ($user_base !== '' && strpos($normalized_file, trailingslashit($user_base)) === 0) {
                $addon_id = $this->build_user_addon_id(basename($normalized_file));
            }

            if ($addon_id === '' || !isset($active_lookup[$addon_id])) {
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

            if (!isset($detected[$addon_id])) {
                $detected[$addon_id] = array(
                    'pages' => array(),
                );
            }

            if (!isset($detected[$addon_id]['pages'][$slug])) {
                $detected[$addon_id]['pages'][$slug] = array(
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
        foreach ($detected as $addon_id => $data) {
            ksort($detected[$addon_id]['pages']);
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
            $valid_tabs = array('wp', 'user');
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

                <a href="?page=addon-manager&tab=user" class="nav-tab <?php echo $active_tab === 'user' ? 'nav-tab-active' : ''; ?>">Addons de usuario</a>
            </h2>

            <div id="addon-message"></div>

            <?php
            $user_dir = '';
            if ($active_tab === 'user') {
                $user_dir = $this->ensure_user_addons_dir();
            }

            $all_addons = $this->get_addons();
            $addons = isset($all_addons[$active_tab]) ? $all_addons[$active_tab] : array();
            $active_lookup = array_flip($this->get_all_active_addon_ids());
            $settings_pages = $this->get_detected_settings_pages();
            $blocked_addons = $this->get_blocked_addons();

            if (empty($addons)) {
                echo '<p>No hay addons disponibles en esta sección.</p>';
            } else {
                echo '<div class="addons-grid">';
                foreach ($addons as $addon) {
                    $addon_id = isset($addon['id']) ? (string) $addon['id'] : '';
                    if ($addon_id === '') {
                        continue;
                    }

                    $is_active = isset($active_lookup[$addon_id]);
                    $parameters_text = $this->get_addon_parameters_text($addon);
                    $description_text = $this->get_addon_description_text($addon);
                    $addon_pages = isset($settings_pages[$addon_id]['pages']) ? $settings_pages[$addon_id]['pages'] : array();
                    $blocked_message = isset($blocked_addons[$addon_id]['message']) ? (string) $blocked_addons[$addon_id]['message'] : '';
            ?>
                    <div class="addon-card <?php echo $is_active ? 'active' : ''; ?>">
                        <div class="addon-header">
                            <label class="switch">
                                <input type="checkbox"
                                    class="addon-toggle"
                                    data-addon="<?php echo esc_attr($addon_id); ?>"
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
                            <?php if (!$is_active && $blocked_message !== ''): ?>
                                <p style="margin:10px 0 0;color:#b32d2e;"><strong>Último bloqueo:</strong> <?php echo esc_html($blocked_message); ?></p>
                            <?php endif; ?>
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
                            <?php if (isset($addon['origin']) && (string) $addon['origin'] === 'user' && !empty($addon['file'])): ?>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:10px;" onsubmit="return confirm('¿Seguro que quieres eliminar este addon de usuario?');">
                                    <input type="hidden" name="action" value="addon_manager_delete_user_addon">
                                    <input type="hidden" name="user_addon_file" value="<?php echo esc_attr((string) $addon['file']); ?>">
                                    <?php wp_nonce_field('addon_manager_delete_user_addon'); ?>
                                    <button type="submit" class="button button-link-delete">Eliminar archivo</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
            <?php
                }
                echo '</div>';
            }

            if ($active_tab === 'user'):
            ?>
                <div class="addon-upload-box" style="background:#fff;border-radius:12px;padding:14px;margin-top:14px;">
                    <h3 style="margin-top:0;">Subir addon de usuario (.php)</h3>
                    <form id="am-user-addon-upload-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" data-nonce="<?php echo esc_attr(wp_create_nonce('addon_manager_upload_user_addon')); ?>">
                        <input type="hidden" name="action" value="addon_manager_upload_user_addon">
                        <?php wp_nonce_field('addon_manager_upload_user_addon'); ?>
                        <input type="file" name="user_addon_file" accept=".php" required>
                        <button type="submit" class="button button-secondary">Subir addon</button>
                    </form>
                    <p style="margin:10px 0 0;"><strong>Ruta:</strong> <code><?php echo esc_html($user_dir !== '' ? $user_dir : 'No disponible'); ?></code></p>
                    <p style="margin:8px 0 0;"><strong>Cabecera mínima recomendada:</strong></p>
                    <pre style="margin:8px 0 0;padding:10px;background:#f6f7f7;border-radius:8px;white-space:pre-wrap;"><code>/**
 * Plugin Name: Mi Addon
 * Description: Que hace el addon
 * Marketing Description: Resumen comercial para la tarjeta
 * Parameters: Shortcode [mi_addon foo="bar"] o "Sin parametros"
 * Version: 1.0.0
 */</code></pre>
                    <p style="margin:8px 0 0;color:#666;">Detalle útil: si falta <code>Marketing Description</code>, se usa <code>Description</code>. Si falta <code>Parameters</code>, se mostrará “Sin parámetros”.</p>
                </div>
            <?php endif; ?>

            <div class="addon-instructions" style="background:#ffffff;border-radius:20px;padding:15px;margin:20px 0;">
                <h2 style="margin-top:0;">Instrucciones de Uso</h2>
                <p><strong>¿Qué es esto?</strong> Gestor de addons modulares para extender funcionalidades de WordPress sin sobrecargar el sitio.</p>

                <h3> Cómo usar:</h3>
                <ol>
                    <li><strong>Activar/Desactivar:</strong> Usa los switches para activar solo los addons que necesites.</li>
                    <li><strong>Añadir nuevos addons de usuario:</strong> En la pestaña "Addons de usuario" sube un único archivo <code>.php</code> a <code>uploads/addon-manager/user-addons/</code>.</li>
                    <li><strong>Estructura recomendada:</strong> Cada addon debe tener cabecera con <code>Plugin Name</code>, <code>Description</code>, <code>Marketing Description</code>, <code>Parameters</code> y <code>Version</code>.</li>
                    <li><strong>Tarjetas en UI:</strong> "Descripción" usa <code>Marketing Description</code> (fallback: <code>Description</code>) y "Parámetros" usa <code>Parameters</code> (fallback legacy: <code>Long Description</code>).</li>
                    <li><strong>Activación segura:</strong> cualquier addon (propio o de usuario) entra en cuarentena y pasa un loopback healthcheck antes de quedar activo.</li>
                </ol>

                <h3> Consideraciones:</h3>
                <ul>
                    <li>Si falla el healthcheck, el addon se revierte automáticamente y se registra aviso claro en admin.</li>
                    <li>Si ocurre fatal tras activarlo, se desactiva automáticamente el último addon activado.</li>
                    <li>Solo se ejecutan los que tienen el <strong>switch activado</strong>.</li>
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

        $addon_id = isset($_POST['addon']) ? sanitize_text_field(wp_unslash($_POST['addon'])) : '';
        if ($addon_id === '') {
            wp_send_json_error('Addon inválido.');
        }

        $all_addons = $this->get_all_addons_index();
        if (!isset($all_addons[$addon_id])) {
            wp_send_json_error('No se encontró el addon solicitado.');
        }

        $addon = $all_addons[$addon_id];
        $parsed = $this->parse_addon_id($addon_id);
        if ($parsed['origin'] === '') {
            wp_send_json_error('ID de addon inválido.');
        }

        $is_active = in_array($addon_id, $this->get_all_active_addon_ids(), true);
        if ($is_active) {
            $this->disable_addon_by_id($addon_id);
            $this->clear_blocked_addon($addon_id);
            $pending = $this->get_pending_activation();
            if (!empty($pending['addon_id']) && $pending['addon_id'] === $addon_id) {
                $this->clear_pending_activation();
            }

            $success_message = 'Addon desactivado correctamente.';
            $this->set_admin_notice('success', $success_message);
            $current_tab = isset($_POST['current_tab']) ? sanitize_key(wp_unslash($_POST['current_tab'])) : 'wp';
            wp_send_json_success(array(
                'message' => $success_message,
                'redirect_url' => $this->build_upload_redirect_url('success', $success_message, $current_tab),
            ));
        }

        if ($parsed['origin'] === 'user') {
            $user_file_path = $this->get_addon_path_by_id($addon_id);
            if (!is_file($user_file_path)) {
                $reason = 'El archivo del addon de usuario no existe.';
                $this->set_admin_notice('error', $reason);
                $current_tab = isset($_POST['current_tab']) ? sanitize_key(wp_unslash($_POST['current_tab'])) : 'user';
                wp_send_json_error(array(
                    'message' => $reason,
                    'redirect_url' => $this->build_upload_redirect_url('error', $reason, $current_tab),
                ));
            }

            $lint_message = '';
            if (!$this->lint_php_file($user_file_path, $lint_message)) {
                $reason = $this->build_syntax_error_message($lint_message);
                $this->set_blocked_addon($addon_id, $reason);
                $this->set_admin_notice('error', $reason);
                $current_tab = isset($_POST['current_tab']) ? sanitize_key(wp_unslash($_POST['current_tab'])) : 'user';
                wp_send_json_error(array(
                    'message' => $reason,
                    'redirect_url' => $this->build_upload_redirect_url('error', $reason, $current_tab),
                ));
            }
        }

        $this->set_pending_activation($addon);
        $health = $this->perform_activation_healthcheck($addon);
        if (empty($health['ok'])) {
            $reason = isset($health['message']) ? (string) $health['message'] : 'Falló la verificación de salud.';
            $this->set_blocked_addon($addon_id, $reason);
            $this->set_admin_notice('error', 'No se activó "' . $addon['name'] . '": ' . $reason);
            $this->clear_pending_activation();
            $current_tab = isset($_POST['current_tab']) ? sanitize_key(wp_unslash($_POST['current_tab'])) : 'wp';
            $error_message = 'No se activó el addon: ' . $reason;
            wp_send_json_error(array(
                'message' => $error_message,
                'redirect_url' => $this->build_upload_redirect_url('error', $error_message, $current_tab),
            ));
        }

        if ($parsed['origin'] === 'core') {
            $active_core = $this->get_active_core_addons();
            $active_core[] = $parsed['value'];
            $this->set_active_core_addons($active_core);
        } else {
            $active_user = $this->get_active_user_addons();
            $active_user[] = $parsed['value'];
            $this->set_active_user_addons($active_user);
        }

        $approved_path = $this->get_addon_path_by_id($addon_id);
        $approved_signature = $this->build_addon_file_signature($approved_path);
        if ($approved_signature !== '') {
            $this->set_approved_signature($addon_id, $approved_signature);
        }

        $this->clear_pending_activation();
        $this->clear_blocked_addon($addon_id);
        $success_message = 'Addon activado correctamente tras verificación.';
        $this->set_admin_notice('success', $success_message);
        $current_tab = isset($_POST['current_tab']) ? sanitize_key(wp_unslash($_POST['current_tab'])) : 'wp';
        wp_send_json_success(array(
            'message' => $success_message,
            'redirect_url' => $this->build_upload_redirect_url('success', $success_message, $current_tab),
        ));
    }
}

new Addon_Manager();

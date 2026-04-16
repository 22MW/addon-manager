<?php
defined('ABSPATH') || exit;

/**
 * GitHub Releases updater for Addon Manager.
 *
 * Important:
 * - SLUG must match plugin folder name: addon-manager
 * - Main file must remain: addon-manager/addon-manager.php
 */
final class Addon_Manager_Github_Updater
{
    private const REPO = '22MW/addon-manager';
    private const ASSET_NAME = 'addon-manager.zip';
    private const SLUG = 'addon-manager';
    private const CACHE_KEY = 'addon_manager_github_release_latest';

    public function register_hooks(): void
    {
        add_filter('site_transient_update_plugins', array($this, 'filter_plugin_updates'));
        add_filter('plugins_api', array($this, 'filter_plugin_info'), 10, 3);
        add_filter('upgrader_source_selection', array($this, 'fix_source_dir'), 10, 4);
    }

    /**
     * Fetch latest GitHub release, cached for one hour.
     *
     * @return array<string,mixed>|null
     */
    private function get_latest_release(): ?array
    {
        $cached = get_transient(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $response = wp_remote_get(
            'https://api.github.com/repos/' . self::REPO . '/releases/latest',
            array(
                'timeout' => 10,
                'headers' => array(
                    'Accept' => 'application/vnd.github+json',
                    'User-Agent' => 'Addon-Manager',
                ),
            )
        );

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data)) {
            return null;
        }

        set_transient(self::CACHE_KEY, $data, HOUR_IN_SECONDS);
        return $data;
    }

    /**
     * Extract version from tag_name (e.g. v1.0.1 -> 1.0.1).
     *
     * @param array<string,mixed> $release
     */
    private function get_remote_version(array $release): string
    {
        return ltrim((string) ($release['tag_name'] ?? ''), 'v');
    }

    /**
     * Get package URL from release assets.
     *
     * @param array<string,mixed> $release
     */
    private function get_package_url(array $release): string
    {
        if (!empty($release['assets']) && is_array($release['assets'])) {
            foreach ($release['assets'] as $asset) {
                if (is_array($asset) && isset($asset['name']) && $asset['name'] === self::ASSET_NAME) {
                    return (string) ($asset['browser_download_url'] ?? '');
                }
            }
        }
        return '';
    }

    /**
     * Inject update metadata into WP plugin updates transient.
     *
     * @param object|false $transient
     * @return object|false
     */
    public function filter_plugin_updates(object|false $transient): object|false
    {
        if (!is_object($transient) || !isset($transient->checked) || !is_array($transient->checked)) {
            return $transient;
        }

        $release = $this->get_latest_release();
        if (!$release) {
            return $transient;
        }

        $remote_version = $this->get_remote_version($release);
        $package_url = $this->get_package_url($release);
        $plugin_slug = self::SLUG . '/' . self::SLUG . '.php';

        if (empty($remote_version) || empty($package_url) || empty($transient->checked[$plugin_slug])) {
            return $transient;
        }

        if (version_compare($remote_version, $transient->checked[$plugin_slug], '<=')) {
            return $transient;
        }

        $transient->response[$plugin_slug] = (object) array(
            'slug' => self::SLUG,
            'plugin' => $plugin_slug,
            'new_version' => $remote_version,
            'url' => 'https://github.com/' . self::REPO,
            'package' => $package_url,
        );

        return $transient;
    }

    /**
     * Provide plugin information in WP's "View details" modal.
     *
     * @param false|object|array $result
     * @param string $action
     * @param object $args
     * @return false|object|array
     */
    public function filter_plugin_info($result, string $action, object $args)
    {
        if ('plugin_information' !== $action || empty($args->slug) || $args->slug !== self::SLUG) {
            return $result;
        }

        $release = $this->get_latest_release();
        if (!$release) {
            return $result;
        }

        $info = new stdClass();
        $info->name = 'Addon Manager';
        $info->slug = self::SLUG;
        $info->version = $this->get_remote_version($release);
        $info->author = '<a href="https://22mw.online/">22MW</a>';
        $info->homepage = 'https://github.com/' . self::REPO;
        $info->requires = '6.0';
        $info->requires_php = '8.0';
        $package_url = $this->get_package_url($release);
        if ($package_url === '') {
            return $result;
        }
        $info->download_link = $package_url;
        $info->sections = array(
            'description' => 'Panel central para activar/desactivar mini-addons (WordPress, WooCommerce y Multisite) desde una única interfaz.',
            'changelog' => $this->format_release_changelog($release),
        );

        return $info;
    }

    /**
     * Convert GitHub release body to simple HTML list for changelog section.
     *
     * @param array<string,mixed> $release
     */
    private function format_release_changelog(array $release): string
    {
        $body = trim((string) ($release['body'] ?? ''));
        $version = $this->get_remote_version($release);
        $date = substr((string) ($release['published_at'] ?? ''), 0, 10);

        if ($body === '') {
            return '<p><strong>' . esc_html($version) . '</strong>' . ($date ? ' - ' . esc_html($date) : '') . '</p>';
        }

        $lines = explode("\n", $body);
        $output = '<p><strong>' . esc_html($version) . '</strong>' . ($date ? ' - ' . esc_html($date) : '') . '</p><ul>';
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $line = preg_replace('/^[-*]\s+/', '', $line) ?? $line;
            $output .= '<li>' . esc_html($line) . '</li>';
        }
        $output .= '</ul>';

        return $output;
    }

    /**
     * Ensure extracted ZIP folder matches plugin slug.
     *
     * @param string $source
     * @param string $remote_source
     * @param object $upgrader
     * @param array<string,mixed> $hook_extra
     */
    public function fix_source_dir(string $source, string $remote_source, object $upgrader, array $hook_extra): string
    {
        $plugin_slug = self::SLUG . '/' . self::SLUG . '.php';

        if (empty($hook_extra['plugin']) || $hook_extra['plugin'] !== $plugin_slug) {
            return $source;
        }

        if (basename($source) === self::SLUG) {
            return $source;
        }

        $corrected = trailingslashit(dirname($source)) . self::SLUG;
        // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        if (@rename($source, $corrected)) {
            return $corrected;
        }

        return $source;
    }
}

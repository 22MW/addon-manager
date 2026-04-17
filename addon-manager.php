<?php
/**
 * Plugin Name: Addon Manager
 * Plugin URI: https://22mw.online/
 * Description: Panel central para activar/desactivar mini-addons (WordPress, WooCommerce y Multisite) desde una única interfaz.
 * Version: 1.0.6
 * Author: 22MW
 * Author URI: https://22mw.online/
 * Update URI: https://github.com/22MW/addon-manager
 * Text Domain: 22mw
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'ADDON_MANAGER_FILE', __FILE__ );
define( 'ADDON_MANAGER_VERSION', '1.0.6' );

require_once plugin_dir_path( ADDON_MANAGER_FILE ) . 'includes/Core/class-addon-manager.php';

add_action(
    'plugins_loaded',
    static function () {
        load_plugin_textdomain( '22mw', false, dirname( plugin_basename( ADDON_MANAGER_FILE ) ) . '/languages' );
    }
);

if ( is_admin() ) {
    require_once plugin_dir_path( ADDON_MANAGER_FILE ) . 'includes/class-github-updater.php';
    ( new Addon_Manager_Github_Updater() )->register_hooks();
}

new Addon_Manager();

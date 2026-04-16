<?php
/**
 * Plugin Name: Disable textdomain notice
 * Description: Reduce avisos de textdomain en ejecución/admin para limpiar logs y notificaciones.
 * Version: 1.22
 * Text Domain: 22mw_muplugins
 * Author:22MW
 * **/

add_action('init', function() {
    error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR);
    @ini_set('display_errors', 0);
}, 1);

add_action('admin_init', function() {
    error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR);
    @ini_set('display_errors', 0);
}, 1);

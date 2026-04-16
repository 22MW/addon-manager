<?php
/*
Plugin Name: Multisite Orphan Table Scanner
Description: Escanea la base de datos de la red para detectar tablas huérfanas o no vinculadas a sitios activos.
Version: 1.0

*/
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('network_admin_menu', function () {
    add_menu_page('Orphan Table Scanner', 'Orphan Tables', 'manage_network', 'orphan-tables', 'scan_orphan_tables');
});

function scan_orphan_tables() {
    global $wpdb;

    echo '<div class="wrap"><h1>Escaneo de Tablas Huérfanas</h1>';

    $sites = get_sites(['fields' => 'ids']);
    $valid_prefixes = ['wp_']; // base principal
    foreach ($sites as $site_id) {
        $valid_prefixes[] = $wpdb->get_blog_prefix($site_id);
    }

    $all_tables = $wpdb->get_col("SHOW TABLES");
    $orphans = [];

    foreach ($all_tables as $table) {
        $matched = false;
        foreach ($valid_prefixes as $prefix) {
            if (strpos($table, $prefix) === 0) {
                $matched = true;
                break;
            }
        }
        if (! $matched) {
            $orphans[] = $table;
        }
    }

    echo "<h2>Tablas fuera de prefijos del multisite (probablemente huérfanas):</h2><ol>";
    foreach ($orphans as $table) {
        echo "<li style='color:red;font-weight:bold;'>$table</li>";
    }
    echo "</ol>";

    // Búsqueda básica de plugins comunes
    echo "<h2>Tablas posiblemente de plugins desinstalados:</h2><ol>";
    $plugins_sospechosos = ['rank_math', 'yoast', 'wordfence', 'woo_', 'mailchimp', 'w3tc', 'revslider'];
    foreach ($all_tables as $table) {
        foreach ($plugins_sospechosos as $slug) {
            if (strpos($table, $slug) !== false) {
                echo "<li>$table</li>";
                break;
            }
        }
    }
    echo "</ol>";

    echo "</div>";
}

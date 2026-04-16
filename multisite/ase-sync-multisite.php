<?php
/**
 * Plugin Name: ASE Sync Multisite
 * Description: Sincroniza la configuración de ASE en toda la red multisite y registra incidencias en log.
 * Marketing Description: Asegura consistencia operativa de ASE en toda la red multisite.
 * Parameters: Sin configuración diaria: sincroniza ASE en red y registra incidencias.
 * Author: 22MW
 * Version: 1.4
 */

if (!defined('ABSPATH')) {
    exit; // Evita acceso directo.
}

// Definir ID del sitio principal (ajustar si el ID no es 1)
//define('ASE_MAIN_SITE_ID', 1);

/**
 * Registra mensajes en el log de WordPress para depuración
 */
function ase_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG === true) {
        error_log('[ASE SYNC] ' . $message);
    }
}

/**
 * Copia la configuración de ASE del sitio principal a todos los sub-sitios.
 */
function ase_sync_settings_to_subsites() {
    if (!is_multisite() || get_current_blog_id() == ASE_MAIN_SITE_ID) {
        return;
    }

    switch_to_blog(ASE_MAIN_SITE_ID);
    $ase_settings = get_option('admin_site_enhancements');
    restore_current_blog();

    if ($ase_settings) {
        update_option('admin_site_enhancements', $ase_settings);

        // Limpiar caché y transients para aplicar cambios inmediatamente
        delete_transient('admin_site_enhancements');
        wp_cache_flush();

        // Agregar notificación de admin
       /* add_action('admin_notices', function () {
            echo '<div class="notice notice-success is-dismissible">
                    <p><strong>ASE</strong>: La configuración ha sido sincronizada con el sitio principal.</p>
                  </div>';
        });*/

    } else {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error is-dismissible">
                    <p><strong>ASE</strong>: No se pudo obtener la configuración del sitio principal.</p>
                  </div>';
        });
    }
}
//add_action('admin_init', 'ase_sync_settings_to_subsites');


/**
 * Forzar sincronización en toda la red cuando se actualiza la configuración de ASE.
 */
function ase_force_sync_on_update($option, $old_value, $new_value) {
    if (!is_multisite() || get_current_blog_id() != ASE_MAIN_SITE_ID || $option !== 'ase_settings') {
        return;
    }

    ase_log('Iniciando sincronización de ASE en toda la red.');

    $sites = get_sites(['fields' => 'ids']);
    foreach ($sites as $site_id) {
        if ($site_id != ASE_MAIN_SITE_ID) {
            switch_to_blog($site_id);
            update_option('ase_settings', $new_value);
            restore_current_blog();
            ase_log('Configuración aplicada en sitio ID ' . $site_id);
        }
    }

    ase_log('Sincronización completada.');
}
//add_action('update_option_ase_settings', 'ase_force_sync_on_update', 10, 3);

/**
 * Bloquear la configuración de ASE en los sub-sitios.
 */
function ase_block_settings_completely() {
    if (get_current_blog_id() != ASE_MAIN_SITE_ID) {
        // Oculta ASE del menú de herramientas
        remove_submenu_page('tools.php', 'admin-site-enhancements');

        // Evita el acceso directo forzando una redirección si alguien intenta acceder a ASE
        if (isset($_GET['page']) && $_GET['page'] === 'admin-site-enhancements') {
            wp_redirect(admin_url());
            exit;
        }

        // Mostrar aviso
        /*add_action('admin_notices', function () {
            echo '<div class="notice notice-warning"><p><strong>Las opciones de ASE están bloqueadas y solo pueden ser modificadas desde el sitio principal.</strong></p></div>';
        });*/

       // error_log('[ASE SYNC] Menú de ASE oculto y acceso bloqueado en sitio ID ' . get_current_blog_id());
    }
}
//add_action('admin_menu', 'ase_block_settings_completely', 999);

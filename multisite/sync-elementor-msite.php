<?php
/*
Plugin Name: Sync Elementor Multisite
Description: Sincroniza plantillas Elementor desde un sitio maestro hacia sitios destino en red multisite.
Marketing Description: Escala cambios de diseño sincronizando plantillas Elementor entre sitios.
Parameters: Sincronización de plantillas Elementor desde sitio maestro.
Author: 22MW
Version: 2.1
*/

if (!defined('ABSPATH')) exit;

define('SYNC_ELEMENTOR_MASTER_SITE_ID', 120);
define('SYNC_ELEMENTOR_INCLUDE_OPTION', 'sync_elementor_include_ids');
define('SYNC_ELEMENTOR_LOG_PATH', WP_CONTENT_DIR . '/sync-elementor.log');

// Menú administrativo solo en el sitio maestro
/*add_action('admin_menu', function() {
    if (
        get_current_blog_id() == SYNC_ELEMENTOR_MASTER_SITE_ID 
        && !is_network_admin()
    ) {
        add_menu_page(
            'Sync Elementor Multisite',
            'Sync Elementor',
            'manage_options',
            'sync-elementor',
            'sync_elementor_admin_page',
            'dashicons-update',
            60
        );
    }
});
*/
// Página de administración
function sync_elementor_admin_page() {
    $include_ids = get_site_option(SYNC_ELEMENTOR_INCLUDE_OPTION, []);
    if (!is_array($include_ids)) $include_ids = [];

    if (isset($_POST['save_inclusions'])) {
        check_admin_referer('sync_elementor_save_inclusions');
        $include_ids = array_map('intval', isset($_POST['include_ids']) ? (array)$_POST['include_ids'] : []);
        update_site_option(SYNC_ELEMENTOR_INCLUDE_OPTION, $include_ids);
        echo '<div class="updated"><p>Lista de sitios a sincronizar actualizada.</p></div>';
    }

    $sites = get_sites(['number' => 0]);
    ?>
    <div class="wrap">
        <h1>Sync Elementor Multisite</h1>

        <h2>1. Selecciona los sitios donde sincronizar</h2>
        <form method="post" id="form-sitios">
            <?php wp_nonce_field('sync_elementor_save_inclusions'); ?>
            <label><input type="checkbox" id="select-all-sites"> Seleccionar todas</label>
            <table class="widefat">
                <thead><tr><th>Sincronizar</th><th>ID</th><th>URL</th></tr></thead>
                <tbody>
                <?php foreach ($sites as $site): if ($site->blog_id == SYNC_ELEMENTOR_MASTER_SITE_ID) continue; ?>
                    <tr>
                        <td><input type="checkbox" name="include_ids[]" value="<?php echo $site->blog_id; ?>" <?php checked(in_array($site->blog_id, $include_ids)); ?>></td>
                        <td><?php echo $site->blog_id; ?></td>
                        <td><?php echo esc_url(get_site_url($site->blog_id)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p><input type="submit" name="save_inclusions" class="button button-primary" value="Guardar selección de sitios"></p>
        </form>

        <hr>

        <h2>2. Selecciona las plantillas a sincronizar</h2>
        <form method="post" id="form-plantillas">
            <?php wp_nonce_field('sync_elementor_sync_templates'); ?>
            <label><input type="checkbox" id="select-all-templates"> Seleccionar todas</label>
            <table class="widefat">
                <thead><tr><th>Sincronizar</th><th>ID</th><th>Título</th><th>Slug</th><th>Tipo</th></tr></thead>
                <tbody>
                <?php
                $templates = get_posts([
                    'post_type' => 'elementor_library',
                    'posts_per_page' => -1,
                    'orderby' => 'title',
                    'order' => 'ASC'
                ]);
                foreach ($templates as $template):
                    $type = get_post_meta($template->ID, '_elementor_template_type', true);
                ?>
                <tr>
                    <td><input type="checkbox" name="template_ids[]" value="<?php echo $template->ID; ?>"></td>
                    <td><?php echo $template->ID; ?></td>
                    <td><?php echo esc_html($template->post_title); ?></td>
                    <td><?php echo esc_html($template->post_name); ?></td>
                    <td><?php echo esc_html($type); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p><input type="submit" name="sync_elementor_now" class="button button-primary" value="SINCRONIZAR"></p>
        </form>
        <script>
        document.getElementById('select-all-sites').addEventListener('change', function() {
            document.querySelectorAll('#form-sitios input[type=checkbox][name="include_ids[]"]').forEach(function(cb) {
                cb.checked = document.getElementById('select-all-sites').checked;
            });
        });
        document.getElementById('select-all-templates').addEventListener('change', function() {
            document.querySelectorAll('#form-plantillas input[type=checkbox][name="template_ids[]"]').forEach(function(cb) {
                cb.checked = document.getElementById('select-all-templates').checked;
            });
        });
        </script>
    </div>
    <?php

    if (isset($_POST['sync_elementor_now'])) {
        sync_elementor_do_sync($include_ids, isset($_POST['template_ids']) ? (array)$_POST['template_ids'] : []);
    }
}

// Sincronización de plantillas: borra antiguo y crea nuevo SIEMPRE
function sync_elementor_do_sync($include_ids, $template_ids) {
    if (!current_user_can('manage_options')) return;
    check_admin_referer('sync_elementor_sync_templates');

    if (empty($include_ids) || empty($template_ids)) {
        echo '<div class="notice notice-error"><p>Debes seleccionar al menos un sitio y una plantilla.</p></div>';
        return;
    }

    $sites = get_sites(['number' => 0]);
    $log_entries = [];
    $results = [];

    switch_to_blog(SYNC_ELEMENTOR_MASTER_SITE_ID);
    $templates = [];
    foreach ($template_ids as $tid) {
        $post = get_post($tid);
        if (!$post) continue;
        $meta = [];
        foreach (['_elementor_data','_elementor_page_settings','_elementor_template_type','_elementor_conditions'] as $key) {
            $meta[$key] = get_post_meta($tid, $key, true);
        }
        if (empty($meta['_elementor_data'])) continue;
        $templates[] = [
            'ID' => $tid,
            'post' => $post,
            'meta' => $meta
        ];
    }
    restore_current_blog();

    if (empty($templates)) {
        echo '<div class="notice notice-error"><p>No hay plantillas válidas para sincronizar.</p></div>';
        return;
    }

    foreach ($sites as $site) {
        $sid = $site->blog_id;
        if (!in_array($sid, $include_ids)) continue;

        switch_to_blog($sid);
        $site_url = get_site_url();
        $admin_url = admin_url();
        $edit_template_url = admin_url('edit.php?post_type=elementor_library');
        $status = 'OK';

        foreach ($templates as $tpl) {
            // Buscar por slug y eliminar SIEMPRE si existe
            $existing = get_page_by_path($tpl['post']->post_name, OBJECT, 'elementor_library');
            if ($existing) {
                wp_delete_post($existing->ID, true);
            }
            // Crear nueva plantilla
            $postarr = [
                'post_type'    => 'elementor_library',
                'post_title'   => $tpl['post']->post_title,
                'post_name'    => $tpl['post']->post_name,
                'post_status'  => $tpl['post']->post_status,
                'post_content' => '',
            ];
            try {
                $new_id = wp_insert_post($postarr);
                if (is_wp_error($new_id) || !$new_id) throw new Exception("Error al crear post");
                foreach ($tpl['meta'] as $k=>$v) {
                    if (!is_null($v) && $v !== '') {
                        update_post_meta($new_id, $k, $v);
                    }
                }
                // SOLO ejecuta el hook si Elementor y el documento están disponibles y es el tipo correcto
                if (class_exists('\Elementor\Plugin')) {
                    $document = \Elementor\Plugin::instance()->documents->get($new_id);
                    if (
                        $document &&
                        is_object($document) &&
                        method_exists($document, 'get_name')
                    ) {
                        do_action('elementor/document/after_save', $document, get_post($new_id));
                    }
                }
                delete_post_meta($new_id, '_elementor_version');
            } catch (Exception $e) {
                $status = 'ERROR: ' . $e->getMessage();
                error_log('SYNC ELEMENTOR ERROR: ' . $e->getMessage());
            }
        }
        restore_current_blog();

        $log_entries[] = date('Y-m-d H:i:s') . " | $sid | $site_url | $admin_url | $edit_template_url | $status";
        $results[] = [
            'id' => $sid,
            'url' => $site_url,
            'admin' => $admin_url,
            'edit' => $edit_template_url,
            'status' => $status
        ];
    }

    $log_txt = implode("\n", $log_entries) . "\n";
    @file_put_contents(SYNC_ELEMENTOR_LOG_PATH, $log_txt, FILE_APPEND);

    echo '<h2>Resumen de sincronización</h2>';
    echo '<table class="widefat"><thead><tr><th>ID</th><th>URL</th><th>Admin</th><th>Plantillas</th><th>Estado</th></tr></thead><tbody>';
    foreach ($results as $r) {
        printf(
            '<tr><td>%d</td><td><a href="%s" target="_blank">%s</a></td><td><a href="%s" target="_blank">Admin</a></td><td><a href="%s" target="_blank">Editar Plantillas</a></td><td>%s</td></tr>',
            $r['id'], esc_url($r['url']), esc_html($r['url']),
            esc_url($r['admin']),
            esc_url($r['edit']),
            esc_html($r['status'])
        );
    }
    echo '</tbody></table>';
    echo '<p>Log guardado en: <code>' . esc_html(SYNC_ELEMENTOR_LOG_PATH) . '</code></p>';
}

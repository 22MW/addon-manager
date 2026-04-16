<?php
/**
 * Plugin Name: Multisite SEO Indexing Manager
 * Description: Gestiona estado de indexación SEO (index/noindex) en todos los sitios de la red multisite.
 * Version: 1.0
 * Network: true
 */

// Solo en Network Admin
if (!is_network_admin()) {
    return;
}

// Añadir página al menú de Network Admin
add_action('network_admin_menu', 'msi_add_admin_menu');
function msi_add_admin_menu() {
    add_menu_page(
        'Indexación SEO',
        'Indexación SEO',
        'manage_network_options',
        'multisite-seo-indexing',
        'msi_admin_page',
        'dashicons-search',
        30
    );
}

// Procesar acciones
add_action('admin_init', 'msi_process_actions');
function msi_process_actions() {
    if (!isset($_POST['msi_action']) || !isset($_POST['msi_nonce'])) {
        return;
    }
    
    if (!wp_verify_nonce($_POST['msi_nonce'], 'msi_update_indexing')) {
        wp_die('Error de seguridad');
    }
    
    if (!current_user_can('manage_network_options')) {
        wp_die('Permisos insuficientes');
    }
    
    $action = $_POST['msi_action'];
    $sites = isset($_POST['msi_sites']) ? array_map('intval', $_POST['msi_sites']) : [];
    
    if (empty($sites)) {
        add_settings_error('msi_messages', 'msi_error', 'No se seleccionó ninguna web', 'error');
        return;
    }
    
    $value = ($action === 'allow') ? '1' : '0';
    $count = 0;
    
    foreach ($sites as $site_id) {
        switch_to_blog($site_id);
        update_option('blog_public', $value);
        restore_current_blog();
        $count++;
    }
    
    $message = $action === 'allow' 
        ? "Indexación abierta en $count web(s)" 
        : "Indexación bloqueada en $count web(s)";
    
    add_settings_error('msi_messages', 'msi_success', $message, 'updated');
    set_transient('msi_messages', get_settings_errors('msi_messages'), 30);
}

// Página de administración
function msi_admin_page() {
    // Mostrar mensajes
    if ($messages = get_transient('msi_messages')) {
        delete_transient('msi_messages');
        foreach ($messages as $message) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($message['type']),
                esc_html($message['message'])
            );
        }
    }
    
 $orderby = isset($_GET['orderby']) ? $_GET['orderby'] : 'id';
    $order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'desc' : 'asc';
    
    $sites = get_sites([
        'number' => 9999,
        'orderby' => ($orderby === 'indexing' ? 'id' : $orderby),
        'order' => ($orderby === 'indexing' ? 'ASC' : $order)
    ]);
    
    // Si se ordena por indexación, hacerlo manualmente
    if ($orderby === 'indexing') {
        usort($sites, function($a, $b) use ($order) {
            switch_to_blog($a->blog_id);
            $a_public = get_option('blog_public');
            restore_current_blog();
            
            switch_to_blog($b->blog_id);
            $b_public = get_option('blog_public');
            restore_current_blog();
            
            $result = $a_public <=> $b_public;
            return ($order === 'asc') ? $result : -$result;
        });
    }
    
    // Función para generar enlaces ordenables
    function msi_sortable_link($column, $label) {
        $current_orderby = isset($_GET['orderby']) ? $_GET['orderby'] : '';
        $current_order = isset($_GET['order']) ? $_GET['order'] : 'asc';
        $new_order = ($current_orderby === $column && $current_order === 'asc') ? 'desc' : 'asc';
        $arrow = '';
        
        if ($current_orderby === $column) {
            $arrow = $current_order === 'asc' ? ' ▲' : ' ▼';
        }
        
        return sprintf(
            '<a href="%s">%s%s</a>',
            add_query_arg(['orderby' => $column, 'order' => $new_order]),
            esc_html($label),
            $arrow
        );
    }
    ?>
    <div class="wrap">
        <h1>Control de Indexación SEO - Multisite</h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('msi_update_indexing', 'msi_nonce'); ?>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:40px"><input type="checkbox" id="select-all"></th>
                        <th style="width:60px"><?php echo msi_sortable_link('id', 'ID'); ?></th>
                        <th><?php echo msi_sortable_link('blogname', 'Web'); ?></th>
                        <th>URL</th>
                        <th style="width:180px"><?php echo msi_sortable_link('indexing', 'Estado Indexación'); ?></th>
                    </tr>
                </thead>
                    <tr>
                        <th style="width:40px"><input type="checkbox" id="select-all"></th>
                        <th>ID</th>
                        <th>Web</th>
                        <th>URL</th>
                        <th>Estado Indexación</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sites as $site): 
                        switch_to_blog($site->blog_id);
                        $blog_public = get_option('blog_public');
                        $indexable = ($blog_public == '1');
                        restore_current_blog();
                    ?>
                    <tr>
                        <td><input type="checkbox" name="msi_sites[]" value="<?php echo $site->blog_id; ?>" class="site-checkbox"></td>
                        <td><?php echo $site->blog_id; ?></td>
                        <td><?php echo esc_html($site->blogname); ?></td>
                        <td><a href="<?php echo esc_url($site->siteurl); ?>" target="_blank"><?php echo esc_html($site->domain . $site->path); ?></a></td>
                        <td>
                            <span class="status-badge" style="padding:5px 10px;border-radius:3px;<?php echo $indexable ? 'background:#46b450;color:#fff' : 'background:#dc3232;color:#fff'; ?>">
                                <?php echo $indexable ? '✓ Abierta' : '✗ Bloqueada'; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div style="margin-top:20px;">
                <button type="submit" name="msi_action" value="allow" class="button button-primary">
                    Abrir Indexación (Seleccionadas)
                </button>
                <button type="submit" name="msi_action" value="block" class="button">
                    Bloquear Indexación (Seleccionadas)
                </button>
            </div>
        </form>
    </div>
    
    <script>
    document.getElementById('select-all').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.site-checkbox');
        checkboxes.forEach(cb => cb.checked = this.checked);
    });
    </script>
    <?php
}

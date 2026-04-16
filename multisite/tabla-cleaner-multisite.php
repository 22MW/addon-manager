<?php
/**
 * MU Plugin: Network DB Table Cleaner (Agrupado + Matching + Orden)
 * Description: Lista y agrupa tablas no-core en multisite para limpieza controlada por grupos.
 * Author: 22MW
 * Version: 1.2.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
/*
add_action('network_admin_menu', function () {
    if ( current_user_can('manage_network') ) {
        add_menu_page(
            'DB-TABLE Cleaner',
            'DB-TABLE Cleaner',
            'manage_network',
            'mu-db-table-cleaner',
            'mu_db_table_cleaner_screen',
            'dashicons-trash',
            80
        );
    }
});
*/
/** Mapa orientativo de prefijos de tabla -> nombre de plugin (edítalo a tu gusto). */
function mu_db_table_cleaner_prefix_map() {
    return array(
        'actionscheduler'      => 'Action Scheduler (librería de WooCommerce)',
        'addonlibrary'         => 'Slider Revolution – Add-On Library',
        'admin_columns'        => 'Admin Columns Pro',
        'apto'                 => 'Advanced Post Types Order',
        'bwg'                  => 'Photo Gallery by 10Web',
        'db_tables_cleaner'    => 'DB Tables Cleaner',
        'duplicator'           => 'Duplicator',
        'e_'                   => 'Elementor (Submissions/Notes/Events)',
        'icl'                  => 'WPML',
        'irecommendthis'       => 'I Recommend This',
        'jet_smart_filters'    => 'JetSmartFilters (Crocoblock)',
        'jet'                 => 'JetEngine (Crocoblock)',
        'layerslider'          => 'LayerSlider WP',
        'login_redirects'      => 'LoginWP (Peter’s Login Redirect)',
        'masterslider'         => 'Master Slider',
        'mepr'                 => 'MemberPress',
        'nf3'                  => 'Ninja Forms',
        'page_generator'       => 'Page Generator Pro',
        'pmxe'                 => 'WP All Export Pro',
        'pmxi'                 => 'WP All Import',
        'podsrel'              => 'Pods',
        'post_smtp'            => 'Post SMTP',
        'ppress'               => 'ProfilePress',
        'rank_math'            => 'Rank Math SEO',
        'rcb'                  => 'Real Cookie Banner ?¿',
        'real_queue'           => 'Real Media Library',
        'realmedialibrary'     => 'Real Media Library',
        'redirection'          => 'Redirection',
        'responsive_menu'      => 'Responsive Menu',
        'revslider'            => 'Slider Revolution',
        'shortpixel'           => 'ShortPixel Image Optimizer',
        'simple_history'       => 'Simple History',
        'siq'                  => 'Zoho SalesIQ (chat)',
        'toolset'              => 'Toolset Types',
        'umbrella'             => 'WP Umbrella',
        'wpcb'                 => 'WPCodeBox',
        'wpdatatables'               => 'wpDataTables',
        'wpr'                 => 'WP Rocket',
        'yoast'                => 'Yoast SEO',

    );
}

/**
 * Normaliza el “nombre base” de una tabla:
 * - Quita sufijos puramente numéricos finales: _2024, _01, _0001, _2024_07, etc.
 *   (para agrupar copias por mes/año/índice)
 */
function mu_db_table_cleaner_base_name( $local_table_name ) {
    return preg_replace('/(?:_\d+)+$/', '', $local_table_name);
}

/**
 * Devuelve true si la tabla (sin prefijo local) debe ocultarse.
 * Aquí ocultamos cualquier tabla que contenga 'realmedialibrary' (case-insensitive).
 * Añade más palabras clave si lo necesitas.
 */
function mu_db_table_cleaner_should_exclude( $after_without_local_prefix ) {
    $s = strtolower($after_without_local_prefix);
   // $blocklist = array('realmedialibrary'); // añade 'rml', etc. si procede
    $blocklist = array(''); // añade 'rml', etc. si procede
    foreach ($blocklist as $kw) {
        if ($kw !== '' && strpos($s, strtolower($kw)) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Matching avanzado: intenta asignar plugin en este orden:
 *  1) Si $after_clean comienza por "<key>_" o es exactamente "<key>" para alguna clave del mapa.
 *  2) Si no, usa la primera pieza (fallback) y busca esa clave.
 */
function mu_db_table_cleaner_guess_plugin( $after_clean, $map ) {
    $lc = strtolower($after_clean);

    // 1) prefijo verdadero del nombre (más robusto)
    foreach ($map as $key => $plugin_name) {
        $k = strtolower($key);
        if ($lc === $k || strpos($lc, $k . '_') === 0) {
            return $plugin_name;
        }
    }

    // 2) fallback por primera pieza
    $first_piece = strstr($after_clean, '_', true);
    if ($first_piece === false) $first_piece = $after_clean;
    $first_piece = strtolower($first_piece);
    if (isset($map[$first_piece])) return $map[$first_piece];

    return 'Desconocido';
}

/** Construye el estado agrupado: base_name => { plugin, size, tables[] } */
function mu_db_table_cleaner_state_grouped() {
    global $wpdb;

    $base_prefix = $wpdb->base_prefix; // 'wp_'
    $db_name     = DB_NAME;

    // Tablas core a excluir
    $site_core = array(
        'commentmeta','comments','links','options','postmeta','posts',
        'term_relationships','term_taxonomy','termmeta','terms','usermeta','users'
    );
    $network_core = array(
        'blogs','blog_versions','registration_log','signups','site','sitemeta',
        'users','usermeta','blogmeta','sitecategories'
    );

    // Carga del esquema
    $sql = $wpdb->prepare(
        "SELECT table_name, IFNULL(data_length,0) AS data_len, IFNULL(index_length,0) AS index_len
         FROM information_schema.tables
         WHERE table_schema = %s
           AND table_name LIKE %s",
        $db_name,
        $base_prefix . '%'
    );
    $rows = $wpdb->get_results($sql);
    $map  = mu_db_table_cleaner_prefix_map();

    $groups = array(); // key = base_name

    foreach ( $rows as $r ) {
        $tname = $r->table_name;
        $size  = (int)$r->data_len + (int)$r->index_len;

        // (1) Prefijo local: admite varios bloques numéricos (wp_7_, wp_11_7_, etc.)
        $local_prefix = $base_prefix;
        if ( preg_match('/^' . preg_quote($base_prefix, '/') . '(?:\d+_)+/i', $tname, $m) ) {
            // $m[0] será 'wp_7_' o 'wp_11_7_'…
            $local_prefix = $m[0];
        }

        // (2) Parte tras el prefijo local
        $after = substr($tname, strlen($local_prefix));

        // (3) Limpia cualquier bloque numérico inicial que aún quede (7_, 11_7_, …)
        $after_clean = preg_replace('/^(?:\d+_)+/', '', $after);

        // (4) Excluir core usando la versión limpia
        if ( in_array($after_clean, $site_core, true) ) continue;
        if ( $local_prefix === $base_prefix && in_array($after_clean, $network_core, true) ) continue;

        // (5) Excluir por blocklist (p.ej. realmedialibrary)
        if ( mu_db_table_cleaner_should_exclude($after_clean) ) continue;

        // (6-7) Sugerencia de plugin (matching avanzado)
        $plugin_guess = mu_db_table_cleaner_guess_plugin( $after_clean, $map );

        // (8) Base name para agrupar (quita sufijos numéricos finales)
        $base_name = mu_db_table_cleaner_base_name($after_clean);

        if ( ! isset($groups[$base_name]) ) {
            $groups[$base_name] = array(
                'plugin' => $plugin_guess,
                'size'   => 0,
                'tables' => array()
            );
        }
        $groups[$base_name]['size'] += $size;
        $groups[$base_name]['tables'][] = array(
            'table' => $tname,
            'size'  => $size
        );

        // Si el grupo era "Desconocido" y ahora tenemos mapeo, actualiza
        if ( $groups[$base_name]['plugin'] === 'Desconocido' && $plugin_guess !== 'Desconocido' ) {
            $groups[$base_name]['plugin'] = $plugin_guess;
        }
    }

    // No ordenamos aquí; lo hace la pantalla según los controles
    return $groups;
}

/** Formatea tamaño en bytes. */
function mu_db_table_cleaner_hsize( $bytes ) {
    $u = array('B','KB','MB','GB','TB');
    $i = 0;
    while ( $bytes >= 1024 && $i < count($u)-1 ) { $bytes /= 1024; $i++; }
    return sprintf('%.2f %s', $bytes, $u[$i]);
}

/** Pantalla del limpiador (Network Admin). */
function mu_db_table_cleaner_screen() {
    if ( ! current_user_can('manage_network') ) wp_die('No tienes permisos suficientes.');
    global $wpdb;

    // --- Controles de ordenación (GET, sin AJAX) ---
    $order_by  = isset($_GET['order_by'])  ? sanitize_text_field($_GET['order_by'])  : 'base';
    $order_dir = isset($_GET['order_dir']) ? strtolower(sanitize_text_field($_GET['order_dir'])) : 'asc';
    if (!in_array($order_by, array('base','plugin','count','size'), true)) $order_by = 'base';
    if (!in_array($order_dir, array('asc','desc'), true)) $order_dir = 'asc';

    $groups = mu_db_table_cleaner_state_grouped();

    // --- Ordenación sin AJAX (ordenamos las claves según criterio) ---
    $keys = array_keys($groups);
    switch ($order_by) {
        case 'plugin':
            usort($keys, function($a,$b) use($groups,$order_dir){
                $A = strtolower($groups[$a]['plugin']);
                $B = strtolower($groups[$b]['plugin']);
                if ($A === $B) return 0;
                $cmp = $A < $B ? -1 : 1;
                return $order_dir === 'asc' ? $cmp : -$cmp;
            });
            break;
        case 'count':
            usort($keys, function($a,$b) use($groups,$order_dir){
                $A = count($groups[$a]['tables']);
                $B = count($groups[$b]['tables']);
                if ($A === $B) return 0;
                $cmp = $A < $B ? -1 : 1;
                return $order_dir === 'asc' ? $cmp : -$cmp;
            });
            break;
        case 'size':
            usort($keys, function($a,$b) use($groups,$order_dir){
                $A = (int)$groups[$a]['size'];
                $B = (int)$groups[$b]['size'];
                if ($A === $B) return 0;
                $cmp = $A < $B ? -1 : 1;
                return $order_dir === 'asc' ? $cmp : -$cmp;
            });
            break;
        default: // 'base'
            natcasesort($keys);
            if ($order_dir === 'desc') $keys = array_reverse($keys);
    }

    $dropped = array();
    $errors  = array();

    // Borrado por grupos (base_name)
    if ( isset($_POST['mu_db_drop_groups']) && check_admin_referer('mu_db_table_cleaner_nonce','mu_db_table_cleaner_nonce') ) {
        $keys_to_drop = isset($_POST['group_keys']) && is_array($_POST['group_keys']) ? array_map('sanitize_text_field', $_POST['group_keys']) : array();
        $confirm = isset($_POST['confirm_phrase']) ? trim(wp_unslash($_POST['confirm_phrase'])) : '';

        if ( $confirm !== 'BORRAR' ) {
            $errors[] = 'Debes escribir exactamente "BORRAR" para confirmar.';
        } else {
            foreach ( $keys_to_drop as $base_name ) {
                if ( ! isset($groups[$base_name]) ) { $errors[] = "Grupo desconocido: $base_name"; continue; }
                foreach ( $groups[$base_name]['tables'] as $tbl ) {
                    $table = $tbl['table'];
                    // Sanitiza nombre (solo A-Z, a-z, 0-9 y _)
                    $safe = preg_replace('/[^A-Za-z0-9_]/', '', $table);
                    if ( $safe !== $table ) { $errors[] = "Nombre no permitido: $table"; continue; }
                    $sql = "DROP TABLE IF EXISTS `{$table}`";
                    $res = $wpdb->query($sql);
                    if ( $res !== false ) $dropped[] = $table; else $errors[] = "Error al borrar: $table";
                }
            }
            // Recalcular listas tras borrar
            $groups = mu_db_table_cleaner_state_grouped();
            $keys = array_keys($groups);
            // Reaplicar el mismo orden
            switch ($order_by) {
                case 'plugin':
                    usort($keys, function($a,$b) use($groups,$order_dir){
                        $A = strtolower($groups[$a]['plugin']);
                        $B = strtolower($groups[$b]['plugin']);
                        if ($A === $B) return 0;
                        $cmp = $A < $B ? -1 : 1;
                        return $order_dir === 'asc' ? $cmp : -$cmp;
                    });
                    break;
                case 'count':
                    usort($keys, function($a,$b) use($groups,$order_dir){
                        $A = count($groups[$a]['tables']);
                        $B = count($groups[$b]['tables']);
                        if ($A === $B) return 0;
                        $cmp = $A < $B ? -1 : 1;
                        return $order_dir === 'asc' ? $cmp : -$cmp;
                    });
                    break;
                case 'size':
                    usort($keys, function($a,$b) use($groups,$order_dir){
                        $A = (int)$groups[$a]['size'];
                        $B = (int)$groups[$b]['size'];
                        if ($A === $B) return 0;
                        $cmp = $A < $B ? -1 : 1;
                        return $order_dir === 'asc' ? $cmp : -$cmp;
                    });
                    break;
                default:
                    natcasesort($keys);
                    if ($order_dir === 'desc') $keys = array_reverse($keys);
            }
        }
    }

    ?>
    <div class="wrap">
        <h1>Network DB Table Cleaner (Agrupado)</h1>
        <p><strong>ATENCIÓN:</strong> Haz copia de seguridad antes de borrar. Al marcar un grupo se eliminarán <em>todas</em> las tablas que comparten ese <code>base_name</code> en toda la red. Las tablas que contengan “realmedialibrary” NO se muestran.</p>

        <?php if ($dropped): ?>
            <div class="notice notice-success"><p><strong>Tablas borradas:</strong> <?php echo esc_html(implode(', ', $dropped)); ?></p></div>
        <?php endif; ?>
        <?php if ($errors): ?>
            <div class="notice notice-error"><p><?php echo esc_html(implode(' | ', $errors)); ?></p></div>
        <?php endif; ?>

        <form method="get" style="margin:10px 0;">
            <input type="hidden" name="page" value="mu-db-table-cleaner">
            <label>Ordenar por:
                <select name="order_by">
                    <option value="base"   <?php selected($order_by,'base'); ?>>Base name</option>
                    <option value="plugin" <?php selected($order_by,'plugin'); ?>>Plugin</option>
                    <option value="count"  <?php selected($order_by,'count'); ?>>Nº tablas</option>
                    <option value="size"   <?php selected($order_by,'size'); ?>>Tamaño total</option>
                </select>
            </label>
            <label style="margin-left:8px;">Dirección:
                <select name="order_dir">
                    <option value="asc"  <?php selected($order_dir,'asc'); ?>>Asc</option>
                    <option value="desc" <?php selected($order_dir,'desc'); ?>>Desc</option>
                </select>
            </label>
            <button class="button" type="submit" style="margin-left:8px;">Aplicar</button>
        </form>

        <form method="post">
            <?php wp_nonce_field('mu_db_table_cleaner_nonce','mu_db_table_cleaner_nonce'); ?>

            <p>
                <label><input type="checkbox" id="toggle-all"> <strong>Seleccionar / deseleccionar todos los grupos</strong></label>
            </p>

            <style>
                .mu-tbl{width:100%; border-collapse:collapse; background:#fff}
                .mu-tbl th,.mu-tbl td{padding:8px 10px; border-bottom:1px solid #eee}
                .mu-size{white-space:nowrap}
                details summary{cursor:pointer; color:#2271b1}
                .badge{background:#f3f4f6; border-radius:10px; padding:2px 8px; font-size:11px; margin-left:6px}
                .wrap code{background:#f6f7f7; padding:2px 5px; border-radius:3px}
            </style>

            <table class="mu-tbl">
                <thead>
                    <tr>
                        <th style="width:28px"></th>
                        <th>Base name</th>
                        <th>Plugin (sugerido)</th>
                        <th>Nº tablas</th>
                        <th class="mu-size">Tamaño total</th>
                        <th>Detalles</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($keys as $base): $data = $groups[$base]; ?>
                    <tr>
                        <td><input type="checkbox" class="chk-group" name="group_keys[]" value="<?php echo esc_attr($base); ?>"></td>
                        <td><code><?php echo esc_html($base); ?></code></td>
                        <td><?php echo esc_html($data['plugin']); ?></td>
                        <td><span class="badge"><?php echo count($data['tables']); ?></span></td>
                        <td class="mu-size"><?php echo esc_html( mu_db_table_cleaner_hsize($data['size']) ); ?></td>
                        <td>
                            <details>
                                <summary>ver tablas</summary>
                                <div>
                                    <?php foreach ($data['tables'] as $t): ?>
                                        <div><code><?php echo esc_html($t['table']); ?></code> — <?php echo esc_html(mu_db_table_cleaner_hsize($t['size'])); ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <p style="margin-top:14px">
                Escribe <code>BORRAR</code> para confirmar:
                <input type="text" name="confirm_phrase" value="" style="width:120px">
                &nbsp;
                <button type="submit" name="mu_db_drop_groups" class="button button-primary" onclick="return confirm('¿Seguro que deseas borrar TODOS los grupos seleccionados? Esta acción no se puede deshacer.');">
                    Borrar grupos seleccionados
                </button>
            </p>
        </form>
    </div>

    <script>
    (function(){
        const all = document.getElementById('toggle-all');
        if (all){
            all.addEventListener('change', ()=> {
                document.querySelectorAll('.chk-group').forEach(cb => cb.checked = all.checked);
            });
        }
    })();
    </script>
    <?php
}

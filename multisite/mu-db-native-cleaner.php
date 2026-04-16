<?php
/**
 * MU Plugin: Network DB Native Cleaner (Standalone)
 * Description: Escanea y limpia rastros de plugins en tablas nativas WP (options/meta/transients/cron) por prefijos en multisite.
 * Author: 22MW
 * Version: 1.0.1
 */

if ( ! defined('ABSPATH') ) exit;

/** Top-level menu (independiente) */
add_action('network_admin_menu', function () {
    if ( current_user_can('manage_network') ) {
        add_menu_page(
            'DB Native Cleaner',
            'DB Native Cleaner',
            'manage_network',
            'mu-db-native-cleaner',
            'mu_db_native_cleaner_screen',
            'dashicons-filter',
            81
        );
    }
});

/**
 * FIRMAS por plugin -> prefijos por área. (EDITA/AMPLÍA aquí)
 * Solo prefijos (sin comodines). El plugin convertirá a LIKE 'prefijo%'.
 *
 * Áreas válidas: options, postmeta, usermeta, termmeta, commentmeta, transients, cron
 * - transients: busca en _transient_{prefijo}% y _site_transient_{prefijo}% (tabla options)
 * - cron: elimina hooks cuyo nombre empiece por el prefijo
 */
function mu_dbnc_signatures() {
    return array(
        'layerslider' => array(
            'options'    => array('layerslider_', 'ls-', 'ls_', 'layerslider'),
            'postmeta'   => array('layerslider_', 'ls-', 'ls_'),
            'transients' => array('ls_', 'layerslider'),
            'cron'       => array('layerslider_'),
        ),
        'revslider' => array(
            'options'    => array('revslider_', 'revslider-', 'rev_'),
            'postmeta'   => array('revslider_', 'rev_'),
            'transients' => array('revslider_', 'rev_'),
            'cron'       => array('revslider_'),
        ),
        'irecommendthis' => array(
            'options'    => array('irecommendthis', 'i_recommend_this'),
            'postmeta'   => array('irecommendthis', 'i_recommend_this'),
            'usermeta'   => array('irecommendthis', 'i_recommend_this'),
            'transients' => array('irecommendthis','i_recommend_this'),
        ),
        'bsr' => array( // Better Search Replace
            'options'    => array('bsr_', 'better-search-replace'),
            'transients' => array('bsr_'),
        ),
        'memberpress' => array(
            'options'    => array('mepr_', 'memberpress_'),
            'postmeta'   => array('mepr_', 'memberpress_', '_mepr_'),
            'usermeta'   => array('mepr_', 'memberpress_', '_mepr_'),
            'transients' => array('mepr_', 'memberpress_'),
            'cron'       => array('mepr_', 'memberpress_'),
        ),
        'wpml' => array(
            'options'    => array('icl_', 'wpml_'),
            'postmeta'   => array('_icl_', 'wpml_'),
            'termmeta'   => array('wpml_'),
            'transients' => array('icl_', 'wpml_'),
            'cron'       => array('wpml_', 'icl_'),
        ),
        'updraft' => array(
            'options'    => array('updraft_', 'updraftplus_'),
            'transients' => array('updraft_', 'updraftplus_'),
            'cron'       => array('updraft_', 'updraftplus_'),
        ),
        // Añade aquí tus firmas favoritas...
    );
}

/** Helpers */
function mu_dbnc_like_prefix($p){ return str_replace(array('%','_'), array('\%','\_'), $p) . '%'; }
function mu_dbnc_bytes_h($b){ $u=array('B','KB','MB','GB','TB'); $i=0; while($b>=1024 && $i<count($u)-1){$b/=1024;$i++;} return sprintf('%.2f %s',$b,$u[$i]); }

/**
 * Escanea: devuelve conteos por firma y área (agregado de toda la red)
 * @param string[] $selected_slugs
 * @param string   $extra  Prefijos extra (uno por línea)
 * @return array
 */
function mu_dbnc_scan_counts($selected_slugs, $extra) {
    global $wpdb;

    $sign = mu_dbnc_signatures();
    $targets = array();
    foreach($selected_slugs as $slug){
        if(isset($sign[$slug])){
            $targets[$slug] = $sign[$slug];
        }
    }
    // Prefijos extra manuales
    $extra = array_filter(array_map('trim', preg_split('/[\r\n]+/', (string)$extra)));
    if($extra){
        $extra_map = array(
            'options'=>$extra,'postmeta'=>$extra,'usermeta'=>$extra,'termmeta'=>$extra,
            'commentmeta'=>$extra,'transients'=>$extra,'cron'=>$extra
        );
        $targets['_extra'] = $extra_map;
    }

    if(!$targets) return array();

    $sites = get_sites(array('number'=>0));
    $agg = array(); // [slug][area] => count

    foreach($targets as $slug => $areas){
        foreach(array('options','postmeta','usermeta','termmeta','commentmeta','transients','cron') as $area){
            $agg[$slug][$area] = 0;
        }
    }

    foreach($sites as $s){
        $blog_id = (int)$s->blog_id;
        $prefix  = $wpdb->get_blog_prefix($blog_id);

        foreach($targets as $slug => $areas){
            // options
            if(!empty($areas['options'])){
                foreach($areas['options'] as $p){
                    $like = mu_dbnc_like_prefix($p);
                    $agg[$slug]['options'] += (int)$wpdb->get_var("SELECT COUNT(*) FROM `{$prefix}options` WHERE option_name LIKE '{$like}'");
                }
            }
            // postmeta
            if(!empty($areas['postmeta'])){
                foreach($areas['postmeta'] as $p){
                    $like = mu_dbnc_like_prefix($p);
                    $agg[$slug]['postmeta'] += (int)$wpdb->get_var("SELECT COUNT(*) FROM `{$prefix}postmeta` WHERE meta_key LIKE '{$like}'");
                }
            }
            // usermeta
            if(!empty($areas['usermeta'])){
                foreach($areas['usermeta'] as $p){
                    $like = mu_dbnc_like_prefix($p);
                    $agg[$slug]['usermeta'] += (int)$wpdb->get_var("SELECT COUNT(*) FROM `{$prefix}usermeta` WHERE meta_key LIKE '{$like}'");
                }
            }
            // termmeta
            if(!empty($areas['termmeta'])){
                foreach($areas['termmeta'] as $p){
                    $like = mu_dbnc_like_prefix($p);
                    $agg[$slug]['termmeta'] += (int)$wpdb->get_var("SELECT COUNT(*) FROM `{$prefix}termmeta` WHERE meta_key LIKE '{$like}'");
                }
            }
            // commentmeta
            if(!empty($areas['commentmeta'])){
                foreach($areas['commentmeta'] as $p){
                    $like = mu_dbnc_like_prefix($p);
                    $agg[$slug]['commentmeta'] += (int)$wpdb->get_var("SELECT COUNT(*) FROM `{$prefix}commentmeta` WHERE meta_key LIKE '{$like}'");
                }
            }
            // transients (en options)
            if(!empty($areas['transients'])){
                foreach($areas['transients'] as $p){
                    $like1 = mu_dbnc_like_prefix('_transient_' . $p);
                    $like2 = mu_dbnc_like_prefix('_site_transient_' . $p);
                    $agg[$slug]['transients'] += (int)$wpdb->get_var("SELECT COUNT(*) FROM `{$prefix}options` WHERE option_name LIKE '{$like1}' OR option_name LIKE '{$like2}'");
                }
            }
            // cron: cuenta hooks cuyo nombre empieza por el prefijo
            if(!empty($areas['cron'])){
                $cron = get_blog_option($blog_id, 'cron');
                if(is_array($cron)){
                    foreach(array_keys($cron) as $ts){
                        if(!is_array($cron[$ts])) continue;
                        foreach($cron[$ts] as $hook => $data){
                            foreach($areas['cron'] as $p){
                                if(stripos($hook, $p) === 0){
                                    $agg[$slug]['cron']++;
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    return $agg;
}

/**
 * Borrado por firmas y áreas (por lotes)
 * @param string[] $selected_slugs
 * @param string[] $areas_to_delete
 * @param string   $extra
 * @return array ['deleted'=>[], 'errors'=>[]]
 */
function mu_dbnc_delete($selected_slugs, $areas_to_delete, $extra) {
    global $wpdb;

    $sign = mu_dbnc_signatures();
    $targets = array();
    foreach($selected_slugs as $slug){
        if(isset($sign[$slug])){
            $targets[$slug] = $sign[$slug];
        }
    }
    $extra = array_filter(array_map('trim', preg_split('/[\r\n]+/', (string)$extra)));
    if($extra){
        $extra_map = array(
            'options'=>$extra,'postmeta'=>$extra,'usermeta'=>$extra,'termmeta'=>$extra,
            'commentmeta'=>$extra,'transients'=>$extra,'cron'=>$extra
        );
        $targets['_extra'] = $extra_map;
    }
    if(!$targets) return array('deleted'=>array(),'errors'=>array('No hay firmas seleccionadas.'));

    $sites = get_sites(array('number'=>0));
    $deleted = array(); $errors = array();

    foreach($sites as $s){
        $blog_id = (int)$s->blog_id;
        $prefix  = $wpdb->get_blog_prefix($blog_id);

        foreach($targets as $slug => $areas){
            // options
            if(in_array('options',$areas_to_delete,true) && !empty($areas['options'])){
                foreach($areas['options'] as $p){
                    $like = mu_dbnc_like_prefix($p);
                    $res = $wpdb->query("DELETE FROM `{$prefix}options` WHERE option_name LIKE '{$like}'");
                    if($res!==false) $deleted[] = "{$prefix}options option_name LIKE {$p}% ({$res})"; else $errors[] = "Error borrando options {$p} en blog {$blog_id}";
                }
            }
            // postmeta
            if(in_array('postmeta',$areas_to_delete,true) && !empty($areas['postmeta'])){
                foreach($areas['postmeta'] as $p){
                    $like = mu_dbnc_like_prefix($p);
                    do {
                        $res = $wpdb->query("DELETE FROM `{$prefix}postmeta` WHERE meta_key LIKE '{$like}' LIMIT 10000");
                    } while($res && $res==10000);
                    if($res!==false) $deleted[] = "{$prefix}postmeta meta_key LIKE {$p}%"; else $errors[]="Error borrando postmeta {$p} en blog {$blog_id}";
                }
            }
            // usermeta
            if(in_array('usermeta',$areas_to_delete,true) && !empty($areas['usermeta'])){
                foreach($areas['usermeta'] as $p){
                    $like = mu_dbnc_like_prefix($p);
                    do {
                        $res = $wpdb->query("DELETE FROM `{$prefix}usermeta` WHERE meta_key LIKE '{$like}' LIMIT 10000");
                    } while($res && $res==10000);
                    if($res!==false) $deleted[] = "{$prefix}usermeta meta_key LIKE {$p}%"; else $errors[]="Error usermeta {$p} blog {$blog_id}";
                }
            }
            // termmeta
            if(in_array('termmeta',$areas_to_delete,true) && !empty($areas['termmeta'])){
                foreach($areas['termmeta'] as $p){
                    $like = mu_dbnc_like_prefix($p);
                    do {
                        $res = $wpdb->query("DELETE FROM `{$prefix}termmeta` WHERE meta_key LIKE '{$like}' LIMIT 10000");
                    } while($res && $res==10000);
                    if($res!==false) $deleted[] = "{$prefix}termmeta meta_key LIKE {$p}%"; else $errors[]="Error termmeta {$p} blog {$blog_id}";
                }
            }
            // commentmeta
            if(in_array('commentmeta',$areas_to_delete,true) && !empty($areas['commentmeta'])){
                foreach($areas['commentmeta'] as $p){
                    $like = mu_dbnc_like_prefix($p);
                    do {
                        $res = $wpdb->query("DELETE FROM `{$prefix}commentmeta` WHERE meta_key LIKE '{$like}' LIMIT 10000");
                    } while($res && $res==10000);
                    if($res!==false) $deleted[] = "{$prefix}commentmeta meta_key LIKE {$p}%"; else $errors[]="Error commentmeta {$p} blog {$blog_id}";
                }
            }
            // transients (options)
            if(in_array('transients',$areas_to_delete,true) && !empty($areas['transients'])){
                foreach($areas['transients'] as $p){
                    $like1 = mu_dbnc_like_prefix('_transient_' . $p);
                    $like2 = mu_dbnc_like_prefix('_site_transient_' . $p);
                    $res1 = $wpdb->query("DELETE FROM `{$prefix}options` WHERE option_name LIKE '{$like1}'");
                    $res2 = $wpdb->query("DELETE FROM `{$prefix}options` WHERE option_name LIKE '{$like2}'");
                    if($res1!==false && $res2!==false) $deleted[] = "{$prefix}options transients {$p}%"; else $errors[]="Error transients {$p} blog {$blog_id}";
                }
            }
            // cron hooks
            if(in_array('cron',$areas_to_delete,true) && !empty($areas['cron'])){
                $cron = get_blog_option($blog_id, 'cron');
                if(is_array($cron)){
                    $changed = false;
                    foreach(array_keys($cron) as $ts){
                        if(!is_array($cron[$ts])) continue;
                        foreach(array_keys($cron[$ts]) as $hook){
                            foreach($areas['cron'] as $p){
                                if(stripos($hook, $p) === 0){
                                    unset($cron[$ts][$hook]);
                                    $changed = true;
                                }
                            }
                        }
                        if(empty($cron[$ts])) unset($cron[$ts]);
                    }
                    if($changed){
                        update_blog_option($blog_id, 'cron', $cron);
                        $deleted[] = "cron hooks {$blog_id}";
                    }
                }
            }
        }
    }

    return compact('deleted','errors');
}

/** Pantalla (independiente) */
function mu_db_native_cleaner_screen() {
    if ( ! current_user_can('manage_network') ) wp_die('No tienes permisos suficientes.');

    $sign = mu_dbnc_signatures();

    $selected = isset($_POST['slugs']) && is_array($_POST['slugs']) ? array_map('sanitize_key', $_POST['slugs']) : array();
    $areas    = isset($_POST['areas']) && is_array($_POST['areas']) ? array_map('sanitize_text_field', $_POST['areas']) : array('options','postmeta','usermeta','termmeta','commentmeta','transients','cron');
    $extra    = isset($_POST['extra']) ? wp_unslash($_POST['extra']) : '';

    $action   = isset($_POST['action_do']) ? sanitize_text_field($_POST['action_do']) : '';
    $confirm  = isset($_POST['confirm_phrase']) ? trim(wp_unslash($_POST['confirm_phrase'])) : '';

    $counts = array(); $result = null;

    if ( $action === 'scan' && check_admin_referer('mu_dbnc','mu_dbnc') ) {
        $counts = mu_dbnc_scan_counts($selected, $extra);
    } elseif ( $action === 'delete' && check_admin_referer('mu_dbnc','mu_dbnc') ) {
        if ($confirm !== 'BORRAR') {
            $result = array('deleted'=>array(), 'errors'=>array('Debes escribir exactamente "BORRAR" para confirmar.'));
        } else {
            $result = mu_dbnc_delete($selected, $areas, $extra);
        }
    }

    ?>
    <div class="wrap">
        <h1>DB Native Cleaner</h1>
        <p>Escanea y borra registros de plugins en tablas nativas de WordPress por <em>prefijo seguro</em>. Recomendado: <strong>haz backup</strong> antes de borrar.</p>

        <?php if ($result): ?>
            <?php if (!empty($result['deleted'])): ?>
                <div class="notice notice-success"><p><strong>Acciones realizadas:</strong><br><?php echo esc_html(implode(' | ', $result['deleted'])); ?></p></div>
            <?php endif; ?>
            <?php if (!empty($result['errors'])): ?>
                <div class="notice notice-error"><p><?php echo esc_html(implode(' | ', $result['errors'])); ?></p></div>
            <?php endif; ?>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field('mu_dbnc','mu_dbnc'); ?>

            <h2 class="title">1) Elige firmas</h2>
            <p>
                <?php foreach($sign as $slug=>$cfg): ?>
                    <label style="display:inline-block; margin:4px 10px 4px 0;">
                        <input type="checkbox" name="slugs[]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug,$selected,true)); ?>>
                        <code><?php echo esc_html($slug); ?></code>
                    </label>
                <?php endforeach; ?>
            </p>

            <h2 class="title">2) Áreas a incluir</h2>
            <p>
                <?php foreach(array('options','postmeta','usermeta','termmeta','commentmeta','transients','cron') as $a): ?>
                    <label style="margin-right:12px;">
                        <input type="checkbox" name="areas[]" value="<?php echo esc_attr($a); ?>" <?php checked(in_array($a,$areas,true)); ?>>
                        <?php echo esc_html($a); ?>
                    </label>
                <?php endforeach; ?>
            </p>

            <h2 class="title">3) Prefijos extra (opcional)</h2>
            <p><small>Uno por línea. Se aplican como prefijos a todas las áreas seleccionadas. Ej.: <code>myplugin_</code> o <code>foo-</code></small></p>
            <p><textarea name="extra" rows="4" cols="80" style="max-width:800px;"><?php echo esc_textarea($extra); ?></textarea></p>

            <p>
                <button class="button" name="action_do" value="scan">🔎 Previsualizar conteos</button>
            </p>

            <?php if ($counts): ?>
                <h2 class="title">Resultado del escaneo</h2>
                <table class="widefat striped">
                    <thead><tr>
                        <th>Firma</th>
                        <th>options</th><th>postmeta</th><th>usermeta</th><th>termmeta</th><th>commentmeta</th><th>transients</th><th>cron*</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach($counts as $slug=>$r): ?>
                        <tr>
                            <td><code><?php echo esc_html($slug); ?></code></td>
                            <td><?php echo (int)$r['options']; ?></td>
                            <td><?php echo (int)$r['postmeta']; ?></td>
                            <td><?php echo (int)$r['usermeta']; ?></td>
                            <td><?php echo (int)$r['termmeta']; ?></td>
                            <td><?php echo (int)$r['commentmeta']; ?></td>
                            <td><?php echo (int)$r['transients']; ?></td>
                            <td><?php echo (int)$r['cron']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p><small>*El conteo de <code>cron</code> es aproximado (nº de hooks detectados por prefijo).</small></p>
                <p style="margin-top:12px;">
                    Escribe <code>BORRAR</code> para confirmar:
                    <input type="text" name="confirm_phrase" value="" style="width:160px">
                    <button class="button button-primary" name="action_do" value="delete" onclick="return confirm('¿Seguro que deseas BORRAR los registros indicados? No se puede deshacer.');">🗑️ Borrar seleccionados</button>
                </p>
            <?php endif; ?>
        </form>

        <hr>
        <details>
            <summary><strong>Notas y buenas prácticas</strong></summary>
            <ul style="list-style:disc;padding-left:20px;">
                <li>Haz <strong>backup</strong> completo de la base de datos.</li>
                <li>Empieza limpiando <em>transients</em> y datos claramente obsoletos (plugins desinstalados).</li>
                <li>Para <em>cron</em>, este panel elimina hooks por prefijo del nombre del hook (no por payload). Revisa los prefijos antes.</li>
                <li>Si un plugin creó <em>post types</em> propios, añade su limpieza desde tu panel de contenidos o extiende este MU-plugin para gestionar <code>posts</code> por <code>post_type</code>.</li>
            </ul>
        </details>
    </div>
    <?php
}

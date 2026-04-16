<?php
/**
 * Plugin Name: Ver TODOS metadatos de cualquier post/CPT
 * Description: Añade un metabox de auditoría para ver postmeta y datos WP_Post en cualquier post type del admin.
 * Marketing Description: Auditoría total de contenido y metadatos sin instalar herramientas externas.
 * Parameters: Sin configuración: añade metabox de auditoría automáticamente.
 * Version: 1.0
 * Author: 22MW
 */

// Añadir el metabox a todos los post types editables
add_action('add_meta_boxes', function() {
    $post_types = get_post_types(['show_ui'=>true], 'names');
    foreach($post_types as $pt) {
        add_meta_box(
            'ver_todos_metadatos_post',
            'VER TODOS LOS METADATOS DEL POST',
            function($post) {
                $post_id = $post->ID;

                // Metadatos custom/postmeta
                echo '<h4>postmeta (<code>get_post_meta()</code>):</h4>';
                $postmeta = get_post_meta($post_id);
                echo '<table class="widefat fixed"><tbody>';
                foreach($postmeta as $k => $vals) {
                    foreach($vals as $val) {
                        echo '<tr><td style="font-weight:bold;">'.esc_html($k).'</td><td><pre style="white-space:pre-wrap;">'.esc_html(print_r(@unserialize($val) !== false ? unserialize($val) : $val,true)).'</pre></td></tr>';
                    }
                }
                echo '</tbody></table>';

                // Datos principales WP_Post
                echo '<h4>Datos principales (<code>WP_Post</code>):</h4>';
                $post_obj = get_post($post_id);
                foreach ((array)$post_obj as $k=>$v) {
                    echo '<b>'.esc_html($k).':</b> <pre style="white-space:pre-wrap;">'.esc_html(print_r($v,true)).'</pre>';
                }
            },
            $pt,
            'advanced',
            'default'
        );
    }
});

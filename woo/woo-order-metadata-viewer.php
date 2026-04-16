<?php
/**
 * Plugin Name: Ver TODOS metadatos pedido Woo (Clásico + HPOS)
 * Description: Metabox de auditoría para ver todos los metadatos y datos del pedido (Woo clásico y HPOS).
 * Version: 1.1
 * Author: 22MW
 */
add_action('add_meta_boxes', function() {
    foreach(['shop_order','woocommerce_page_wc-orders'] as $screen) {
        add_meta_box(
            'todos_metadatos_woo',
            'VER TODOS LOS METADATOS DEL PEDIDO',
            function($post) {
                // Mostrar meta
                $post_id = isset($post->ID) ? $post->ID : (isset($_GET['id']) ? intval($_GET['id']) : 0);
                echo '<h4>Campos postmeta:</h4>';
                $postmeta = get_post_meta($post_id);
                echo '<table class="widefat fixed"><tbody>';
                foreach($postmeta as $k => $vals) {
                    foreach($vals as $val) {
                        echo '<tr><td style="font-weight:bold;">'.esc_html($k).'</td><td><pre style="white-space:pre-wrap;">'.esc_html(print_r(@unserialize($val) !== false ? unserialize($val) : $val,true)).'</pre></td></tr>';
                    }
                }
                echo '</tbody></table>';
                // Datos principales pedido
                if(function_exists('wc_get_order')) {
                    $order = wc_get_order($post_id);
                    if($order) {
                        echo '<h4>Datos principales ($order->get_data()):</h4>';
                        foreach($order->get_data() as $k=>$v) {
                            echo '<b>'.esc_html($k).':</b> <pre style="white-space:pre-wrap;">'.esc_html(print_r($v,true)).'</pre>';
                        }
                    }
                }
            },
            $screen,
            'advanced',
            'default'
        );
    }
});

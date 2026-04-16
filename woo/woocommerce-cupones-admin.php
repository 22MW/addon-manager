<?php
/*
Plugin Name: WC cupones pedidos admin y emails
Description: Muestra cupones usados en pedidos WooCommerce dentro del admin y en emails de administración.
Version: 2.22
Autor:22MW
*/

// Mostrar cupones usados en el panel de admin de pedidos
add_action('woocommerce_admin_order_data_after_billing_address', function($order) {
    $cupones = $order->get_coupon_codes();
    if (!empty($cupones)) {
        echo '<p><strong>Cupones utilizados:</strong> ' . implode(', ', $cupones) . '</p>';
    }
});

// Mostrar cupones usados en los correos de administración
add_action('woocommerce_email_after_order_table', function($order, $is_admin_email) {
    if ($is_admin_email && $order instanceof WC_Order) {
        foreach ( $order->get_items('coupon') as $item ) {
            $code = $item->get_code();
            $coupon = new WC_Coupon($code);
            $amount = $item->get_discount();
            $type = $coupon->get_discount_type();
            $description = $coupon->get_description();

            echo '<p>';
            echo '<strong>Cupón:</strong> ' . esc_html($coupon->get_code());
            if ($description) {
              echo '<br><em>' . esc_html($description) . '</em>';
            }
            echo '<br>Tipo: ' . esc_html($type);
            echo '<br>Descuento: ' . wc_price($amount, array('currency' => $order->get_currency()));
            echo '</p>';
        }
    }
}, 15, 2);

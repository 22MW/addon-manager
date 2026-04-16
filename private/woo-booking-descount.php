<?php

/**
 * Plugin Name: Descuento Automático para WC Bookings
 * Description: Aplica descuentos automáticos en WooCommerce Bookings según productos, días y rango de fechas configurado.
 * Marketing Description: Activa campañas de descuento en reservas con reglas de calendario.
 * Parameters: Reglas de campaña definidas en código (productos, días y fechas).
 * Version: 1.7
 * Author: 22MW
 */

// Mostrar precio con descuento en formulario de Booking
add_filter('woocommerce_bookings_calculated_booking_cost', function ($booking_cost, $product, $data) {
    // Verificar si el producto es uno de los que tienen descuento
    $product_ids = [32384, 40733, 33262, 40745, 33867, 40748];

    if (in_array($product->get_id(), $product_ids)) {
        if (isset($data['_start_date'])) {
            $start = (int)$data['_start_date'];
            $weekday = date('N', $start); // lunes=1 ... viernes=5
            $month = date('m', $start);
            $year = date('Y', $start);

            if ($month == '09' && $year == '2025' && ($weekday == 1 || $weekday == 5)) {
                // Aplicar descuento del 10% en el booking
                return $booking_cost * 0.9;
            }
        }
    }

    return $booking_cost;
}, 10, 3);

// Mensaje en checkout
add_action('woocommerce_review_order_before_payment', function () {
    // Verificar si hay productos con descuento
    $product_ids = [32384, 40733, 33262, 40745, 33867, 40748];
    $has_discount = false;

    foreach (WC()->cart->get_cart() as $cart_item) {
        if (in_array($cart_item['product_id'], $product_ids) && !empty($cart_item['booking']['_start_date'])) {
            $start = (int)$cart_item['booking']['_start_date'];
            $weekday = date('N', $start);
            $month = date('m', $start);
            $year = date('Y', $start);

            if ($month == '09' && $year == '2025' && ($weekday == 1 || $weekday == 5)) {
                $has_discount = true;
                break;
            }
        }
    }

    if ($has_discount) {
        $lang = apply_filters('wpml_current_language', NULL);
        if ($lang == 'en') $msg = '10% DISCOUNT SEPTEMBER 2025 applied!';
        elseif ($lang == 'de') $msg = '10% RABATT SEPTEMBER 2025 angewendet!';
        else $msg = '¡DESCUENTO 10% SEPTIEMBRE 2025 aplicado!';

        echo '<div style="color: #feedd6;font-weight:bold;margin:10px 0;background: #220e1c;display: flex;padding: 5px;">' . $msg . '</div>';
    }
});

// Mensaje en carrito
add_action('woocommerce_before_cart_totals', function () {
    // Verificar si hay productos con descuento
    $product_ids = [32384, 40733, 33262, 40745, 33867, 40748];
    $has_discount = false;

    foreach (WC()->cart->get_cart() as $cart_item) {
        if (in_array($cart_item['product_id'], $product_ids) && !empty($cart_item['booking']['_start_date'])) {
            $start = (int)$cart_item['booking']['_start_date'];
            $weekday = date('N', $start);
            $month = date('m', $start);
            $year = date('Y', $start);

            if ($month == '09' && $year == '2025' && ($weekday == 1 || $weekday == 5)) {
                $has_discount = true;
                break;
            }
        }
    }

    if ($has_discount) {
        $lang = apply_filters('wpml_current_language', NULL);
        if ($lang == 'en') $msg = '10% DISCOUNT SEPTEMBER 2025 applied!';
        elseif ($lang == 'de') $msg = '10% RABATT SEPTEMBER 2025 angewendet!';
        else $msg = '¡DESCUENTO 10% SEPTIEMBRE 2025 aplicado!';

        echo '<div style="color: #feedd6;font-weight:bold;margin:10px 0;background: #220e1c;display: flex;padding: 5px;">' . $msg . '</div>';
    }
});

// Soporte para carrito flotante de Elementor
add_filter('woocommerce_widget_cart_item_quantity', function ($html, $cart_item, $cart_item_key) {
    if (isset($cart_item['booking']) && isset($cart_item['booking']['_start_date'])) {
        $product_ids = [32384, 40733, 33262, 40745, 33867, 40748];
        if (in_array($cart_item['product_id'], $product_ids)) {
            $start = (int)$cart_item['booking']['_start_date'];
            $weekday = date('N', $start);
            $month = date('m', $start);
            $year = date('Y', $start);

            if ($month == '09' && $year == '2025' && ($weekday == 1 || $weekday == 5)) {
                return $html . ' <span style="color:#e63946;font-size:0.8em;">(-10%)</span>';
            }
        }
    }
    return $html;
}, 10, 3);

// Mostrar mensaje informativo en la página de producto
add_action('woocommerce_before_add_to_cart_button', function () {
    global $product;

    if ($product && $product->get_type() === 'booking') {
        $product_ids = [32384, 40733, 33262, 40745, 33867, 40748];

        if (in_array($product->get_id(), $product_ids)) {
            $lang = apply_filters('wpml_current_language', NULL);
            if ($lang == 'en') {
                $msg = 'Select Monday or Friday in September 2025 for a 10% discount!';
            } elseif ($lang == 'de') {
                $msg = 'Wählen Sie Montag oder Freitag im September 2025 für einen 10% Rabatt!';
            } else {
                $msg = '¡Selecciona lunes o viernes en septiembre 2025 para un 10% de descuento!';
            }

            echo '<div id="booking-discount-notice" style="color: #220e1c; font-weight:bold; margin:10px 0; background: #feedd6; padding: 5px; text-align:center">' . $msg . '</div>';

            // Script para actualizar visualmente cuando se selecciona una fecha con descuento
?>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $(document.body).on('wc-booking-date-selected', function() {
                        setTimeout(function() {
                            var bookingForm = $('form.cart');
                            var startDate = bookingForm.find('input[name="wc_bookings_field_start_date_year"]').val() + '-' +
                                bookingForm.find('input[name="wc_bookings_field_start_date_month"]').val() + '-' +
                                bookingForm.find('input[name="wc_bookings_field_start_date_day"]').val();

                            var date = new Date(startDate);
                            var month = date.getMonth() + 1;
                            var year = date.getFullYear();
                            var day = date.getDay();

                            // Si es septiembre 2025 y es lunes (1) o viernes (5)
                            if (month === 9 && year === 2025 && (day === 1 || day === 5)) {
                                $('#booking-discount-notice').css('display', 'block').addClass('discount-active');
                            } else {
                                $('#booking-discount-notice').css('display', 'none').removeClass('discount-active');
                            }
                        }, 500);
                    });
                });
            </script>
<?php
        }
    }
});

<?php
/**
 * Plugin Name: Envío Gratis Automático por Categoría
 * Description: Aplica envío gratis automático por categorías, cantidad mínima y fechas, mostrando cupón informativo en checkout.
 * Version: 1.4
 * Author: 22 MW
 */

// Evitar acceso directo
if (!defined('ABSPATH')) exit;

class Envio_Gratis_Categoria {
    
    private $categorias_ids = array(240, 400, 333); // Array de IDs de categorías
    private $cantidad_minima = 6;
    private $codigo_cupon = 'HALLOWINE';
    private $fecha_inicio = '2025-10-20'; // AAAA-MM-DD
    private $fecha_fin = '2025-11-03'; // AAAA-MM-DD
    
    public function __construct() {
        add_action('woocommerce_before_calculate_totals', array($this, 'aplicar_cupon_automatico'), 10, 1);
        add_filter('woocommerce_package_rates', array($this, 'ocultar_otros_envios'), 100, 2);
    }
    
    // Métodos públicos para acceder a propiedades privadas
    public function get_codigo_cupon() {
        return $this->codigo_cupon;
    }
    
    public function get_fecha_fin() {
        return $this->fecha_fin;
    }
    
    // Verificar si está dentro del periodo válido
    private function esta_en_periodo_valido() {
        $ahora = current_time('timestamp');
        $inicio = strtotime($this->fecha_inicio . ' 00:00:00');
        $fin = strtotime($this->fecha_fin . ' 23:59:59');
        
        return ($ahora >= $inicio && $ahora <= $fin);
    }
    
    // Contar productos de las categorías en el carrito
    private function contar_productos_categoria() {
        if (!WC()->cart) return 0;
        
        $total = 0;
        foreach (WC()->cart->get_cart() as $item) {
            if (has_term($this->categorias_ids, 'product_cat', $item['product_id'])) {
                $total += $item['quantity'];
            }
        }
        return $total;
    }
    
    // Aplicar cupón automático cuando se cumpla la condición
    public function aplicar_cupon_automatico($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;
        
        if (!$this->esta_en_periodo_valido()) {
            if (WC()->cart->has_discount($this->codigo_cupon)) {
                WC()->cart->remove_coupon($this->codigo_cupon);
            }
            return;
        }
        
        $cantidad = $this->contar_productos_categoria();
        
        if ($cantidad >= $this->cantidad_minima) {
            if (!WC()->cart->has_discount($this->codigo_cupon)) {
                WC()->cart->apply_coupon($this->codigo_cupon);
            }
        } else {
            if (WC()->cart->has_discount($this->codigo_cupon)) {
                WC()->cart->remove_coupon($this->codigo_cupon);
            }
        }
    }
    
    // Ocultar otros métodos de envío si hay envío gratis
    public function ocultar_otros_envios($rates, $package) {
        $free_shipping = array();
        
        foreach ($rates as $rate_id => $rate) {
            if ('free_shipping' === $rate->method_id) {
                $free_shipping[$rate_id] = $rate;
            }
        }
        
        return !empty($free_shipping) ? $free_shipping : $rates;
    }
}

// Crear el cupón si no existe
function crear_cupon_envio_gratis() {
    $instance = new Envio_Gratis_Categoria();
    
    if (!wc_get_coupon_id_by_code($instance->get_codigo_cupon())) {
        $coupon = new WC_Coupon();
        $coupon->set_code($instance->get_codigo_cupon());
        $coupon->set_discount_type('fixed_cart');
        $coupon->set_amount(0);
        $coupon->set_free_shipping(true);
        $coupon->set_date_expires(strtotime($instance->get_fecha_fin() . ' 23:59:59'));
        $coupon->set_description('Envío gratis por 6+ productos de categoría específica');
        $coupon->save();
    }
}
add_action('init', 'crear_cupon_envio_gratis');

// Iniciar la clase
new Envio_Gratis_Categoria();

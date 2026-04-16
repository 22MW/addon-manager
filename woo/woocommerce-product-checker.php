<?php

/**
 * Plugin Name: WooCommerce Product Purchase Checker
 * Description: Añade shortcodes para comprobar compras por usuario/producto y listar productos comprados.
 * Version: 1.0.22
 * Author:  22MW
 */
print_r('hola');
// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase principal del plugin
 */
class WooCommerce_Product_Checker
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('init', array($this, 'init'));
    }

    /**
     * Inicializar el plugin
     */
    public function init()
    {
        // Verificar si WooCommerce está activo
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // Registrar los shortcodes
        add_shortcode('check_product_purchased', array($this, 'check_product_purchased_shortcode'));
        add_shortcode('user_purchased_products', array($this, 'user_purchased_products_shortcode'));
    }

    /**
     * Mostrar aviso si WooCommerce no está activo
     */
    public function woocommerce_missing_notice()
    {
        echo '<div class="notice notice-error"><p>WooCommerce Product Purchase Checker requiere que WooCommerce esté activo.</p></div>';
    }

    /**
     * Función del shortcode
     */
    public function check_product_purchased_shortcode($atts)
    {
        // Valores por defecto
        $atts = shortcode_atts(array(
            'user_id' => 0, // 0 = usuario actual
        ), $atts, 'check_product_purchased');

        // Obtener el ID del producto actual
        $product_id = $this->get_current_product_id();

        return $this->has_user_purchased_product($product_id, $atts['user_id']);
    }

    /**
     * Función del shortcode para mostrar todos los productos comprados
     */
    public function user_purchased_products_shortcode($atts)
    {
        // Valores por defecto
        $atts = shortcode_atts(array(
            'user_id' => 0, // 0 = usuario actual
        ), $atts, 'user_purchased_products');

        return $this->get_user_purchased_products($atts['user_id']);
    }

    /**
     * Obtener el ID del producto actual
     */
    private function get_current_product_id()
    {
        global $post;

        // Si estamos en una página de producto single
        if (is_product()) {
            return get_the_ID();
        }

        // Si estamos en el loop de productos
        if (is_woocommerce() && isset($post) && $post->post_type === 'product') {
            return $post->ID;
        }

        // Intentar obtener desde el contexto global de WooCommerce
        if (function_exists('wc_get_product')) {
            global $woocommerce_loop;
            if (isset($woocommerce_loop['product_id'])) {
                return $woocommerce_loop['product_id'];
            }
        }

        // Última opción: verificar si hay un post actual que sea producto
        if (isset($post) && $post->post_type === 'product') {
            return $post->ID;
        }

        // Si no encontramos producto, verificar parámetros GET/POST por si acaso
        if (isset($_GET['product_id'])) {
            return intval($_GET['product_id']);
        }

        return 0;
    }

    /**
     * Obtener todos los productos comprados por un usuario
     */
    private function get_user_purchased_products($user_id = 0)
    {
        // Si no se especifica user_id, usar el usuario actual
        if (empty($user_id)) {
            $user_id = get_current_user_id();
        }

        // Si no hay usuario logueado, devolver 0
        if (empty($user_id)) {
            return 0;
        }

        // Buscar órdenes del usuario
        $orders = wc_get_orders(array(
            'customer_id' => $user_id,
            'status' => array('wc-completed', 'wc-processing'),
            'limit' => -1,
        ));

        if (empty($orders)) {
            return 0;
        }

        $purchased_products = array();

        // Verificar cada orden
        foreach ($orders as $order) {
            if (!$order) {
                continue;
            }

            // Obtener productos de esta orden
            foreach ($order->get_items() as $item) {
                $item_product_id = $item->get_product_id();
                $item_variation_id = $item->get_variation_id();

                // Agregar producto principal
                if ($item_product_id && !in_array($item_product_id, $purchased_products)) {
                    $purchased_products[] = $item_product_id;
                }

                // Agregar variación si existe
                if ($item_variation_id && !in_array($item_variation_id, $purchased_products)) {
                    $purchased_products[] = $item_variation_id;
                }
            }
        }

        // Si no hay productos comprados, devolver 0
        if (empty($purchased_products)) {
            return 0;
        }

        // Convertir a string separado por comas
        return implode(',', $purchased_products);
    }

    /**
     * Verificar si un usuario ha comprado un producto
     */
    private function has_user_purchased_product($product_id, $user_id = 0)
    {
        // Validaciones básicas
        if (empty($product_id)) {
            return 0;
        }

        // Si no se especifica user_id, usar el usuario actual
        if (empty($user_id)) {
            $user_id = get_current_user_id();
        }

        // Si no hay usuario logueado, devolver 0
        if (empty($user_id)) {
            return 0;
        }

        // Verificar que el producto existe
        $product = wc_get_product($product_id);
        if (!$product) {
            return 0;
        }

        // Buscar órdenes del usuario que contengan este producto
        $orders = wc_get_orders(array(
            'customer_id' => $user_id,
            'status' => array('wc-completed', 'wc-processing'),
            'limit' => -1,
            'return' => 'ids',
        ));

        if (empty($orders)) {
            return 0;
        }

        // Verificar cada orden
        foreach ($orders as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                continue;
            }

            // Verificar si el producto está en esta orden
            foreach ($order->get_items() as $item) {
                $item_product_id = $item->get_product_id();
                $item_variation_id = $item->get_variation_id();

                // Verificar producto simple o variación
                if ($item_product_id == $product_id || $item_variation_id == $product_id) {
                    return 1;
                }
            }
        }

        return 0;
    }
}

// Inicializar el plugin
new WooCommerce_Product_Checker();

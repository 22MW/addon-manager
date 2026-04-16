<?php
/**
 * Plugin Name: WooCommerce Cart Cleaner
 * Description: Limpia carritos abandonados/caducados en intervalos configurables desde WooCommerce > Productos > Cart Cleaner.
 * Version: 4.2
 * Author: 22MW
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class WC_Cart_Cleaner {
    
    private $option_name = 'wc_cart_cleaner_interval';
    private $enabled_option = 'wc_cart_cleaner_enabled';
    private $log_option = 'wc_cart_cleaner_log_persistent';
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_filter('woocommerce_get_sections_products', array($this, 'add_section'));
        add_filter('woocommerce_get_settings_products', array($this, 'add_settings'), 10, 2);
        add_action('woocommerce_settings_save_products', array($this, 'save_settings'));
        
        // Añadir AJAX para obtener timestamp actualizado
        add_action('wp_ajax_get_next_cron_time', array($this, 'ajax_get_next_cron_time'));
    }
    
    public function init() {
        if (get_option($this->enabled_option, 'yes') === 'yes') {
            if (!wp_next_scheduled('wc_clean_expired_carts')) {
                wp_schedule_event(time(), 'wc_cart_clean_interval', 'wc_clean_expired_carts');
            }
        } else {
            wp_clear_scheduled_hook('wc_clean_expired_carts');
        }
        
        add_filter('cron_schedules', array($this, 'add_cron_interval'));
        add_action('wc_clean_expired_carts', array($this, 'clean_expired_carts'));
    }

    public function disable_unsaved_changes_warning() {
    if (isset($_GET['section']) && $_GET['section'] === 'cart_cleaner') {
        echo '<script>
        jQuery(document).ready(function($) {
            $(window).off("beforeunload");
        });
        </script>';
    }
}
    
public function add_cron_interval($schedules) {
    $interval = get_option($this->option_name, 1440); // Default 1440 si no existe
    
    // VALIDACIÓN: Si es 0, vacío o menor que 1, usar 1440
    if (empty($interval) || $interval < 1) {
        $interval = 1440;
        update_option($this->option_name, 1440); // Corregir en BD
    }
    
    $interval = $interval * 60; // Convertir a segundos
    
    $schedules['wc_cart_clean_interval'] = array(
        'interval' => $interval,
        'display'  => sprintf(__('Cada %d minutos'), $interval / 60)
    );
    return $schedules;
}

    
    public function clean_expired_carts() {
        if (get_option($this->enabled_option, 'yes') !== 'yes') {
            return;
        }
        
        global $wpdb;
        
        $result = array(
            'sessions' => 0,
            'bookings' => 0,
            'total' => 0
        );
        
        // Borrar sesiones expiradas
        $deleted_sessions = $wpdb->query("
            DELETE FROM {$wpdb->prefix}woocommerce_sessions 
            WHERE session_expiry < UNIX_TIMESTAMP()
        ");
        
        if ($deleted_sessions === false) {
            $deleted_sessions = 0;
        }
        
        // Limpiar transients de carritos
        $wpdb->query("
            DELETE FROM {$wpdb->prefix}options 
            WHERE option_name LIKE '_transient_wc_cart%'
            OR option_name LIKE '_transient_timeout_wc_cart%'
        ");
        
        // Borrar bookings in-cart
        $deleted_bookings = 0;
        if (class_exists('WC_Bookings')) {
            $deleted_bookings = $wpdb->query("
                DELETE FROM {$wpdb->prefix}posts 
                WHERE post_type = 'wc_booking' 
                AND post_status = 'in-cart'
            ");
            
            if ($deleted_bookings === false) {
                $deleted_bookings = 0;
            }
        }
        
        $result = array(
            'sessions' => $deleted_sessions,
            'bookings' => $deleted_bookings,
            'total' => $deleted_sessions + $deleted_bookings
        );
        
        // Guardar en log
        $this->add_to_log($result);
        
        return $result;
    }
    
    private function add_to_log($result) {
        // Crear archivo de log físico
        $log_file = WP_CONTENT_DIR . '/wc-cart-cleaner.log';
        
        // LIMPIAR ARCHIVO FÍSICO cada 24 horas
        if (file_exists($log_file)) {
            $file_age = time() - filemtime($log_file);
            $one_day = 24 * 60 * 60;
            
            // Si el archivo tiene más de 24 horas, vaciarlo
            if ($file_age > $one_day) {
                file_put_contents($log_file, ''); // Vaciar archivo
                $log_line = date('Y-m-d H:i:s') . " - LOG REINICIADO (archivo vaciado automáticamente)" . PHP_EOL;
                file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
            }
        }
        
        // Escribir nueva línea
        $log_line = date('Y-m-d H:i:s') . " - Automático: {$result['sessions']} carritos + {$result['bookings']} bookings = {$result['total']} total" . PHP_EOL;
        file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
        
        // Usar wp_options para persistencia PERMANENTE (historial completo)
        $log = get_option($this->log_option, array());
        if (!is_array($log)) {
            $log = array();
        }
        
        // Crear nueva entrada
        $new_entry = array(
            'timestamp' => current_time('Y-m-d H:i:s'),
            'deleted' => $result['total'],
            'sessions' => $result['sessions'],
            'bookings' => $result['bookings'],
            'type' => 'Automático'
        );
        
        // Añadir nueva entrada SIN FILTRAR POR 24 HORAS
        $log[] = $new_entry;
        
        // OPCIONAL: Limitar a últimas 1000 entradas para evitar que crezca infinito
        if (count($log) > 1000) {
            $log = array_slice($log, -1000); // Mantener solo las últimas 1000
        }
        
        // Guardar en options
        update_option($this->log_option, $log);
    }
    
    private function read_log_file_last_24h() {
        $log_file = WP_CONTENT_DIR . '/wc-cart-cleaner.log';
        
        if (!file_exists($log_file)) {
            return array();
        }
        
        $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return array();
        }
        
        $entries = array();
        $cutoff = time() - (24 * 60 * 60); // 24 horas atrás
        
        foreach ($lines as $line) {
            // Saltar líneas de reinicio
            if (strpos($line, 'LOG REINICIADO') !== false) {
                continue;
            }
            
            // Parsear línea: "2025-09-24 15:32:36 - Automático: 0 carritos + 12 bookings = 12 total"
            if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) - Automático: (\d+) carritos \+ (\d+) bookings = (\d+) total$/', $line, $matches)) {
                $timestamp = $matches[1];
                $sessions = (int)$matches[2];
                $bookings = (int)$matches[3];
                $total = (int)$matches[4];
                
                // Filtrar por 24 horas
                $entry_time = strtotime($timestamp);
                if ($entry_time > $cutoff) {
                    $entries[] = array(
                        'timestamp' => $timestamp,
                        'sessions' => $sessions,
                        'bookings' => $bookings,
                        'deleted' => $total,
                        'type' => 'Automático'
                    );
                }
            }
        }
        
        return $entries;
    }
    
    public function ajax_get_next_cron_time() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'get_next_cron_time')) {
            wp_die();
        }
        
        // Obtener el próximo cron actualizado
        $next_run = wp_next_scheduled('wc_clean_expired_carts');
        
        wp_send_json_success($next_run);
    }
    
    public function add_section($sections) {
        $sections['cart_cleaner'] = __('Limpiador de Carritos');
        return $sections;
    }
    
    public function add_settings($settings, $current_section) {
        if ('cart_cleaner' == $current_section) {
            
            // Añadir HTML personalizado al final del formulario
            add_action('woocommerce_admin_field_cart_cleaner_info', array($this, 'display_cart_cleaner_info'));
            
            $custom_settings = array(
                array(
                    'name' => __('Limpiador de Carritos Caducados'),
                    'type' => 'title',
                    'desc' => __('Configura la limpieza automática de carritos caducados') . $this->get_current_status(),
                    'id'   => 'cart_cleaner_settings'
                ),
                array(
                    'name'     => __('Activar limpiador automático'),
                    'type'     => 'checkbox',
                    'desc'     => __('Habilitar la limpieza automática de carritos'),
                    'id'       => $this->enabled_option,
                    'default'  => 'yes'
                ),
                array(
                    'name'     => __('Intervalo de limpieza (minutos)'),
                    'type'     => 'number',
                    'desc'     => __('Cada cuántos minutos limpiar los carritos caducados </br><small>(ej.: 600=10h, 1440=1d, 10080=7d, 43200=30d)</small>'),
                    'id'       => $this->option_name,
                    'default'  => '1440',
                    'custom_attributes' => array(
                        'min'  => '1',
                        'step' => '1'
                    )
                ),
                array(
                    'type' => 'sectionend',
                    'id'   => 'cart_cleaner_settings'
                ),
                // Campo personalizado para mostrar información fuera del formulario
                array(
                    'type' => 'cart_cleaner_info',
                    'id'   => 'cart_cleaner_info'
                )
            );
            return $custom_settings;
        }
        return $settings;
    }
    
    public function display_cart_cleaner_info() {
        ?>
        <div style="margin-top: 20px;">
            <style>
            #wc_cart_cleaner_interval{
                border: none;
                border-radius: 5px;
                color: #000; 
            }
            </style>
        </div>

        <!-- Secciones ocultas que se moverán con JS -->
        <div id="cart-cleaner-sections" style="display: none;">
            <div style="padding: 20px 50px;">
                <!-- Estadísticas y Historial -->
                <div style="margin-bottom: 30px;">
                    <h2 style="color: #0073aa; border-bottom: 1px solid #ddd; padding-bottom: 10px;"> Estadísticas y Historial</h2>
                    <?php echo $this->get_status_and_log(); ?>
                </div>

                <!-- Archivo de Log -->
                <div style="margin-bottom: 30px;">
                    <h2 style="color: #0073aa; border-bottom: 1px solid #ddd; padding-bottom: 10px;">📄 Archivo de Log Completo</h2>
                    <?php echo $this->get_log_file_info(); ?>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Buscar el formulario y las secciones
            var mainForm = document.getElementById('mainform');
            var sections = document.getElementById('cart-cleaner-sections');
            
            if (mainForm && sections) {
                // Mover las secciones después del formulario
                sections.style.display = 'block';
                mainForm.parentNode.insertBefore(sections, mainForm.nextSibling);
            }
        });
        </script>
        <?php
    }
    
    private function get_current_status() {
        // Obtener el timestamp ACTUAL después de cualquier cambio
        $next_run = wp_next_scheduled('wc_clean_expired_carts');
        $status = get_option($this->enabled_option, 'yes') === 'yes' ? 'Activo' : 'Inactivo';
        
        $html = '<div style="background: #ffffff;padding: 15px;border-radius: 5px;">';
        $html .= '<h4 style="margin-top: 0;font-size: 16px;">Estado del Sistema</h4>';
        $html .= '<p><strong>Sistema automático:</strong> ' . $status . '</p>';
        
        if ($next_run) {
            $html .= '<p><strong>Próxima ejecución:</strong> <span id="next-execution" data-timestamp="' . $next_run . '">' . date('Y-m-d H:i:s', $next_run) . '</span></p>';
            
            // Script mejorado que actualiza el timestamp si es necesario
            add_action('admin_footer', function() use ($next_run) {
                echo '<script type="text/javascript">
                if (typeof jQuery !== "undefined") {
                    jQuery(document).ready(function($) {
                        function updateNextExecution() {
                            const element = document.getElementById("next-execution");
                            if (!element) return;
                            
                            // Usar el timestamp ACTUAL del servidor
                            const timestamp = ' . $next_run . ' * 1000;
                            const now = new Date().getTime();
                            const diff = timestamp - now;
                            
                            // Si la diferencia es negativa o muy pequeña, recalcular
                            if (diff <= 1000) {
                                // Hacer una petición AJAX para obtener el nuevo timestamp
                                $.post(ajaxurl, {
                                    action: "get_next_cron_time",
                                    nonce: "' . wp_create_nonce('get_next_cron_time') . '"
                                }, function(response) {
                                    if (response.success && response.data) {
                                        element.dataset.timestamp = response.data;
                                        // Actualizar inmediatamente con el nuevo timestamp
                                        updateNextExecutionWithTimestamp(response.data * 1000);
                                    }
                                });
                                return;
                            }
                            
                            updateNextExecutionWithTimestamp(timestamp);
                        }
                        
                        function updateNextExecutionWithTimestamp(timestamp) {
                            const element = document.getElementById("next-execution");
                            if (!element) return;
                            
                            const now = new Date().getTime();
                            const diff = timestamp - now;
                            
                            if (diff <= 0) {
                                element.textContent = "Ejecutándose ahora...";
                                return;
                            }
                            
                            const minutes = Math.floor(diff / (1000 * 60));
                            const seconds = Math.floor((diff % (1000 * 60)) / 1000);
                            
                            if (minutes > 0) {
                                element.textContent = "En " + minutes + " minutos y " + seconds + " segundos";
                            } else {
                                element.textContent = "En " + seconds + " segundos";
                            }
                        }
                        
                        updateNextExecution();
                        setInterval(updateNextExecution, 1000);
                    });
                }
                </script>';
            });
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    private function get_status_and_log() {
        // Estadísticas totales desde wp_options
        $log_total = get_option($this->log_option, array());
        
        // Historial últimas 24h desde archivo
        $log_24h = $this->read_log_file_last_24h();
        
        $html = '<div>';
        
        if (is_array($log_total) && count($log_total) > 0) {
            // Estadísticas TOTALES desde wp_options
            $total_deleted = array_sum(array_column($log_total, 'deleted'));
            $total_sessions = array_sum(array_column($log_total, 'sessions'));
            $total_bookings = array_sum(array_column($log_total, 'bookings'));
            $executions = count($log_total);
            
            $html .= '<h4 style="font-size: 18px;">Estadísticas totales (desde instalación):</h4>';
            
            // Estadísticas en línea horizontal
            $html .= '<div style="display: flex;gap: 20px;margin-bottom: 15px;flex-wrap: wrap;padding: 20px;background: #e7e7e7;">';
            $html .= '<div style="background: white; padding: 10px 15px; border-radius: 4px; text-align: center; min-width: 120px;">';
            $html .= '<div style="font-size: 24px; font-weight: bold; color: #0073aa;padding-bottom: 10px;">' . $total_deleted . '</div>';
            $html .= '<div style="font-size: 12px; color: #333; font-weight: bold">Total borrados</div>';
            $html .= '</div>';
            $html .= '<div style="background: white; padding: 10px 15px; border-radius: 4px; text-align: center; min-width: 120px;">';
            $html .= '<div style="font-size: 24px; font-weight: bold; color: #d63638;padding-bottom: 10px;">' . $total_sessions . '</div>';
            $html .= '<div style="font-size: 12px; color: #333; font-weight: bold">Carritos</div>';
            $html .= '</div>';
            $html .= '<div style="background: white; padding: 10px 15px; border-radius: 4px; text-align: center; min-width: 120px;">';
            $html .= '<div style="font-size: 24px; font-weight: bold; color: #e27d60;padding-bottom: 10px;">' . $total_bookings . '</div>';
            $html .= '<div style="font-size: 12px; color: #333; font-weight: bold">Bookings</div>';
            $html .= '</div>';
                $html .= '<div style="background: white; padding: 10px 15px; border-radius: 4px; text-align: center; min-width: 120px;">';
            $html .= '<div style="font-size: 24px; font-weight: bold; color: #00a32a;padding-bottom: 10px;">' . $executions . '</div>';
            $html .= '<div style="font-size: 12px; color: #333; font-weight: bold">Ejecuciones</div>';
            $html .= '</div>';
            $html .= '</div>';
            
            $html .= '<h4  style="font-size: 18px;">Historial últimas 24 horas (' . count($log_24h) . ' ejecuciones):</h5>';
            
            // Contenedor con scroll CORREGIDO
            $html .= '<div style="               
                max-height: 250px;
                background: #e7e7e7;
                padding: 20px;
                border: none;
                border-radius: 4px;
                overflow: auto;
                word-wrap: break-word;
                box-sizing: border-box;
                display: flex;
                flex-direction: row;
                flex-wrap: wrap;
                gap: 20px;
                align-items: flex-start;
            ">';
            
            // Mostrar entradas del archivo (últimas 24h)
            $all_log = array_reverse($log_24h);
            foreach ($all_log as $entry) {
                $sessions = isset($entry['sessions']) ? $entry['sessions'] : 0;
                $bookings = isset($entry['bookings']) ? $entry['bookings'] : 0;
                $total = $entry['deleted'];
                
                $html .= '<div style="
                    margin-bottom: 10px; 
                    padding: 15px; 
                    background: #fafafa;
                    border-radius: 5px;
                    word-wrap: break-word;
                    overflow-wrap: break-word;
                ">';
                
                $html .= '<div style="
                    display: flex; 
                    justify-content: space-between; 
                    align-items: center; 
                    margin-bottom: 4px;
                    flex-wrap: wrap;
                ">';
                $html .= '<strong style="color: #0073aa; font-size: 13px; padding-bottom: 5px">' . $entry['timestamp'] . '</strong>';
                $html .= '</div>';
                
                if ($total > 0) {
                    $html .= '<div style="
                        color: #333; 
                        font-size: 12px; 
                        line-height: 1.4;
                        word-wrap: break-word;
                    ">';
                    if ($bookings > 0 && $sessions > 0) {
                        $html .= '🛒 ' . $sessions . ' carritos + 📅 ' . $bookings . ' bookings = <strong style="color: #d63638;">' . $total . '</strong> total';
                    } else if ($bookings > 0) {
                        $html .= '📅 <strong style="color: #d63638;">' . $bookings . '</strong> bookings borrados';
                    } else if ($sessions > 0) {
                        $html .= '🛒 <strong style="color: #d63638;">' . $sessions . '</strong> carritos/sesiones borrados';
                    } else {
                        $html .= ' <strong style="color: #d63638;">' . $total . '</strong> elementos borrados';
                    }
                    $html .= '</div>';
                } else {
                    $html .= '<div style="color: #999; font-size: 12px;">✅ Sin elementos que borrar</div>';
                }
                
                $html .= '</div>';
            }
            
            $html .= '</div>';
            
        } else {
            $html .= '<p><em>📭 No hay datos de las últimas 24 horas</em></p>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    private function get_log_file_info() {
        $log_file = WP_CONTENT_DIR . '/wc-cart-cleaner.log';
        
        $html = '<div>';
        
        if (file_exists($log_file)) {
            $file_size = round(filesize($log_file) / 1024, 2);
            $last_modified = date('Y-m-d H:i:s', filemtime($log_file));
            $html .= '<p style="margin: 5px 0; padding: 8px; background: white; border-radius: 3px; font-family: monospace; font-size: 12px; word-break: break-all; border: 1px solid #ddd;"><code>' . $log_file . '</code></p>';
            
            $html .= '<div style="display: flex;margin-top: 10px;gap: 20px;">';
            $html .= '<div><strong>Tamaño:</strong> ' . $file_size . ' KB</div>';
            $html .= '<div><strong>Última modificación:</strong> ' . $last_modified . '</div>';
            $html .= '</div>';
        } else {
            $html .= '<p style="color: #666;">El archivo de log no existe aún. Se creará en la primera ejecución.</p>';
            $html .= '<p><strong>Se creará en:</strong> <code>' . $log_file . '</code></p>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    public function save_settings() {
    if (isset($_POST[$this->option_name])) {
        $new_interval = intval($_POST[$this->option_name]);
        
        // VALIDACIÓN: Si es 0 o menor que 1, usar 1440
        if ($new_interval < 1) {
            $new_interval = 1440;
        }
        
        $old_interval = get_option($this->option_name, 1440);
        
        if ($new_interval !== $old_interval) {
            update_option($this->option_name, $new_interval);
            wp_clear_scheduled_hook('wc_clean_expired_carts');
            if (get_option($this->enabled_option, 'yes') === 'yes') {
                wp_schedule_event(time(), 'wc_cart_clean_interval', 'wc_clean_expired_carts');
            }
        }
    }
    
    $new_enabled = isset($_POST[$this->enabled_option]) ? 'yes' : 'no';
    $old_enabled = get_option($this->enabled_option, 'yes');
    
    if ($new_enabled !== $old_enabled) {
        update_option($this->enabled_option, $new_enabled);
        wp_clear_scheduled_hook('wc_clean_expired_carts');
        
        if ($new_enabled === 'yes') {
            wp_schedule_event(time(), 'wc_cart_clean_interval', 'wc_clean_expired_carts');
        }
    }
}

}

new WC_Cart_Cleaner();

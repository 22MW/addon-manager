<?php
/**
 * Plugin Name: Limpiador de Reservas
 * Description: Limpia reservas WooCommerce Bookings canceladas o pendientes según reglas configurables.
 * Marketing Description: Mantiene limpio el sistema de reservas eliminando estados inservibles automáticamente.
 * Parameters: Panel propio en WooCommerce para limpieza automática de reservas.
 * Version: 2.2
 * Author: 22 MW
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class LimpiadorReservas {
    
    const OPTION_CRON_CANCELLED_ENABLED = 'lr_cron_cancelled_enabled';
    const OPTION_CRON_PENDING_ENABLED = 'lr_cron_pending_enabled';
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('lr_cleanup_cancelled_bookings', array($this, 'cleanup_cancelled_bookings'));
        add_action('lr_cleanup_pending_bookings', array($this, 'cleanup_pending_bookings'));
        
        // Programar crons si están activados
        $this->schedule_crons();
    }
    
    private function schedule_crons() {
        // Cron para canceladas
        if (get_option(self::OPTION_CRON_CANCELLED_ENABLED, false)) {
            if (!wp_next_scheduled('lr_cleanup_cancelled_bookings')) {
                wp_schedule_event(time(), 'daily', 'lr_cleanup_cancelled_bookings');
            }
        } else {
            wp_clear_scheduled_hook('lr_cleanup_cancelled_bookings');
        }
        
        // Cron para pendientes
        if (get_option(self::OPTION_CRON_PENDING_ENABLED, false)) {
            if (!wp_next_scheduled('lr_cleanup_pending_bookings')) {
                wp_schedule_event(time(), 'daily', 'lr_cleanup_pending_bookings');
            }
        } else {
            wp_clear_scheduled_hook('lr_cleanup_pending_bookings');
        }
    }
    
    public function init() {
        if (!class_exists('WC_Bookings')) {
            add_action('admin_notices', array($this, 'booking_plugin_notice'));
            return;
        }
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=wc_booking',
            'Limpiador de Reservas',
            'Limpiar Reservas',
            'manage_options',
            'limpiador-reservas',
            array($this, 'admin_page')
        );
    }
    
    public function admin_page() {
        $tab = isset($_GET['tab']) ? $_GET['tab'] : 'cancelled';
        
        // Procesar acciones
        if (isset($_POST['toggle_cancelled_cron'])) {
            $current = get_option(self::OPTION_CRON_CANCELLED_ENABLED, false);
            update_option(self::OPTION_CRON_CANCELLED_ENABLED, !$current);
            $this->schedule_crons();
            echo '<div class="notice notice-success"><p>Limpieza automática de canceladas ' . (!$current ? 'activada' : 'desactivada') . '</p></div>';
        }
        
        if (isset($_POST['toggle_pending_cron'])) {
            $current = get_option(self::OPTION_CRON_PENDING_ENABLED, false);
            update_option(self::OPTION_CRON_PENDING_ENABLED, !$current);
            $this->schedule_crons();
            echo '<div class="notice notice-success"><p>Limpieza automática de pendientes ' . (!$current ? 'activada' : 'desactivada') . '</p></div>';
        }
        
        if (isset($_POST['manual_cleanup_cancelled'])) {
            $deleted = $this->cleanup_cancelled_bookings();
            echo '<div class="notice notice-success"><p>Se eliminaron ' . $deleted . ' reservas canceladas</p></div>';
        }
        
        if (isset($_POST['manual_cleanup_pending'])) {
            $deleted = $this->cleanup_pending_bookings();
            echo '<div class="notice notice-success"><p>Se eliminaron ' . $deleted . ' reservas pendientes</p></div>';
        }
        
        ?>
        <div class="wrap">
            <h1>Limpiador de Reservas</h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?post_type=wc_booking&page=limpiador-reservas&tab=cancelled" 
                   class="nav-tab <?php echo $tab == 'cancelled' ? 'nav-tab-active' : ''; ?>">
                   Reservas Canceladas
                </a>
                <a href="?post_type=wc_booking&page=limpiador-reservas&tab=pending" 
                   class="nav-tab <?php echo $tab == 'pending' ? 'nav-tab-active' : ''; ?>">
                   Pendientes Confirmación
                </a>
            </h2>
            
            <?php if ($tab == 'cancelled'): ?>
                <?php $this->cancelled_tab(); ?>
            <?php else: ?>
                <?php $this->pending_tab(); ?>
            <?php endif; ?>
            
        </div>
        <?php
    }
    
    private function cancelled_tab() {
        $cron_enabled = get_option(self::OPTION_CRON_CANCELLED_ENABLED, false);
        $next_run = wp_next_scheduled('lr_cleanup_cancelled_bookings');
        ?>
        <div class="card" style="font-size: 14px;padding: 40px;border: none;box-shadow: none;">
            <strong>Reservas Canceladas:</strong> Esta función elimina automáticamente todas las reservas que tienen estado "cancelado" y que fueron creadas hace más de 48 horas. Esto ayuda a mantener la base de datos limpia eliminando reservas canceladas antiguas que ya no son necesarias.
        </div>
        <div class="card" style="border: none;box-shadow: none;">
            <h2>Limpieza Automática - Canceladas</h2>
            <p>Estado: <strong><?php echo $cron_enabled ? 'Activado' : 'Desactivado'; ?></strong></p>
            <?php if ($next_run): ?>
                <p>Próxima ejecución: <?php echo date('d/m/Y H:i:s', $next_run); ?></p>
            <?php endif; ?>
            
            <form method="post">
                <input type="submit" name="toggle_cancelled_cron" 
                       class="button button-primary" 
                       value="<?php echo $cron_enabled ? 'Desactivar' : 'Activar'; ?> Limpieza Automática">
            </form>
        </div>
        
        <div class="card" style="border: none;box-shadow: none;">
            <h2>Limpieza Manual - Canceladas</h2>
            <p>Elimina reservas canceladas más antiguas de 48 horas.</p>
            
            <form method="post" onsubmit="return confirm('¿Eliminar reservas canceladas?');">
                <input type="submit" name="manual_cleanup_cancelled" 
                       class="button button-secondary" 
                       value="Limpiar Canceladas">
            </form>
        </div>
        <?php
    }
    
    private function pending_tab() {
        $cron_enabled = get_option(self::OPTION_CRON_PENDING_ENABLED, false);
        $next_run = wp_next_scheduled('lr_cleanup_pending_bookings');
        ?>
        <div class="card" style="font-size: 14px;padding: 40px;border: none;box-shadow: none;">
            <strong>Pendientes de Confirmación:</strong> Esta función elimina automáticamente TODAS las reservas que están pendientes de confirmación (pending-confirmation). Estas reservas nunca fueron confirmadas y pueden ser eliminadas de forma segura.
        </div>
        <div class="card" style="border: none;box-shadow: none;">
            <h2>Limpieza Automática - Pendientes</h2>
            <p>Estado: <strong><?php echo $cron_enabled ? 'Activado' : 'Desactivado'; ?></strong></p>
            <?php if ($next_run): ?>
                <p>Próxima ejecución: <?php echo date('d/m/Y H:i:s', $next_run); ?></p>
            <?php endif; ?>
            
            <form method="post">
                <input type="submit" name="toggle_pending_cron" 
                       class="button button-primary" 
                       value="<?php echo $cron_enabled ? 'Desactivar' : 'Activar'; ?> Limpieza Automática">
            </form>
        </div>
        
        <div class="card" style="border: none;box-shadow: none;">
            <h2>Limpieza Manual - Pendientes</h2>
            <p>Elimina reservas pendientes cuya fecha de inicio ya pasó.</p>
            
            <form method="post" onsubmit="return confirm('¿Eliminar reservas pendientes vencidas?');">
                <input type="submit" name="manual_cleanup_pending" 
                       class="button button-secondary" 
                       value="Limpiar Pendientes">
            </form>
        </div>
        <?php
    }
    
    public function cleanup_cancelled_bookings() {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-48 hours'));
        
        $booking_ids = $wpdb->get_col($wpdb->prepare("
            SELECT p.ID 
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'wc_booking'
            AND p.post_status = 'cancelled'
            AND p.post_date < %s
        ", $cutoff_date));
        
        return $this->delete_bookings($booking_ids, 'canceladas');
    }
    
public function cleanup_pending_bookings() {
    global $wpdb;
    
    $booking_ids = $wpdb->get_col("
        SELECT ID 
        FROM {$wpdb->posts}
        WHERE post_type = 'wc_booking'
        AND post_status = 'pending-confirmation'
    ");
    
    return $this->delete_bookings($booking_ids, 'pendientes');
}


    
    private function delete_bookings($booking_ids, $type) {
        global $wpdb;
        
        $deleted_count = 0;
        
        foreach ($booking_ids as $booking_id) {
            $wpdb->delete($wpdb->postmeta, array('post_id' => $booking_id));
            $wpdb->delete($wpdb->posts, array('ID' => $booking_id));
            $deleted_count++;
        }
        
        error_log("Limpiador Reservas: Se eliminaron {$deleted_count} reservas {$type}");
        return $deleted_count;
    }
    
    public function booking_plugin_notice() {
        ?>
        <div class="notice notice-error">
            <p>El Limpiador de Reservas requiere WooCommerce Bookings para funcionar.</p>
        </div>
        <?php
    }
}

new LimpiadorReservas();

<?php
/**
 * Plugin Name: MS DB Cleaner
 * Description: Limpieza controlada de base de datos para red WordPress Multisite con panel en Network Admin.
 * Marketing Description: Mantenimiento central de base de datos para conservar rendimiento en multisite.
 * Parameters: Suite de limpieza de base de datos para Network Admin.
 * Version: 1.0.5
 * Author: WP Admin
 * Network: true
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class MS_DB_Cleaner {
    // Nombre de la página
    const MENU_SLUG = 'ms-db-cleaner';
    
    // Capacidad necesaria para acceder
    const CAPABILITY = 'manage_network';
    
    // Opciones para guardar estado y configuración
    const OPTION_LOG = 'ms_db_cleaner_log';
    const OPTION_BATCH_SIZE = 'ms_db_cleaner_batch_size';
    const OPTION_LAST_SITE = 'ms_db_cleaner_last_site';
    const OPTION_RUNNING = 'ms_db_cleaner_running';
    
    /**
     * Constructor
     */
    public function __construct() {
        // Solo inicializar en el área de administración
        if (is_admin()) {
            add_action('network_admin_menu', [$this, 'add_network_menu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
            
            // Registrar acciones AJAX
            add_action('wp_ajax_ms_db_cleaner_process', [$this, 'ajax_process_batch']);
            add_action('wp_ajax_ms_db_cleaner_reset', [$this, 'ajax_reset_process']);
            add_action('wp_ajax_ms_db_cleaner_get_log', [$this, 'ajax_get_log']);
            add_action('wp_ajax_ms_db_cleaner_save_settings', [$this, 'ajax_save_settings']);
        }
    }
    
    /**
     * Añadir menú de administración de red
     */
    public function add_network_menu() {
        add_submenu_page(
            'settings.php',
            'Limpieza de Base de Datos',
            'Limpieza DB',
            self::CAPABILITY,
            self::MENU_SLUG,
            [$this, 'render_admin_page']
        );
    }
    
    /**
     * Cargar scripts y estilos
     */
    public function enqueue_scripts($hook) {
        if ('settings_page_' . self::MENU_SLUG !== $hook) {
            return;
        }
        
        // Agregar estilos inline
        add_action('admin_head', function() {
            echo '<style>
                .ms-db-cleaner-section { margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
                .ms-db-cleaner-section h2 { margin-top: 0; }
                .ms-db-cleaner-progress { width: 100%; height: 20px; margin: 10px 0; }
                .ms-db-cleaner-log { max-height: 300px; overflow-y: auto; padding: 10px; background: #f6f7f7; border: 1px solid #ddd; font-family: monospace; }
                .ms-db-cleaner-actions { margin: 15px 0; }
                .ms-db-cleaner-actions button { margin-right: 10px; }
                .ms-db-cleaner-status { margin: 10px 0; }
                .ms-db-cleaner-option { margin: 10px 0; }
            </style>';
        });
        
        // Script principal
        wp_enqueue_script('jquery');
        
        // Script inline (evita problemas de carga)
        add_action('admin_footer', function() {
            ?>
            <script>
            jQuery(document).ready(function($) {
                // Variables para el seguimiento del proceso
                let isRunning = false;
                let taskId = '';
                let processedSites = 0;
                let totalSites = 0;
                
                // Elementos DOM
                const $progressBar = $('#ms-db-cleaner-progress');
                const $statusText = $('#ms-db-cleaner-status');
                const $logContainer = $('#ms-db-cleaner-log');
                const $startButton = $('#ms-db-cleaner-start');
                const $stopButton = $('#ms-db-cleaner-stop');
                const $resetButton = $('#ms-db-cleaner-reset');
                const $settingsForm = $('#ms-db-cleaner-settings');
                
                // Cargar log al iniciar
                getLog();
                
                // Guardar configuración
                $settingsForm.on('submit', function(e) {
                    e.preventDefault();
                    
                    $.post(ajaxurl, {
                        action: 'ms_db_cleaner_save_settings',
                        nonce: '<?php echo wp_create_nonce('ms_db_cleaner_nonce'); ?>',
                        batch_size: $('#batch_size').val()
                    }, function(response) {
                        if (response.success) {
                            alert('Configuración guardada correctamente.');
                        } else {
                            alert('Error al guardar la configuración.');
                        }
                    });
                });
                
                // Iniciar proceso
                $startButton.on('click', function() {
                    if (isRunning) return;
                    
                    isRunning = true;
                    taskId = $('#task_select').val();
                    
                    $startButton.prop('disabled', true);
                    $stopButton.prop('disabled', false);
                    $resetButton.prop('disabled', true);
                    
                    $statusText.text('Iniciando proceso...');
                    $progressBar.val(0);
                    
                    processBatch();
                });
                
                // Detener proceso
                $stopButton.on('click', function() {
                    isRunning = false;
                    $startButton.prop('disabled', false);
                    $stopButton.prop('disabled', true);
                    $resetButton.prop('disabled', false);
                    $statusText.text('Proceso detenido por el usuario.');
                });
                
                // Resetear proceso
                $resetButton.on('click', function() {
                    if (confirm('¿Seguro que desea reiniciar el proceso? Se perderá el progreso actual.')) {
                        $.post(ajaxurl, {
                            action: 'ms_db_cleaner_reset',
                            nonce: '<?php echo wp_create_nonce('ms_db_cleaner_nonce'); ?>'
                        }, function(response) {
                            if (response.success) {
                                $progressBar.val(0);
                                $statusText.text('Proceso reiniciado.');
                                getLog();
                            }
                        });
                    }
                });
                
                // Procesar lote
                function processBatch() {
                    if (!isRunning) return;
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'ms_db_cleaner_process',
                            nonce: '<?php echo wp_create_nonce('ms_db_cleaner_nonce'); ?>',
                            task: taskId
                        },
                        timeout: 60000, // 60 segundos
                        success: function(response) {
                            if (response.success) {
                                processedSites = response.data.processed;
                                totalSites = response.data.total;
                                
                                // Actualizar progreso
                                const progress = Math.min((processedSites / totalSites) * 100, 100);
                                $progressBar.val(progress);
                                
                                $statusText.text(
                                    `Procesando sitios: ${processedSites} de ${totalSites} (${Math.round(progress)}%)`
                                );
                                
                                // Actualizar log
                                getLog();
                                
                                // Continuar o finalizar
                                if (response.data.finished || !isRunning) {
                                    isRunning = false;
                                    $startButton.prop('disabled', false);
                                    $stopButton.prop('disabled', true);
                                    $resetButton.prop('disabled', false);
                                    
                                    if (response.data.finished) {
                                        $statusText.text('Proceso completado con éxito.');
                                    }
                                } else {
                                    // Pequeña pausa entre lotes para no sobrecargar el servidor
                                    setTimeout(processBatch, 2000); // 2 segundos de pausa
                                }
                            } else {
                                isRunning = false;
                                $startButton.prop('disabled', false);
                                $stopButton.prop('disabled', true);
                                $resetButton.prop('disabled', false);
                                $statusText.text('Error: ' + (response.data && response.data.message ? response.data.message : 'Error desconocido'));
                            }
                        },
                        error: function(xhr, status, error) {
                            console.log('Error AJAX:', status, error);
                            isRunning = false;
                            $startButton.prop('disabled', false);
                            $stopButton.prop('disabled', true);
                            $resetButton.prop('disabled', false);
                            $statusText.text('Error de conexión. Intente con un tamaño de lote más pequeño.');
                        }
                    });
                }
                
                // Obtener log
                function getLog() {
                    $.post(ajaxurl, {
                        action: 'ms_db_cleaner_get_log',
                        nonce: '<?php echo wp_create_nonce('ms_db_cleaner_nonce'); ?>'
                    }, function(response) {
                        if (response.success) {
                            $logContainer.html(response.data);
                            $logContainer.scrollTop($logContainer[0].scrollHeight);
                        }
                    });
                }
            });
            </script>
            <?php
        });
    }
    
    /**
     * Renderizar página de administración
     */
    public function render_admin_page() {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(__('No tienes permisos para acceder a esta página.'));
        }
        
        // Obtener configuración
        $batch_size = get_site_option(self::OPTION_BATCH_SIZE, 3);
        $last_site = get_site_option(self::OPTION_LAST_SITE, 0);
        $running = get_site_option(self::OPTION_RUNNING, false);
        
        // Tareas disponibles
        $tasks = $this->get_available_tasks();
        
        ?>
        <div class="wrap">
            <h1>Limpieza de Base de Datos para Multisite</h1>
            
            <div class="ms-db-cleaner-section">
                <h2>Configuración</h2>
                <form id="ms-db-cleaner-settings">
                    <div class="ms-db-cleaner-option">
                        <label for="batch_size">Tamaño del lote (sitios por ejecución):</label>
                        <input type="number" id="batch_size" name="batch_size" value="<?php echo esc_attr($batch_size); ?>" min="1" max="5">
                        <p class="description">Se recomienda un valor pequeño (1-3) para evitar timeouts.</p>
                    </div>
                    
                    <div class="ms-db-cleaner-option">
                        <label for="task_select">Tarea de limpieza:</label>
                        <select id="task_select" name="task">
                            <?php foreach ($tasks as $id => $task): ?>
                                <option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($task['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="ms-db-cleaner-option">
                        <button type="submit" class="button button-secondary">Guardar configuración</button>
                    </div>
                </form>
            </div>
            
            <div class="ms-db-cleaner-section">
                <h2>Ejecución de tareas</h2>
                <div class="ms-db-cleaner-progress-container">
                    <progress id="ms-db-cleaner-progress" class="ms-db-cleaner-progress" value="0" max="100"></progress>
                    <div id="ms-db-cleaner-status" class="ms-db-cleaner-status">
                        Listo para iniciar proceso.
                    </div>
                </div>
                
                <div class="ms-db-cleaner-actions">
                    <button id="ms-db-cleaner-start" class="button button-primary">Iniciar limpieza</button>
                    <button id="ms-db-cleaner-stop" class="button" disabled>Detener proceso</button>
                    <button id="ms-db-cleaner-reset" class="button">Reiniciar proceso</button>
                </div>
            </div>
            
            <div class="ms-db-cleaner-section">
                <h2>Registro de actividad</h2>
                <div id="ms-db-cleaner-log" class="ms-db-cleaner-log">
                    Cargando registro...
                </div>
            </div>
            
            <div class="ms-db-cleaner-section">
                <h2>Información</h2>
                <p>Este plugin permite realizar tareas de limpieza controlada en la base de datos de la red.</p>
                <ul>
                    <li><strong>Recomendación:</strong> Realiza siempre una copia de seguridad completa antes de iniciar cualquier limpieza.</li>
                    <li><strong>Procesamiento:</strong> El sistema procesa un sitio por vez para evitar timeouts.</li>
                    <li><strong>Si experimentas errores:</strong> Haz click en "Reiniciar proceso" para empezar de nuevo.</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Obtener tareas disponibles
     * 
     * @return array Lista de tareas disponibles
     */
    private function get_available_tasks() {
        return apply_filters('ms_db_cleaner_tasks', [
            'old_revisions' => [
                'name' => 'Eliminar revisiones antiguas (más de 30 días)',
                'description' => 'Elimina revisiones antiguas de posts que ya no se necesitan.',
                'callback' => [$this, 'clean_old_revisions'],
            ],
            'expired_transients' => [
                'name' => 'Eliminar transients expirados',
                'description' => 'Elimina los transients que han expirado y ya no son necesarios.',
                'callback' => [$this, 'clean_expired_transients'],
            ],
            'spam_comments' => [
                'name' => 'Eliminar comentarios spam',
                'description' => 'Elimina comentarios marcados como spam.',
                'callback' => [$this, 'clean_spam_comments'],
            ],
            'trash_posts' => [
                'name' => 'Eliminar posts en papelera',
                'description' => 'Elimina permanentemente los posts que están en la papelera.',
                'callback' => [$this, 'clean_trash_posts'],
            ],
            'optimize_tables' => [
                'name' => 'Optimizar tablas de la base de datos',
                'description' => 'Ejecuta OPTIMIZE TABLE en las tablas de WordPress.',
                'callback' => [$this, 'optimize_tables'],
            ],
        ]);
    }
    
    /**
     * Procesar lote via AJAX
     * Versión 1.0.5: Solucionado el problema de procesamiento de sitios
     */
    public function ajax_process_batch() {
        // Aumentar tiempo máximo de ejecución si es posible
        @set_time_limit(120);
        
        // Verificar nonce
        if (!check_ajax_referer('ms_db_cleaner_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Nonce inválido']);
        }
        
        // Verificar permisos
        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(['message' => 'Permisos insuficientes']);
        }
        
        // Obtener tarea seleccionada
        $task_id = isset($_POST['task']) ? sanitize_key($_POST['task']) : '';
        $tasks = $this->get_available_tasks();
        
        if (!isset($tasks[$task_id])) {
            wp_send_json_error(['message' => 'Tarea no válida']);
        }
        
        // Obtener último sitio procesado
        $last_site_id = intval(get_site_option(self::OPTION_LAST_SITE, 0));
        
        // Obtener todos los IDs de sitios de forma manual para evitar problemas con get_sites()
        global $wpdb;
        $blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs} WHERE blog_id > {$last_site_id} ORDER BY blog_id ASC LIMIT 1");
        
        // Si no hay más sitios, obtenemos el primer sitio para reiniciar
        if (empty($blog_ids)) {
            if ($last_site_id > 0) {
                $blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs} ORDER BY blog_id ASC LIMIT 1");
                $this->log_action('Reiniciando ciclo de procesamiento para ' . $tasks[$task_id]['name']);
            }
            
            // Si aún no hay sitios, terminamos
            if (empty($blog_ids)) {
                update_site_option(self::OPTION_LAST_SITE, 0);
                update_site_option(self::OPTION_RUNNING, false);
                $this->log_action('No se encontraron sitios para procesar.');
                
                wp_send_json_success([
                    'processed' => 0,
                    'total' => 0,
                    'finished' => true,
                ]);
                return;
            }
        }
        
        // Obtener el ID del siguiente sitio
        $site_id = intval($blog_ids[0]);
        
        // Obtener el total de sitios para mostrar progreso
        $total_sites = $wpdb->get_var("SELECT COUNT(blog_id) FROM {$wpdb->blogs}");
        
        // Cambiar al sitio
        switch_to_blog($site_id);
        
        // Ejecutar tarea
        try {
            $result = call_user_func($tasks[$task_id]['callback']);
            $this->log_action(sprintf(
                'Sitio #%d (%s): %s - %s',
                $site_id,
                get_option('blogname'),
                $tasks[$task_id]['name'],
                is_wp_error($result) ? $result->get_error_message() : $result
            ));
        } catch (Exception $e) {
            $this->log_action(sprintf(
                'Error en sitio #%d (%s): %s - %s',
                $site_id,
                get_option('blogname'),
                $tasks[$task_id]['name'],
                $e->getMessage()
            ));
        }
        
        // Restaurar sitio principal
        restore_current_blog();
        
        // Actualizar último sitio procesado
        update_site_option(self::OPTION_LAST_SITE, $site_id);
        
        // Calcular sitio actual para la barra de progreso
        $current_position = $wpdb->get_var("SELECT COUNT(blog_id) FROM {$wpdb->blogs} WHERE blog_id <= {$site_id}");
        
        // Devolver resultado
        wp_send_json_success([
            'processed' => $current_position,
            'total' => $total_sites,
            'finished' => false // Nunca terminamos hasta que no haya sitios disponibles
        ]);
    }
    
    /**
     * Resetear proceso via AJAX
     */
    public function ajax_reset_process() {
        // Verificar nonce
        if (!check_ajax_referer('ms_db_cleaner_nonce', 'nonce', false)) {
            wp_send_json_error();
        }
        
        // Verificar permisos
        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error();
        }
        
        // Reiniciar opciones
        update_site_option(self::OPTION_LAST_SITE, 0);
        update_site_option(self::OPTION_RUNNING, false);
        
        $this->log_action('Proceso reiniciado por el usuario.');
        
        wp_send_json_success();
    }
    
    /**
     * Obtener log via AJAX
     */
    public function ajax_get_log() {
        // Verificar nonce
        if (!check_ajax_referer('ms_db_cleaner_nonce', 'nonce', false)) {
            wp_send_json_error();
        }
        
        // Verificar permisos
        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error();
        }
        
        $log = get_site_option(self::OPTION_LOG, []);
        $log_html = '';
        
        if (!empty($log)) {
            foreach ($log as $entry) {
                $time = date('Y-m-d H:i:s', $entry['time']);
                $log_html .= sprintf('<div>[%s] %s</div>', $time, esc_html($entry['message']));
            }
        } else {
            $log_html = 'No hay entradas en el registro.';
        }
        
        wp_send_json_success($log_html);
    }
    
    /**
     * Guardar configuración via AJAX
     */
    public function ajax_save_settings() {
        // Verificar nonce
        if (!check_ajax_referer('ms_db_cleaner_nonce', 'nonce', false)) {
            wp_send_json_error();
        }
        
        // Verificar permisos
        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error();
        }
        
        // Guardar tamaño de lote
        if (isset($_POST['batch_size'])) {
            $batch_size = (int) $_POST['batch_size'];
            if ($batch_size < 1) $batch_size = 1;
            if ($batch_size > 5) $batch_size = 5;
            
            update_site_option(self::OPTION_BATCH_SIZE, $batch_size);
        }
        
        wp_send_json_success();
    }
    
    /**
     * Registrar una acción en el log
     *
     * @param string $message Mensaje a registrar
     */
    private function log_action($message) {
        $log = get_site_option(self::OPTION_LOG, []);
        
        // Limitar tamaño del log (máximo 100 entradas)
        if (count($log) >= 100) {
            array_shift($log);
        }
        
        $log[] = [
            'time' => time(),
            'message' => $message,
        ];
        
        update_site_option(self::OPTION_LOG, $log);
        
        return true;
    }
    
    /**
     * ACCIONES DE LIMPIEZA
     * Estas son las funciones que realizan las tareas de limpieza
     */
    
    /**
     * Eliminar revisiones antiguas
     * 
     * @return string Resultado de la operación
     */
    public function clean_old_revisions() {
        global $wpdb;
        
        // Obtener revisiones más antiguas de 30 días
        $date_limit = date('Y-m-d', strtotime('-30 days'));
        
        $revisions = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} 
                WHERE post_type = 'revision' 
                AND post_date < %s
                LIMIT 100", // Limitar a 100 para evitar sobrecarga
                $date_limit
            )
        );
        
        if (empty($revisions)) {
            return "No se encontraron revisiones antiguas para eliminar.";
        }
        
        $count = 0;
        foreach ($revisions as $revision_id) {
            wp_delete_post($revision_id, true);
            $count++;
        }
        
        return sprintf("Eliminadas %d revisiones antiguas.", $count);
    }
    
    /**
     * Eliminar transients expirados
     * 
     * @return string Resultado de la operación
     */
    public function clean_expired_transients() {
        global $wpdb;
        
        // Eliminar transients expirados
        $time = time();
        $expired = $wpdb->query(
            $wpdb->prepare(
                "DELETE a, b FROM {$wpdb->options} a
                LEFT JOIN {$wpdb->options} b ON b.option_name = CONCAT('_transient_timeout_', SUBSTRING(a.option_name, 12))
                WHERE a.option_name LIKE %s
                AND b.option_value < %d
                LIMIT 100", // Limitar a 100 para evitar sobrecarga
                $wpdb->esc_like('_transient_') . '%',
                $time
            )
        );
        
        return sprintf("Eliminados %d transients expirados.", $expired ? $expired : 0);
    }
    
    /**
     * Eliminar comentarios spam
     * 
     * @return string Resultado de la operación
     */
    public function clean_spam_comments() {
        global $wpdb;
        
        $spam_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam' LIMIT 100"
        );
        
        if (!$spam_count) {
            return "No se encontraron comentarios spam para eliminar.";
        }
        
        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam' LIMIT 100"
        );
        
        return sprintf("Eliminados %d comentarios spam.", $deleted ? $deleted : 0);
    }
    
    /**
     * Eliminar posts en papelera
     * 
     * @return string Resultado de la operación
     */
    public function clean_trash_posts() {
        global $wpdb;
        
        $trash_posts = $wpdb->get_col(
            "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'trash' LIMIT 50"
        );
        
        if (empty($trash_posts)) {
            return "No se encontraron posts en papelera para eliminar.";
        }
        
        $deleted = 0;
        foreach ($trash_posts as $post_id) {
            if (wp_delete_post($post_id, true)) {
                $deleted++;
            }
        }
        
        return sprintf("Eliminados %d posts de la papelera.", $deleted);
    }
    
    /**
     * Optimizar tablas de la base de datos
     * 
     * @return string Resultado de la operación
     */
    public function optimize_tables() {
        global $wpdb;
        
        // Obtener tablas del sitio actual
        $tables = $wpdb->get_col(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $wpdb->esc_like($wpdb->prefix) . '%'
            )
        );
        
        if (empty($tables)) {
            return "No se encontraron tablas para optimizar.";
        }
        
        // Optimizar tablas (solo 1 a la vez para evitar timeouts)
        $table = reset($tables);
        $result = $wpdb->query("OPTIMIZE TABLE $table");
        
        return sprintf("Optimizada tabla: %s", $table);
    }
}

// Inicializar plugin
new MS_DB_Cleaner();

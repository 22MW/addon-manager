<?php
/**
 * Plugin Name: VERI*FAC*WOO Lifetime Network
 * Description: Automatiza gestión de socios Lifetime, partners y subagencias en flujo WooCommerce.
 * Version: 1.0
 */

// 1. ASIGNAR NIVEL LIFETIME AL COMPLETAR PEDIDO
add_action('woocommerce_order_status_completed', 'vfw_asignar_lifetime_en_compra', 10, 1);

function vfw_asignar_lifetime_en_compra($order_id) {
    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();
    
    // Verificar si ya se procesó
    if (get_post_meta($order_id, '_lifetime_procesado', true)) {
        return;
    }
    
    // Buscar si el pedido contiene "Socio Lifetime"
    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        
        // CAMBIAR 123 por el ID real de tu producto "Socio Lifetime"
        if ($product_id == 774) {
            
            // PMPro ya asigna el nivel por la integración, pero por si acaso:
            if (function_exists('pmpro_changeMembershipLevel')) {
                pmpro_changeMembershipLevel('6', $user_id); // usar ID o slug del nivel
            }
            
            // Marcar como procesado
            update_post_meta($order_id, '_lifetime_procesado', 'yes');
            
            // Enviar email de bienvenida (opcional)
            wp_mail(
                $order->get_billing_email(),
                'Bienvenido al Plan Socio Lifetime',
                'Ya puedes gestionar tu red de sub-agencias desde Mi Cuenta.'
            );
            
            break;
        }
    }
}

// 2. AÑADIR PESTAÑA "MI RED" EN MI CUENTA
add_action('init', 'vfw_registrar_endpoint_mi_red');
function vfw_registrar_endpoint_mi_red() {
    add_rewrite_endpoint('mi-red', EP_ROOT | EP_PAGES);
}

add_filter('woocommerce_account_menu_items', 'vfw_anadir_menu_mi_red');

function vfw_anadir_menu_mi_red($items) {
    // Solo mostrar si es Admin Lifetime
    if (pmpro_hasMembershipLevel('6')) { // Usar el SLUG correcto
        $new_items = array();
        foreach ($items as $key => $value) {
            $new_items[$key] = $value;
            if ($key === 'dashboard') {
                $new_items['mi-red'] = 'Mi Red';
            }
        }
        return $new_items;
    }
    return $items;
}

add_action('woocommerce_account_mi-red_endpoint', 'vfw_mostrar_panel_mi_red');

function vfw_mostrar_panel_mi_red() {
    $user_id = get_current_user_id();
    
    // Verificar si es Admin Lifetime (CAMBIAR por el ID o slug correcto de tu nivel)
    if (!pmpro_hasMembershipLevel('6', $user_id)) { // Cambia 'admin-lifetime' por tu slug real
        echo '<p>Esta sección es solo para Admin Lifetime.</p>';
        return;
    }
    
    // Obtener socios lifetime vinculados a este admin
    $socios = get_users(array(
        'meta_key' => 'parent_admin_id',
        'meta_value' => $user_id,
        'fields' => 'all'
    ));
    
    ?>
    <h2>Mi Red de Socios Lifetime</h2>
    
    <h3>Invitar Nuevo Socio Lifetime</h3>
    <form method="post" action="">
        <?php wp_nonce_field('vfw_add_socio', 'vfw_nonce'); ?>
        <p>
            <label>Email del Socio:</label>
            <input type="email" name="socio_email" required style="width:300px;" />
            <small>Si el email no existe, se creará automáticamente una nueva cuenta.</small>
        </p>
        <p>
            <label>Nombre (opcional, si se crea cuenta nueva):</label>
            <input type="text" name="socio_nombre" style="width:300px;" />
        </p>
        <button type="submit" name="vfw_add_socio" class="button">Invitar Socio</button>
    </form>
    
    <?php
    // PROCESAR INVITACIÓN
    if (isset($_POST['vfw_add_socio']) && check_admin_referer('vfw_add_socio', 'vfw_nonce')) {
        $email = sanitize_email($_POST['socio_email']);
        $nombre = sanitize_text_field($_POST['socio_nombre']);
        
        // Buscar si el usuario ya existe
        $socio_user = get_user_by('email', $email);
        
        if (!$socio_user) {
            // CREAR CUENTA NUEVA
            $username = sanitize_user(current(explode('@', $email))); // Usar parte antes del @
            $password = wp_generate_password(12, true);
            
            // Asegurar que el username sea único
            $base_username = $username;
            $counter = 1;
            while (username_exists($username)) {
                $username = $base_username . $counter;
                $counter++;
            }
            
            $socio_user_id = wp_create_user($username, $password, $email);
            
            if (is_wp_error($socio_user_id)) {
                echo '<p style="color:red;">Error al crear la cuenta: ' . $socio_user_id->get_error_message() . '</p>';
            } else {
                // Actualizar nombre si se proporcionó
                if (!empty($nombre)) {
                    wp_update_user(array(
                        'ID' => $socio_user_id,
                        'display_name' => $nombre,
                        'first_name' => $nombre
                    ));
                }
                
                // Vincular al Admin Lifetime
                update_user_meta($socio_user_id, 'parent_admin_id', $user_id);
                
                // Asignar nivel "Socio Lifetime" (CAMBIAR por tu ID/slug correcto)
                if (function_exists('pmpro_changeMembershipLevel')) {
                    pmpro_changeMembershipLevel('7', $socio_user_id); // Usar ID o slug
                }
                
                // Enviar email con credenciales
                wp_mail(
                    $email,
                    'Bienvenido a la red VERI*FAC*WOO',
                    "Has sido invitado como Socio Lifetime.\n\n" .
                    "Usuario: $username\n" .
                    "Contraseña: $password\n\n" .
                    "Accede aquí: " . wp_login_url()
                );
                
                echo '<p style="color:green;">✓ Socio invitado y cuenta creada. Se ha enviado un email con las credenciales.</p>';
                
                // Recargar lista
                $socios = get_users(array(
                    'meta_key' => 'parent_admin_id',
                    'meta_value' => $user_id,
                    'fields' => 'all'
                ));
            }
        } else {
            // Usuario ya existe, solo vincular
            $parent_actual = get_user_meta($socio_user->ID, 'parent_admin_id', true);
            
            if ($parent_actual && $parent_actual != $user_id) {
                echo '<p style="color:red;">Este usuario ya pertenece a otra red.</p>';
            } else {
                // Vincular
                update_user_meta($socio_user->ID, 'parent_admin_id', $user_id);
                
                // Asignar nivel "Socio Lifetime"
                pmpro_changeMembershipLevel('socio-lifetime', $socio_user->ID);
                
                // Notificar por email
                wp_mail(
                    $email,
                    'Has sido añadido a una red VERI*FAC*WOO',
                    "Has sido añadido como Socio Lifetime a la red de un Admin.\n\n" .
                    "Accede a tu cuenta: " . wp_login_url()
                );
                
                echo '<p style="color:green;">✓ Usuario existente vinculado correctamente.</p>';
                
                // Recargar lista
                $socios = get_users(array(
                    'meta_key' => 'parent_admin_id',
                    'meta_value' => $user_id,
                    'fields' => 'all'
                ));
            }
        }
    }
    ?>
    
    <h3>Mis Socios Lifetime (<?php echo count($socios); ?>)</h3>
    <?php if (empty($socios)) : ?>
        <p>Todavía no tienes socios invitados.</p>
    <?php else : ?>
        <table class="shop_table">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Email</th>
                    <th>Fecha registro</th>
                    <th>Emisores</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($socios as $socio) : 
                    // Contar emisores de este socio (ajusta según tu CPT)
                    $emisores_count = count(get_posts(array(
                        'post_type' => 'emisor',
                        'author' => $socio->ID,
                        'posts_per_page' => -1
                    )));
                ?>
                <tr>
                    <td><?php echo esc_html($socio->display_name); ?></td>
                    <td><?php echo esc_html($socio->user_email); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($socio->user_registered)); ?></td>
                    <td><?php echo $emisores_count; ?></td>
                    <td>
                        <a href="?desvincular=<?php echo $socio->ID; ?>&_wpnonce=<?php echo wp_create_nonce('desvincular_'.$socio->ID); ?>" 
                           onclick="return confirm('¿Seguro que quieres desvincular este socio?');">
                            Desvincular
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <?php
    
    // PROCESAR DESVINCULACIÓN
    if (isset($_GET['desvincular']) && wp_verify_nonce($_GET['_wpnonce'], 'desvincular_'.$_GET['desvincular'])) {
        $socio_id = intval($_GET['desvincular']);
        delete_user_meta($socio_id, 'parent_admin_id');
        
        // Opcional: quitar nivel o dejarlo (tú decides)
        // pmpro_changeMembershipLevel(0, $socio_id); 
        
        echo '<p style="color:green;">✓ Socio desvinculado.</p>';
        echo '<script>window.location.href="' . wc_get_account_endpoint_url('mi-red') . '";</script>';
    }
}

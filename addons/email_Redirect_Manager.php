<?php
/**
 * Plugin Name: Email Redirect Manager
 * Description: Redirige correos salientes de WordPress a destinatarios de prueba con prefijo configurable (Tools > Email Redirect).
 * Version: 1.2.2
 * Author: 22 MW

 */

// Añadir página de configuración en Tools
add_action('admin_menu', 'erm_add_admin_menu');
function erm_add_admin_menu() {
    add_management_page(
        'Gestión de Email',
        'Email Redirect',
        'manage_options',
        'email-redirect-manager',
        'erm_options_page'
    );
}

// Página de opciones
function erm_options_page() {
    ?>
    <div class="wrap">
        <h1>Configuración de Redirección de Emails</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('erm_settings');
            do_settings_sections('email-redirect-manager');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Registrar configuraciones
add_action('admin_init', 'erm_settings_init');
function erm_settings_init() {
    register_setting('erm_settings', 'erm_email_destino');
    register_setting('erm_settings', 'erm_prefijo_asunto');

    add_settings_section(
        'erm_section',
        'Opciones de Redirección',
        null,
        'email-redirect-manager'
    );

    add_settings_field(
        'erm_email_destino',
        'Email(s) de Destino',
        'erm_email_destino_render',
        'email-redirect-manager',
        'erm_section'
    );

    add_settings_field(
        'erm_prefijo_asunto',
        'Prefijo del Asunto',
        'erm_prefijo_asunto_render',
        'email-redirect-manager',
        'erm_section'
    );
}

function erm_email_destino_render() {
    $value = get_option('erm_email_destino', 'tuemail@gmail.com');
    ?>
    <input type="text" name="erm_email_destino" value="<?php echo esc_attr($value); ?>" style="width: 400px;">
    <p class="description">Puedes añadir varios emails separados por coma (ej: email1@gmail.com, email2@gmail.com)</p>
    <?php
}

function erm_prefijo_asunto_render() {
    $value = get_option('erm_prefijo_asunto', '[REDIRECCIONADO] -  ');
    ?>
    <input type="text" name="erm_prefijo_asunto" value="<?php echo esc_attr($value); ?>" style="width: 400px;">
    <p class="description">Texto que se añadirá al inicio del asunto de todos los emails</p>
    <?php
}

// Redirigir y modificar correos
add_filter('wp_mail', 'erm_redirigir_correos');
function erm_redirigir_correos($args) {
    $nuevo_destino = get_option('erm_email_destino', 'tuemail@gmail.com');
    $texto_previo = get_option('erm_prefijo_asunto', '[REDIRECCIONADO] ');
    
    $args['to'] = $nuevo_destino;
    $args['subject'] = $texto_previo . $args['subject'];
    
    return $args;
}

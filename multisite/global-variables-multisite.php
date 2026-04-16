<?php
/*
Plugin Name: Global Variables Settings for Specific Site (Network Admin)
Description: Gestiona variables globales desde Network Admin sobre un sitio objetivo configurable.
Marketing Description: Centraliza variables globales para reducir errores de configuración por sitio.
Parameters: Gestiona variables globales desde Network Admin.
Version: 2.2
Author: 22MW
*/

// Define la ID de la web en la que se guardarán todas las opciones.
if ( ! defined( 'TARGET_BLOG_ID' ) ) {
    define( 'TARGET_BLOG_ID', 1 );
}

// Evita el acceso directo.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Actualiza una opción en el sitio definido en TARGET_BLOG_ID.
 *
 * @param string $option El nombre de la opción.
 * @param mixed  $value  El valor que se desea guardar.
 */
function update_target_site_option( $option, $value ) {
    switch_to_blog( TARGET_BLOG_ID );
    update_option( $option, $value );
    restore_current_blog();
}

/**
 * Recupera una opción desde el sitio definido en TARGET_BLOG_ID.
 *
 * @param string $option  El nombre de la opción.
 * @param mixed  $default Valor por defecto si no existe la opción.
 * @return mixed El valor de la opción.
 */
function get_target_site_option( $option, $default = false ) {
    switch_to_blog( TARGET_BLOG_ID );
    $value = get_option( $option, $default );
    restore_current_blog();
    return $value;
}

/**
 * Agrega un menú en el Network Admin para gestionar las opciones.
 */
function global_variables_network_menu() {
    add_menu_page(
        'Variables Globales',                  // Título de la página.
        'Variables Globales',                  // Texto del menú.
        'manage_network',                      // Capacidad requerida en Network Admin.
        'global_variables',                    // Slug de la página.
        'global_variables_network_page',       // Función que muestra el contenido.
        'dashicons-admin-generic',             // Icono del menú.
        90                                     // Posición.
    );
}
add_action( 'network_admin_menu', 'global_variables_network_menu' );

/**
 * Muestra la página de configuración en el Network Admin y procesa el formulario.
 */
function global_variables_network_page() {
    // Procesamiento del formulario.
    if ( isset( $_POST['global_variables_nonce'] ) && wp_verify_nonce( $_POST['global_variables_nonce'], 'save_global_variables' ) ) {
        $main_site_id   = isset( $_POST['main_site_id'] ) ? absint( $_POST['main_site_id'] ) : 1;
        $excluded_sites = isset( $_POST['excluded_sites'] ) ? sanitize_text_field( $_POST['excluded_sites'] ) : '';

        update_target_site_option( 'main_site_id', $main_site_id );
        update_target_site_option( 'excluded_sites', $excluded_sites );

        echo '<div class="updated"><p>Opciones guardadas correctamente</p></div>';
    }

    // Obtiene las opciones del sitio objetivo.
    $main_site_id   = get_target_site_option( 'main_site_id', 1 );
    $excluded_sites = get_target_site_option( 'excluded_sites', '' );
    ?>
    <div class="wrap">
        <h1>Configuración de Variables Globales</h1>
        <form method="post" action="">
            <?php wp_nonce_field( 'save_global_variables', 'global_variables_nonce' ); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">ID del sitio con templates globales</th>
                    <td>
                        <input type="number" name="main_site_id" value="<?php echo esc_attr( $main_site_id ); ?>" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">IDs de sitios excluidos</th>
                    <td>
                        <textarea name="excluded_sites" rows="5" cols="50"><?php echo esc_textarea( $excluded_sites ); ?></textarea>
                        <p class="description">Ingresa una lista separada por comas, por ejemplo: 2,3,4</p>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Guardar Opciones' ); ?>
        </form>
    </div>
    <?php
}
?>

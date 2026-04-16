<?php
/**
 * Plugin Name: Cambio de Contraseña
 * Description: Formulario frontal para usuarios logueados que permite cambiar contraseña con validación y shortcode [change_pass_form].
 * Version: 1.3.0
 * Text Domain: 22mw_muplugins
 * Author:22MW
 */

// Cargar traducciones

add_action('muplugins_loaded', 'fcp_cargar_traducciones');
function fcp_cargar_traducciones() {
    load_muplugin_textdomain('22mw_muplugins', basename(dirname(__FILE__)));
}

// ============================================
// TEXTOS - MODIFICAR AQUÍ
// ============================================
function fcp_obtener_textos() {
    return array(
        // Etiquetas formulario
        'label_nueva' => __('Nueva contraseña:', '22mw_muplugins'),
        'label_confirmar' => __('Confirmar contraseña:', '22mw_muplugins'),
        'boton_submit' => __('Cambiar contraseña', '22mw_muplugins'),
        
        // Reglas de contraseña
        'titulo_reglas' => __('La contraseña debe cumplir:', '22mw_muplugins'),
        'regla_longitud' => __('Mínimo 8 caracteres', '22mw_muplugins'),
        'regla_letra' => __('Al menos una letra', '22mw_muplugins'),
        'regla_numero' => __('Al menos un número', '22mw_muplugins'),
        'regla_simbolo' => __('Al menos un símbolo (!@#$%^&*)', '22mw_muplugins'),
        
        // Mensajes validación
        'coinciden' => __('✓ Las contraseñas coinciden', '22mw_muplugins'),
        'no_coinciden' => __('✗ Las contraseñas no coinciden', '22mw_muplugins'),
        'no_cumple' => __('La contraseña no cumple los requisitos.', '22mw_muplugins'),
        
        // Mensajes sistema
        'error_seguridad' => __('Error de seguridad.', '22mw_muplugins'),
        'debe_login' => __('Debes iniciar sesión para cambiar tu contraseña.', '22mw_muplugins'),
        'debe_login_ajax' => __('Debes iniciar sesión.', '22mw_muplugins'),
        'exito' => __('Contraseña cambiada correctamente. Revisa tu email.', '22mw_muplugins'),
        
        // Email
        'email_asunto' => __('Contraseña cambiada', '22mw_muplugins'),
        'email_mensaje' => __('Hola %s,<br><br>Tu contraseña ha sido cambiada correctamente.<br><br>Si no has sido tú quien realizó este cambio, ponte en contacto con el administrador de la web inmediatamente.<br><br>Saludos.', '22mw_muplugins')
    );
}

// Cargar Font Awesome
add_action('wp_enqueue_scripts', 'fcp_cargar_assets');
function fcp_cargar_assets() {
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css', array(), '6.5.0');
}

// Registrar shortcode
add_shortcode('change_pass_form', 'fcp_formulario_shortcode');

function fcp_formulario_shortcode() {
    $textos = fcp_obtener_textos();
    
    if (!is_user_logged_in()) {
        return '<p class="fcp-aviso">' . $textos['debe_login'] . '</p>';
    }
    
    ob_start();
    ?>
    <div class="fcp-container">
        <form id="fcp-form" method="post">
            <?php wp_nonce_field('cambiar_password_action', 'cambiar_password_nonce'); ?>
            
            <div class="fcp-field">
                <label class="fcp-label"><?php echo esc_html($textos['label_nueva']); ?></label>
                <div class="fcp-password-wrapper">
                    <input type="password" name="nueva_password" id="nueva_password" class="fcp-input" required>
                    <i class="far fa-eye fcp-toggle-password" data-target="nueva_password"></i>
                </div>
            </div>
            
            <div class="fcp-field">
                <label class="fcp-label"><?php echo esc_html($textos['label_confirmar']); ?></label>
                <div class="fcp-password-wrapper">
                    <input type="password" name="confirmar_password" id="confirmar_password" class="fcp-input" required>
                    <i class="far fa-eye fcp-toggle-password" data-target="confirmar_password"></i>
                </div>
                <span class="fcp-match-message"></span>
            </div>
            
            <div class="fcp-rules">
                <p class="fcp-rules-titulo"><?php echo esc_html($textos['titulo_reglas']); ?></p>
                <ul class="fcp-rules-lista">
                    <li class="fcp-rule rule-length"><?php echo esc_html($textos['regla_longitud']); ?></li>
                    <li class="fcp-rule rule-letter"><?php echo esc_html($textos['regla_letra']); ?></li>
                    <li class="fcp-rule rule-number"><?php echo esc_html($textos['regla_numero']); ?></li>
                    <li class="fcp-rule rule-symbol"><?php echo esc_html($textos['regla_simbolo']); ?></li>
                </ul>
            </div>
            
            <button type="submit" class="fcp-submit"><?php echo esc_html($textos['boton_submit']); ?></button>
            <div class="fcp-message"></div>
        </form>
    </div>
    
    <style>
        .fcp-container { 
            max-width: 400px; 
            margin: 20px auto; 
        }
        
        .fcp-field { 
            margin-bottom: 15px; 
        }
        
        .fcp-label {
            display: block;
            margin-bottom: 5px;
        }
        
        .fcp-password-wrapper { 
            position: relative; 
            display: flex; 
        }
        
        .fcp-input { 
            width: 100%; 
            padding: 10px; 
            padding-right: 40px;
            background-color: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .fcp-input:focus {
            background-color: #dedede;
            outline: none;
            border-color: #999;
        }
        
        .fcp-toggle-password { 
            position: absolute; 
            right: 10px; 
            top: 50%; 
            transform: translateY(-50%); 
            cursor: pointer; 
            user-select: none;
            color: #666;
        }
        
        .fcp-rules { 
            background: #f5f5f5; 
            padding: 15px; 
            border-radius: 5px; 
            margin: 15px 0;
            border: none;
            display: none;
        }
        
        .fcp-rules-titulo {
            margin: 0 0 10px 0;
            font-size: small;
            font-weight: 600;
        }
        
        .fcp-rules-lista { 
            margin: 0;
            padding-left: 20px;
            font-size: small;
        }
        
        .fcp-rule { 
            color: #666;
            font-size: small;
        }
        
        .fcp-rule.valid { 
            color: #28a745; 
        }
        
        .fcp-match-message { 
            display: block; 
            font-size: small; 
            margin-top: 5px; 
        }
        
        .fcp-match-message.error { 
            color: #dc3545; 
        }
        
        .fcp-match-message.success { 
            color: #28a745; 
        }
        
        .fcp-submit { 
            background: #0073aa; 
            color: white; 
            padding: 10px 20px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            width: 100%;
            font-size: 16px;
        }
        
        .fcp-submit:disabled { 
            opacity: 0.5; 
            cursor: not-allowed; 
        }
        
        .fcp-message { 
            margin-top: 15px; 
            padding: 10px; 
            border-radius: 5px;
            background: #f5f5f5;
            border: none;
            font-size: small;
            display: none;
        }
        
        .fcp-message.success { 
            color: #155724; 
        }
        
        .fcp-message.error { 
            color: #721c24; 
        }
        
        .fcp-aviso {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
            border: none;
            font-size: small;
        }
    </style>
    
    <script>
    (function($) {
        $(document).ready(function() {
            const textos = {
                coinciden: '<?php echo esc_js($textos['coinciden']); ?>',
                no_coinciden: '<?php echo esc_js($textos['no_coinciden']); ?>',
                no_cumple: '<?php echo esc_js($textos['no_cumple']); ?>'
            };
            
            const rules = {
                length: /.{8,}/,
                letter: /[a-zA-Z]/,
                number: /[0-9]/,
                symbol: /[!@#$%^&*(),.?":{}|<>]/
            };
            
            // Mostrar/ocultar contraseña
            $('.fcp-toggle-password').on('click', function() {
                const target = $(this).data('target');
                const input = $('#' + target);
                const type = input.attr('type') === 'password' ? 'text' : 'password';
                input.attr('type', type);
                $(this).toggleClass('fa-eye fa-eye-slash');
            });
            
            // Validar contraseña en tiempo real
            $('#nueva_password').on('input', function() {
                const password = $(this).val();
                $('.fcp-rules').show();
                
                $('.rule-length').toggleClass('valid', rules.length.test(password));
                $('.rule-letter').toggleClass('valid', rules.letter.test(password));
                $('.rule-number').toggleClass('valid', rules.number.test(password));
                $('.rule-symbol').toggleClass('valid', rules.symbol.test(password));
            });
            
            // Comprobar coincidencia de contraseñas
            $('#confirmar_password').on('input', function() {
                const nueva = $('#nueva_password').val();
                const confirmar = $(this).val();
                const mensaje = $('.fcp-match-message');
                
                if (confirmar.length > 0) {
                    if (nueva === confirmar) {
                        mensaje.text(textos.coinciden).removeClass('error').addClass('success');
                    } else {
                        mensaje.text(textos.no_coinciden).removeClass('success').addClass('error');
                    }
                } else {
                    mensaje.text('').removeClass('success error');
                }
            });
            
            // Enviar formulario
            $('#fcp-form').on('submit', function(e) {
                e.preventDefault();
                const nueva = $('#nueva_password').val();
                const confirmar = $('#confirmar_password').val();
                const mensaje = $('.fcp-message');
                
                // Validar reglas
                const esValida = Object.values(rules).every(regex => regex.test(nueva));
                
                if (!esValida) {
                    mensaje.html(textos.no_cumple).removeClass('success').addClass('error').show();
                    return;
                }
                
                if (nueva !== confirmar) {
                    mensaje.html(textos.no_coinciden).removeClass('success').addClass('error').show();
                    return;
                }
                
                $('.fcp-submit').prop('disabled', true);
                
                $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'fcp_cambiar_password',
                    nonce: $('[name="cambiar_password_nonce"]').val(),
                    nueva_password: nueva
                }, function(response) {
                    $('.fcp-submit').prop('disabled', false);
                    if (response.success) {
                        mensaje.html(response.data.message).removeClass('error').addClass('success').show();
                        $('#fcp-form')[0].reset();
                        $('.fcp-rules').hide();
                        $('.fcp-match-message').text('').removeClass('success error');
                    } else {
                        mensaje.html(response.data.message).removeClass('success').addClass('error').show();
                    }
                });
            });
        });
    })(jQuery);
    </script>
    <?php
    return ob_get_clean();
}

// Procesar cambio de contraseña
add_action('wp_ajax_fcp_cambiar_password', 'fcp_procesar_cambio');

function fcp_procesar_cambio() {
    $textos = fcp_obtener_textos();
    
    if (!check_ajax_referer('cambiar_password_action', 'nonce', false)) {
        wp_send_json_error(['message' => $textos['error_seguridad']]);
    }
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => $textos['debe_login_ajax']]);
    }
    
    $nueva_password = sanitize_text_field($_POST['nueva_password']);
    $user_id = get_current_user_id();
    
    // Validar contraseña servidor
    if (strlen($nueva_password) < 8 || 
        !preg_match('/[a-zA-Z]/', $nueva_password) ||
        !preg_match('/[0-9]/', $nueva_password) ||
        !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $nueva_password)) {
        wp_send_json_error(['message' => $textos['no_cumple']]);
    }
    
    // Cambiar contraseña
    wp_set_password($nueva_password, $user_id);
    
    // Enviar email confirmación
    $user = get_userdata($user_id);
    $to = $user->user_email;
    $subject = $textos['email_asunto'];
    $message = sprintf($textos['email_mensaje'], $user->display_name);
    
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    wp_mail($to, $subject, $message, $headers);
    
    wp_send_json_success(['message' => $textos['exito']]);
}

# Addon Manager - Arquitectura Técnica

## Objetivo
Este plugin centraliza la activación y desactivación de addons (core y de usuario) con validaciones de seguridad para evitar caídas del sitio por errores fatales o sintaxis inválida.

## Estructura actual
- `addon-manager.php`
Bootstrap del plugin. Define constantes, carga text domain, registra el updater y arranca la clase principal.
- `includes/Core/class-addon-manager.php`
Clase principal con lógica de runtime, seguridad, activación segura, admin UI y gestión de addons de usuario.
- `includes/class-github-updater.php`
Integración de updates desde GitHub Releases.
- `addons/`, `woo/`, `multisite/`
Addons core incluidos en el plugin.
- `wp-content/uploads/addon-manager/user-addons/`
Addons de usuario subidos desde la interfaz de administración.

## Flujo de runtime
1. Se cargan addons activos core y usuario.
2. Antes de incluir cada addon:
- se valida existencia de archivo,
- se comprueba firma del archivo (modo estricto),
- se ejecuta lint PHP.
3. Si algo falla, se bloquea el addon, se desactiva automáticamente y se registra aviso administrativo.

## Flujo de activación segura
1. Usuario activa addon desde UI.
2. Se marca activación pendiente.
3. Se ejecuta loopback healthcheck interno.
4. Si healthcheck falla:
- no se activa,
- se guarda motivo,
- se notifica en admin.
5. Si ocurre fatal durante carga/activación, shutdown handler desactiva el addon implicado.

## Seguridad aplicada
- `defined( 'ABSPATH' ) || exit;` en archivos de entrada.
- `current_user_can( 'manage_options' )` en acciones sensibles.
- Nonces en formularios y AJAX.
- Sanitización y validación en inputs (`sanitize_text_field`, `sanitize_key`, `wp_unslash`, etc.).
- Escaping en salida (`esc_html`, `esc_attr`, `esc_url`).
- Restricción de subida a `.php` con validaciones de tamaño, cabecera mínima, patrones bloqueados y lint.

## Reglas de mantenimiento
- Cambios funcionales en pasos pequeños y aislados.
- Mantener compatibilidad de hooks existentes.
- Validación mínima obligatoria en cada cambio:
- `php -l` en archivos tocados.
- `git diff --check`.
